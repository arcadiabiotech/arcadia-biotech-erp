<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'roles.manage', 'display_name' => 'Manage roles', 'group' => 'Administration'],
            ['name' => 'permissions.manage', 'display_name' => 'Manage permissions', 'group' => 'Administration'],
            ['name' => 'dealer-assignments.manage', 'display_name' => 'Manage dealer assignments', 'group' => 'Marketing'],

            ['name' => 'dealers.view', 'display_name' => 'View dealers', 'group' => 'Dealers'],
            ['name' => 'dealers.create', 'display_name' => 'Create dealers', 'group' => 'Dealers'],
            ['name' => 'dealers.edit', 'display_name' => 'Edit dealers', 'group' => 'Dealers'],
            ['name' => 'dealers.delete', 'display_name' => 'Delete dealers', 'group' => 'Dealers'],

            ['name' => 'farmers.view', 'display_name' => 'View farmers', 'group' => 'Farmers'],
            ['name' => 'farmers.create', 'display_name' => 'Create farmers', 'group' => 'Farmers'],
            ['name' => 'farmers.edit', 'display_name' => 'Edit farmers', 'group' => 'Farmers'],
            ['name' => 'farmers.delete', 'display_name' => 'Delete farmers', 'group' => 'Farmers'],

            ['name' => 'users.view', 'display_name' => 'View users', 'group' => 'Users'],
            ['name' => 'users.create', 'display_name' => 'Create users', 'group' => 'Users'],
            ['name' => 'users.edit', 'display_name' => 'Edit users', 'group' => 'Users'],
            ['name' => 'users.delete', 'display_name' => 'Delete users', 'group' => 'Users'],

            ['name' => 'bookings.view', 'display_name' => 'View bookings', 'group' => 'Bookings'],
            ['name' => 'bookings.create', 'display_name' => 'Create bookings', 'group' => 'Bookings'],
            ['name' => 'bookings.edit', 'display_name' => 'Edit bookings', 'group' => 'Bookings'],
            ['name' => 'bookings.approve', 'display_name' => 'Approve bookings', 'group' => 'Bookings'],
            ['name' => 'bookings.reject', 'display_name' => 'Reject bookings', 'group' => 'Bookings'],
            ['name' => 'bookings.hold', 'display_name' => 'Hold bookings', 'group' => 'Bookings'],
            ['name' => 'bookings.unlock', 'display_name' => 'Unlock bookings', 'group' => 'Bookings'],
            ['name' => 'bookings.delete', 'display_name' => 'Delete bookings', 'group' => 'Bookings'],

            ['name' => 'payments.view', 'display_name' => 'View payments', 'group' => 'Payments'],
            ['name' => 'payments.create', 'display_name' => 'Receive payments', 'group' => 'Payments'],

            ['name' => 'challans.view', 'display_name' => 'View challans', 'group' => 'Challans'],
            ['name' => 'challans.create', 'display_name' => 'Create challans', 'group' => 'Challans'],

            ['name' => 'invoices.view', 'display_name' => 'View invoices', 'group' => 'Invoices'],
            ['name' => 'invoices.create', 'display_name' => 'Create invoices', 'group' => 'Invoices'],

            ['name' => 'dispatch.view', 'display_name' => 'View dispatches', 'group' => 'Dispatch'],
            ['name' => 'dispatch.create', 'display_name' => 'Create dispatches', 'group' => 'Dispatch'],

            ['name' => 'reports.view', 'display_name' => 'View reports', 'group' => 'Reports'],

            ['name' => 'settings.manage', 'display_name' => 'Manage settings', 'group' => 'Settings'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission['name']], $permission + ['status' => true]);
        }

        $allPermissionNames = array_column($permissions, 'name');

        $roleMatrix = [
            'super-admin' => $allPermissionNames,
            'admin' => $allPermissionNames,
            'marketing' => ['dealers.view', 'farmers.view', 'bookings.view', 'bookings.create', 'bookings.edit'],
            'dealer' => ['dealers.view', 'farmers.view', 'farmers.edit', 'bookings.view'],
            'accounts' => [
                'dealers.view',
                'farmers.view', 'farmers.edit',
                'bookings.view', 'bookings.create', 'bookings.edit',
                'payments.view', 'payments.create',
                'challans.view', 'challans.create',
                'invoices.view', 'invoices.create',
            ],
            'dispatch' => ['dispatch.view', 'dispatch.create', 'bookings.view'],
            'staff' => [],
        ];

        foreach ($roleMatrix as $roleName => $permissionNames) {
            if (empty($permissionNames)) {
                continue;
            }

            $role = Role::where('name', $roleName)->first();
            $permissionIds = Permission::whereIn('name', $permissionNames)->pluck('id');
            $role?->permissions()->syncWithoutDetaching($permissionIds);
        }
    }
}
