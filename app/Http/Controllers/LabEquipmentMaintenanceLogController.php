<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabEquipmentMaintenanceLogStoreRequest;
use App\Http\Requests\LabEquipmentMaintenanceLogUpdateRequest;
use App\Models\ActivityLog;
use App\Models\Approval;
use App\Models\LabEquipment;
use App\Models\LabEquipmentMaintenanceLog;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\LabNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LabEquipmentMaintenanceLogController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly LabNumberGenerator $numbers,
    ) {
        $this->authorizeResource(LabEquipmentMaintenanceLog::class, 'lab_maintenance');
    }

    public function index(Request $request)
    {
        $logs = $this->filtered($request)
            ->with(['equipment', 'performedBy'])
            ->latest('maintenance_date')
            ->paginate(15)
            ->withQueryString();

        return view('lab-maintenance.index', [
            'logs' => $logs,
            'trashed' => $request->boolean('trashed'),
        ]);
    }

    public function create(Request $request)
    {
        return view('lab-maintenance.form', [
            'labMaintenance' => new LabEquipmentMaintenanceLog(['maintenance_date' => now()->toDateString(), 'maintenance_type' => 'routine']),
            'equipmentOptions' => $this->equipmentOptions(),
            'technicians' => $this->technicianOptions($request->user()),
        ]);
    }

    public function store(LabEquipmentMaintenanceLogStoreRequest $request)
    {
        $data = $request->validated();
        $data['performed_by'] = $request->user()->hasRole(['super-admin', 'admin']) && $request->filled('performed_by')
            ? $request->input('performed_by')
            : $request->user()->id;
        $data['log_no'] = $this->numbers->next(LabEquipmentMaintenanceLog::class, 'log_no', 'EM');
        $data['status'] = 'draft';
        $data['created_by'] = $request->user()->id;

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('lab-maintenance/photos', 'public');
        }

        $log = LabEquipmentMaintenanceLog::create($data);

        ActivityLog::record('lab-maintenance', $log->id, 'create', [], $log->toArray());

        return redirect()->route('lab-maintenance.show', $log)->with('success', 'Maintenance log saved.');
    }

    public function show(LabEquipmentMaintenanceLog $labMaintenance)
    {
        $labMaintenance->load(['equipment', 'performedBy', 'createdBy', 'updatedBy']);

        $activity = ActivityLog::where('module', 'lab-maintenance')->where('record_id', $labMaintenance->id)->latest()->limit(30)->get();
        $approvalLevels = $this->approvals->levelsFor('lab-maintenance', $labMaintenance->id)->load(['approvedBy', 'rejectedBy', 'unlockBy']);

        return view('lab-maintenance.show', compact('labMaintenance', 'activity', 'approvalLevels'));
    }

    public function edit(Request $request, LabEquipmentMaintenanceLog $labMaintenance)
    {
        return view('lab-maintenance.form', [
            'labMaintenance' => $labMaintenance,
            'equipmentOptions' => $this->equipmentOptions(),
            'technicians' => $this->technicianOptions($request->user()),
        ]);
    }

    public function update(LabEquipmentMaintenanceLogUpdateRequest $request, LabEquipmentMaintenanceLog $labMaintenance)
    {
        $original = $labMaintenance->toArray();

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        if ($request->hasFile('photo')) {
            if ($labMaintenance->photo) {
                Storage::disk('public')->delete($labMaintenance->photo);
            }
            $data['photo'] = $request->file('photo')->store('lab-maintenance/photos', 'public');
        }

        $labMaintenance->update($data);

        ActivityLog::record('lab-maintenance', $labMaintenance->id, 'update', $original, $labMaintenance->fresh()->toArray());

        return redirect()->route('lab-maintenance.show', $labMaintenance)->with('success', 'Maintenance log updated.');
    }

    public function destroy(LabEquipmentMaintenanceLog $labMaintenance)
    {
        DB::transaction(function () use ($labMaintenance) {
            $labMaintenance->update(['deleted_by' => auth()->id()]);
            $labMaintenance->delete();
        });

        ActivityLog::record('lab-maintenance', $labMaintenance->id, 'delete');

        return redirect()->route('lab-maintenance.index')->with('success', 'Maintenance log deleted successfully.');
    }

    public function restore(LabEquipmentMaintenanceLog $labMaintenance)
    {
        $this->authorize('restore', $labMaintenance);

        DB::transaction(function () use ($labMaintenance) {
            $labMaintenance->restore();
            $labMaintenance->update(['deleted_by' => null]);
        });

        ActivityLog::record('lab-maintenance', $labMaintenance->id, 'update', [], [], 'Maintenance log restored');

        return redirect()->route('lab-maintenance.index')->with('success', 'Maintenance log restored successfully.');
    }

    public function submit(LabEquipmentMaintenanceLog $labMaintenance)
    {
        $this->authorize('submit', $labMaintenance);

        $original = $labMaintenance->only('status');
        $labMaintenance->update(['status' => 'pending']);

        ActivityLog::record('lab-maintenance', $labMaintenance->id, 'update', $original, ['status' => 'pending'], 'Submitted for supervisor approval');

        return back()->with('success', 'Maintenance log submitted for supervisor approval.');
    }

    public function approve(Request $request, LabEquipmentMaintenanceLog $labMaintenance)
    {
        $this->authorize('approve', $labMaintenance);

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
            'signature_data' => ['required', 'string'],
        ]);

        $signature = $this->storeSignature($validated['signature_data']);
        $original = $labMaintenance->only('status');

        DB::transaction(function () use ($labMaintenance, $validated, $request, $signature) {
            $labMaintenance->update(['status' => 'approved']);

            $this->approvals->record(
                'lab-maintenance',
                $labMaintenance->id,
                Approval::LEVEL_VERIFICATION,
                'approved',
                $request->user(),
                $validated['remarks'] ?? null,
                $labMaintenance->log_no,
                $this->notifyRecipients($labMaintenance->performed_by),
                $signature,
            );
        });

        ActivityLog::record('lab-maintenance', $labMaintenance->id, 'approve', $original, ['status' => 'approved'], $validated['remarks'] ?? null);

        return back()->with('success', 'Maintenance log approved.');
    }

    public function reject(Request $request, LabEquipmentMaintenanceLog $labMaintenance)
    {
        $this->authorize('approve', $labMaintenance);

        $validated = $request->validate(['remarks' => ['required', 'string', 'max:1000']]);

        $original = $labMaintenance->only('status');

        DB::transaction(function () use ($labMaintenance, $validated, $request) {
            $labMaintenance->update(['status' => 'rejected']);

            $this->approvals->record(
                'lab-maintenance',
                $labMaintenance->id,
                Approval::LEVEL_VERIFICATION,
                'rejected',
                $request->user(),
                $validated['remarks'],
                $labMaintenance->log_no,
                $this->notifyRecipients($labMaintenance->performed_by),
            );
        });

        ActivityLog::record('lab-maintenance', $labMaintenance->id, 'reject', $original, ['status' => 'rejected'], $validated['remarks']);

        return back()->with('success', 'Maintenance log rejected.');
    }

    public function unlock(Request $request, LabEquipmentMaintenanceLog $labMaintenance)
    {
        $this->authorize('unlock', $labMaintenance);

        $original = $labMaintenance->only('status');

        DB::transaction(function () use ($labMaintenance, $request) {
            $labMaintenance->update(['status' => 'draft']);

            $this->approvals->record(
                'lab-maintenance',
                $labMaintenance->id,
                Approval::LEVEL_VERIFICATION,
                'unlocked',
                $request->user(),
                null,
                $labMaintenance->log_no,
                $this->notifyRecipients($labMaintenance->performed_by),
            );
        });

        ActivityLog::record('lab-maintenance', $labMaintenance->id, 'unlock', $original, ['status' => 'draft']);

        return back()->with('success', 'Maintenance log unlocked for editing.');
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', LabEquipmentMaintenanceLog::class);

        $logs = $this->filtered($request)->with(['equipment', 'performedBy'])->latest('maintenance_date')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="lab-maintenance.csv"',
        ];

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Log No', 'Equipment', 'Performed By', 'Date', 'Type', 'Next Due', 'Status']);
            foreach ($logs as $log) {
                fputcsv($out, [
                    $log->log_no,
                    $log->equipment?->name,
                    $log->performedBy?->name,
                    $log->maintenance_date?->format('Y-m-d'),
                    $log->maintenance_type,
                    $log->next_due_date?->format('Y-m-d'),
                    $log->status,
                ]);
            }
            fclose($out);
        }, 'lab-maintenance.csv', $headers);
    }

    private function filtered(Request $request)
    {
        $user = $request->user();
        $search = $request->string('search')->toString();
        $trashed = $request->boolean('trashed');

        return LabEquipmentMaintenanceLog::query()
            ->when($trashed, fn ($q) => $q->onlyTrashed())
            ->when($user->hasRole('lab-technician'), fn ($q) => $q->where('performed_by', $user->id))
            ->when($request->filled('lab_equipment_id'), fn ($q) => $q->where('lab_equipment_id', $request->input('lab_equipment_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('maintenance_date', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn ($q) => $q->whereDate('maintenance_date', '<=', $request->input('to_date')))
            ->when($search, fn ($q) => $q->where(fn ($qq) => $qq
                ->where('log_no', 'like', "%{$search}%")
                ->orWhereHas('equipment', fn ($qqq) => $qqq->where('name', 'like', "%{$search}%"))));
    }

    private function equipmentOptions()
    {
        return LabEquipment::where('status', '!=', 'decommissioned')->orderBy('name')->get();
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

    private function notifyRecipients(int $performedById)
    {
        $approvers = User::whereHas('role', fn ($q) => $q->whereIn('name', ['supervisor', 'admin', 'super-admin']))
            ->where('status', true)
            ->get();

        return $approvers->push(User::find($performedById))
            ->filter()
            ->unique('id')
            ->reject(fn ($user) => $user->id === auth()->id());
    }

    private function storeSignature(string $dataUrl): string
    {
        $encoded = preg_replace('#^data:image/\w+;base64,#i', '', $dataUrl);
        $path = 'approvals/signatures/'.uniqid('sig_', true).'.png';

        Storage::disk('public')->put($path, base64_decode($encoded));

        return $path;
    }
}
