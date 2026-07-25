<?php

use App\Models\LabChemicalUsage;
use App\Models\Role;
use App\Models\User;

function makeChemicalRoleUser(string $roleName, array $attributes = []): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(array_merge(['role_id' => $role->id, 'status' => true], $attributes));
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

test('a technician only sees their own chemical usage entries in the index', function () {
    $technician = makeChemicalRoleUser('lab-technician');
    $other = makeChemicalRoleUser('lab-technician');

    $own = LabChemicalUsage::factory()->create(['used_by' => $technician->id]);
    $others = LabChemicalUsage::factory()->create(['used_by' => $other->id]);

    $response = $this->actingAs($technician)->get(route('lab-chemicals.index'));

    $response->assertOk()->assertSee($own->usage_no)->assertDontSee($others->usage_no);
});

test('a chemical usage entry is locked for editing once submitted', function () {
    $technician = makeChemicalRoleUser('lab-technician');
    $usage = LabChemicalUsage::factory()->create(['used_by' => $technician->id, 'status' => 'draft']);

    $this->actingAs($technician)->get(route('lab-chemicals.edit', $usage))->assertOk();

    $usage->update(['status' => 'pending']);
    $this->actingAs($technician)->get(route('lab-chemicals.edit', $usage))->assertForbidden();
});

test('supervisor approves a pending chemical usage entry with a signature', function () {
    $supervisor = makeChemicalRoleUser('supervisor');
    $usage = LabChemicalUsage::factory()->create(['status' => 'pending']);

    $this->actingAs($supervisor)->post(route('lab-chemicals.approve', $usage), [
        'signature_data' => 'data:image/png;base64,'.base64_encode('sig'),
    ])->assertRedirect();

    expect($usage->fresh()->status)->toBe('approved');
});

test('a lab technician cannot approve or delete chemical usage entries', function () {
    $technician = makeChemicalRoleUser('lab-technician');
    $usage = LabChemicalUsage::factory()->create(['status' => 'pending']);

    $this->actingAs($technician)->post(route('lab-chemicals.approve', $usage), [
        'signature_data' => 'data:image/png;base64,'.base64_encode('sig'),
    ])->assertForbidden();

    $this->actingAs($technician)->delete(route('lab-chemicals.destroy', $usage))->assertForbidden();
});

test('admin can log a chemical usage entry on behalf of a chosen technician', function () {
    $admin = makeChemicalRoleUser('admin');
    $technician = makeChemicalRoleUser('lab-technician');

    $response = $this->actingAs($admin)->post(route('lab-chemicals.store'), [
        'used_by' => $technician->id,
        'usage_date' => now()->toDateString(),
        'chemical_name' => 'Sodium Hypochlorite',
        'quantity_used' => 5,
        'unit' => 'ml',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $usage = LabChemicalUsage::where('chemical_name', 'Sodium Hypochlorite')->first();
    expect($usage->used_by)->toBe($technician->id);
    expect($usage->created_by)->toBe($admin->id);
});

test('a technician creating their own entry cannot reassign it to someone else', function () {
    $technician = makeChemicalRoleUser('lab-technician');
    $otherTechnician = makeChemicalRoleUser('lab-technician');

    $this->actingAs($technician)->post(route('lab-chemicals.store'), [
        'used_by' => $otherTechnician->id,
        'usage_date' => now()->toDateString(),
        'chemical_name' => 'Ethanol',
        'quantity_used' => 2,
        'unit' => 'ml',
    ]);

    $usage = LabChemicalUsage::where('chemical_name', 'Ethanol')->first();
    expect($usage->used_by)->toBe($technician->id);
});
