<?php

namespace App\Policies;

use App\Models\LabEquipment;
use App\Models\User;

class LabEquipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'supervisor', 'lab-technician']) || $user->hasPermission('lab-equipment.view');
    }

    public function view(User $user, LabEquipment $labEquipment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('lab-equipment.create');
    }

    public function update(User $user, LabEquipment $labEquipment): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('lab-equipment.edit');
    }

    public function delete(User $user, LabEquipment $labEquipment): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('lab-equipment.delete');
    }

    public function restore(User $user, LabEquipment $labEquipment): bool
    {
        return $user->hasRole(['super-admin', 'admin']) && $user->hasPermission('lab-equipment.delete');
    }
}
