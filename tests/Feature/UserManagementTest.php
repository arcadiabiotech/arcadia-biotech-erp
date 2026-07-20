<?php

use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

function roleUser(string $roleName): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(['role_id' => $role->id]);
}

test('non-admin roles are forbidden from the users module', function () {
    $marketing = roleUser('marketing');

    $this->actingAs($marketing)->get(route('users.index'))->assertForbidden();
    $this->actingAs($marketing)->get(route('users.create'))->assertForbidden();
});

test('admin can create a user', function () {
    $admin = roleUser('admin');
    $marketingRole = Role::where('name', 'marketing')->first();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'New Marketer',
        'username' => 'new-marketer',
        'email' => 'marketer@example.com',
        'mobile' => '9876543210',
        'role_id' => $marketingRole->id,
        'password' => 'password123',
        'status' => 1,
    ]);

    $response->assertRedirect(route('users.index'));
    $this->assertDatabaseHas('users', ['username' => 'new-marketer', 'role_id' => $marketingRole->id]);
});

test('admin cannot delete their own account', function () {
    $admin = roleUser('admin');

    $this->actingAs($admin)->delete(route('users.destroy', $admin))->assertForbidden();
    $this->assertModelExists($admin);
});

test('the last super-admin cannot be deleted', function () {
    $superAdminRole = Role::where('name', 'super-admin')->first();
    $onlySuperAdmin = User::factory()->create(['role_id' => $superAdminRole->id]);
    $admin = roleUser('admin');

    $this->actingAs($admin)->delete(route('users.destroy', $onlySuperAdmin))->assertForbidden();
    $this->assertModelExists($onlySuperAdmin);
});

test('a super-admin can be deleted when another super-admin remains', function () {
    $superAdminRole = Role::where('name', 'super-admin')->first();
    $firstSuperAdmin = User::factory()->create(['role_id' => $superAdminRole->id]);
    $secondSuperAdmin = User::factory()->create(['role_id' => $superAdminRole->id]);

    $this->actingAs($firstSuperAdmin)->delete(route('users.destroy', $secondSuperAdmin))
        ->assertRedirect(route('users.index'));

    $this->assertSoftDeleted($secondSuperAdmin);
});

test('accounts role can create bookings and payments but cannot approve bookings', function () {
    $accounts = roleUser('accounts');

    expect($accounts->hasPermission('bookings.create'))->toBeTrue();
    expect($accounts->hasPermission('payments.create'))->toBeTrue();
    expect($accounts->hasPermission('challans.create'))->toBeTrue();
    expect($accounts->hasPermission('invoices.create'))->toBeTrue();
    expect($accounts->hasPermission('bookings.approve'))->toBeFalse();
    expect($accounts->hasPermission('bookings.reject'))->toBeFalse();
    expect($accounts->hasPermission('bookings.hold'))->toBeFalse();
    expect($accounts->hasPermission('bookings.unlock'))->toBeFalse();
});

test('only admin and super-admin hold booking approve, reject, hold and unlock permissions', function () {
    $admin = roleUser('admin');
    $marketing = roleUser('marketing');
    $dispatch = roleUser('dispatch');
    $staff = roleUser('staff');

    foreach (['bookings.approve', 'bookings.reject', 'bookings.hold', 'bookings.unlock'] as $permission) {
        expect($admin->hasPermission($permission))->toBeTrue();
        expect($marketing->hasPermission($permission))->toBeFalse();
        expect($dispatch->hasPermission($permission))->toBeFalse();
        expect($staff->hasPermission($permission))->toBeFalse();
    }
});

test('admin cannot create a super-admin user', function () {
    $admin = roleUser('admin');
    $superAdminRole = Role::where('name', 'super-admin')->first();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Sneaky Admin',
        'username' => 'sneaky-admin',
        'email' => 'sneaky@example.com',
        'mobile' => '9876500000',
        'role_id' => $superAdminRole->id,
        'password' => 'password123',
        'status' => 1,
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('users', ['username' => 'sneaky-admin']);
});

test('admin cannot edit an existing super-admin user', function () {
    $admin = roleUser('admin');
    $superAdminRole = Role::where('name', 'super-admin')->first();
    $superAdmin = User::factory()->create(['role_id' => $superAdminRole->id]);

    $this->actingAs($admin)->get(route('users.edit', $superAdmin))->assertForbidden();

    $this->actingAs($admin)->put(route('users.update', $superAdmin), [
        'name' => 'Renamed',
        'username' => $superAdmin->username,
        'email' => $superAdmin->email,
        'mobile' => $superAdmin->mobile,
        'role_id' => $superAdminRole->id,
        'status' => 1,
    ])->assertForbidden();
});

