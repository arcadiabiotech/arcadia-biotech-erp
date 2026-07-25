<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabContaminationRecordStoreRequest;
use App\Http\Requests\LabContaminationRecordUpdateRequest;
use App\Models\ActivityLog;
use App\Models\Approval;
use App\Models\LabContaminationRecord;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\LabNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LabContaminationRecordController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly LabNumberGenerator $numbers,
    ) {
        $this->authorizeResource(LabContaminationRecord::class, 'lab_contamination');
    }

    public function index(Request $request)
    {
        $records = $this->filtered($request)
            ->with('reportedBy')
            ->latest('contamination_date')
            ->paginate(15)
            ->withQueryString();

        return view('lab-contamination.index', [
            'records' => $records,
            'trashed' => $request->boolean('trashed'),
        ]);
    }

    public function create(Request $request)
    {
        return view('lab-contamination.form', [
            'labContamination' => new LabContaminationRecord(['contamination_date' => now()->toDateString(), 'severity' => 'low', 'contamination_type' => 'unknown']),
            'technicians' => $this->technicianOptions($request->user()),
        ]);
    }

    public function store(LabContaminationRecordStoreRequest $request)
    {
        $data = $request->validated();
        $data['reported_by'] = $request->user()->hasRole(['super-admin', 'admin']) && $request->filled('reported_by')
            ? $request->input('reported_by')
            : $request->user()->id;
        $data['contamination_no'] = $this->numbers->next(LabContaminationRecord::class, 'contamination_no', 'CR');
        $data['status'] = 'draft';
        $data['created_by'] = $request->user()->id;

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('lab-contamination/photos', 'public');
        }

        $record = LabContaminationRecord::create($data);

        ActivityLog::record('lab-contamination', $record->id, 'create', [], $record->toArray());

        return redirect()->route('lab-contamination.show', $record)->with('success', 'Contamination record logged.');
    }

    public function show(LabContaminationRecord $labContamination)
    {
        $labContamination->load(['reportedBy', 'createdBy', 'updatedBy']);

        $activity = ActivityLog::where('module', 'lab-contamination')->where('record_id', $labContamination->id)->latest()->limit(30)->get();
        $approvalLevels = $this->approvals->levelsFor('lab-contamination', $labContamination->id)->load(['approvedBy', 'rejectedBy', 'unlockBy']);

        return view('lab-contamination.show', compact('labContamination', 'activity', 'approvalLevels'));
    }

    public function edit(Request $request, LabContaminationRecord $labContamination)
    {
        return view('lab-contamination.form', [
            'labContamination' => $labContamination,
            'technicians' => $this->technicianOptions($request->user()),
        ]);
    }

    public function update(LabContaminationRecordUpdateRequest $request, LabContaminationRecord $labContamination)
    {
        $original = $labContamination->toArray();

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        if ($request->hasFile('photo')) {
            if ($labContamination->photo) {
                Storage::disk('public')->delete($labContamination->photo);
            }
            $data['photo'] = $request->file('photo')->store('lab-contamination/photos', 'public');
        }

        $labContamination->update($data);

        ActivityLog::record('lab-contamination', $labContamination->id, 'update', $original, $labContamination->fresh()->toArray());

        return redirect()->route('lab-contamination.show', $labContamination)->with('success', 'Contamination record updated.');
    }

    public function destroy(LabContaminationRecord $labContamination)
    {
        DB::transaction(function () use ($labContamination) {
            $labContamination->update(['deleted_by' => auth()->id()]);
            $labContamination->delete();
        });

        ActivityLog::record('lab-contamination', $labContamination->id, 'delete');

        return redirect()->route('lab-contamination.index')->with('success', 'Contamination record deleted successfully.');
    }

    public function restore(LabContaminationRecord $labContamination)
    {
        $this->authorize('restore', $labContamination);

        DB::transaction(function () use ($labContamination) {
            $labContamination->restore();
            $labContamination->update(['deleted_by' => null]);
        });

        ActivityLog::record('lab-contamination', $labContamination->id, 'update', [], [], 'Contamination record restored');

        return redirect()->route('lab-contamination.index')->with('success', 'Contamination record restored successfully.');
    }

    public function submit(LabContaminationRecord $labContamination)
    {
        $this->authorize('submit', $labContamination);

        $original = $labContamination->only('status');
        $labContamination->update(['status' => 'pending']);

        ActivityLog::record('lab-contamination', $labContamination->id, 'update', $original, ['status' => 'pending'], 'Submitted for supervisor approval');

        return back()->with('success', 'Contamination record submitted for supervisor approval.');
    }

    public function approve(Request $request, LabContaminationRecord $labContamination)
    {
        $this->authorize('approve', $labContamination);

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
            'signature_data' => ['required', 'string'],
        ]);

        $signature = $this->storeSignature($validated['signature_data']);
        $original = $labContamination->only('status');

        DB::transaction(function () use ($labContamination, $validated, $request, $signature) {
            $labContamination->update(['status' => 'approved']);

            $this->approvals->record(
                'lab-contamination',
                $labContamination->id,
                Approval::LEVEL_VERIFICATION,
                'approved',
                $request->user(),
                $validated['remarks'] ?? null,
                $labContamination->contamination_no,
                $this->notifyRecipients($labContamination->reported_by),
                $signature,
            );
        });

        ActivityLog::record('lab-contamination', $labContamination->id, 'approve', $original, ['status' => 'approved'], $validated['remarks'] ?? null);

        return back()->with('success', 'Contamination record approved.');
    }

    public function reject(Request $request, LabContaminationRecord $labContamination)
    {
        $this->authorize('approve', $labContamination);

        $validated = $request->validate(['remarks' => ['required', 'string', 'max:1000']]);

        $original = $labContamination->only('status');

        DB::transaction(function () use ($labContamination, $validated, $request) {
            $labContamination->update(['status' => 'rejected']);

            $this->approvals->record(
                'lab-contamination',
                $labContamination->id,
                Approval::LEVEL_VERIFICATION,
                'rejected',
                $request->user(),
                $validated['remarks'],
                $labContamination->contamination_no,
                $this->notifyRecipients($labContamination->reported_by),
            );
        });

        ActivityLog::record('lab-contamination', $labContamination->id, 'reject', $original, ['status' => 'rejected'], $validated['remarks']);

        return back()->with('success', 'Contamination record rejected.');
    }

    public function unlock(Request $request, LabContaminationRecord $labContamination)
    {
        $this->authorize('unlock', $labContamination);

        $original = $labContamination->only('status');

        DB::transaction(function () use ($labContamination, $request) {
            $labContamination->update(['status' => 'draft']);

            $this->approvals->record(
                'lab-contamination',
                $labContamination->id,
                Approval::LEVEL_VERIFICATION,
                'unlocked',
                $request->user(),
                null,
                $labContamination->contamination_no,
                $this->notifyRecipients($labContamination->reported_by),
            );
        });

        ActivityLog::record('lab-contamination', $labContamination->id, 'unlock', $original, ['status' => 'draft']);

        return back()->with('success', 'Contamination record unlocked for editing.');
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', LabContaminationRecord::class);

        $records = $this->filtered($request)->with('reportedBy')->latest('contamination_date')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="lab-contamination.csv"',
        ];

        return response()->streamDownload(function () use ($records) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Contamination No', 'Reported By', 'Date', 'Batch No', 'Type', 'Severity', 'Affected Qty', 'Status']);
            foreach ($records as $record) {
                fputcsv($out, [
                    $record->contamination_no,
                    $record->reportedBy?->name,
                    $record->contamination_date?->format('Y-m-d'),
                    $record->culture_batch_number,
                    $record->contamination_type,
                    $record->severity,
                    $record->affected_qty,
                    $record->status,
                ]);
            }
            fclose($out);
        }, 'lab-contamination.csv', $headers);
    }

    private function filtered(Request $request)
    {
        $user = $request->user();
        $search = $request->string('search')->toString();
        $trashed = $request->boolean('trashed');

        return LabContaminationRecord::query()
            ->when($trashed, fn ($q) => $q->onlyTrashed())
            ->when($user->hasRole('lab-technician'), fn ($q) => $q->where('reported_by', $user->id))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->input('severity')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('contamination_date', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn ($q) => $q->whereDate('contamination_date', '<=', $request->input('to_date')))
            ->when($search, fn ($q) => $q->where(fn ($qq) => $qq
                ->where('contamination_no', 'like', "%{$search}%")
                ->orWhere('culture_batch_number', 'like', "%{$search}%")));
    }

    private function notifyRecipients(int $reportedById)
    {
        $approvers = User::whereHas('role', fn ($q) => $q->whereIn('name', ['supervisor', 'admin', 'super-admin']))
            ->where('status', true)
            ->get();

        return $approvers->push(User::find($reportedById))
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
