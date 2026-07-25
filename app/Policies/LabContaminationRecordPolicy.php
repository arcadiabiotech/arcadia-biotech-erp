<?php

namespace App\Policies;

use App\Models\LabContaminationRecord;
use App\Models\User;

class LabContaminationRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'supervisor', 'lab-technician']) || $user->hasPermission('lab-contamination.view');
    }

    public function view(User $user, LabContaminationRecord $labContamination): bool
    {
        if ($user->hasRole(['super-admin', 'admin', 'supervisor'])) {
            return true;
        }

        return $labContamination->reported_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'lab-technician']) && $user->hasPermission('lab-contamination.create');
    }

    public function update(User $user, LabContaminationRecord $labContamination): bool
    {
        if (! $user->hasPermission('lab-contamination.edit')) {
            return false;
        }

        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        return $labContamination->reported_by === $user->id && $labContamination->status === 'draft';
    }

    public function delete(User $user, LabContaminationRecord $labContamination): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('lab-contamination.delete');
    }

    public function restore(User $user, LabContaminationRecord $labContamination): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('lab-contamination.delete');
    }

    public function submit(User $user, LabContaminationRecord $labContamination): bool
    {
        return $this->update($user, $labContamination) && $labContamination->status === 'draft';
    }

    public function approve(User $user, LabContaminationRecord $labContamination): bool
    {
        if ($labContamination->status !== 'pending') {
            return false;
        }

        return $user->hasRole(['super-admin', 'admin'])
            || ($user->hasRole('supervisor') && $user->hasPermission('lab-contamination.approve'));
    }

    public function unlock(User $user, LabContaminationRecord $labContamination): bool
    {
        return $user->hasRole(['super-admin', 'admin'])
            && $user->hasPermission('lab-contamination.unlock')
            && in_array($labContamination->status, ['approved', 'rejected'], true);
    }
}
