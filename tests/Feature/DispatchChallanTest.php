<?php

use App\Mail\DeliveryChallanMail;
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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

function challanTestUser(string $roleName): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(['role_id' => $role->id]);
}

/**
 * Builds a Dispatch header + one DispatchLine, wrapped in the synthetic
 * DispatchPlan/VehicleAssignment/DispatchPlanItem chain every Dispatch now
 * has to trace back to. Header-only keys (status, challan_no, vehicle_id,
 * created_by, ...) go on the Dispatch; line-only keys (dispatch_qty,
 * extra_qty, qty_per_crate, ...) go on the single DispatchLine.
 */
function makeShipment(array $overrides = []): Dispatch
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

    $lineKeys = ['dispatch_qty', 'extra_qty', 'qty_per_crate', 'crates_returned', 'batch_number', 'plant_age', 'remaining_qty'];
    $lineOverrides = array_intersect_key($overrides, array_flip($lineKeys));
    $headerOverrides = array_diff_key($overrides, $lineOverrides);

    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-'.str()->random(4).'-TEST', 'vehicle_type' => 'Truck', 'status' => true]);

    $plan = DispatchPlan::create(['plan_no' => 'PLN-TEST-'.str()->random(6), 'plan_date' => now()->toDateString()]);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $headerOverrides['vehicle_id'] ?? $vehicle->id]);
    $item = DispatchPlanItem::create(['vehicle_assignment_id' => $assignment->id, 'booking_id' => $booking->id, 'dispatch_qty' => $lineOverrides['dispatch_qty'] ?? 400]);

    $dispatch = Dispatch::create(array_merge([
        'dispatch_no' => 'DIS-TEST-'.str()->random(6),
        'vehicle_assignment_id' => $assignment->id,
        'dispatch_date' => now()->toDateString(),
        'status' => 'draft',
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

test('crate count and total quantity are derived from dispatch, extra and per-crate qty', function () {
    $dispatch = makeShipment(['dispatch_qty' => 400, 'extra_qty' => 20, 'qty_per_crate' => 40]);

    expect($dispatch->total_qty)->toBe(420);
    expect($dispatch->lines->first()->crate_count)->toBe(10.5);
});

test('starting loading (Vehicle Loaded) issues a challan number that was not present before', function () {
    $admin = challanTestUser('admin');
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-01-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $dispatch = makeShipment(['status' => 'pending', 'vehicle_id' => $vehicle->id, 'created_by' => $admin->id]);

    expect($dispatch->challan_no)->toBeNull();

    $this->actingAs($admin)->post(route('dispatches.start-loading', $dispatch))->assertRedirect();

    expect($dispatch->fresh()->status)->toBe('loading');
    expect($dispatch->fresh()->challan_no)->not->toBeNull();
    expect($dispatch->fresh()->loaded_at)->not->toBeNull();
});

test('vehicle out (Dispatch Vehicle) does not change the challan number issued at loading', function () {
    $admin = challanTestUser('admin');
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-23-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $dispatch = makeShipment(['status' => 'pending', 'vehicle_id' => $vehicle->id, 'created_by' => $admin->id]);

    $this->actingAs($admin)->post(route('dispatches.start-loading', $dispatch));
    $challanNo = $dispatch->fresh()->challan_no;
    expect($challanNo)->not->toBeNull();

    $this->actingAs($admin)->post(route('dispatches.vehicle-out', $dispatch))->assertRedirect();

    expect($dispatch->fresh()->status)->toBe('dispatched');
    expect($dispatch->fresh()->challan_no)->toBe($challanNo);
    expect($dispatch->fresh()->dispatched_at)->not->toBeNull();
});

test('admin can view the show, print and pdf pages for a dispatched shipment', function () {
    $admin = challanTestUser('admin');
    $dispatch = makeShipment(['status' => 'dispatched', 'challan_no' => 'CHN-TEST-000001', 'created_by' => $admin->id]);

    $this->actingAs($admin)->get(route('dispatches.show', $dispatch))->assertOk();
    $this->actingAs($admin)->get(route('dispatches.print', $dispatch))->assertOk()->assertSee('DELIVERY CHALLAN')->assertSee('CHN-TEST-000001');
    $this->actingAs($admin)->get(route('dispatches.pdf', $dispatch))->assertOk();
});

test('the dispatch index only shows a View Challan link once a challan number is issued', function () {
    $admin = challanTestUser('admin');
    $withChallan = makeShipment(['status' => 'dispatched', 'challan_no' => 'CHN-TEST-000009', 'created_by' => $admin->id]);
    $withoutChallan = makeShipment(['status' => 'draft', 'created_by' => $admin->id]);

    $response = $this->actingAs($admin)->get(route('dispatches.index'));

    $response->assertOk();
    $response->assertSee(route('dispatches.print', $withChallan));
    $response->assertDontSee(route('dispatches.print', $withoutChallan));
});

test('emailing the challan sends a mail with the challan number in the subject', function () {
    Mail::fake();

    $admin = challanTestUser('admin');
    $dispatch = makeShipment(['status' => 'dispatched', 'challan_no' => 'CHN-TEST-000002', 'created_by' => $admin->id]);

    $this->actingAs($admin)
        ->post(route('dispatches.email-challan', $dispatch), ['email' => 'dealer@example.com'])
        ->assertSessionHasNoErrors();

    Mail::assertSent(DeliveryChallanMail::class, fn ($mail) => $mail->hasTo('dealer@example.com')
        && $mail->dispatch->is($dispatch));
});

test('the public signed challan link works, but an unsigned request to the same URL is rejected', function () {
    $dispatch = makeShipment(['status' => 'dispatched', 'challan_no' => 'CHN-TEST-000003']);

    $signedUrl = URL::temporarySignedRoute('challans.public', now()->addDay(), ['dispatch' => $dispatch->id]);
    $this->get($signedUrl)->assertOk()->assertSee('CHN-TEST-000003');

    $this->get(route('challans.public', $dispatch))->assertForbidden();
});
