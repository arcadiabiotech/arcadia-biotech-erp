<?php

namespace App\Policies;

use App\Models\LabChemicalUsage;
use App\Models\User;

class LabChemicalUsagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'supervisor', 'lab-technician']) || $user->hasPermission('lab-chemicals.view');
    }

    public function view(User $user, LabChemicalUsage $labChemical): bool
    {
        if ($user->hasRole(['super-admin', 'admin', 'supervisor'])) {
            return true;
        }

        return $labChemical->used_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'lab-technician']) && $user->hasPermission('lab-chemicals.create');
    }

    public function update(User $user, LabChemicalUsage $labChemical): bool
    {
        if (! $user->hasPermission('lab-chemicals.edit')) {
            return false;
        }

        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        return $labChemical->used_by === $user->id && $labChemical->status === 'draft';
    }

    public function delete(User $user, LabChemicalUsage $labChemical): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('lab-chemicals.delete');
    }

    public function restore(User $user, LabChemicalUsage $labChemical): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('lab-chemicals.delete');
    }

    public function submit(User $user, LabChemicalUsage $labChemical): bool
    {
        return $this->update($user, $labChemical) && $labChemical->status === 'draft';
    }

    public function approve(User $user, LabChemicalUsage $labChemical): bool
    {
        if ($labChemical->status !== 'pending') {
            return false;
        }

        return $user->hasRole(['super-admin', 'admin'])
            || ($user->hasRole('supervisor') && $user->hasPermission('lab-chemicals.approve'));
    }

    public function unlock(User $user, LabChemicalUsage $labChemical): bool
    {
        return $user->hasRole(['super-admin', 'admin'])
            && $user->hasPermission('lab-chemicals.unlock')
            && in_array($labChemical->status, ['approved', 'rejected'], true);
    }
}
