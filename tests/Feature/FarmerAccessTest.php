<?php

use App\Models\Dealer;
use App\Models\DealerAssignment;
use App\Models\Farmer;
use App\Models\Role;
use App\Models\User;

function farmerRoleUser(string $roleName, array $attributes = []): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(array_merge(['role_id' => $role->id], $attributes));
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

test('marketing user only sees farmers of their assigned dealer', function () {
    $marketing = farmerRoleUser('marketing');
    $assignedDealer = Dealer::factory()->create();
    $otherDealer = Dealer::factory()->create();

    DealerAssignment::create([
        'dealer_id' => $assignedDealer->id,
        'marketing_user_id' => $marketing->id,
        'assigned_date' => now(),
        'status' => true,
    ]);

    $ownFarmer = Farmer::factory()->create(['dealer_id' => $assignedDealer->id]);
    $otherFarmer = Farmer::factory()->create(['dealer_id' => $otherDealer->id]);

    $response = $this->actingAs($marketing)->get(route('farmers.index'));

    $response->assertOk();
    $response->assertSee($ownFarmer->farmer_name);
    $response->assertDontSee($otherFarmer->farmer_name);
});

test('dealer role user only sees their own farmers', function () {
    $ownDealer = Dealer::factory()->create();
    $otherDealer = Dealer::factory()->create();
    $dealerUser = farmerRoleUser('dealer', ['dealer_id' => $ownDealer->id]);

    $ownFarmer = Farmer::factory()->create(['dealer_id' => $ownDealer->id]);
    $otherFarmer = Farmer::factory()->create(['dealer_id' => $otherDealer->id]);

    $response = $this->actingAs($dealerUser)->get(route('farmers.index'));

    $response->assertOk();
    $response->assertSee($ownFarmer->farmer_name);
    $response->assertDontSee($otherFarmer->farmer_name);

    $this->actingAs($dealerUser)->get(route('farmers.show', $otherFarmer))->assertForbidden();
});

test('accounts cannot view, edit or delete farmers', function () {
    // Role-based access refactor: Farmers is not in Accounts' current menu,
    // and the seeder no longer grants Accounts the farmers.view permission
    // (see FarmerPolicy::viewAny/view and PermissionSeeder's role matrix).
    $accounts = farmerRoleUser('accounts');
    $farmer = Farmer::factory()->create();

    $this->actingAs($accounts)->get(route('farmers.index'))->assertForbidden();
    $this->actingAs($accounts)->get(route('farmers.show', $farmer))->assertForbidden();
    $this->actingAs($accounts)->get(route('farmers.edit', $farmer))->assertForbidden();
    $this->actingAs($accounts)->delete(route('farmers.destroy', $farmer))->assertForbidden();
});

test('admin has full crud access to farmers', function () {
    $admin = farmerRoleUser('admin');
    $dealer = Dealer::factory()->create();
    $taluka = \App\Models\Taluka::factory()->create();
    $village = \App\Models\Village::factory()->create(['taluka_id' => $taluka->id]);

    verifyMobileOtp('9123456780');

    $store = $this->actingAs($admin)->post(route('farmers.store'), [
        'farmer_name' => 'Test Farmer',
        'dealer_id' => $dealer->id,
        'mobile' => '9123456780',
        'state_id' => $taluka->district->state_id,
        'district_id' => $taluka->district_id,
        'taluka_id' => $taluka->id,
        'village_id' => $village->id,
        'status' => 1,
    ]);
    $store->assertSessionHasNoErrors()->assertRedirect(route('farmers.index'));

    $farmer = Farmer::where('mobile', '9123456780')->first();
    expect($farmer)->not->toBeNull();
    expect($farmer->farmer_code)->toStartWith('FAR');
    expect($farmer->created_by)->toBe($admin->id);

    $this->actingAs($admin)->delete(route('farmers.destroy', $farmer))->assertRedirect(route('farmers.index'));
    $this->assertSoftDeleted($farmer);

    $this->actingAs($admin)->post(route('farmers.restore', $farmer))->assertRedirect(route('farmers.index'));
    $this->assertDatabaseHas('farmers', ['id' => $farmer->id, 'deleted_at' => null]);
});

