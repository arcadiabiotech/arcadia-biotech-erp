<?php

use App\Models\LabEquipment;
use App\Models\Role;
use App\Models\User;

function makeEquipmentRoleUser(string $roleName, array $attributes = []): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(array_merge(['role_id' => $role->id, 'status' => true], $attributes));
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

test('lab technician and supervisor can view equipment but not create, edit or delete it', function () {
    $technician = makeEquipmentRoleUser('lab-technician');
    $supervisor = makeEquipmentRoleUser('supervisor');
    $equipment = LabEquipment::factory()->create();

    $this->actingAs($technician)->get(route('lab-equipment.index'))->assertOk();
    $this->actingAs($technician)->get(route('lab-equipment.create'))->assertForbidden();
    $this->actingAs($technician)->delete(route('lab-equipment.destroy', $equipment))->assertForbidden();

    $this->actingAs($supervisor)->get(route('lab-equipment.index'))->assertOk();
    $this->actingAs($supervisor)->get(route('lab-equipment.edit', $equipment))->assertForbidden();
});

test('admin can create, edit, delete and restore equipment', function () {
    $admin = makeEquipmentRoleUser('admin');

    $response = $this->actingAs($admin)->post(route('lab-equipment.store'), [
        'equipment_code' => 'EQ000123',
        'name' => 'Autoclave',
        'status' => 'active',
    ]);
    $response->assertSessionHasNoErrors()->assertRedirect(route('lab-equipment.index'));

    $equipment = LabEquipment::where('equipment_code', 'EQ000123')->first();
    expect($equipment)->not->toBeNull();
    expect($equipment->created_by)->toBe($admin->id);

    $this->actingAs($admin)->delete(route('lab-equipment.destroy', $equipment))->assertRedirect();
    $this->assertSoftDeleted('lab_equipment', ['id' => $equipment->id]);

    $this->actingAs($admin)->post(route('lab-equipment.restore', $equipment))->assertRedirect();
    $this->assertDatabaseHas('lab_equipment', ['id' => $equipment->id, 'deleted_at' => null]);
});

test('equipment name only capitalizes the first character, preserving abbreviations like pH', function () {
    $admin = makeEquipmentRoleUser('admin');

    $this->actingAs($admin)->post(route('lab-equipment.store'), [
        'equipment_code' => 'EQ000124',
        'name' => 'pH meter',
        'status' => 'active',
    ])->assertSessionHasNoErrors();

    $equipment = LabEquipment::where('equipment_code', 'EQ000124')->first();
    expect($equipment->name)->toBe('PH meter');
});
