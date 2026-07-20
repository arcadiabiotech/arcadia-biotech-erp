<?php

use App\Models\Approval;
use App\Models\Booking;
use App\Models\Dealer;
use App\Models\Farmer;
use App\Models\Role;
use App\Models\User;
use App\Models\VarietyStock;

function approvalRoleUser(string $roleName): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(['role_id' => $role->id]);
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    foreach (Booking::VARIETIES as $variety) {
        VarietyStock::create(['variety' => $variety, 'actual_qty' => 1000000]);
    }
});

test('a booking cannot be approved until accounts has verified it', function () {
    $admin = approvalRoleUser('admin');
    $booking = Booking::factory()->create(['approval_status' => 'pending']);

    $this->actingAs($admin)->post(route('bookings.approve', $booking))->assertSessionHasErrors('approval_status');
    expect($booking->fresh()->approval_status)->toBe('pending');
});

test('accounts can verify a pending booking but cannot approve it', function () {
    $accounts = approvalRoleUser('accounts');
    $booking = Booking::factory()->create(['approval_status' => 'pending']);

    $this->actingAs($accounts)->post(route('bookings.verify', $booking))->assertRedirect();
    expect($booking->fresh()->approval_status)->toBe('verified');

    $this->actingAs($accounts)->post(route('bookings.approve', $booking))->assertForbidden();
});

test('verifying and approving both write a generic approval record at the correct level', function () {
    $accounts = approvalRoleUser('accounts');
    $admin = approvalRoleUser('admin');
    $booking = Booking::factory()->create(['approval_status' => 'pending']);

    $this->actingAs($accounts)->post(route('bookings.verify', $booking), ['remarks' => 'Looks correct']);

    $level1 = Approval::forRecord('bookings', $booking->id)->where('approval_level', Approval::LEVEL_VERIFICATION)->first();
    expect($level1)->not->toBeNull();
    expect($level1->status)->toBe('verified');
    expect($level1->approved_by)->toBe($accounts->id);
    expect($level1->remarks)->toBe('Looks correct');

    $this->actingAs($admin)->post(route('bookings.approve', $booking->fresh()), ['remarks' => 'Approved']);

    $level2 = Approval::forRecord('bookings', $booking->id)->where('approval_level', Approval::LEVEL_APPROVAL)->first();
    expect($level2)->not->toBeNull();
    expect($level2->status)->toBe('approved');
    expect($level2->approved_by)->toBe($admin->id);
});

test('rejecting before verification records the rejection at level 1, after verification at level 2', function () {
    $admin = approvalRoleUser('admin');

    $earlyReject = Booking::factory()->create(['approval_status' => 'pending']);
    $this->actingAs($admin)->post(route('bookings.reject', $earlyReject));
    $level = Approval::forRecord('bookings', $earlyReject->id)->first();
    expect($level->approval_level)->toBe(Approval::LEVEL_VERIFICATION);
    expect($level->status)->toBe('rejected');

    $lateReject = Booking::factory()->create(['approval_status' => 'verified']);
    $this->actingAs($admin)->post(route('bookings.reject', $lateReject));
    $level = Approval::forRecord('bookings', $lateReject->id)->first();
    expect($level->approval_level)->toBe(Approval::LEVEL_APPROVAL);
    expect($level->status)->toBe('rejected');
});

test('unlocking an approved booking records an unlocked approval entry', function () {
    $admin = approvalRoleUser('admin');
    $booking = Booking::factory()->create(['approval_status' => 'approved']);

    $this->actingAs($admin)->post(route('bookings.unlock', $booking))->assertRedirect();

    $level = Approval::forRecord('bookings', $booking->id)->where('approval_level', Approval::LEVEL_APPROVAL)->first();
    expect($level->status)->toBe('unlocked');
    expect($level->unlock_by)->toBe($admin->id);
});

test('a decision notifies the booking creator', function () {
    $creator = approvalRoleUser('marketing');
    $admin = approvalRoleUser('admin');
    $dealer = Dealer::factory()->create();
    $booking = Booking::factory()->create(['dealer_id' => $dealer->id, 'created_by' => $creator->id, 'approval_status' => 'pending']);

    $this->actingAs($admin)->post(route('bookings.verify', $booking));

    expect($creator->fresh()->unreadNotifications()->count())->toBe(1);
});

test('completing dispatch records a completed approval entry at level 2', function () {
    $admin = approvalRoleUser('admin');
    $booking = Booking::factory()->create(['approval_status' => 'approved']);
    app(App\Services\StockReservationService::class)->reserve($booking);

    $this->actingAs($admin)->post(route('bookings.complete-dispatch', $booking))->assertRedirect();

    $level = Approval::forRecord('bookings', $booking->id)->where('approval_level', Approval::LEVEL_APPROVAL)->first();
    expect($level->status)->toBe('completed');
});
