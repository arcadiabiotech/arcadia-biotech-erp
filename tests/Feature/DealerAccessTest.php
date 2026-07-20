<?php

use App\Models\Dealer;
use App\Models\DealerAssignment;
use App\Models\Role;
use App\Models\User;

function makeRoleUser(string $roleName, array $attributes = []): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(array_merge(['role_id' => $role->id], $attributes));
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

test('marketing user only sees their assigned dealer in the index', function () {
    $marketing = makeRoleUser('marketing');
    $assignedDealer = Dealer::factory()->create();
    $otherDealer = Dealer::factory()->create();

    DealerAssignment::create([
        'dealer_id' => $assignedDealer->id,
        'marketing_user_id' => $marketing->id,
        'assigned_date' => now(),
        'status' => true,
    ]);

    $response = $this->actingAs($marketing)->get(route('dealers.index'));

    $response->assertOk();
    $response->assertSee($assignedDealer->dealer_name);
    $response->assertDontSee($otherDealer->dealer_name);
});

test('marketing user gets 403 editing a dealer not assigned to them', function () {
    $marketing = makeRoleUser('marketing');
    $otherDealer = Dealer::factory()->create();

    $response = $this->actingAs($marketing)->get(route('dealers.edit', $otherDealer));

    $response->assertForbidden();
});

test('dealer role user only sees their own dealer record', function () {
    $ownDealer = Dealer::factory()->create();
    $otherDealer = Dealer::factory()->create();
    $dealerUser = makeRoleUser('dealer', ['dealer_id' => $ownDealer->id]);

    $response = $this->actingAs($dealerUser)->get(route('dealers.index'));

    $response->assertOk();
    $response->assertSee($ownDealer->dealer_name);
    $response->assertDontSee($otherDealer->dealer_name);

    $this->actingAs($dealerUser)->get(route('dealers.edit', $otherDealer))->assertForbidden();
});

test('marketing and dealer roles cannot create or delete dealers', function () {
    $marketing = makeRoleUser('marketing');
    $dealer = Dealer::factory()->create();

    $this->actingAs($marketing)->get(route('dealers.create'))->assertForbidden();
    $this->actingAs($marketing)->delete(route('dealers.destroy', $dealer))->assertForbidden();
});

test('admin can view, edit and delete any dealer', function () {
    $admin = makeRoleUser('admin');
    $dealer = Dealer::factory()->create();

    $this->actingAs($admin)->get(route('dealers.index'))->assertOk();
    $this->actingAs($admin)->get(route('dealers.edit', $dealer))->assertOk();
    $this->actingAs($admin)->delete(route('dealers.destroy', $dealer))->assertRedirect(route('dealers.index'));

    $this->assertSoftDeleted($dealer);
});

test('non-admin roles are forbidden from managing dealer assignments', function () {
    $marketing = makeRoleUser('marketing');

    $this->actingAs($marketing)->get(route('dealer-assignments.create'))->assertForbidden();
});

test('accounts can view any dealer but cannot edit or delete', function () {
    $accounts = makeRoleUser('accounts');
    $dealer = Dealer::factory()->create();

    $this->actingAs($accounts)->get(route('dealers.index'))->assertOk();
    $this->actingAs($accounts)->get(route('dealers.show', $dealer))->assertOk();
    $this->actingAs($accounts)->get(route('dealers.edit', $dealer))->assertForbidden();
    $this->actingAs($accounts)->delete(route('dealers.destroy', $dealer))->assertForbidden();
});

test('marketing without the dealers.edit permission cannot edit even their own assigned dealer', function () {
    $marketing = makeRoleUser('marketing');
    $dealer = Dealer::factory()->create();

    DealerAssignment::create([
        'dealer_id' => $dealer->id,
        'marketing_user_id' => $marketing->id,
        'assigned_date' => now(),
        'status' => true,
    ]);

    $this->actingAs($marketing)->get(route('dealers.edit', $dealer))->assertForbidden();
});

test('marketing granted dealers.edit can edit only their own assigned dealer', function () {
    $marketing = makeRoleUser('marketing');
    $ownDealer = Dealer::factory()->create();
    $otherDealer = Dealer::factory()->create();

    DealerAssignment::create([
        'dealer_id' => $ownDealer->id,
        'marketing_user_id' => $marketing->id,
        'assigned_date' => now(),
        'status' => true,
    ]);

    $editPermission = \App\Models\Permission::where('name', 'dealers.edit')->first();
    $marketing->role->permissions()->syncWithoutDetaching([$editPermission->id]);

    $this->actingAs($marketing)->get(route('dealers.edit', $ownDealer))->assertOk();
    $this->actingAs($marketing)->get(route('dealers.edit', $otherDealer))->assertForbidden();
});

test('dealer role user can view their own profile but not another dealer profile', function () {
    $ownDealer = Dealer::factory()->create();
    $otherDealer = Dealer::factory()->create();
    $dealerUser = makeRoleUser('dealer', ['dealer_id' => $ownDealer->id]);

    $this->actingAs($dealerUser)->get(route('dealers.show', $ownDealer))->assertOk();
    $this->actingAs($dealerUser)->get(route('dealers.show', $otherDealer))->assertForbidden();
});

test('dealers index can be filtered by status and state', function () {
    $admin = makeRoleUser('admin');
    $state = \App\Models\State::factory()->create();
    $activeDealer = Dealer::factory()->create(['status' => true, 'state_id' => $state->id]);
    $inactiveDealer = Dealer::factory()->create(['status' => false]);

    $this->actingAs($admin)->get(route('dealers.index', ['status' => '0']))
        ->assertSee($inactiveDealer->dealer_name)->assertDontSee($activeDealer->dealer_name);

    $this->actingAs($admin)->get(route('dealers.index', ['state' => $state->id]))
        ->assertSee($activeDealer->dealer_name)->assertDontSee($inactiveDealer->dealer_name);
});

test('deleted dealers are hidden by default and restorable by an admin', function () {
    $admin = makeRoleUser('admin');
    $dealer = Dealer::factory()->create();
    $dealer->delete();

    $this->actingAs($admin)->get(route('dealers.index'))->assertDontSee($dealer->dealer_name);
    $this->actingAs($admin)->get(route('dealers.index', ['trashed' => 1]))->assertSee($dealer->dealer_name);

    $this->actingAs($admin)->post(route('dealers.restore', $dealer))->assertRedirect(route('dealers.index'));
    $this->assertDatabaseHas('dealers', ['id' => $dealer->id, 'deleted_at' => null]);
});

test('creating a dealer auto-generates a DLR-prefixed code and records who created it', function () {
    $admin = makeRoleUser('admin');
    $state = \App\Models\State::factory()->create();
    $district = \App\Models\District::factory()->create(['state_id' => $state->id]);

    $response = $this->actingAs($admin)->post(route('dealers.store'), [
        'firm_name' => 'Test Farms',
        'dealer_name' => 'Test Dealer',
        'mobile' => '9988776655',
        'state_id' => $state->id,
        'district_id' => $district->id,
        'status' => 1,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('dealers.index'));

    $dealer = Dealer::where('mobile', '9988776655')->first();
    expect($dealer)->not->toBeNull();
    expect($dealer->dealer_code)->toStartWith('DLR');
    expect($dealer->created_by)->toBe($admin->id);
    expect($dealer->district_id)->toBe($district->id);
});
