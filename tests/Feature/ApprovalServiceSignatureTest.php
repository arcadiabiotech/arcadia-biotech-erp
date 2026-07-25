<?php

use App\Models\Approval;
use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalService;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

test('record() stores a signature when one is passed', function () {
    $role = Role::firstOrCreate(['name' => 'supervisor'], ['display_name' => 'Supervisor', 'status' => true]);
    $actor = User::factory()->create(['role_id' => $role->id]);

    $approval = app(ApprovalService::class)->record(
        'lab-checklists',
        1,
        Approval::LEVEL_VERIFICATION,
        'approved',
        $actor,
        'ok',
        'LC-2026-000001',
        [],
        'approvals/signatures/example.png',
    );

    expect($approval->signature)->toBe('approvals/signatures/example.png');
});

test('record() called the pre-existing way (no signature argument) still works, proving backward compatibility', function () {
    $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin', 'status' => true]);
    $actor = User::factory()->create(['role_id' => $role->id]);

    $approval = app(ApprovalService::class)->record(
        'bookings',
        1,
        Approval::LEVEL_APPROVAL,
        'approved',
        $actor,
        null,
        'BK-2026-000001',
    );

    expect($approval->status)->toBe('approved');
    expect($approval->signature)->toBeNull();
});
