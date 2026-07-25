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
use App\Models\VehicleAssignment;

function returnTestUser(string $roleName): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(['role_id' => $role->id]);
}

function makeReturnShipment(array $overrides = []): Dispatch
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

    $lineKeys = ['dispatch_qty', 'extra_qty', 'qty_per_crate', 'crates_returned', 'damage_qty', 'batch_number', 'plant_age', 'remaining_qty'];
    $lineOverrides = array_intersect_key($overrides, array_flip($lineKeys));
    $headerOverrides = array_diff_key($overrides, $lineOverrides);

    $vehicle = \App\Models\Vehicle::create(['vehicle_no' => 'GJ-'.str()->random(4).'-TEST', 'vehicle_type' => 'Truck', 'status' => true]);

    $plan = DispatchPlan::create(['plan_no' => 'PLN-TEST-'.str()->random(6), 'plan_date' => now()->toDateString()]);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id]);
    $item = DispatchPlanItem::create(['vehicle_assignment_id' => $assignment->id, 'booking_id' => $booking->id, 'dispatch_qty' => 400]);

    $dispatch = Dispatch::create(array_merge([
        'dispatch_no' => 'DIS-TEST-'.str()->random(6),
        'vehicle_assignment_id' => $assignment->id,
        'vehicle_id' => $vehicle->id,
        'dispatch_date' => now()->toDateString(),
        'status' => 'dispatched',
    ], $headerOverrides));

    DispatchLine::create(array_merge([
        'dispatch_id' => $dispatch->id,
        'dispatch_plan_item_id' => $item->id,
        'booking_id' => $booking->id,
        'dealer_id' => $dealer->id,
        'farmer_id' => $farmer->id,
        'dispatch_qty' => 400,
        'extra_qty' => 20,
        'qty_per_crate' => 40,
        'remaining_qty' => 0,
    ], $lineOverrides));

    return $dispatch->fresh('lines');
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

test('dispatch role can record a full crate return and it is marked complete', function () {
    $dispatchUser = returnTestUser('dispatch');
    $dispatch = makeReturnShipment(['status' => 'dispatched', 'created_by' => $dispatchUser->id]);
    $line = $dispatch->lines->first();

    expect($line->crate_count)->toBe(10.5);

    $response = $this->actingAs($dispatchUser)->post(route('dispatches.record-return', $dispatch), [
        'lines' => [$line->id => ['returned_qty' => 10.5, 'damage_qty' => 0]],
        'vehicle_returned_at' => now()->toDateString(),
        'return_remarks' => 'All crates back, none damaged',
    ]);

    $response->assertRedirect();

    $freshLine = $line->fresh();
    expect((float) $freshLine->crates_returned)->toBe(10.5);
    expect($dispatch->fresh()->vehicle_returned_at->toDateString())->toBe(now()->toDateString());
    expect($freshLine->return_status)->toBe('complete');
    expect($freshLine->pending_crates)->toBe(0.0);
});

test('a partial crate return is marked partial with the correct pending count', function () {
    $dispatchUser = returnTestUser('dispatch');
    $dispatch = makeReturnShipment(['status' => 'delivered']);
    $line = $dispatch->lines->first();

    $this->actingAs($dispatchUser)->post(route('dispatches.record-return', $dispatch), [
        'lines' => [$line->id => ['returned_qty' => 4]],
        'vehicle_returned_at' => now()->toDateString(),
    ])->assertRedirect();

    $freshLine = $line->fresh();
    expect($freshLine->return_status)->toBe('partial');
    expect($freshLine->pending_crates)->toBe(6.5);
});

test('damaged crates count toward what is accounted for but not toward returned', function () {
    $dispatchUser = returnTestUser('dispatch');
    $dispatch = makeReturnShipment(['status' => 'dispatched']);
    $line = $dispatch->lines->first();

    $this->actingAs($dispatchUser)->post(route('dispatches.record-return', $dispatch), [
        'lines' => [$line->id => ['returned_qty' => 8, 'damage_qty' => 2.5]],
        'vehicle_returned_at' => now()->toDateString(),
    ])->assertRedirect();

    $freshLine = $line->fresh();
    expect((float) $freshLine->crates_returned)->toBe(8.0);
    expect((float) $freshLine->damage_qty)->toBe(2.5);
    expect($freshLine->pending_crates)->toBe(0.0);
    expect($freshLine->return_status)->toBe('complete');

    $freshDispatch = $dispatch->fresh('lines');
    expect($freshDispatch->total_crates)->toBe(10.5);
    expect($freshDispatch->returned_crates)->toBe(8.0);
    expect($freshDispatch->damage_crates)->toBe(2.5);
    expect($freshDispatch->crate_return_pending)->toBe(0.0);
    expect($freshDispatch->crate_return_status)->toBe('returned');
});

