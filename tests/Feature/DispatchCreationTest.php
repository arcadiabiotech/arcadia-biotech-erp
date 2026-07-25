<?php

use App\Models\Booking;
use App\Models\Dealer;
use App\Models\Dispatch;
use App\Models\DispatchPlan;
use App\Models\DispatchPlanItem;
use App\Models\Farmer;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleLoading;

function creationTestUser(string $roleName): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(['role_id' => $role->id]);
}

function creationTestBooking(?Dealer $dealer = null): Booking
{
    $dealer ??= Dealer::factory()->create();
    $farmer = Farmer::factory()->create(['dealer_id' => $dealer->id]);

    return Booking::factory()->create([
        'dealer_id' => $dealer->id,
        'farmer_id' => $farmer->id,
        'approval_status' => 'approved',
        'payment_status' => 'completed',
        'plant_qty' => 400,
        'plant_rate' => 12,
    ]);
}

/**
 * A VehicleAssignment whose loading is already Approved (completed) —
 * the precondition DispatchService::eligibleAssignments() checks — with one
 * DispatchPlanItem per [booking, qty] pair. Built directly via Eloquent
 * (bypassing the planning HTTP flow, already covered by
 * DispatchPlanningTest) so these tests can focus purely on the
 * Dispatch-creation step itself.
 */
function loadedAssignment(array $items, ?Vehicle $vehicle = null): VehicleAssignment
{
    $vehicle ??= Vehicle::create(['vehicle_no' => 'GJ-'.str()->random(4).'-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-TEST-'.str()->random(6), 'plan_date' => now()->toDateString(), 'approval_status' => 'approved']);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id, 'driver_name' => 'Ramesh', 'driver_mobile' => '9988776655']);

    foreach ($items as [$booking, $qty]) {
        DispatchPlanItem::create([
            'vehicle_assignment_id' => $assignment->id,
            'booking_id' => $booking->id,
            'dispatch_qty' => $qty,
            'loaded_at' => now(),
        ]);
    }

    VehicleLoading::create(['vehicle_assignment_id' => $assignment->id, 'status' => 'completed', 'loading_finished_at' => now()]);

    return $assignment->fresh('items');
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

test('only vehicles with completed loading and remaining quantity are eligible for dispatch', function () {
    $dispatchUser = creationTestUser('dispatch');

    $notLoaded = VehicleAssignment::create([
        'dispatch_plan_id' => DispatchPlan::create(['plan_no' => 'PLN-TEST-A', 'plan_date' => now()->toDateString(), 'approval_status' => 'approved'])->id,
        'vehicle_id' => Vehicle::create(['vehicle_no' => 'GJ-NL-TEST', 'vehicle_type' => 'Truck', 'status' => true])->id,
    ]);
    DispatchPlanItem::create(['vehicle_assignment_id' => $notLoaded->id, 'booking_id' => creationTestBooking()->id, 'dispatch_qty' => 100, 'loaded_at' => now()]);

    $eligible = loadedAssignment([[creationTestBooking(), 100]]);

    $response = $this->actingAs($dispatchUser)->get(route('dispatches.create'));

    $response->assertOk();
    $response->assertSee($eligible->vehicle->vehicle_no);
    $response->assertDontSee($notLoaded->vehicle->vehicle_no);
});

test('dispatch role can create a dispatch from an eligible vehicle assignment', function () {
    $dispatchUser = creationTestUser('dispatch');
    $booking = creationTestBooking();
    $assignment = loadedAssignment([[$booking, 400]]);
    $item = $assignment->items->first();

    $response = $this->actingAs($dispatchUser)->post(route('dispatches.store'), [
        'vehicle_assignment_id' => $assignment->id,
        'dispatch_date' => now()->toDateString(),
        'lines' => [
            $item->id => ['qty' => 400, 'extra_qty' => 10, 'qty_per_crate' => 40],
        ],
    ]);

    $response->assertRedirect();

    $dispatch = Dispatch::first();
    expect($dispatch)->not->toBeNull();
    expect($dispatch->vehicle_assignment_id)->toBe($assignment->id);
    expect($dispatch->lines)->toHaveCount(1);
    expect($dispatch->lines->first()->dispatch_qty)->toBe(400);
    expect($dispatch->lines->first()->booking_id)->toBe($booking->id);
    expect($dispatch->total_qty)->toBe(410);
    expect($item->fresh()->remaining_qty)->toBe(0);
    expect($assignment->fresh()->dispatch_status)->toBe('completed');
});

