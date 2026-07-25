<?php

use App\Models\Booking;
use App\Models\Dealer;
use App\Models\Dispatch;
use App\Models\DispatchLine;
use App\Models\DispatchPlan;
use App\Models\DispatchPlanItem;
use App\Models\Farmer;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\StockReservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VarietyStock;
use App\Services\StockReservationService;

function rejectionTestUser(string $roleName): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(['role_id' => $role->id]);
}

/**
 * A Dispatch header already at 'dispatched' status (past loading/vehicle-out)
 * with one DispatchLine, plus a real StockReservation for its booking —
 * everything markDelivered()/complete() need to actually move stock.
 */
function makeDispatchedShipment(int $qty = 400, string $variety = 'G9'): Dispatch
{
    VarietyStock::firstOrCreate(['variety' => $variety], ['actual_qty' => 10000]);

    $dealer = Dealer::factory()->create();
    $farmer = Farmer::factory()->create(['dealer_id' => $dealer->id]);
    $booking = Booking::factory()->create([
        'dealer_id' => $dealer->id,
        'farmer_id' => $farmer->id,
        'variety' => $variety,
        'approval_status' => 'approved',
        'payment_status' => 'completed',
        'plant_qty' => $qty,
        'plant_rate' => 12,
    ]);
    app(StockReservationService::class)->reserve($booking);

    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-'.str()->random(4).'-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-TEST-'.str()->random(6), 'plan_date' => now()->toDateString(), 'approval_status' => 'approved']);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id]);
    $item = DispatchPlanItem::create(['vehicle_assignment_id' => $assignment->id, 'booking_id' => $booking->id, 'dispatch_qty' => $qty, 'loaded_at' => now()]);

    $dispatch = Dispatch::create([
        'dispatch_no' => 'DIS-TEST-'.str()->random(6),
        'vehicle_assignment_id' => $assignment->id,
        'vehicle_id' => $vehicle->id,
        'dispatch_date' => now()->toDateString(),
        'status' => 'dispatched',
        'challan_no' => 'CHN-TEST-'.str()->random(6),
    ]);

    DispatchLine::create([
        'dispatch_id' => $dispatch->id,
        'dispatch_plan_item_id' => $item->id,
        'booking_id' => $booking->id,
        'dealer_id' => $dealer->id,
        'farmer_id' => $farmer->id,
        'dispatch_qty' => $qty,
        'extra_qty' => 0,
        'remaining_qty' => 0,
    ]);

    return $dispatch->fresh('lines');
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

test('marking delivered with no rejection input defaults every line to fully accepted', function () {
    $admin = rejectionTestUser('admin');
    $dispatch = makeDispatchedShipment(400);
    $line = $dispatch->lines->first();

    $this->actingAs($admin)->post(route('dispatches.deliver', $dispatch))->assertRedirect();

    $fresh = $line->fresh();
    expect($fresh->accepted_qty)->toBe(400);
    expect($fresh->rejected_qty)->toBe(0);
    expect($dispatch->fresh()->status)->toBe('delivered');
});

test('a farmer rejecting part of a line does not block marking the dispatch delivered', function () {
    $admin = rejectionTestUser('admin');
    $dispatch = makeDispatchedShipment(400);
    $line = $dispatch->lines->first();

    $response = $this->actingAs($admin)->post(route('dispatches.deliver', $dispatch), [
        'lines' => [
            $line->id => ['accepted_qty' => 350, 'rejected_qty' => 50, 'rejection_reason' => 'Some plants damaged in transit'],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $fresh = $line->fresh();
    expect($fresh->accepted_qty)->toBe(350);
    expect($fresh->rejected_qty)->toBe(50);
    expect($fresh->rejection_reason)->toBe('Some plants damaged in transit');
    expect($dispatch->fresh()->status)->toBe('delivered');
});

test('completing a partially rejected dispatch only converts the accepted qty to stock and frees the rejected qty', function () {
    $admin = rejectionTestUser('admin');
    $dispatch = makeDispatchedShipment(400, 'G9');
    $line = $dispatch->lines->first();
    $booking = $line->booking;

    $this->actingAs($admin)->post(route('dispatches.deliver', $dispatch), [
        'lines' => [$line->id => ['accepted_qty' => 350, 'rejected_qty' => 50]],
    ])->assertRedirect();

    $stockBefore = VarietyStock::where('variety', 'G9')->first();
    $actualBefore = $stockBefore->actual_qty;

    $this->actingAs($admin)->post(route('dispatches.complete', $dispatch))->assertRedirect();

    $stock = VarietyStock::where('variety', 'G9')->first();
    expect($stock->actual_qty)->toBe($actualBefore - 350); // only the accepted 350 actually leaves stock

    $reservation = StockReservation::where('booking_id', $booking->id)->first();
    expect($reservation->status)->toBe('converted');
    expect($reservation->reserved_qty)->toBe(0);
    expect($reservation->converted_qty)->toBe(350);
    expect($reservation->released_qty)->toBe(50);

    // The 50 rejected plants are free again — not counted as reserved.
    expect($stock->reservedQty())->toBe(0);
});

test('invoicing a partially rejected dispatch only bills the accepted quantity', function () {
    $admin = rejectionTestUser('admin');
    $dispatch = makeDispatchedShipment(400, 'G9');
    $line = $dispatch->lines->first();
    $dealer = $line->dealer;

    $this->actingAs($admin)->post(route('dispatches.deliver', $dispatch), [
        'lines' => [$line->id => ['accepted_qty' => 350, 'rejected_qty' => 50]],
    ])->assertRedirect();

    $this->actingAs($admin)->post(route('dispatches.complete', $dispatch))->assertRedirect();

    $this->actingAs($admin)->post(route('invoices.store'), [
        'dispatch_id' => $dispatch->id,
        'dealer_id' => $dealer->id,
        'invoice_date' => now()->toDateString(),
    ])->assertRedirect();

    $invoice = Invoice::first();
    expect((float) $invoice->subtotal)->toBe(350 * 12.0); // billed for accepted qty only, not the full 400 shipped
    expect($invoice->lines->first()->qty)->toBe(350);
});

test('accepted plus rejected must equal the shipped total for a line', function () {
    $admin = rejectionTestUser('admin');
    $dispatch = makeDispatchedShipment(400);
    $line = $dispatch->lines->first();

    $response = $this->actingAs($admin)->post(route('dispatches.deliver', $dispatch), [
        'lines' => [$line->id => ['accepted_qty' => 350, 'rejected_qty' => 100]], // 450 != 400 shipped
    ]);

    $response->assertSessionHasErrors(["lines.{$line->id}.accepted_qty"]);
    expect($dispatch->fresh()->status)->toBe('dispatched');
});
