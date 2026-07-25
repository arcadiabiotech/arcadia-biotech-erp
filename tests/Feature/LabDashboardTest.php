<?php

use App\Models\LabDailyChecklist;
use App\Models\Role;
use App\Models\User;

function makeDashboardRoleUser(string $roleName, array $attributes = []): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(array_merge(['role_id' => $role->id, 'status' => true], $attributes));
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

test('lab technician, supervisor and admin can all view the lab ops dashboard', function () {
    foreach (['lab-technician', 'supervisor', 'admin', 'super-admin'] as $roleName) {
        $user = makeDashboardRoleUser($roleName);
        $this->actingAs($user)->get(route('lab.dashboard'))->assertOk();
    }
});

test('a role with no lab menu is forbidden from the lab ops dashboard', function () {
    $marketing = makeDashboardRoleUser('marketing');

    $this->actingAs($marketing)->get(route('lab.dashboard'))->assertForbidden();
});

test('the technician dashboard shows whether today\'s checklist has been submitted', function () {
    $technician = makeDashboardRoleUser('lab-technician');
    $checklist = LabDailyChecklist::factory()->create(['employee_id' => $technician->id, 'checklist_date' => now()->toDateString()]);

    $this->actingAs($technician)->get(route('lab.dashboard'))->assertOk()->assertSee($checklist->checklist_no);
});
