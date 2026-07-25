<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabChemicalUsageStoreRequest;
use App\Http\Requests\LabChemicalUsageUpdateRequest;
use App\Models\ActivityLog;
use App\Models\Approval;
use App\Models\LabChemicalUsage;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\LabNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LabChemicalUsageController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly LabNumberGenerator $numbers,
    ) {
        $this->authorizeResource(LabChemicalUsage::class, 'lab_chemical');
    }

    public function index(Request $request)
    {
        $usages = $this->filtered($request)
            ->with('usedBy')
            ->latest('usage_date')
            ->paginate(15)
            ->withQueryString();

        return view('lab-chemicals.index', [
            'usages' => $usages,
            'trashed' => $request->boolean('trashed'),
        ]);
    }

    public function create(Request $request)
    {
        return view('lab-chemicals.form', [
            'labChemical' => new LabChemicalUsage(['usage_date' => now()->toDateString()]),
            'technicians' => $this->technicianOptions($request->user()),
        ]);
    }

    public function store(LabChemicalUsageStoreRequest $request)
    {
        $data = $request->validated();
        $data['used_by'] = $request->user()->hasRole(['super-admin', 'admin']) && $request->filled('used_by')
            ? $request->input('used_by')
            : $request->user()->id;
        $data['usage_no'] = $this->numbers->next(LabChemicalUsage::class, 'usage_no', 'CU');
        $data['status'] = 'draft';
        $data['created_by'] = $request->user()->id;

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('lab-chemicals/photos', 'public');
        }

        $usage = LabChemicalUsage::create($data);

        ActivityLog::record('lab-chemicals', $usage->id, 'create', [], $usage->toArray());

        return redirect()->route('lab-chemicals.show', $usage)->with('success', 'Chemical usage logged.');
    }

    public function show(LabChemicalUsage $labChemical)
    {
        $labChemical->load(['usedBy', 'createdBy', 'updatedBy']);

        $activity = ActivityLog::where('module', 'lab-chemicals')->where('record_id', $labChemical->id)->latest()->limit(30)->get();
        $approvalLevels = $this->approvals->levelsFor('lab-chemicals', $labChemical->id)->load(['approvedBy', 'rejectedBy', 'unlockBy']);

        return view('lab-chemicals.show', compact('labChemical', 'activity', 'approvalLevels'));
    }

    public function edit(Request $request, LabChemicalUsage $labChemical)
    {
        return view('lab-chemicals.form', [
            'labChemical' => $labChemical,
            'technicians' => $this->technicianOptions($request->user()),
        ]);
    }

    public function update(LabChemicalUsageUpdateRequest $request, LabChemicalUsage $labChemical)
    {
        $original = $labChemical->toArray();

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        if ($request->hasFile('photo')) {
            if ($labChemical->photo) {
                Storage::disk('public')->delete($labChemical->photo);
            }
            $data['photo'] = $request->file('photo')->store('lab-chemicals/photos', 'public');
        }

        $labChemical->update($data);

        ActivityLog::record('lab-chemicals', $labChemical->id, 'update', $original, $labChemical->fresh()->toArray());

        return redirect()->route('lab-chemicals.show', $labChemical)->with('success', 'Chemical usage updated.');
    }

    public function destroy(LabChemicalUsage $labChemical)
    {
        DB::transaction(function () use ($labChemical) {
            $labChemical->update(['deleted_by' => auth()->id()]);
            $labChemical->delete();
        });

        ActivityLog::record('lab-chemicals', $labChemical->id, 'delete');

        return redirect()->route('lab-chemicals.index')->with('success', 'Chemical usage deleted successfully.');
    }

    public function restore(LabChemicalUsage $labChemical)
    {
        $this->authorize('restore', $labChemical);

        DB::transaction(function () use ($labChemical) {
            $labChemical->restore();
            $labChemical->update(['deleted_by' => null]);
        });

        ActivityLog::record('lab-chemicals', $labChemical->id, 'update', [], [], 'Chemical usage restored');

        return redirect()->route('lab-chemicals.index')->with('success', 'Chemical usage restored successfully.');
    }

    public function submit(LabChemicalUsage $labChemical)
    {
        $this->authorize('submit', $labChemical);

        $original = $labChemical->only('status');
        $labChemical->update(['status' => 'pending']);

        ActivityLog::record('lab-chemicals', $labChemical->id, 'update', $original, ['status' => 'pending'], 'Submitted for supervisor approval');

        return back()->with('success', 'Chemical usage submitted for supervisor approval.');
    }

    public function approve(Request $request, LabChemicalUsage $labChemical)
    {
        $this->authorize('approve', $labChemical);

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
            'signature_data' => ['required', 'string'],
        ]);

        $signature = $this->storeSignature($validated['signature_data']);
        $original = $labChemical->only('status');

        DB::transaction(function () use ($labChemical, $validated, $request, $signature) {
            $labChemical->update(['status' => 'approved']);

            $this->approvals->record(
                'lab-chemicals',
                $labChemical->id,
                Approval::LEVEL_VERIFICATION,
                'approved',
                $request->user(),
                $validated['remarks'] ?? null,
                $labChemical->usage_no,
                $this->notifyRecipients($labChemical->used_by),
                $signature,
            );
        });

        ActivityLog::record('lab-chemicals', $labChemical->id, 'approve', $original, ['status' => 'approved'], $validated['remarks'] ?? null);

        return back()->with('success', 'Chemical usage approved.');
    }

    public function reject(Request $request, LabChemicalUsage $labChemical)
    {
        $this->authorize('approve', $labChemical);

        $validated = $request->validate(['remarks' => ['required', 'string', 'max:1000']]);

        $original = $labChemical->only('status');

        DB::transaction(function () use ($labChemical, $validated, $request) {
            $labChemical->update(['status' => 'rejected']);

            $this->approvals->record(
                'lab-chemicals',
                $labChemical->id,
                Approval::LEVEL_VERIFICATION,
                'rejected',
                $request->user(),
                $validated['remarks'],
                $labChemical->usage_no,
                $this->notifyRecipients($labChemical->used_by),
            );
        });

        ActivityLog::record('lab-chemicals', $labChemical->id, 'reject', $original, ['status' => 'rejected'], $validated['remarks']);

        return back()->with('success', 'Chemical usage rejected.');
    }

    public function unlock(Request $request, LabChemicalUsage $labChemical)
    {
        $this->authorize('unlock', $labChemical);

        $original = $labChemical->only('status');

        DB::transaction(function () use ($labChemical, $request) {
            $labChemical->update(['status' => 'draft']);

            $this->approvals->record(
                'lab-chemicals',
                $labChemical->id,
                Approval::LEVEL_VERIFICATION,
                'unlocked',
                $request->user(),
                null,
                $labChemical->usage_no,
                $this->notifyRecipients($labChemical->used_by),
            );
        });

        ActivityLog::record('lab-chemicals', $labChemical->id, 'unlock', $original, ['status' => 'draft']);

        return back()->with('success', 'Chemical usage unlocked for editing.');
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', LabChemicalUsage::class);

        $usages = $this->filtered($request)->with('usedBy')->latest('usage_date')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="lab-chemicals.csv"',
        ];

        return response()->streamDownload(function () use ($usages) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Usage No', 'Chemical', 'Used By', 'Date', 'Quantity', 'Unit', 'Batch No', 'Status']);
            foreach ($usages as $usage) {
                fputcsv($out, [
                    $usage->usage_no,
                    $usage->chemical_name,
                    $usage->usedBy?->name,
                    $usage->usage_date?->format('Y-m-d'),
                    $usage->quantity_used,
                    $usage->unit,
                    $usage->batch_number,
                    $usage->status,
                ]);
            }
            fclose($out);
        }, 'lab-chemicals.csv', $headers);
    }

    private function filtered(Request $request)
    {
        $user = $request->user();
        $search = $request->string('search')->toString();
        $trashed = $request->boolean('trashed');

        return LabChemicalUsage::query()
            ->when($trashed, fn ($q) => $q->onlyTrashed())
            ->when($user->hasRole('lab-technician'), fn ($q) => $q->where('used_by', $user->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('usage_date', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn ($q) => $q->whereDate('usage_date', '<=', $request->input('to_date')))
            ->when($search, fn ($q) => $q->where(fn ($qq) => $qq
                ->where('usage_no', 'like', "%{$search}%")
                ->orWhere('chemical_name', 'like', "%{$search}%")));
    }

    private function notifyRecipients(int $usedById)
    {
        $approvers = User::whereHas('role', fn ($q) => $q->whereIn('name', ['supervisor', 'admin', 'super-admin']))
            ->where('status', true)
            ->get();

        return $approvers->push(User::find($usedById))
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
