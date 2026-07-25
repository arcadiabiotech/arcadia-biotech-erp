<?php

use App\Models\Booking;
use App\Models\Challan;
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
use Illuminate\Support\Facades\URL;

function challanGenTestUser(string $roleName): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(['role_id' => $role->id]);
}

/**
 * A Dispatch in 'pending' status with one DispatchLine per [dealer, farmer,
 * qty] triple — ready for the start-loading test to push it to 'loading'
 * and trigger ChallanService::generateForDispatch() (challan generation now
 * happens at "Vehicle Loaded"/start-loading, not at vehicle-out).
 */
function challanGenDispatch(array $farmerQtyByDealer): Dispatch
{
    $vehicle = Vehicle::create(['vehicle_no' => 'GJ-'.str()->random(4).'-TEST', 'vehicle_type' => 'Truck', 'status' => true]);
    $plan = DispatchPlan::create(['plan_no' => 'PLN-TEST-'.str()->random(6), 'plan_date' => now()->toDateString(), 'route' => 'Test Route']);
    $assignment = VehicleAssignment::create(['dispatch_plan_id' => $plan->id, 'vehicle_id' => $vehicle->id]);

    $dispatch = Dispatch::create([
        'dispatch_no' => 'DIS-TEST-'.str()->random(6),
        'vehicle_assignment_id' => $assignment->id,
        'vehicle_id' => $vehicle->id,
        'dispatch_date' => now()->toDateString(),
        'status' => 'pending',
    ]);

    foreach ($farmerQtyByDealer as $dealerName => $farmerQtys) {
        $dealer = Dealer::factory()->create(['dealer_name' => $dealerName]);

        foreach ($farmerQtys as $farmerName => $qty) {
            $farmer = Farmer::factory()->create(['dealer_id' => $dealer->id, 'farmer_name' => $farmerName]);
            $booking = Booking::factory()->create(['dealer_id' => $dealer->id, 'farmer_id' => $farmer->id, 'approval_status' => 'approved', 'plant_qty' => $qty, 'plant_rate' => 10]);
            $item = DispatchPlanItem::create(['vehicle_assignment_id' => $assignment->id, 'booking_id' => $booking->id, 'dispatch_qty' => $qty]);

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
        }
    }

    return $dispatch->fresh('lines');
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

test('a single-farmer dealer gets no dealer/farmer challan records — the existing combined challan already covers it', function () {
    $admin = challanGenTestUser('admin');
    $dispatch = challanGenDispatch(['ABC Agro' => ['Farmer One' => 2000]]);

    $this->actingAs($admin)->post(route('dispatches.start-loading', $dispatch))->assertRedirect();

    expect($dispatch->fresh()->challan_no)->not->toBeNull();
    expect(Challan::where('dispatch_id', $dispatch->id)->count())->toBe(0);
});

test('a dealer with 2+ farmers gets one Master Dealer Challan plus one Farmer Challan per farmer, correctly numbered', function () {
    $admin = challanGenTestUser('admin');
    $dispatch = challanGenDispatch([
        'ABC Agro' => ['Farmer One' => 2000, 'Farmer Two' => 1500, 'Farmer Three' => 2500],
    ]);

    $this->actingAs($admin)->post(route('dispatches.start-loading', $dispatch))->assertRedirect();

    $dealerChallan = Challan::where('dispatch_id', $dispatch->id)->where('type', Challan::TYPE_DEALER)->first();
    expect($dealerChallan)->not->toBeNull();
    expect($dealerChallan->challan_no)->toMatch('/^DC-\d{4}-\d{6}$/');

    $farmerChallans = Challan::where('dispatch_id', $dispatch->id)->where('type', Challan::TYPE_FARMER)->orderBy('challan_no')->get();
    expect($farmerChallans)->toHaveCount(3);

    $expectedSuffix = substr($dealerChallan->challan_no, 3); // "YYYY-NNNNNN"
    foreach ($farmerChallans as $index => $farmerChallan) {
        expect($farmerChallan->challan_no)->toBe('FC-'.$expectedSuffix.'-'.str_pad($index + 1, 2, '0', STR_PAD_LEFT));
        expect($farmerChallan->parent_challan_id)->toBe($dealerChallan->id);
        expect($farmerChallan->dealer_id)->toBe($dealerChallan->dealer_id);
    }

    // Farmers are ordered alphabetically by name.
    expect($farmerChallans->pluck('farmer_id'))->toEqual(
        Farmer::whereIn('id', $farmerChallans->pluck('farmer_id'))->orderBy('farmer_name')->pluck('id')
    );
});

test("a farmer challan shows only that farmer's own quantity, never the dealer total", function () {
    $admin = challanGenTestUser('admin');
    $dispatch = challanGenDispatch([
        'ABC Agro' => ['Farmer One' => 2000, 'Farmer Two' => 1500, 'Farmer Three' => 2500],
    ]);
    $this->actingAs($admin)->post(route('dispatches.start-loading', $dispatch));

    $farmerOneChallan = Challan::where('dispatch_id', $dispatch->id)->where('type', Challan::TYPE_FARMER)
        ->whereHas('farmer', fn ($q) => $q->where('farmer_name', 'Farmer One'))->first();

    expect($farmerOneChallan->lines()->sum('dispatch_qty'))->toBe(2000);

    $response = $this->actingAs($admin)->get(route('challan-docs.show', $farmerOneChallan));
    $response->assertOk()->assertSee('2,000')->assertDontSee('6,000');
});

test('a dispatch spanning two dealers, each with 2+ farmers, gets one dealer challan per dealer', function () {
    $admin = challanGenTestUser('admin');
    $dispatch = challanGenDispatch([
        'Dealer A' => ['A Farmer One' => 100, 'A Farmer Two' => 200],
        'Dealer B' => ['B Farmer One' => 300, 'B Farmer Two' => 400],
    ]);

    $this->actingAs($admin)->post(route('dispatches.start-loading', $dispatch));

    expect(Challan::where('dispatch_id', $dispatch->id)->where('type', Challan::TYPE_DEALER)->count())->toBe(2);
    expect(Challan::where('dispatch_id', $dispatch->id)->where('type', Challan::TYPE_FARMER)->count())->toBe(4);
});

test('dealer challan print, pdf, and the combined dispatch-level print/pdf/zip/print-all all render successfully', function () {
    $admin = challanGenTestUser('admin');
    $dispatch = challanGenDispatch([
        'ABC Agro' => ['Farmer One' => 2000, 'Farmer Two' => 1500],
    ]);
    $this->actingAs($admin)->post(route('dispatches.start-loading', $dispatch));

    $dealerChallan = Challan::where('dispatch_id', $dispatch->id)->where('type', Challan::TYPE_DEALER)->first();

    $this->actingAs($admin)->get(route('challan-docs.show', $dealerChallan))->assertOk()->assertSee($dealerChallan->challan_no);
    $this->actingAs($admin)->get(route('challan-docs.pdf', $dealerChallan))->assertOk();
    $this->actingAs($admin)->get(route('dispatches.challans.farmers-print', $dispatch))->assertOk();
    $this->actingAs($admin)->get(route('dispatches.challans.farmers-pdf', $dispatch))->assertOk();
    $this->actingAs($admin)->get(route('dispatches.challans.print-all', $dispatch))->assertOk();
    $this->actingAs($admin)->get(route('dispatches.challans.zip', $dispatch))->assertOk();
});

test('the public signed challan-docs link works, but an unsigned request is rejected', function () {
    $admin = challanGenTestUser('admin');
    $dispatch = challanGenDispatch(['ABC Agro' => ['Farmer One' => 2000, 'Farmer Two' => 1500]]);
    $this->actingAs($admin)->post(route('dispatches.start-loading', $dispatch));

    $dealerChallan = Challan::where('dispatch_id', $dispatch->id)->where('type', Challan::TYPE_DEALER)->first();

    $signedUrl = URL::temporarySignedRoute('challan-docs.public', now()->addDay(), ['challan' => $dealerChallan->id]);
    $this->get($signedUrl)->assertOk()->assertSee($dealerChallan->challan_no);

    $this->get(route('challan-docs.public', $dealerChallan))->assertForbidden();
});

test('the dispatch show page lists dealer and farmer challans with print/pdf/whatsapp links once generated', function () {
    $admin = challanGenTestUser('admin');
    $dispatch = challanGenDispatch(['ABC Agro' => ['Farmer One' => 2000, 'Farmer Two' => 1500]]);
    $this->actingAs($admin)->post(route('dispatches.start-loading', $dispatch));

    $dealerChallan = Challan::where('dispatch_id', $dispatch->id)->where('type', Challan::TYPE_DEALER)->first();
    $farmerChallans = Challan::where('dispatch_id', $dispatch->id)->where('type', Challan::TYPE_FARMER)->get();

    $response = $this->actingAs($admin)->get(route('dispatches.show', $dispatch));

    $response->assertOk();
    $response->assertSee($dealerChallan->challan_no);
    foreach ($farmerChallans as $farmerChallan) {
        $response->assertSee($farmerChallan->challan_no);
    }
});
