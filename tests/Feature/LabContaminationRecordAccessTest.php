<?php

use App\Models\LabContaminationRecord;
use App\Models\Role;
use App\Models\User;

function makeContaminationRoleUser(string $roleName, array $attributes = []): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(array_merge(['role_id' => $role->id, 'status' => true], $attributes));
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

test('a technician only sees their own contamination records in the index', function () {
    $technician = makeContaminationRoleUser('lab-technician');
    $other = makeContaminationRoleUser('lab-technician');

    $own = LabContaminationRecord::factory()->create(['reported_by' => $technician->id]);
    $others = LabContaminationRecord::factory()->create(['reported_by' => $other->id]);

    $response = $this->actingAs($technician)->get(route('lab-contamination.index'));

    $response->assertOk()->assertSee($own->contamination_no)->assertDontSee($others->contamination_no);
});

test('a contamination record is locked for editing once submitted', function () {
    $technician = makeContaminationRoleUser('lab-technician');
    $record = LabContaminationRecord::factory()->create(['reported_by' => $technician->id, 'status' => 'draft']);

    $this->actingAs($technician)->get(route('lab-contamination.edit', $record))->assertOk();

    $record->update(['status' => 'pending']);
    $this->actingAs($technician)->get(route('lab-contamination.edit', $record))->assertForbidden();
});

test('supervisor approves a pending contamination record with a signature', function () {
    $supervisor = makeContaminationRoleUser('supervisor');
    $record = LabContaminationRecord::factory()->create(['status' => 'pending', 'severity' => 'critical']);

    $this->actingAs($supervisor)->post(route('lab-contamination.approve', $record), [
        'signature_data' => 'data:image/png;base64,'.base64_encode('sig'),
    ])->assertRedirect();

    expect($record->fresh()->status)->toBe('approved');
});

test('a lab technician cannot approve or delete contamination records', function () {
    $technician = makeContaminationRoleUser('lab-technician');
    $record = LabContaminationRecord::factory()->create(['status' => 'pending']);

    $this->actingAs($technician)->post(route('lab-contamination.approve', $record), [
        'signature_data' => 'data:image/png;base64,'.base64_encode('sig'),
    ])->assertForbidden();

    $this->actingAs($technician)->delete(route('lab-contamination.destroy', $record))->assertForbidden();
});
