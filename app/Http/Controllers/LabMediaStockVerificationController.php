<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabMediaStockVerificationStoreRequest;
use App\Http\Requests\LabMediaStockVerificationUpdateRequest;
use App\Models\ActivityLog;
use App\Models\Approval;
use App\Models\LabMediaStockVerification;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\LabNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LabMediaStockVerificationController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly LabNumberGenerator $numbers,
    ) {
        $this->authorizeResource(LabMediaStockVerification::class, 'lab_media');
    }

    public function index(Request $request)
    {
        $verifications = $this->filtered($request)
            ->with('verifiedBy')
            ->latest('verification_date')
            ->paginate(15)
            ->withQueryString();

        return view('lab-media.index', [
            'verifications' => $verifications,
            'trashed' => $request->boolean('trashed'),
        ]);
    }

    public function create(Request $request)
    {
        return view('lab-media.form', [
            'labMedia' => new LabMediaStockVerification(['verification_date' => now()->toDateString()]),
            'technicians' => $this->technicianOptions($request->user()),
        ]);
    }

    public function store(LabMediaStockVerificationStoreRequest $request)
    {
        $data = $request->validated();
        $data['verified_by'] = $request->user()->hasRole(['super-admin', 'admin']) && $request->filled('verified_by')
            ? $request->input('verified_by')
            : $request->user()->id;
        $data['verification_no'] = $this->numbers->next(LabMediaStockVerification::class, 'verification_no', 'MS');
        $data['closing_stock'] = ($data['opening_stock'] ?? 0) + ($data['received_qty'] ?? 0) - ($data['consumed_qty'] ?? 0);
        $data['status'] = 'draft';
        $data['created_by'] = $request->user()->id;

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('lab-media/photos', 'public');
        }

        $verification = LabMediaStockVerification::create($data);

        ActivityLog::record('lab-media', $verification->id, 'create', [], $verification->toArray());

        return redirect()->route('lab-media.show', $verification)->with('success', 'Media stock verification saved.');
    }

    public function show(LabMediaStockVerification $labMedia)
    {
        $labMedia->load(['verifiedBy', 'createdBy', 'updatedBy']);

        $activity = ActivityLog::where('module', 'lab-media')->where('record_id', $labMedia->id)->latest()->limit(30)->get();
        $approvalLevels = $this->approvals->levelsFor('lab-media', $labMedia->id)->load(['approvedBy', 'rejectedBy', 'unlockBy']);

        return view('lab-media.show', compact('labMedia', 'activity', 'approvalLevels'));
    }

    public function edit(Request $request, LabMediaStockVerification $labMedia)
    {
        return view('lab-media.form', [
            'labMedia' => $labMedia,
            'technicians' => $this->technicianOptions($request->user()),
        ]);
    }

    public function update(LabMediaStockVerificationUpdateRequest $request, LabMediaStockVerification $labMedia)
    {
        $original = $labMedia->toArray();

        $data = $request->validated();
        $data['closing_stock'] = ($data['opening_stock'] ?? 0) + ($data['received_qty'] ?? 0) - ($data['consumed_qty'] ?? 0);
        $data['updated_by'] = $request->user()->id;

        if ($request->hasFile('photo')) {
            if ($labMedia->photo) {
                Storage::disk('public')->delete($labMedia->photo);
            }
            $data['photo'] = $request->file('photo')->store('lab-media/photos', 'public');
        }

        $labMedia->update($data);

        ActivityLog::record('lab-media', $labMedia->id, 'update', $original, $labMedia->fresh()->toArray());

        return redirect()->route('lab-media.show', $labMedia)->with('success', 'Media stock verification updated.');
    }

    public function destroy(LabMediaStockVerification $labMedia)
    {
        DB::transaction(function () use ($labMedia) {
            $labMedia->update(['deleted_by' => auth()->id()]);
            $labMedia->delete();
        });

        ActivityLog::record('lab-media', $labMedia->id, 'delete');

        return redirect()->route('lab-media.index')->with('success', 'Media stock verification deleted successfully.');
    }

    public function restore(LabMediaStockVerification $labMedia)
    {
        $this->authorize('restore', $labMedia);

        DB::transaction(function () use ($labMedia) {
            $labMedia->restore();
            $labMedia->update(['deleted_by' => null]);
        });

        ActivityLog::record('lab-media', $labMedia->id, 'update', [], [], 'Media stock verification restored');

        return redirect()->route('lab-media.index')->with('success', 'Media stock verification restored successfully.');
    }

    public function submit(LabMediaStockVerification $labMedia)
    {
        $this->authorize('submit', $labMedia);

        $original = $labMedia->only('status');
        $labMedia->update(['status' => 'pending']);

        ActivityLog::record('lab-media', $labMedia->id, 'update', $original, ['status' => 'pending'], 'Submitted for supervisor approval');

        return back()->with('success', 'Media stock verification submitted for supervisor approval.');
    }

    public function approve(Request $request, LabMediaStockVerification $labMedia)
    {
        $this->authorize('approve', $labMedia);

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
            'signature_data' => ['required', 'string'],
        ]);

        $signature = $this->storeSignature($validated['signature_data']);
        $original = $labMedia->only('status');

        DB::transaction(function () use ($labMedia, $validated, $request, $signature) {
            $labMedia->update(['status' => 'approved']);

            $this->approvals->record(
                'lab-media',
                $labMedia->id,
                Approval::LEVEL_VERIFICATION,
                'approved',
                $request->user(),
                $validated['remarks'] ?? null,
                $labMedia->verification_no,
                $this->notifyRecipients($labMedia->verified_by),
                $signature,
            );
        });

        ActivityLog::record('lab-media', $labMedia->id, 'approve', $original, ['status' => 'approved'], $validated['remarks'] ?? null);

        return back()->with('success', 'Media stock verification approved.');
    }

    public function reject(Request $request, LabMediaStockVerification $labMedia)
    {
        $this->authorize('approve', $labMedia);

        $validated = $request->validate(['remarks' => ['required', 'string', 'max:1000']]);

        $original = $labMedia->only('status');

        DB::transaction(function () use ($labMedia, $validated, $request) {
            $labMedia->update(['status' => 'rejected']);

            $this->approvals->record(
                'lab-media',
                $labMedia->id,
                Approval::LEVEL_VERIFICATION,
                'rejected',
                $request->user(),
                $validated['remarks'],
                $labMedia->verification_no,
                $this->notifyRecipients($labMedia->verified_by),
            );
        });

        ActivityLog::record('lab-media', $labMedia->id, 'reject', $original, ['status' => 'rejected'], $validated['remarks']);

        return back()->with('success', 'Media stock verification rejected.');
    }

    public function unlock(Request $request, LabMediaStockVerification $labMedia)
    {
        $this->authorize('unlock', $labMedia);

        $original = $labMedia->only('status');

        DB::transaction(function () use ($labMedia, $request) {
            $labMedia->update(['status' => 'draft']);

            $this->approvals->record(
                'lab-media',
                $labMedia->id,
                Approval::LEVEL_VERIFICATION,
                'unlocked',
                $request->user(),
                null,
                $labMedia->verification_no,
                $this->notifyRecipients($labMedia->verified_by),
            );
        });

        ActivityLog::record('lab-media', $labMedia->id, 'unlock', $original, ['status' => 'draft']);

        return back()->with('success', 'Media stock verification unlocked for editing.');
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', LabMediaStockVerification::class);

        $verifications = $this->filtered($request)->with('verifiedBy')->latest('verification_date')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="lab-media.csv"',
        ];

        return response()->streamDownload(function () use ($verifications) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Verification No', 'Media', 'Verified By', 'Date', 'Opening', 'Received', 'Consumed', 'Closing', 'Reorder Level', 'Status']);
            foreach ($verifications as $verification) {
                fputcsv($out, [
                    $verification->verification_no,
                    $verification->media_name,
                    $verification->verifiedBy?->name,
                    $verification->verification_date?->format('Y-m-d'),
                    $verification->opening_stock,
                    $verification->received_qty,
                    $verification->consumed_qty,
                    $verification->closing_stock,
                    $verification->reorder_level,
                    $verification->status,
                ]);
            }
            fclose($out);
        }, 'lab-media.csv', $headers);
    }

    private function filtered(Request $request)
    {
        $user = $request->user();
        $search = $request->string('search')->toString();
        $trashed = $request->boolean('trashed');

        return LabMediaStockVerification::query()
            ->when($trashed, fn ($q) => $q->onlyTrashed())
            ->when($user->hasRole('lab-technician'), fn ($q) => $q->where('verified_by', $user->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('verification_date', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn ($q) => $q->whereDate('verification_date', '<=', $request->input('to_date')))
            ->when($search, fn ($q) => $q->where(fn ($qq) => $qq
                ->where('verification_no', 'like', "%{$search}%")
                ->orWhere('media_name', 'like', "%{$search}%")));
    }

    private function notifyRecipients(int $verifiedById)
    {
        $approvers = User::whereHas('role', fn ($q) => $q->whereIn('name', ['supervisor', 'admin', 'super-admin']))
            ->where('status', true)
            ->get();

        return $approvers->push(User::find($verifiedById))
            ->filter()
            ->unique('id')
            ->reject(fn ($user) => $user->id === auth()->id());
    }

    /**
     * Admin/Super Admin may log this on behalf of any active Lab Technician;
     * a Lab Technician only ever sees (and is forced into) themselves.
     */
    private function technicianOptions(User $user)
    {
        if ($user->hasRole(['super-admin', 'admin'])) {
            return User::whereHas('role', fn ($q) => $q->where('name', 'lab-technician'))->where('status', true)->orderBy('name')->get();
        }

        return collect([$user]);
    }

    private function storeSignature(string $dataUrl): string
    {
        $encoded = preg_replace('#^data:image/\w+;base64,#i', '', $dataUrl);
        $path = 'approvals/signatures/'.uniqid('sig_', true).'.png';

        Storage::disk('public')->put($path, base64_decode($encoded));

        return $path;
    }
}
