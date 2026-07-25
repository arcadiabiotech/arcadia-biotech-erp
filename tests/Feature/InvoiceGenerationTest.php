<?php

use App\Models\Booking;
use App\Models\Dealer;
use App\Models\Dispatch;
use App\Models\DispatchLine;
use App\Models\DispatchPlan;
use App\Models\DispatchPlanItem;
use App\Models\Farmer;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;

function invoiceTestUser(string $roleName): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(['role_id' => $role->id]);
}

function invoiceTestBooking(Dealer $dealer): Booking
{
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
 * A Completed Dispatch header with one DispatchLine per [dealer, booking,
 * qty] triple, wired through the required DispatchPlan/VehicleAssignment/
 * DispatchPlanItem chain. Built directly via Eloquent — the Dispatch
 * creation flow itself is covered by DispatchCreationTest, so these tests
 * can focus purely on Invoice generation against an already-completed
 * multi-dealer shipment.
 */
function makeCompletedDispatch(array $dealerBookingQty): Dispatch
{
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-'.str()->random(4).'-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-TEST-'.str()->random(6), 'plan_date' => now()->toDateString(), 'approval_status' => 'approved']);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id]);
    $dispatch = Dispatch::create(['dispatch_no' => 'DIS-TEST-'.str()->random(6), 'vehicle_assignment_id' => $assignment->id, 'dispatch_date' => now()->toDateString(), 'status' => 'completed']);

    foreach ($dealerBookingQty as [$dealer, $booking, $qty]) {
        $item = DispatchPlanItem::create(['vehicle_assignment_id' => $assignment->id, 'booking_id' => $booking->id, 'dispatch_qty' => $qty, 'loaded_at' => now()]);

        DispatchLine::create([
            'dispatch_id' => $dispatch->id,
            'dispatch_plan_item_id' => $item->id,
            'booking_id' => $booking->id,
            'dealer_id' => $dealer->id,
            'farmer_id' => $booking->farmer_id,
            'dispatch_qty' => $qty,
            'extra_qty' => 0,
            'remaining_qty' => 0,
        ]);
    }

    return $dispatch->fresh('lines');
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

test('a multi-dealer completed dispatch offers one eligible dispatch/dealer pair per dealer', function () {
    $admin = invoiceTestUser('admin');
    $dealerA = Dealer::factory()->create();
    $dealerB = Dealer::factory()->create();
    $dispatch = makeCompletedDispatch([
        [$dealerA, invoiceTestBooking($dealerA), 200],
        [$dealerB, invoiceTestBooking($dealerB), 150],
    ]);

    $response = $this->actingAs($admin)->get(route('invoices.create'));

    $response->assertOk();
    $response->assertSee($dealerA->dealer_name);
    $response->assertSee($dealerB->dealer_name);
});

test('creating an invoice for one dealer only covers that dealer lines and computes the correct subtotal', function () {
    $admin = invoiceTestUser('admin');
    $dealerA = Dealer::factory()->create();
    $dealerB = Dealer::factory()->create();
    $bookingA = invoiceTestBooking($dealerA);
    $bookingB = invoiceTestBooking($dealerB);
    $dispatch = makeCompletedDispatch([
        [$dealerA, $bookingA, 200],
        [$dealerB, $bookingB, 150],
    ]);

    $response = $this->actingAs($admin)->post(route('invoices.store'), [
        'dispatch_id' => $dispatch->id,
        'dealer_id' => $dealerA->id,
        'invoice_date' => now()->toDateString(),
    ]);

    $response->assertRedirect();

    $invoice = Invoice::first();
    expect($invoice)->not->toBeNull();
    expect($invoice->dealer_id)->toBe($dealerA->id);
    expect((float) $invoice->subtotal)->toBe(2400.0); // 200 plants * 12
    expect($invoice->lines)->toHaveCount(1);
    expect($invoice->lines->first()->booking_id)->toBe($bookingA->id);

    // Dealer B's pair on the same dispatch is still eligible — a
    // multi-dealer Dispatch is invoiced per dealer, independently.
    $this->actingAs($admin)->get(route('invoices.create'))->assertSee($dealerB->dealer_name);
});

test('the same dispatch can be invoiced a second time for its other dealer, but not the same dealer twice', function () {
    $admin = invoiceTestUser('admin');
    $dealerA = Dealer::factory()->create();
    $dealerB = Dealer::factory()->create();
    $dispatch = makeCompletedDispatch([
        [$dealerA, invoiceTestBooking($dealerA), 200],
        [$dealerB, invoiceTestBooking($dealerB), 150],
    ]);

    $this->actingAs($admin)->post(route('invoices.store'), [
        'dispatch_id' => $dispatch->id,
        'dealer_id' => $dealerA->id,
        'invoice_date' => now()->toDateString(),
    ])->assertRedirect();

    $this->actingAs($admin)->post(route('invoices.store'), [
        'dispatch_id' => $dispatch->id,
        'dealer_id' => $dealerB->id,
        'invoice_date' => now()->toDateString(),
    ])->assertRedirect();

    expect(Invoice::count())->toBe(2);

    // Dealer A already has an invoice for this dispatch — rejected.
    $this->actingAs($admin)->post(route('invoices.store'), [
        'dispatch_id' => $dispatch->id,
        'dealer_id' => $dealerA->id,
        'invoice_date' => now()->toDateString(),
    ])->assertSessionHasErrors(['dispatch_id']);

    expect(Invoice::count())->toBe(2);
});

test('generating an invoice debits the ledger once for that dealer with a null farmer_id, and updates each covered booking', function () {
    $admin = invoiceTestUser('admin');
    $dealer = Dealer::factory()->create();
    $bookingA = invoiceTestBooking($dealer);
    $bookingB = invoiceTestBooking($dealer);
    $dispatch = makeCompletedDispatch([
        [$dealer, $bookingA, 200],
        [$dealer, $bookingB, 150],
    ]);

    $this->actingAs($admin)->post(route('invoices.store'), [
        'dispatch_id' => $dispatch->id,
        'dealer_id' => $dealer->id,
        'invoice_date' => now()->toDateString(),
    ])->assertRedirect();

    $invoice = Invoice::first();
    expect($invoice->lines)->toHaveCount(2);
    expect((float) $invoice->subtotal)->toBe((float) ((200 * 12) + (150 * 12)));

    $this->actingAs($admin)->post(route('invoices.generate', $invoice))->assertRedirect();

    $invoice = $invoice->fresh();
    expect($invoice->status)->toBe('generated');

    $ledgerEntries = LedgerEntry::where('module', 'invoices')->where('record_id', $invoice->id)->get();
    expect($ledgerEntries)->toHaveCount(1);
    expect($ledgerEntries->first()->dealer_id)->toBe($dealer->id);
    expect($ledgerEntries->first()->farmer_id)->toBeNull();
    expect((float) $ledgerEntries->first()->debit)->toBe((float) $invoice->grand_total);

    expect($bookingA->fresh()->invoice_status)->toBe('generated');
    expect($bookingB->fresh()->invoice_status)->toBe('generated');
});

test('only admin/super-admin can access invoices — accounts is excluded', function () {
    $accountsUser = invoiceTestUser('accounts');

    $this->actingAs($accountsUser)->get(route('invoices.index'))->assertForbidden();
    $this->actingAs($accountsUser)->get(route('invoices.create'))->assertForbidden();
});
