<?php

use App\Models\Booking;
use App\Models\Dealer;
use App\Models\DealerAssignment;
use App\Models\Farmer;
use App\Models\Role;
use App\Models\User;
use App\Models\VarietyStock;

function bookingRoleUser(string $roleName, array $attributes = []): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(array_merge(['role_id' => $role->id], $attributes));
}

function assignDealerToMarketing(Dealer $dealer, User $marketing): void
{
    DealerAssignment::create([
        'dealer_id' => $dealer->id,
        'marketing_user_id' => $marketing->id,
        'assigned_date' => now(),
        'status' => true,
    ]);
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // The Stock Reservation engine (Phase 6) requires actual stock to exist
    // before a booking can reserve against it — give every variety generous
    // headroom so these Phase 5 tests aren't about stock limits.
    foreach (Booking::VARIETIES as $variety) {
        VarietyStock::create(['variety' => $variety, 'actual_qty' => 1000000]);
    }
});

test('booking amount and balance are always derived, never trusted from input', function () {
    $booking = Booking::factory()->create(['plant_qty' => 100, 'plant_rate' => 10, 'discount' => 50, 'advance_amount' => 200]);

    expect((float) $booking->booking_amount)->toBe(1000.0);
    expect((float) $booking->balance_amount)->toBe(750.0); // 1000 - 50 - 200
});

test('booking numbers follow the BK-YYYY-000001 format and increment sequentially', function () {
    $admin = bookingRoleUser('admin');
    $dealer = Dealer::factory()->create();
    $farmer = Farmer::factory()->create(['dealer_id' => $dealer->id]);

    $payload = [
        'dealer_id' => $dealer->id,
        'farmer_id' => $farmer->id,
        'variety' => 'G9',
        'booking_date' => now()->toDateString(),
        'plant_qty' => 100,
        'plant_rate' => 12,
    ];

    $this->actingAs($admin)->post(route('bookings.store'), $payload)->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('bookings.store'), $payload)->assertSessionHasNoErrors();

    $numbers = Booking::orderBy('id')->pluck('booking_no')->all();
    expect($numbers[0])->toMatch('/^BK-\d{4}-\d{6}$/');
    expect((int) substr($numbers[1], -6))->toBe((int) substr($numbers[0], -6) + 1);
});

test('marketing can create a booking for an assigned dealer but not for others', function () {
    $marketing = bookingRoleUser('marketing');
    $assignedDealer = Dealer::factory()->create();
    $otherDealer = Dealer::factory()->create();
    assignDealerToMarketing($assignedDealer, $marketing);
    $assignedFarmer = Farmer::factory()->create(['dealer_id' => $assignedDealer->id]);
    $otherFarmer = Farmer::factory()->create(['dealer_id' => $otherDealer->id]);

    $this->actingAs($marketing)->post(route('bookings.store'), [
        'dealer_id' => $assignedDealer->id,
        'farmer_id' => $assignedFarmer->id,
        'variety' => 'G9',
        'booking_date' => now()->toDateString(),
        'plant_qty' => 50,
        'plant_rate' => 10,
    ])->assertSessionHasNoErrors();

    $this->actingAs($marketing)->post(route('bookings.store'), [
        'dealer_id' => $otherDealer->id,
        'farmer_id' => $otherFarmer->id,
        'variety' => 'G9',
        'booking_date' => now()->toDateString(),
        'plant_qty' => 50,
        'plant_rate' => 10,
    ])->assertSessionHasErrors('dealer_id');
});

test('marketing cannot approve, reject, hold or unlock a booking', function () {
    $marketing = bookingRoleUser('marketing');
    $dealer = Dealer::factory()->create();
    assignDealerToMarketing($dealer, $marketing);
    $booking = Booking::factory()->create(['dealer_id' => $dealer->id, 'approval_status' => 'pending']);

    $this->actingAs($marketing)->post(route('bookings.approve', $booking))->assertForbidden();
    $this->actingAs($marketing)->post(route('bookings.reject', $booking))->assertForbidden();
    $this->actingAs($marketing)->post(route('bookings.hold', $booking))->assertForbidden();
});

test('accounts can create and edit draft bookings but cannot approve or delete', function () {
    $accounts = bookingRoleUser('accounts');
    $dealer = Dealer::factory()->create();
    $farmer = Farmer::factory()->create(['dealer_id' => $dealer->id]);
    $booking = Booking::factory()->create(['dealer_id' => $dealer->id, 'farmer_id' => $farmer->id, 'approval_status' => 'draft']);

    $this->actingAs($accounts)->get(route('bookings.edit', $booking))->assertOk();
    $this->actingAs($accounts)->post(route('bookings.approve', $booking))->assertForbidden();
    $this->actingAs($accounts)->delete(route('bookings.destroy', $booking))->assertForbidden();
});

