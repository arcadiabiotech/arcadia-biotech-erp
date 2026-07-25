<?php

use App\Models\Booking;
use App\Models\Dealer;
use App\Models\Dispatch;
use App\Models\DispatchLine;
use App\Models\DispatchPlan;
use App\Models\DispatchPlanItem;
use App\Models\Farmer;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;

function transportTestUser(string $roleName): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(['role_id' => $role->id]);
}

/**
 * Builds a "dispatched" shipment with odometer_start/cost_per_km/
 * driver_allowance already snapshotted onto the Dispatch row — exactly
 * what DispatchService::createFromAssignment() does at creation time — so
 * these tests can exercise markVehicleReturned()/vehicleReturned() in
 * isolation without going through the full Dispatch Planning -> Loading ->
 * Dispatch creation flow (already covered by DispatchCreationTest).
 */
function makeTransportDispatch(array $dispatchOverrides = [], ?float $vehicleCostPerKm = 18.0, ?int $startKm = 25120): Dispatch
{
    $dealer = Dealer::factory()->create();
    $farmer = Farmer::factory()->create(['dealer_id' => $dealer->id]);
    $booking = Booking::factory()->create([
        'dealer_id' => $dealer->id,
        'farmer_id' => $farmer->id,
        'approval_status' => 'approved',
        'plant_qty' => 400,
        'plant_rate' => 12,
    ]);

    $vehicle = Vehicle::create([
        'vehicle_no' => 'GJ-'.str()->random(4).'-TEST',
        'vehicle_type' => 'Truck',
        'status' => true,
        'cost_per_km' => $vehicleCostPerKm,
    ]);

    $plan = DispatchPlan::create(['plan_no' => 'PLN-TEST-'.str()->random(6), 'plan_date' => now()->toDateString()]);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id, 'start_km' => $startKm]);
    $item = DispatchPlanItem::create(['vehicle_assignment_id' => $assignment->id, 'booking_id' => $booking->id, 'dispatch_qty' => 400]);

    $dispatch = Dispatch::create(array_merge([
        'dispatch_no' => 'DIS-TEST-'.str()->random(6),
        'vehicle_assignment_id' => $assignment->id,
        'vehicle_id' => $vehicle->id,
        'dispatch_date' => now()->toDateString(),
        'status' => 'dispatched',
        'odometer_start' => $startKm,
        'cost_per_km' => $vehicle->cost_per_km,
        'driver_allowance' => $vehicle->driver_allowance,
    ], $dispatchOverrides));

    DispatchLine::create([
        'dispatch_id' => $dispatch->id,
        'dispatch_plan_item_id' => $item->id,
        'booking_id' => $booking->id,
        'dealer_id' => $dealer->id,
        'farmer_id' => $farmer->id,
        'dispatch_qty' => 400,
        'extra_qty' => 0,
        'remaining_qty' => 0,
    ]);

    return $dispatch->fresh('lines');
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

test('marking a vehicle returned computes total km, transport cost and total transport expense per the spec formula', function () {
    $dispatchUser = transportTestUser('dispatch');
    $dispatch = makeTransportDispatch();

    $response = $this->actingAs($dispatchUser)->post(route('dispatches.vehicle-returned', $dispatch), [
        'odometer_end' => 25465,
        'toll_charges' => 100,
        'other_expenses' => 50,
        'driver_allowance' => 300,
    ]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors();

    $fresh = $dispatch->fresh();
    expect($fresh->odometer_end)->toBe(25465);
    expect($fresh->total_km)->toBe(345);
    expect((float) $fresh->transport_cost)->toBe(6210.0);
    expect((float) $fresh->total_transport_expense)->toBe(6660.0);
});

test('odometer end less than odometer start is rejected', function () {
    $dispatchUser = transportTestUser('dispatch');
    $dispatch = makeTransportDispatch();

    $response = $this->actingAs($dispatchUser)->post(route('dispatches.vehicle-returned', $dispatch), [
        'odometer_end' => 25000,
    ]);

    $response->assertSessionHasErrors(['odometer_end']);
    expect($dispatch->fresh()->odometer_end)->toBeNull();
});

test('a dispatch whose assignment never captured start_km can still be marked returned, with no cost computed', function () {
    $dispatchUser = transportTestUser('dispatch');
    $dispatch = makeTransportDispatch(['odometer_start' => null, 'cost_per_km' => null], null, null);

    $response = $this->actingAs($dispatchUser)->post(route('dispatches.vehicle-returned', $dispatch), [
        'odometer_end' => 500,
    ]);

    $response->assertRedirect();
    $fresh = $dispatch->fresh();
    expect($fresh->odometer_end)->toBe(500);
    expect($fresh->total_km)->toBeNull();
    expect($fresh->transport_cost)->toBeNull();
    expect($fresh->total_transport_expense)->toBeNull();
});

test('once transport cost is saved, a non-admin cannot resubmit vehicle-returned, but an admin can correct it', function () {
    $dispatchUser = transportTestUser('dispatch');
    $admin = transportTestUser('super-admin');
    $dispatch = makeTransportDispatch();

    $this->actingAs($dispatchUser)->post(route('dispatches.vehicle-returned', $dispatch), [
        'odometer_end' => 25465,
    ])->assertRedirect();

    $this->actingAs($dispatchUser)->post(route('dispatches.vehicle-returned', $dispatch), [
        'odometer_end' => 25500,
    ])->assertForbidden();

    $this->actingAs($admin)->post(route('dispatches.vehicle-returned', $dispatch), [
        'odometer_end' => 25500,
    ])->assertRedirect();

    expect($dispatch->fresh()->odometer_end)->toBe(25500);
});

test('vehicle store and update require cost_per_km', function () {
    $admin = transportTestUser('super-admin');

    $this->actingAs($admin)->post(route('vehicles.store'), [
        'vehicle_no' => 'GJ-90-NOCOST',
        'status' => true,
    ])->assertSessionHasErrors(['cost_per_km']);

    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-91-TEST', 'status' => true, 'cost_per_km' => 10]);

    $this->actingAs($admin)->put(route('vehicles.update', $vehicle), [
        'vehicle_no' => 'GJ-91-TEST',
        'status' => true,
    ])->assertSessionHasErrors(['cost_per_km']);
});

test('vehicle assignment store and update require start_km', function () {
    $admin = transportTestUser('super-admin');
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-92-TEST', 'status' => true, 'cost_per_km' => 10]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-TEST-NOKM', 'plan_date' => now()->toDateString(), 'approval_status' => 'approved']);

    $this->actingAs($admin)->post(route('vehicle-assignments.store', $plan), [
        'vehicle_id' => $vehicle->id,
    ])->assertSessionHasErrors(['start_km']);

    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id, 'start_km' => 100]);

    $this->actingAs($admin)->put(route('vehicle-assignments.update', $assignment), [
        'vehicle_id' => $vehicle->id,
    ])->assertSessionHasErrors(['start_km']);
});

