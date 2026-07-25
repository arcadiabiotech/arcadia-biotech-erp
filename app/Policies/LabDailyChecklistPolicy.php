<?php

namespace App\Policies;

use App\Models\LabDailyChecklist;
use App\Models\User;

class LabDailyChecklistPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'supervisor', 'lab-technician']) || $user->hasPermission('lab-checklists.view');
    }

    public function view(User $user, LabDailyChecklist $labChecklist): bool
    {
        if ($user->hasRole(['super-admin', 'admin', 'supervisor'])) {
            return true;
        }

        return $labChecklist->employee_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'lab-technician']) && $user->hasPermission('lab-checklists.create');
    }

    /**
     * Locked once submitted for approval — matches Booking's draft-only
     * edit rule. Admin/Super Admin may always edit (correcting mistakes),
     * a Lab Technician only their own record and only while still draft.
     */
    public function update(User $user, LabDailyChecklist $labChecklist): bool
    {
        if (! $user->hasPermission('lab-checklists.edit')) {
            return false;
        }

        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        return $labChecklist->employee_id === $user->id && $labChecklist->status === 'draft';
    }

    public function delete(User $user, LabDailyChecklist $labChecklist): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('lab-checklists.delete');
    }

    public function restore(User $user, LabDailyChecklist $labChecklist): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('lab-checklists.delete');
    }

    public function submit(User $user, LabDailyChecklist $labChecklist): bool
    {
        return $this->update($user, $labChecklist) && $labChecklist->status === 'draft';
    }

    /**
     * Shared by both the approve and reject actions — same precedent as
     * DispatchPlanController::reject() authorizing against 'approve'.
     */
    public function approve(User $user, LabDailyChecklist $labChecklist): bool
    {
        if ($labChecklist->status !== 'pending') {
            return false;
        }

        return $user->hasRole(['super-admin', 'admin'])
            || ($user->hasRole('supervisor') && $user->hasPermission('lab-checklists.approve'));
    }

    public function unlock(User $user, LabDailyChecklist $labChecklist): bool
    {
        return $user->hasRole(['super-admin', 'admin'])
            && $user->hasPermission('lab-checklists.unlock')
            && in_array($labChecklist->status, ['approved', 'rejected'], true);
    }
}