test('returned plus damaged crates cannot exceed what was sent', function () {
    $dispatchUser = returnTestUser('dispatch');
    $dispatch = makeReturnShipment(['status' => 'completed']);
    $line = $dispatch->lines->first();

    $response = $this->actingAs($dispatchUser)->post(route('dispatches.record-return', $dispatch), [
        'lines' => [$line->id => ['returned_qty' => 9, 'damage_qty' => 3]],
        'vehicle_returned_at' => now()->toDateString(),
    ]);

    $response->assertSessionHasErrors(['crates_returned']);
    expect($line->fresh()->crates_returned)->toBeNull();
});

test('returning more crates than were sent is rejected', function () {
    $dispatchUser = returnTestUser('dispatch');
    $dispatch = makeReturnShipment(['status' => 'completed']);
    $line = $dispatch->lines->first();

    $response = $this->actingAs($dispatchUser)->post(route('dispatches.record-return', $dispatch), [
        'lines' => [$line->id => ['returned_qty' => 99]],
        'vehicle_returned_at' => now()->toDateString(),
    ]);

    $response->assertSessionHasErrors(['crates_returned']);
    expect($line->fresh()->crates_returned)->toBeNull();
});

test('a dispatch that has not left yet cannot have a return recorded', function () {
    $dispatchUser = returnTestUser('dispatch');
    $dispatch = makeReturnShipment(['status' => 'loading']);
    $line = $dispatch->lines->first();

    $this->actingAs($dispatchUser)->post(route('dispatches.record-return', $dispatch), [
        'lines' => [$line->id => ['returned_qty' => 1]],
        'vehicle_returned_at' => now()->toDateString(),
    ])->assertForbidden();
});

test('vehicle return can be recorded with no crate lines to report', function () {
    $dispatchUser = returnTestUser('dispatch');
    $dispatch = makeReturnShipment(['status' => 'dispatched', 'qty_per_crate' => null]);

    $response = $this->actingAs($dispatchUser)->post(route('dispatches.record-return', $dispatch), [
        'vehicle_returned_at' => now()->toDateString(),
        'return_remarks' => 'No crates involved in this shipment',
    ]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors();
    expect($dispatch->fresh()->vehicle_returned_at->toDateString())->toBe(now()->toDateString());
    expect($dispatch->fresh('lines')->total_crates)->toBeNull();
});

test('a dealer role user cannot record a return', function () {
    $dealerUser = returnTestUser('dealer');
    $dispatch = makeReturnShipment(['status' => 'dispatched']);
    $line = $dispatch->lines->first();

    $this->actingAs($dealerUser)->post(route('dispatches.record-return', $dispatch), [
        'lines' => [$line->id => ['returned_qty' => 1]],
        'vehicle_returned_at' => now()->toDateString(),
    ])->assertForbidden();
});

test('crate return report lists pending and returned dispatches and can be filtered by status', function () {
    $admin = returnTestUser('super-admin');

    $pendingDispatch = makeReturnShipment(['status' => 'dispatched']);
    $returnedDispatch = makeReturnShipment(['status' => 'dispatched', 'crates_returned' => 10.5, 'vehicle_returned_at' => now()->toDateString()]);

    $response = $this->actingAs($admin)->get(route('reports.crate-returns'));

    $response->assertOk();
    $response->assertSee($pendingDispatch->dispatch_no);
    $response->assertSee($returnedDispatch->dispatch_no);

    $pendingOnly = $this->actingAs($admin)->get(route('reports.crate-returns', ['status' => 'pending']));
    $pendingOnly->assertOk();
    $pendingOnly->assertSee($pendingDispatch->dispatch_no);
    $pendingOnly->assertDontSee($returnedDispatch->dispatch_no);
});

test('a dealer role user cannot view the crate return report', function () {
    $dealerUser = returnTestUser('dealer');

    $this->actingAs($dealerUser)->get(route('reports.crate-returns'))->assertForbidden();
});