test('a vehicle carrying two dealers creates one dispatch with one line per dealer', function () {
    $dispatchUser = creationTestUser('dispatch');
    $dealerA = Dealer::factory()->create();
    $dealerB = Dealer::factory()->create();
    $bookingA = creationTestBooking($dealerA);
    $bookingB = creationTestBooking($dealerB);
    $assignment = loadedAssignment([[$bookingA, 200], [$bookingB, 150]]);
    [$itemA, $itemB] = $assignment->items;

    $response = $this->actingAs($dispatchUser)->post(route('dispatches.store'), [
        'vehicle_assignment_id' => $assignment->id,
        'dispatch_date' => now()->toDateString(),
        'lines' => [
            $itemA->id => ['qty' => 200],
            $itemB->id => ['qty' => 150],
        ],
    ]);

    $response->assertRedirect();

    $dispatch = Dispatch::with('lines')->first();
    expect($dispatch->lines)->toHaveCount(2);
    expect($dispatch->dealers->pluck('id')->sort()->values()->all())->toBe(collect([$dealerA->id, $dealerB->id])->sort()->values()->all());
});

test('partial dispatch: dispatching less than the planned quantity leaves the item eligible for a second dispatch', function () {
    $dispatchUser = creationTestUser('dispatch');
    $booking = creationTestBooking();
    $assignment = loadedAssignment([[$booking, 400]]);
    $item = $assignment->items->first();

    $this->actingAs($dispatchUser)->post(route('dispatches.store'), [
        'vehicle_assignment_id' => $assignment->id,
        'dispatch_date' => now()->toDateString(),
        'lines' => [$item->id => ['qty' => 250]],
    ])->assertRedirect();

    expect($item->fresh()->remaining_qty)->toBe(150);
    expect($assignment->fresh()->dispatch_status)->toBe('partially_dispatched');

    // The vehicle is still eligible for a second, remainder Dispatch.
    $this->actingAs($dispatchUser)->get(route('dispatches.create'))->assertSee($assignment->vehicle->vehicle_no);

    $this->actingAs($dispatchUser)->post(route('dispatches.store'), [
        'vehicle_assignment_id' => $assignment->id,
        'dispatch_date' => now()->toDateString(),
        'lines' => [$item->id => ['qty' => 150]],
    ])->assertRedirect();

    expect($item->fresh()->remaining_qty)->toBe(0);
    expect($assignment->fresh()->dispatch_status)->toBe('completed');
    expect(Dispatch::count())->toBe(2);
});

test('requesting more than the remaining planned quantity for a line is rejected', function () {
    $dispatchUser = creationTestUser('dispatch');
    $booking = creationTestBooking();
    $assignment = loadedAssignment([[$booking, 400]]);
    $item = $assignment->items->first();

    $response = $this->actingAs($dispatchUser)->post(route('dispatches.store'), [
        'vehicle_assignment_id' => $assignment->id,
        'dispatch_date' => now()->toDateString(),
        'lines' => [$item->id => ['qty' => 500]],
    ]);

    $response->assertSessionHasErrors(["lines.{$item->id}.qty"]);
    expect(Dispatch::count())->toBe(0);
});

test('eligibleAssignments() scopes marketing users to vehicles carrying only their assigned dealers', function () {
    // DispatchPolicy::create() doesn't currently grant Marketing the
    // dispatches.create route (only super-admin/admin/dispatch) — this
    // scoping exists in the service for the same reason
    // DispatchPlanningService::eligibleBookings() scopes Marketing, so it's
    // exercised directly against the service rather than over HTTP.
    $marketingUser = creationTestUser('marketing');
    $myDealer = Dealer::factory()->create();
    \App\Models\DealerAssignment::create(['dealer_id' => $myDealer->id, 'marketing_user_id' => $marketingUser->id, 'status' => true, 'assigned_date' => now()->toDateString()]);
    $otherDealer = Dealer::factory()->create();

    $myAssignment = loadedAssignment([[creationTestBooking($myDealer), 100]]);
    $otherAssignment = loadedAssignment([[creationTestBooking($otherDealer), 100]]);

    $eligible = app(\App\Services\DispatchService::class)->eligibleAssignments($marketingUser);

    expect($eligible->pluck('id')->all())->toBe([$myAssignment->id]);
});
