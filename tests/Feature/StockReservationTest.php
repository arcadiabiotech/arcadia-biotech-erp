<?php

use App\Models\Booking;
use App\Models\Dealer;
use App\Models\Farmer;
use App\Models\Role;
use App\Models\StockReservation;
use App\Models\User;
use App\Models\VarietyStock;

function stockRoleUser(string $roleName): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(['role_id' => $role->id]);
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    VarietyStock::create(['variety' => 'G9', 'actual_qty' => 10000]);
});

test('creating a booking automatically reserves stock, not actual stock', function () {
    $admin = stockRoleUser('admin');
    $dealer = Dealer::factory()->create();
    $farmer = Farmer::factory()->create(['dealer_id' => $dealer->id]);

    $this->actingAs($admin)->post(route('bookings.store'), [
        'dealer_id' => $dealer->id,
        'farmer_id' => $farmer->id,
        'variety' => 'G9',
        'booking_date' => now()->toDateString(),
        'plant_qty' => 2000,
        'plant_rate' => 10,
    ])->assertSessionHasNoErrors();

    $booking = Booking::first();
    $reservation = StockReservation::where('booking_id', $booking->id)->first();

    expect($reservation)->not->toBeNull();
    expect($reservation->status)->toBe('reserved');
    expect($reservation->reserved_qty)->toBe(2000);

    // Actual stock must be untouched — only reserved.
    expect(VarietyStock::where('variety', 'G9')->first()->actual_qty)->toBe(10000);
    expect(VarietyStock::where('variety', 'G9')->first()->availableQty())->toBe(8000);
});

test('a booking cannot reserve more than the available stock', function () {
    $admin = stockRoleUser('admin');
    $dealer = Dealer::factory()->create();
    $farmer = Farmer::factory()->create(['dealer_id' => $dealer->id]);

    $response = $this->actingAs($admin)->post(route('bookings.store'), [
        'dealer_id' => $dealer->id,
        'farmer_id' => $farmer->id,
        'variety' => 'G9',
        'booking_date' => now()->toDateString(),
        'plant_qty' => 50000, // exceeds actual stock of 10000
        'plant_rate' => 10,
    ]);

    $response->assertSessionHasErrors('plant_qty');
    expect(Booking::count())->toBe(0);
    expect(StockReservation::count())->toBe(0);
});

test('editing a booking updates its reservation quantity', function () {
    $admin = stockRoleUser('admin');
    $dealer = Dealer::factory()->create();
    $farmer = Farmer::factory()->create(['dealer_id' => $dealer->id]);
    $booking = Booking::factory()->create(['dealer_id' => $dealer->id, 'farmer_id' => $farmer->id, 'variety' => 'G9', 'plant_qty' => 1000, 'approval_status' => 'draft']);
    $reservation = app(App\Services\StockReservationService::class)->reserve($booking);

    $this->actingAs($admin)->put(route('bookings.update', $booking), [
        'dealer_id' => $dealer->id,
        'farmer_id' => $farmer->id,
        'variety' => 'G9',
        'booking_date' => $booking->booking_date->toDateString(),
        'plant_qty' => 3000,
        'plant_rate' => $booking->plant_rate,
    ])->assertSessionHasNoErrors();

    expect($reservation->fresh()->reserved_qty)->toBe(3000);
});

test('rejecting a booking releases its reservation', function () {
    $admin = stockRoleUser('admin');
    $booking = Booking::factory()->create(['variety' => 'G9', 'plant_qty' => 1500, 'approval_status' => 'pending']);
    $reservation = app(App\Services\StockReservationService::class)->reserve($booking);

    $this->actingAs($admin)->post(route('bookings.reject', $booking))->assertRedirect();

    $reservation->refresh();
    expect($reservation->status)->toBe('released');
    expect($reservation->released_qty)->toBe(1500);
    expect(VarietyStock::where('variety', 'G9')->first()->availableQty())->toBe(10000); // fully freed
});

test('deleting a booking cancels its reservation', function () {
    $admin = stockRoleUser('admin');
    $booking = Booking::factory()->create(['variety' => 'G9', 'plant_qty' => 500]);
    $reservation = app(App\Services\StockReservationService::class)->reserve($booking);

    $this->actingAs($admin)->delete(route('bookings.destroy', $booking))->assertRedirect();

    expect($reservation->fresh()->status)->toBe('cancelled');
});

test('completing dispatch converts the reservation and reduces actual stock', function () {
    $admin = stockRoleUser('admin');
    $booking = Booking::factory()->create(['variety' => 'G9', 'plant_qty' => 2000, 'approval_status' => 'approved']);
    $reservation = app(App\Services\StockReservationService::class)->reserve($booking);

    $this->actingAs($admin)->post(route('bookings.complete-dispatch', $booking))->assertRedirect();

    $reservation->refresh();
    expect($reservation->status)->toBe('converted');
    expect($booking->fresh()->dispatch_status)->toBe('completed');

    $stock = VarietyStock::where('variety', 'G9')->first();
    expect($stock->actual_qty)->toBe(8000); // 10000 - 2000
    expect($stock->reservedQty())->toBe(0); // no longer counted as reserved
    expect($stock->availableQty())->toBe(8000);
});

test('dispatch cannot be completed on a booking that is not approved', function () {
    $admin = stockRoleUser('admin');
    $booking = Booking::factory()->create(['variety' => 'G9', 'approval_status' => 'draft']);

    $this->actingAs($admin)->post(route('bookings.complete-dispatch', $booking))->assertForbidden();
});

test('only admin can complete dispatch', function () {
    $accounts = stockRoleUser('accounts');
    $booking = Booking::factory()->create(['variety' => 'G9', 'approval_status' => 'approved']);

    $this->actingAs($accounts)->post(route('bookings.complete-dispatch', $booking))->assertForbidden();
});
