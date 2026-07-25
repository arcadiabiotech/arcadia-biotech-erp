<?php

use App\Models\LabDailyChecklist;
use App\Models\Role;
use App\Models\User;

function makeReportsRoleUser(string $roleName, array $attributes = []): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(array_merge(['role_id' => $role->id, 'status' => true], $attributes));
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

test('admin, super-admin and supervisor can view lab reports; lab technician cannot', function () {
    foreach (['admin', 'super-admin', 'supervisor'] as $roleName) {
        $user = makeReportsRoleUser($roleName);
        $this->actingAs($user)->get(route('lab-reports.index'))->assertOk();
        $this->actingAs($user)->get(route('lab-reports.show', 'daily'))->assertOk();
    }

    $technician = makeReportsRoleUser('lab-technician');
    $this->actingAs($technician)->get(route('lab-reports.index'))->assertForbidden();
});

test('an unknown report period 404s', function () {
    $admin = makeReportsRoleUser('admin');

    $this->actingAs($admin)->get(route('lab-reports.show', 'yearly'))->assertNotFound();
});

test('daily compliance % is approved checklists divided by active technician headcount', function () {
    $admin = makeReportsRoleUser('admin');

    // 2 active technicians, only 1 has an approved checklist today => 50%.
    $t1 = makeReportsRoleUser('lab-technician');
    $t2 = makeReportsRoleUser('lab-technician');

    LabDailyChecklist::factory()->create(['employee_id' => $t1->id, 'checklist_date' => now()->toDateString(), 'status' => 'approved']);
    LabDailyChecklist::factory()->create(['employee_id' => $t2->id, 'checklist_date' => now()->toDateString(), 'status' => 'pending']);

    $response = $this->actingAs($admin)->get(route('lab-reports.show', 'daily'));

    $response->assertOk()->assertSee('50%');
});

test('csv and pdf exports are accessible to supervisors', function () {
    $supervisor = makeReportsRoleUser('supervisor');

    $this->actingAs($supervisor)->get(route('lab-reports.csv', 'daily'))->assertOk();
    $this->actingAs($supervisor)->get(route('lab-reports.pdf', 'monthly'))->assertOk();
});
