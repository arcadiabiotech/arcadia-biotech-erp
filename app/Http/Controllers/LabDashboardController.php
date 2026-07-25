<?php

namespace App\Http\Controllers;

use App\Models\LabChemicalUsage;
use App\Models\LabContaminationRecord;
use App\Models\LabDailyChecklist;
use App\Models\LabEquipment;
use App\Models\LabEquipmentMaintenanceLog;
use App\Models\LabMediaStockVerification;
use App\Models\User;
use Illuminate\Http\Request;

class LabDashboardController extends Controller
{
    private const WORKFLOW_MODELS = [
        'lab-checklists' => LabDailyChecklist::class,
        'lab-maintenance' => LabEquipmentMaintenanceLog::class,
        'lab-media' => LabMediaStockVerification::class,
        'lab-chemicals' => LabChemicalUsage::class,
        'lab-contamination' => LabContaminationRecord::class,
    ];

    public function index(Request $request)
    {
        $user = $request->user();

        return match ($user->role?->name) {
            'lab-technician' => $this->technician($user),
            'supervisor', 'admin', 'super-admin' => $this->supervisor(),
            default => abort(403),
        };
    }

    private function technician(User $user)
    {
        $todayChecklist = LabDailyChecklist::where('employee_id', $user->id)->whereDate('checklist_date', today())->with('checklistItems')->first();

        $ownPending = collect(self::WORKFLOW_MODELS)->map(function (string $modelClass, string $module) use ($user) {
            $ownerColumn = match ($module) {
                'lab-checklists' => 'employee_id',
                'lab-maintenance' => 'performed_by',
                'lab-media' => 'verified_by',
                'lab-chemicals' => 'used_by',
                'lab-contamination' => 'reported_by',
            };

            return $modelClass::where($ownerColumn, $user->id)->whereIn('status', ['draft', 'pending'])->count();
        });

        $recent = LabDailyChecklist::where('employee_id', $user->id)->latest('checklist_date')->limit(5)->get();

        return view('lab.dashboard.technician', [
            'todayChecklist' => $todayChecklist,
            'ownPending' => $ownPending,
            'recent' => $recent,
        ]);
    }

    private function supervisor()
    {
        $activeTechnicianCount = max(1, User::whereHas('role', fn ($q) => $q->where('name', 'lab-technician'))->where('status', true)->count());

        $todayApproved = LabDailyChecklist::whereDate('checklist_date', today())->where('status', 'approved')->distinct('employee_id')->count('employee_id');
        $dailyCompliance = round(($todayApproved / $activeTechnicianCount) * 100, 1);

        $weekStart = now()->startOfWeek();
        $weekApprovedDays = LabDailyChecklist::where('status', 'approved')->whereBetween('checklist_date', [$weekStart->toDateString(), now()->toDateString()])->count();
        $weekExpectedDays = $activeTechnicianCount * (now()->diffInDays($weekStart) + 1);
        $weeklyCompliance = $weekExpectedDays > 0 ? round(($weekApprovedDays / $weekExpectedDays) * 100, 1) : 0;

        $pendingCounts = collect(self::WORKFLOW_MODELS)->map(fn (string $modelClass) => $modelClass::where('status', 'pending')->count());

        $equipmentUnderMaintenance = LabEquipment::where('status', 'under_maintenance')->count();
        $overdueMaintenance = LabEquipmentMaintenanceLog::whereNotNull('next_due_date')->where('next_due_date', '<', today())->count();
        $lowStockMedia = LabMediaStockVerification::whereNotNull('reorder_level')->whereColumn('closing_stock', '<=', 'reorder_level')->count();
        $openContamination = LabContaminationRecord::where('status', '!=', 'approved')->count();
        $criticalContamination = LabContaminationRecord::where('status', '!=', 'approved')->where('severity', 'critical')->count();

        $pendingRecords = collect(self::WORKFLOW_MODELS)->flatMap(function (string $modelClass, string $module) {
            return $modelClass::where('status', 'pending')->latest()->limit(5)->get()->map(fn ($record) => [
                'module' => $module,
                'record' => $record,
            ]);
        })->sortByDesc(fn ($item) => $item['record']->created_at)->take(10);

        return view('lab.dashboard.supervisor', [
            'dailyCompliance' => $dailyCompliance,
            'weeklyCompliance' => $weeklyCompliance,
            'pendingCounts' => $pendingCounts,
            'equipmentUnderMaintenance' => $equipmentUnderMaintenance,
            'overdueMaintenance' => $overdueMaintenance,
            'lowStockMedia' => $lowStockMedia,
            'openContamination' => $openContamination,
            'criticalContamination' => $criticalContamination,
            'pendingRecords' => $pendingRecords,
        ]);
    }
}
