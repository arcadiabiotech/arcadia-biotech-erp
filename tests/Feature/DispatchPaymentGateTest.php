<?php

use App\Models\Booking;
use App\Models\Dealer;
use App\Models\DispatchPlan;
use App\Models\Farmer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * A booking is now committed to a Dispatch Plan at plan creation/edit time
 * (Dealer -> Booking -> Dispatch Quantity), not later when it's placed on a
 * vehicle — so the payment gate that used to live in
 * DispatchPlanItemController::store() now applies one step earlier, in
 * DispatchPlanStoreRequest/DispatchPlanUpdateRequest (backed by
 * DispatchPlanningService::activeBookingsForPlanning()).
 */
function paymentGateUser(string $roleName, array $extraPermissions = []): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    if ($extraPermissions) {
        $permissionIds = Permission::whereIn('name', $extraPermissions)->pluck('id');
        $role->permissions()->syncWithoutDetaching($permissionIds);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

function makeBookingWithPayment(string $paymentStatus): Booking
{
    $dealer = Dealer::factory()->create();
    $farmer = Farmer::factory()->create(['dealer_id' => $dealer->id]);

    return Booking::factory()->create([
        'dealer_id' => $dealer->id,
        'farmer_id' => $farmer->id,
        'approval_status' => 'approved',
        'payment_status' => $paymentStatus,
        'plant_qty' => 400,
        'plant_rate' => 12,
    ]);
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

test('a fully paid booking is plannable by a normal dispatch planner', function () {
    $planner = paymentGateUser('dispatch-planner');
    $booking = makeBookingWithPayment('completed');

    $response = $this->actingAs($planner)->post(route('dispatch-plans.store'), [
        'plan_date' => now()->toDateString(),
        'farmer_estimates' => [
            ['booking_id' => $booking->id, 'dispatch_qty' => 400],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('dispatch_plan_farmer_estimates', ['booking_id' => $booking->id]);
});

test('a partially paid booking is rejected for a planner without the special permission', function () {
    $planner = paymentGateUser('dispatch-planner');
    $booking = makeBookingWithPayment('partial');

    $response = $this->actingAs($planner)->post(route('dispatch-plans.store'), [
        'plan_date' => now()->toDateString(),
        'farmer_estimates' => [
            ['booking_id' => $booking->id, 'dispatch_qty' => 400],
        ],
    ]);

    $response->assertSessionHasErrors(['farmer_estimates.0.booking_id']);
    $this->assertDatabaseMissing('dispatch_plan_farmer_estimates', ['booking_id' => $booking->id]);
});

test('a partially paid booking is plannable by a planner granted dispatch.partial-payment', function () {
    $planner = paymentGateUser('dispatch-planner', ['dispatch.partial-payment']);
    $booking = makeBookingWithPayment('partial');

    $response = $this->actingAs($planner)->post(route('dispatch-plans.store'), [
        'plan_date' => now()->toDateString(),
        'farmer_estimates' => [
            ['booking_id' => $booking->id, 'dispatch_qty' => 400],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('dispatch_plan_farmer_estimates', ['booking_id' => $booking->id]);
});

test('an unpaid (pending) booking cannot be planned even with the special permission', function () {
    $planner = paymentGateUser('dispatch-planner', ['dispatch.partial-payment']);
    $booking = makeBookingWithPayment('pending');

    $response = $this->actingAs($planner)->post(route('dispatch-plans.store'), [
        'plan_date' => now()->toDateString(),
        'farmer_estimates' => [
            ['booking_id' => $booking->id, 'dispatch_qty' => 400],
        ],
    ]);

    $response->assertSessionHasErrors(['farmer_estimates.0.booking_id']);
    $this->assertDatabaseMissing('dispatch_plan_farmer_estimates', ['booking_id' => $booking->id]);
});

test('an unpaid (pending) booking can be planned by a planner granted the special dispatch.no-payment permission', function () {
    $planner = paymentGateUser('dispatch-planner', ['dispatch.no-payment']);
    $booking = makeBookingWithPayment('pending');

    $response = $this->actingAs($planner)->post(route('dispatch-plans.store'), [
        'plan_date' => now()->toDateString(),
        'farmer_estimates' => [
            ['booking_id' => $booking->id, 'dispatch_qty' => 400],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('dispatch_plan_farmer_estimates', ['booking_id' => $booking->id]);
});

test('dispatch.no-payment also covers partial payment, not just fully unpaid', function () {
    $planner = paymentGateUser('dispatch-planner', ['dispatch.no-payment']);
    $booking = makeBookingWithPayment('partial');

    $response = $this->actingAs($planner)->post(route('dispatch-plans.store'), [
        'plan_date' => now()->toDateString(),
        'farmer_estimates' => [
            ['booking_id' => $booking->id, 'dispatch_qty' => 400],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('dispatch_plan_farmer_estimates', ['booking_id' => $booking->id]);
});

test('admin already has dispatch.no-payment via the role matrix and can plan a fully unpaid booking', function () {
    $admin = paymentGateUser('admin');
    $booking = makeBookingWithPayment('pending');

    $response = $this->actingAs($admin)->post(route('dispatch-plans.store'), [
        'plan_date' => now()->toDateString(),
        'farmer_estimates' => [
            ['booking_id' => $booking->id, 'dispatch_qty' => 400],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('dispatch_plan_farmer_estimates', ['booking_id' => $booking->id]);
});

test('super admin already has the permission and can plan a partially paid booking', function () {
    $superAdmin = paymentGateUser('super-admin');
    $booking = makeBookingWithPayment('partial');

    $response = $this->actingAs($superAdmin)->post(route('dispatch-plans.store'), [
        'plan_date' => now()->toDateString(),
        'farmer_estimates' => [
            ['booking_id' => $booking->id, 'dispatch_qty' => 400],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('dispatch_plan_farmer_estimates', ['booking_id' => $booking->id]);
});

test('a partially paid booking without the permission does not even appear in the new plan form dropdown', function () {
    $planner = paymentGateUser('dispatch-planner');
    $partial = makeBookingWithPayment('partial');
    $completed = makeBookingWithPayment('completed');

    $response = $this->actingAs($planner)->get(route('dispatch-plans.create'));

    $response->assertOk();
    $response->assertSee($completed->booking_no);
    $response->assertDontSee($partial->booking_no);
});

test('a partially paid booking already committed to a plan stays visible and editable when editing that plan', function () {
    $planner = paymentGateUser('dispatch-planner');
    $booking = makeBookingWithPayment('partial');
    $plan = DispatchPlan::create(['plan_no' => 'PLN-2026-PAYGATE1', 'plan_date' => now()->toDateString(), 'created_by' => $planner->id]);
    $plan->farmerEstimates()->create(['dealer_id' => $booking->dealer_id, 'farmer_id' => $booking->farmer_id, 'booking_id' => $booking->id, 'plant_quantity' => 400, 'dispatch_type' => 'full']);

    // Editing the plan without touching this row must not fail even though
    // this planner still lacks dispatch.partial-payment — the booking was
    // already legitimately committed.
    $response = $this->actingAs($planner)->put(route('dispatch-plans.update', $plan), [
        'plan_date' => now()->toDateString(),
        'farmer_estimates' => [
            ['booking_id' => $booking->id, 'dispatch_qty' => 400],
        ],
    ]);

    $response->assertRedirect(route('dispatch-plans.show', $plan));
    expect($plan->fresh()->farmerEstimates()->where('booking_id', $booking->id)->exists())->toBeTrue();
});
