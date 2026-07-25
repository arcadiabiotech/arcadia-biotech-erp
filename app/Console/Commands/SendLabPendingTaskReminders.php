<?php

namespace App\Console\Commands;

use App\Models\LabChemicalUsage;
use App\Models\LabContaminationRecord;
use App\Models\LabDailyChecklist;
use App\Models\LabEquipmentMaintenanceLog;
use App\Models\LabMediaStockVerification;
use App\Models\User;
use App\Notifications\PendingLabTaskNotification;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class SendLabPendingTaskReminders extends Command
{
    protected $signature = 'lab:send-pending-reminders';

    protected $description = 'Notify lab technicians of a missing daily checklist, and supervisors/admins of checklists or logs stuck pending approval';

    private const MISSING_CHECKLIST_CUTOFF = '11:00';

    private const STUCK_APPROVAL_HOURS = 6;

    private const WORKFLOW_MODELS = [
        'lab-checklists' => LabDailyChecklist::class,
        'lab-maintenance' => LabEquipmentMaintenanceLog::class,
        'lab-media' => LabMediaStockVerification::class,
        'lab-chemicals' => LabChemicalUsage::class,
        'lab-contamination' => LabContaminationRecord::class,
    ];

    public function handle(): int
    {
        $this->notifyMissingChecklists();
        $this->notifyStuckApprovals();

        $this->info('Lab Ops pending-task reminders sent.');

        return self::SUCCESS;
    }

    private function notifyMissingChecklists(): void
    {
        if (now()->format('H:i') < self::MISSING_CHECKLIST_CUTOFF) {
            return;
        }

        $technicians = User::whereHas('role', fn ($q) => $q->where('name', 'lab-technician'))->where('status', true)->get();
        $approvers = User::whereHas('role', fn ($q) => $q->whereIn('name', ['supervisor', 'admin', 'super-admin']))->where('status', true)->get();

        foreach ($technicians as $technician) {
            $hasChecklistToday = LabDailyChecklist::where('employee_id', $technician->id)->whereDate('checklist_date', today())->exists();

            if ($hasChecklistToday) {
                continue;
            }

            $alreadyNotified = $technician->notifications()
                ->where('type', PendingLabTaskNotification::class)
                ->whereDate('created_at', today())
                ->where('data->reason', 'missing_checklist')
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            $message = "{$technician->name} has not submitted today's morning checklist.";
            $notification = new PendingLabTaskNotification('missing_checklist', $message);

            $technician->notify($notification);

            foreach ($approvers as $approver) {
                $approver->notify($notification);
            }
        }
    }

    private function notifyStuckApprovals(): void
    {
        $approvers = User::whereHas('role', fn ($q) => $q->whereIn('name', ['supervisor', 'admin', 'super-admin']))->where('status', true)->get();

        if ($approvers->isEmpty()) {
            return;
        }

        $cutoff = now()->subHours(self::STUCK_APPROVAL_HOURS);

        foreach (self::WORKFLOW_MODELS as $module => $modelClass) {
            $stuckRecords = $modelClass::where('status', 'pending')->where('updated_at', '<=', $cutoff)->get();

            foreach ($stuckRecords as $record) {
                $alreadyNotified = DatabaseNotification::where('type', PendingLabTaskNotification::class)
                    ->where('data->reason', 'stuck_approval')
                    ->where('data->module_name', $module)
                    ->where('data->record_id', $record->id)
                    ->exists();

                if ($alreadyNotified) {
                    continue;
                }

                $message = 'A '.str_replace('-', ' ', $module)." entry has been pending approval for over ".self::STUCK_APPROVAL_HOURS.' hours.';
                $notification = new PendingLabTaskNotification('stuck_approval', $message, $module, $record->id);

                foreach ($approvers as $approver) {
                    $approver->notify($notification);
                }
            }
        }
    }
}
