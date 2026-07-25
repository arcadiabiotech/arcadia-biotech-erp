<?php

use App\Models\LabMediaStockVerification;
use App\Models\Role;
use App\Models\User;

function makeMediaRoleUser(string $roleName, array $attributes = []): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(array_merge(['role_id' => $role->id, 'status' => true], $attributes));
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

test('a technician only sees their own media verifications in the index', function () {
    $technician = makeMediaRoleUser('lab-technician');
    $other = makeMediaRoleUser('lab-technician');

    $own = LabMediaStockVerification::factory()->create(['verified_by' => $technician->id]);
    $others = LabMediaStockVerification::factory()->create(['verified_by' => $other->id]);

    $response = $this->actingAs($technician)->get(route('lab-media.index'));

    $response->assertOk()->assertSee($own->verification_no)->assertDontSee($others->verification_no);
});

test('closing stock is computed server-side from opening, received and consumed quantities', function () {
    $technician = makeMediaRoleUser('lab-technician');

    $response = $this->actingAs($technician)->post(route('lab-media.store'), [
        'media_name' => 'MS Medium',
        'unit' => 'litres',
        'verification_date' => now()->toDateString(),
        'opening_stock' => 10,
        'received_qty' => 5,
        'consumed_qty' => 3,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $verification = LabMediaStockVerification::where('media_name', 'MS Medium')->first();
    expect((float) $verification->closing_stock)->toBe(12.0);
    expect($verification->verified_by)->toBe($technician->id);
});

test('a media verification is locked for editing once submitted', function () {
    $technician = makeMediaRoleUser('lab-technician');
    $verification = LabMediaStockVerification::factory()->create(['verified_by' => $technician->id, 'status' => 'draft']);

    $this->actingAs($technician)->get(route('lab-media.edit', $verification))->assertOk();

    $verification->update(['status' => 'pending']);
    $this->actingAs($technician)->get(route('lab-media.edit', $verification))->assertForbidden();
});

test('supervisor approves a pending media verification with a signature', function () {
    $supervisor = makeMediaRoleUser('supervisor');
    $verification = LabMediaStockVerification::factory()->create(['status' => 'pending']);

    $this->actingAs($supervisor)->post(route('lab-media.approve', $verification), [
        'signature_data' => 'data:image/png;base64,'.base64_encode('sig'),
    ])->assertRedirect();

    expect($verification->fresh()->status)->toBe('approved');
});

test('a lab technician cannot approve or delete media verifications', function () {
    $technician = makeMediaRoleUser('lab-technician');
    $verification = LabMediaStockVerification::factory()->create(['status' => 'pending']);

    $this->actingAs($technician)->post(route('lab-media.approve', $verification), [
        'signature_data' => 'data:image/png;base64,'.base64_encode('sig'),
    ])->assertForbidden();

    $this->actingAs($technician)->delete(route('lab-media.destroy', $verification))->assertForbidden();
});