test('transport cost splits proportionally across dealers by plant qty share', function () {
    $dealerA = Dealer::factory()->create();
    $dealerB = Dealer::factory()->create();
    $farmerA = Farmer::factory()->create(['dealer_id' => $dealerA->id]);
    $farmerB = Farmer::factory()->create(['dealer_id' => $dealerB->id]);
    $bookingA = Booking::factory()->create(['dealer_id' => $dealerA->id, 'farmer_id' => $farmerA->id, 'plant_qty' => 300]);
    $bookingB = Booking::factory()->create(['dealer_id' => $dealerB->id, 'farmer_id' => $farmerB->id, 'plant_qty' => 100]);

    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-93-TEST', 'status' => true, 'cost_per_km' => 10]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-TEST-SPLIT', 'plan_date' => now()->toDateString()]);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id, 'start_km' => 0]);
    $itemA = DispatchPlanItem::create(['vehicle_assignment_id' => $assignment->id, 'booking_id' => $bookingA->id, 'dispatch_qty' => 300]);
    $itemB = DispatchPlanItem::create(['vehicle_assignment_id' => $assignment->id, 'booking_id' => $bookingB->id, 'dispatch_qty' => 100]);

    $dispatch = Dispatch::create([
        'dispatch_no' => 'DIS-TEST-SPLIT',
        'vehicle_assignment_id' => $assignment->id,
        'vehicle_id' => $vehicle->id,
        'dispatch_date' => now()->toDateString(),
        'status' => 'completed',
        'total_transport_expense' => 400,
    ]);

    DispatchLine::create(['dispatch_id' => $dispatch->id, 'dispatch_plan_item_id' => $itemA->id, 'booking_id' => $bookingA->id, 'dealer_id' => $dealerA->id, 'farmer_id' => $farmerA->id, 'dispatch_qty' => 300, 'extra_qty' => 0, 'remaining_qty' => 0]);
    DispatchLine::create(['dispatch_id' => $dispatch->id, 'dispatch_plan_item_id' => $itemB->id, 'booking_id' => $bookingB->id, 'dealer_id' => $dealerB->id, 'farmer_id' => $farmerB->id, 'dispatch_qty' => 100, 'extra_qty' => 0, 'remaining_qty' => 0]);

    $allocation = $dispatch->fresh('lines')->dealer_transport_allocation->keyBy('dealer_id');

    expect((float) $allocation[$dealerA->id]['allocated_cost'])->toBe(300.0);
    expect((float) $allocation[$dealerB->id]['allocated_cost'])->toBe(100.0);
});

test('transport cost report is restricted to admin/dispatch and lists dispatches with recorded cost', function () {
    $admin = transportTestUser('super-admin');
    $marketing = transportTestUser('marketing');
    $dispatchUser = transportTestUser('dispatch');

    $dispatch = makeTransportDispatch();
    $this->actingAs($dispatchUser)->post(route('dispatches.vehicle-returned', $dispatch), [
        'odometer_end' => 25465,
    ])->assertRedirect();

    $this->actingAs($marketing)->get(route('reports.transport-cost'))->assertForbidden();

    $response = $this->actingAs($admin)->get(route('reports.transport-cost'));
    $response->assertOk();
    $response->assertSee($dispatch->dispatch_no);
});