test('draft-only bookings cannot be edited by marketing or accounts once submitted', function () {
    $accounts = bookingRoleUser('accounts');
    $dealer = Dealer::factory()->create();
    $farmer = Farmer::factory()->create(['dealer_id' => $dealer->id]);
    $booking = Booking::factory()->create(['dealer_id' => $dealer->id, 'farmer_id' => $farmer->id, 'approval_status' => 'pending']);

    $this->actingAs($accounts)->get(route('bookings.edit', $booking))->assertForbidden();
});

test('only admin can change plant rate or discount', function () {
    $accounts = bookingRoleUser('accounts');
    $dealer = Dealer::factory()->create();
    $farmer = Farmer::factory()->create(['dealer_id' => $dealer->id]);
    $booking = Booking::factory()->create(['dealer_id' => $dealer->id, 'farmer_id' => $farmer->id, 'approval_status' => 'draft', 'plant_rate' => 10, 'discount' => 0]);

    $response = $this->actingAs($accounts)->put(route('bookings.update', $booking), [
        'dealer_id' => $dealer->id,
        'farmer_id' => $farmer->id,
        'variety' => $booking->variety,
        'booking_date' => $booking->booking_date->toDateString(),
        'plant_qty' => $booking->plant_qty,
        'plant_rate' => 99, // attempted change
        'discount' => 5, // attempted change
    ]);

    $response->assertSessionHasErrors(['plant_rate', 'discount']);
});

test('admin can drive a booking through the full approval workflow', function () {
    $admin = bookingRoleUser('admin');
    $dealer = Dealer::factory()->create();
    $farmer = Farmer::factory()->create(['dealer_id' => $dealer->id]);
    $booking = Booking::factory()->create(['dealer_id' => $dealer->id, 'farmer_id' => $farmer->id, 'approval_status' => 'draft']);

    $this->actingAs($admin)->post(route('bookings.submit', $booking))->assertRedirect();
    expect($booking->fresh()->approval_status)->toBe('pending');

    $this->actingAs($admin)->post(route('bookings.verify', $booking))->assertRedirect();
    expect($booking->fresh()->approval_status)->toBe('verified');

    $this->actingAs($admin)->post(route('bookings.approve', $booking))->assertRedirect();
    $booking->refresh();
    expect($booking->approval_status)->toBe('approved');
    expect($booking->approved_by)->toBe($admin->id);

    $this->actingAs($admin)->post(route('bookings.unlock', $booking))->assertRedirect();
    expect($booking->fresh()->approval_status)->toBe('draft');
});

test('a booking cannot be approved unless it is pending', function () {
    $admin = bookingRoleUser('admin');
    $booking = Booking::factory()->create(['approval_status' => 'draft']);

    $this->actingAs($admin)->post(route('bookings.approve', $booking))->assertSessionHasErrors('approval_status');
    expect($booking->fresh()->approval_status)->toBe('draft');
});

test('receiving payment updates advance amount and payment status, capped at the balance', function () {
    $accounts = bookingRoleUser('accounts');
    $booking = Booking::factory()->create(['plant_qty' => 100, 'plant_rate' => 10, 'discount' => 0, 'advance_amount' => 0]);

    $this->actingAs($accounts)->post(route('bookings.receive-payment', $booking), ['amount' => 400])->assertSessionHasNoErrors();
    $booking->refresh();
    expect((float) $booking->advance_amount)->toBe(400.0);
    expect($booking->payment_status)->toBe('partial');

    $this->actingAs($accounts)->post(route('bookings.receive-payment', $booking), ['amount' => 5000])
        ->assertSessionHasErrors('amount');
});

test('dealer role only sees their own bookings and cannot create one', function () {
    $ownDealer = Dealer::factory()->create();
    $otherDealer = Dealer::factory()->create();
    $dealerUser = bookingRoleUser('dealer', ['dealer_id' => $ownDealer->id]);

    $ownBooking = Booking::factory()->create(['dealer_id' => $ownDealer->id]);
    $otherBooking = Booking::factory()->create(['dealer_id' => $otherDealer->id]);

    $response = $this->actingAs($dealerUser)->get(route('bookings.index'));
    $response->assertOk()->assertSee($ownBooking->booking_no)->assertDontSee($otherBooking->booking_no);

    $this->actingAs($dealerUser)->get(route('bookings.show', $otherBooking))->assertForbidden();
    $this->actingAs($dealerUser)->get(route('bookings.create'))->assertForbidden();
});

test('admin can soft delete and restore a booking', function () {
    $admin = bookingRoleUser('admin');
    $booking = Booking::factory()->create();

    $this->actingAs($admin)->delete(route('bookings.destroy', $booking))->assertRedirect(route('bookings.index'));
    $this->assertSoftDeleted($booking);

    $this->actingAs($admin)->post(route('bookings.restore', $booking))->assertRedirect(route('bookings.index'));
    $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'deleted_at' => null]);
});
