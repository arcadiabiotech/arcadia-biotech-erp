<?php

namespace App\Policies;

use App\Models\LabEquipmentMaintenanceLog;
use App\Models\User;

class LabEquipmentMaintenanceLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'supervisor', 'lab-technician']) || $user->hasPermission('lab-maintenance.view');
    }

    public function view(User $user, LabEquipmentMaintenanceLog $labMaintenance): bool
    {
        if ($user->hasRole(['super-admin', 'admin', 'supervisor'])) {
            return true;
        }

        return $labMaintenance->performed_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'lab-technician']) && $user->hasPermission('lab-maintenance.create');
    }

    public function update(User $user, LabEquipmentMaintenanceLog $labMaintenance): bool
    {
        if (! $user->hasPermission('lab-maintenance.edit')) {
            return false;
        }

        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        return $labMaintenance->performed_by === $user->id && $labMaintenance->status === 'draft';
    }

    public function delete(User $user, LabEquipmentMaintenanceLog $labMaintenance): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('lab-maintenance.delete');
    }

    public function restore(User $user, LabEquipmentMaintenanceLog $labMaintenance): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('lab-maintenance.delete');
    }

    public function submit(User $user, LabEquipmentMaintenanceLog $labMaintenance): bool
    {
        return $this->update($user, $labMaintenance) && $labMaintenance->status === 'draft';
    }

    public function approve(User $user, LabEquipmentMaintenanceLog $labMaintenance): bool
    {
        if ($labMaintenance->status !== 'pending') {
            return false;
        }

        return $user->hasRole(['super-admin', 'admin'])
            || ($user->hasRole('supervisor') && $user->hasPermission('lab-maintenance.approve'));
    }

    public function unlock(User $user, LabEquipmentMaintenanceLog $labMaintenance): bool
    {
        return $user->hasRole(['super-admin', 'admin'])
            && $user->hasPermission('lab-maintenance.unlock')
            && in_array($labMaintenance->status, ['approved', 'rejected'], true);
    }
}
