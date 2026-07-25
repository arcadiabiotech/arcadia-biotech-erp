<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Approval;
use App\Models\User;
use App\Notifications\ApprovalDecisionNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * A reusable approval workflow engine: any module records a decision by
 * calling record() with its own module_name/record_id/level — Booking uses
 * it today (level 1 = Accounts verification, level 2 = Admin approval);
 * Dispatch, Payment, Invoice, Plantation and future modules can plug into
 * the exact same table and service without their own approvals schema.
 *
 * Every write is wrapped in a transaction with the target row locked, so
 * two concurrent decisions on the same (module, record, level) can't race.
 */
class ApprovalService
{
    /**
     * @param  Collection<int, User>|array<int, User>  $notify  Users to notify of this decision.
     */
    public function record(
        string $moduleName,
        int $recordId,
        int $level,
        string $status,
        User $actor,
        ?string $remarks,
        string $recordLabel,
        Collection|array $notify = [],
        ?string $signature = null,
    ): Approval {
        return DB::transaction(function () use ($moduleName, $recordId, $level, $status, $actor, $remarks, $recordLabel, $notify, $signature) {
            Approval::firstOrCreate(
                ['module_name' => $moduleName, 'record_id' => $recordId, 'approval_level' => $level],
                ['status' => 'draft']
            );

            $approval = Approval::forRecord($moduleName, $recordId)
                ->where('approval_level', $level)
                ->lockForUpdate()
                ->first();

            $original = $approval->toArray();

            $attributes = ['status' => $status, 'remarks' => $remarks];

            if ($signature !== null) {
                $attributes['signature'] = $signature;
            }

            match ($status) {
                'verified', 'approved', 'completed' => $attributes += ['approved_by' => $actor->id, 'approved_at' => now()],
                'rejected' => $attributes += ['rejected_by' => $actor->id, 'rejected_at' => now()],
                'hold' => $attributes += ['hold_by' => $actor->id, 'hold_at' => now()],
                'unlocked' => $attributes += ['unlock_by' => $actor->id, 'unlock_at' => now()],
                default => null,
            };

            $approval->update($attributes);

            // activity_logs.action is a fixed enum (create/update/delete/
            // approve/reject/hold/unlock/login/logout) — Approval's own
            // status vocabulary (approved/rejected/unlocked/...) doesn't
            // match it 1:1, so it's translated rather than passed through.
            $activityAction = match ($status) {
                'approved' => 'approve',
                'rejected' => 'reject',
                'hold' => 'hold',
                'unlocked' => 'unlock',
                default => 'update',
            };

            ActivityLog::record(
                $moduleName,
                $recordId,
                $activityAction,
                $original,
                $approval->fresh()->toArray(),
                "Approval level {$level}: {$status}".($remarks ? " — {$remarks}" : '')
            );

            foreach ($notify as $recipient) {
                $recipient->notify(new ApprovalDecisionNotification($moduleName, $recordId, $recordLabel, $status, $actor, $remarks));
            }

            return $approval;
        });
    }

    public function statusFor(string $moduleName, int $recordId, int $level): ?string
    {
        return Approval::forRecord($moduleName, $recordId)->where('approval_level', $level)->value('status');
    }

    public function levelsFor(string $moduleName, int $recordId)
    {
        return Approval::forRecord($moduleName, $recordId)->orderBy('approval_level')->get();
    }
}