test('super-admin can manage another super-admin', function () {
    $superAdminRole = Role::where('name', 'super-admin')->first();
    $actingSuperAdmin = User::factory()->create(['role_id' => $superAdminRole->id]);
    $targetSuperAdmin = User::factory()->create(['role_id' => $superAdminRole->id]);

    $this->actingAs($actingSuperAdmin)->get(route('users.edit', $targetSuperAdmin))->assertOk();
});

test('users index can be filtered by role and status', function () {
    $admin = roleUser('admin');
    $activeMarketing = roleUser('marketing');
    $inactiveDispatch = roleUser('dispatch');
    $inactiveDispatch->update(['status' => false]);

    $byRole = $this->actingAs($admin)->get(route('users.index', ['role' => 'marketing']));
    $byRole->assertSee($activeMarketing->name)->assertDontSee($inactiveDispatch->name);

    $byStatus = $this->actingAs($admin)->get(route('users.index', ['status' => '0']));
    $byStatus->assertSee($inactiveDispatch->name)->assertDontSee($activeMarketing->name);
});

test('deleted users are hidden by default and visible via the trashed filter', function () {
    $admin = roleUser('admin');
    $target = roleUser('staff');

    $this->actingAs($admin)->delete(route('users.destroy', $target));

    $this->actingAs($admin)->get(route('users.index'))->assertDontSee($target->name);
    $this->actingAs($admin)->get(route('users.index', ['trashed' => 1]))->assertSee($target->name);
});

test('admin can restore a soft-deleted user', function () {
    $admin = roleUser('admin');
    $target = roleUser('staff');
    $target->delete();

    $this->actingAs($admin)->post(route('users.restore', $target))->assertRedirect(route('users.index'));

    $this->assertDatabaseHas('users', ['id' => $target->id, 'deleted_at' => null]);
});

test('assigning dealers to a marketing user via the user form creates dealer assignments', function () {
    $admin = roleUser('admin');
    $marketingRole = Role::where('name', 'marketing')->first();
    $marketingUser = roleUser('marketing');
    $dealer = \App\Models\Dealer::factory()->create();

    $this->actingAs($admin)->put(route('users.update', $marketingUser), [
        'name' => $marketingUser->name,
        'username' => $marketingUser->username,
        'email' => $marketingUser->email,
        'mobile' => '9876522222',
        'role_id' => $marketingRole->id,
        'status' => 1,
        'dealer_ids' => [$dealer->id],
    ])->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

    $this->assertDatabaseHas('dealer_assignments', [
        'dealer_id' => $dealer->id,
        'marketing_user_id' => $marketingUser->id,
        'deleted_at' => null,
    ]);
});

test('unassigning a dealer from a marketing user soft deletes the assignment', function () {
    $admin = roleUser('admin');
    $marketingRole = Role::where('name', 'marketing')->first();
    $marketingUser = roleUser('marketing');
    $dealer = \App\Models\Dealer::factory()->create();

    $assignment = \App\Models\DealerAssignment::create([
        'dealer_id' => $dealer->id,
        'marketing_user_id' => $marketingUser->id,
        'assigned_by' => $admin->id,
        'assigned_date' => now(),
        'status' => true,
    ]);

    $this->actingAs($admin)->put(route('users.update', $marketingUser), [
        'name' => $marketingUser->name,
        'username' => $marketingUser->username,
        'email' => $marketingUser->email,
        'mobile' => '9876511111',
        'role_id' => $marketingRole->id,
        'status' => 1,
        'dealer_ids' => [],
    ])->assertSessionHasNoErrors();

    $this->assertSoftDeleted($assignment);
});

test('logging in records last login time, ip and an activity log entry', function () {
    $admin = roleUser('admin');
    $admin->forceFill(['password' => bcrypt('password123')])->save();

    $this->post(route('login'), [
        'email' => $admin->email,
        'password' => 'password123',
    ]);

    $admin->refresh();
    expect($admin->last_login_at)->not->toBeNull();
    expect($admin->last_login_ip)->not->toBeNull();

    $this->assertDatabaseHas('activity_logs', [
        'module' => 'users',
        'record_id' => $admin->id,
        'action' => 'login',
    ]);
});