test('mobile number must be unique among farmers', function () {
    $admin = farmerRoleUser('admin');
    $dealer = Dealer::factory()->create();
    $village = \App\Models\Village::factory()->create();
    $existing = Farmer::factory()->create(['mobile' => '9988887777']);

    $response = $this->actingAs($admin)->post(route('farmers.store'), [
        'farmer_name' => 'Duplicate Mobile',
        'dealer_id' => $dealer->id,
        'mobile' => '9988887777',
        'village_id' => $village->id,
        'status' => 1,
    ]);

    $response->assertSessionHasErrors('mobile');
});

test('aadhaar number must be unique among farmers', function () {
    $admin = farmerRoleUser('admin');
    $dealer = Dealer::factory()->create();
    $village = \App\Models\Village::factory()->create();
    $existing = Farmer::factory()->create(['aadhaar_no' => '123456789012']);

    $response = $this->actingAs($admin)->post(route('farmers.store'), [
        'farmer_name' => 'Duplicate Aadhaar',
        'dealer_id' => $dealer->id,
        'mobile' => '9988887766',
        'aadhaar_no' => '123456789012',
        'village_id' => $village->id,
        'status' => 1,
    ]);

    $response->assertSessionHasErrors('aadhaar_no');
});

test('dealer and village are required to create a farmer', function () {
    $admin = farmerRoleUser('admin');

    $response = $this->actingAs($admin)->post(route('farmers.store'), [
        'farmer_name' => 'No Dealer Or Village',
        'mobile' => '9988887755',
        'status' => 1,
    ]);

    $response->assertSessionHasErrors(['dealer_id', 'village_id']);
});

test('marketing cannot attribute a farmer to a dealer they are not assigned to', function () {
    $marketing = farmerRoleUser('marketing');
    $unassignedDealer = Dealer::factory()->create();
    $village = \App\Models\Village::factory()->create();

    $response = $this->actingAs($marketing)->post(route('farmers.store'), [
        'farmer_name' => 'Sneaky Farmer',
        'dealer_id' => $unassignedDealer->id,
        'mobile' => '9988887744',
        'village_id' => $village->id,
        'status' => 1,
    ]);

    // Marketing is now allowed to create farmers in general (under their
    // own assigned dealers) — this dealer just isn't one of them, so it's
    // a validation error on dealer_id, not a blanket 403.
    $response->assertSessionHasErrors('dealer_id');
    expect(Farmer::where('mobile', '9988887744')->exists())->toBeFalse();
});

test('marketing can register a farmer under one of their assigned dealers', function () {
    $marketing = farmerRoleUser('marketing');
    $assignedDealer = Dealer::factory()->create();
    $village = \App\Models\Village::factory()->create();
    $village->load('taluka.district.state');

    DealerAssignment::create([
        'dealer_id' => $assignedDealer->id,
        'marketing_user_id' => $marketing->id,
        'assigned_date' => now(),
        'status' => true,
    ]);

    session(["otp_verified.registration.9988887733" => now()->timestamp]);
    \App\Models\MobileVerification::create([
        'mobile' => '9988887733',
        'otp' => bcrypt('123456'),
        'purpose' => 'registration',
        'status' => 'verified',
        'verified_at' => now(),
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->actingAs($marketing)->post(route('farmers.store'), [
        'farmer_name' => 'Assigned Dealer Farmer',
        'dealer_id' => $assignedDealer->id,
        'mobile' => '9988887733',
        'state_id' => $village->taluka->district->state->id,
        'district_id' => $village->taluka->district->id,
        'taluka_id' => $village->taluka->id,
        'village_id' => $village->id,
        'status' => 1,
    ]);

    $response->assertSessionHasNoErrors();
    $farmer = Farmer::where('mobile', '9988887733')->first();
    expect($farmer)->not->toBeNull();
    expect($farmer->dealer_id)->toBe($assignedDealer->id);
});
