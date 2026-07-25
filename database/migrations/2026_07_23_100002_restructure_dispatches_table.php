<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dispatch becomes a vehicle-trip header; everything booking-specific
     * moves to dispatch_lines. vehicle_assignment_id is left nullable at
     * the DB level (doctrine/dbal isn't installed, so a later "make it
     * required" alter isn't available) — the "must originate from Dispatch
     * Planning" rule is enforced in DispatchStoreRequest instead.
     *
     * The handful of existing dev-only dispatches (no real production
     * history — confirmed 0 invoices/payments/ledger entries exist) are
     * backfilled into a synthetic DispatchPlan -> VehicleAssignment ->
     * DispatchPlanItem each, so they still trace back to a planning record
     * like every dispatch created from now on must.
     */
    public function up(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->foreignId('vehicle_assignment_id')->nullable()->after('id')->constrained('vehicle_assignments')->restrictOnDelete();
        });

        foreach (DB::table('dispatches')->get() as $dispatch) {
            $planId = DB::table('dispatch_plans')->insertGetId([
                'plan_no' => 'PLN-LEGACY-'.$dispatch->id,
                'plan_date' => $dispatch->dispatch_date,
                'approval_status' => 'approved',
                'approved_by' => $dispatch->created_by,
                'approved_at' => now(),
                'created_by' => $dispatch->created_by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $assignmentId = DB::table('vehicle_assignments')->insertGetId([
                'dispatch_plan_id' => $planId,
                'vehicle_id' => $dispatch->vehicle_id,
                'driver_name' => $dispatch->driver_name,
                'driver_mobile' => $dispatch->driver_mobile,
                'created_by' => $dispatch->created_by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $itemId = DB::table('dispatch_plan_items')->insertGetId([
                'vehicle_assignment_id' => $assignmentId,
                'booking_id' => $dispatch->booking_id,
                'dispatch_qty' => $dispatch->dispatch_qty,
                'loaded_at' => now(),
                'created_by' => $dispatch->created_by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('dispatch_lines')->insert([
                'dispatch_id' => $dispatch->id,
                'dispatch_plan_item_id' => $itemId,
                'booking_id' => $dispatch->booking_id,
                'dealer_id' => $dispatch->dealer_id,
                'farmer_id' => $dispatch->farmer_id,
                'dispatch_qty' => $dispatch->dispatch_qty,
                'extra_qty' => $dispatch->extra_qty ?? 0,
                'qty_per_crate' => $dispatch->qty_per_crate,
                'crates_returned' => $dispatch->crates_returned,
                'batch_number' => $dispatch->batch_number,
                'plant_age' => $dispatch->plant_age,
                'remaining_qty' => $dispatch->remaining_qty ?? 0,
                'created_by' => $dispatch->created_by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('dispatches')->where('id', $dispatch->id)->update(['vehicle_assignment_id' => $assignmentId]);
        }

        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropForeign(['dealer_id']);
            $table->dropForeign(['farmer_id']);
            $table->dropColumn([
                'booking_id', 'dealer_id', 'farmer_id',
                'dispatch_qty', 'extra_qty', 'qty_per_crate', 'crates_returned',
                'batch_number', 'plant_age', 'remaining_qty', 'invoice_no',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('dealer_id')->nullable()->constrained('dealers')->nullOnDelete();
            $table->foreignId('farmer_id')->nullable()->constrained('farmers')->nullOnDelete();
            $table->unsignedInteger('dispatch_qty')->nullable();
            $table->unsignedInteger('extra_qty')->nullable();
            $table->unsignedInteger('qty_per_crate')->nullable();
            $table->decimal('crates_returned', 10, 2)->nullable();
            $table->string('batch_number')->nullable();
            $table->string('plant_age')->nullable();
            $table->unsignedInteger('remaining_qty')->nullable();
            $table->string('invoice_no')->nullable();
        });

        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_assignment_id');
        });
    }
};
