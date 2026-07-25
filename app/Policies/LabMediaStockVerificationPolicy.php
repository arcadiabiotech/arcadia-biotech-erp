<?php

namespace App\Policies;

use App\Models\LabMediaStockVerification;
use App\Models\User;

class LabMediaStockVerificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'supervisor', 'lab-technician']) || $user->hasPermission('lab-media.view');
    }

    public function view(User $user, LabMediaStockVerification $labMedia): bool
    {
        if ($user->hasRole(['super-admin', 'admin', 'supervisor'])) {
            return true;
        }

        return $labMedia->verified_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'lab-technician']) && $user->hasPermission('lab-media.create');
    }

    public function update(User $user, LabMediaStockVerification $labMedia): bool
    {
        if (! $user->hasPermission('lab-media.edit')) {
            return false;
        }

        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        return $labMedia->verified_by === $user->id && $labMedia->status === 'draft';
    }

    public function delete(User $user, LabMediaStockVerification $labMedia): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('lab-media.delete');
    }

    public function restore(User $user, LabMediaStockVerification $labMedia): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('lab-media.delete');
    }

    public function submit(User $user, LabMediaStockVerification $labMedia): bool
    {
        return $this->update($user, $labMedia) && $labMedia->status === 'draft';
    }

    public function approve(User $user, LabMediaStockVerification $labMedia): bool
    {
        if ($labMedia->status !== 'pending') {
            return false;
        }

        return $user->hasRole(['super-admin', 'admin'])
            || ($user->hasRole('supervisor') && $user->hasPermission('lab-media.approve'));
    }

    public function unlock(User $user, LabMediaStockVerification $labMedia): bool
    {
        return $user->hasRole(['super-admin', 'admin'])
            && $user->hasPermission('lab-media.unlock')
            && in_array($labMedia->status, ['approved', 'rejected'], true);
    }
}
