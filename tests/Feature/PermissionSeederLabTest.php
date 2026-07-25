<?php

use App\Models\Permission;
use App\Models\Role;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

test('lab-technician role has create/edit/view lab permissions but no approve/delete/unlock', function () {
    $role = Role::where('name', 'lab-technician')->first();
    $permissionNames = $role->permissions()->pluck('name')->all();

    expect($permissionNames)->toContain('lab-checklists.create', 'lab-checklists.edit', 'lab-checklists.view');
    expect($permissionNames)->not->toContain('lab-checklists.approve', 'lab-checklists.delete', 'lab-checklists.unlock', 'lab-reports.view');
});

test('supervisor role retains its dispatch-planning permissions and gains lab approval permissions', function () {
    $role = Role::where('name', 'supervisor')->first();
    $permissionNames = $role->permissions()->pluck('name')->all();

    expect($permissionNames)->toContain('dispatch-planning.view', 'dispatch-planning.load', 'dispatch-planning.approve-loading');
    expect($permissionNames)->toContain('lab-checklists.approve', 'lab-maintenance.approve', 'lab-media.approve', 'lab-chemicals.approve', 'lab-contamination.approve', 'lab-reports.view');
    expect($permissionNames)->not->toContain('lab-checklists.create');
});

test('admin and super-admin automatically hold every lab-* permission', function () {
    $labPermissionNames = Permission::where('name', 'like', 'lab-%')->pluck('name')->all();

    foreach (['admin', 'super-admin'] as $roleName) {
        $role = Role::where('name', $roleName)->first();
        $rolePermissionNames = $role->permissions()->pluck('name')->all();

        foreach ($labPermissionNames as $permissionName) {
            expect($rolePermissionNames)->toContain($permissionName);
        }
    }
});
