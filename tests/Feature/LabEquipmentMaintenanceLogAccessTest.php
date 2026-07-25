<?php

use App\Models\LabEquipmentMaintenanceLog;
use App\Models\Role;
use App\Models\User;

function makeMaintenanceRoleUser(string $roleName, array $attributes = []): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(array_merge(['role_id' => $role->id, 'status' => true], $attributes));
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

test('a technician only sees their own maintenance logs in the index', function () {
    $technician = makeMaintenanceRoleUser('lab-technician');
    $other = makeMaintenanceRoleUser('lab-technician');

    $own = LabEquipmentMaintenanceLog::factory()->create(['performed_by' => $technician->id]);
    $others = LabEquipmentMaintenanceLog::factory()->create(['performed_by' => $other->id]);

    $response = $this->actingAs($technician)->get(route('lab-maintenance.index'));

    $response->assertOk()->assertSee($own->log_no)->assertDontSee($others->log_no);
});

test('a draft maintenance log is locked for editing once submitted', function () {
    $technician = makeMaintenanceRoleUser('lab-technician');
    $log = LabEquipmentMaintenanceLog::factory()->create(['performed_by' => $technician->id, 'status' => 'draft']);

    $this->actingAs($technician)->get(route('lab-maintenance.edit', $log))->assertOk();

    $log->update(['status' => 'pending']);
    $this->actingAs($technician)->get(route('lab-maintenance.edit', $log))->assertForbidden();
});

test('supervisor approves a pending maintenance log with a signature', function () {
    $supervisor = makeMaintenanceRoleUser('supervisor');
    $log = LabEquipmentMaintenanceLog::factory()->create(['status' => 'pending']);

    $this->actingAs($supervisor)->post(route('lab-maintenance.approve', $log), [
        'signature_data' => 'data:image/png;base64,'.base64_encode('sig'),
    ])->assertRedirect();

    expect($log->fresh()->status)->toBe('approved');
});

test('supervisor rejects a pending maintenance log with a required reason', function () {
    $supervisor = makeMaintenanceRoleUser('supervisor');
    $log = LabEquipmentMaintenanceLog::factory()->create(['status' => 'pending']);

    $this->actingAs($supervisor)->post(route('lab-maintenance.reject', $log), [])->assertSessionHasErrors('remarks');

    $this->actingAs($supervisor)->post(route('lab-maintenance.reject', $log), ['remarks' => 'Redo it'])->assertRedirect();
    expect($log->fresh()->status)->toBe('rejected');
});

test('a lab technician cannot approve or delete maintenance logs', function () {
    $technician = makeMaintenanceRoleUser('lab-technician');
    $log = LabEquipmentMaintenanceLog::factory()->create(['status' => 'pending']);

    $this->actingAs($technician)->post(route('lab-maintenance.approve', $log), [
        'signature_data' => 'data:image/png;base64,'.base64_encode('sig'),
    ])->assertForbidden();

    $this->actingAs($technician)->delete(route('lab-maintenance.destroy', $log))->assertForbidden();
});

test('admin can delete and restore a maintenance log', function () {
    $admin = makeMaintenanceRoleUser('admin');
    $log = LabEquipmentMaintenanceLog::factory()->create();

    $this->actingAs($admin)->delete(route('lab-maintenance.destroy', $log))->assertRedirect();
    $this->assertSoftDeleted('lab_equipment_maintenance_logs', ['id' => $log->id]);

    $this->actingAs($admin)->post(route('lab-maintenance.restore', $log))->assertRedirect();
    $this->assertDatabaseHas('lab_equipment_maintenance_logs', ['id' => $log->id, 'deleted_at' => null]);
});
