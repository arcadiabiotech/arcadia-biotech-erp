<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Step 7A "Vehicle KM & Transport Cost": every Dispatch stores its own
     * odometer/cost snapshot, independent of the VehicleAssignment it came
     * from (a VehicleAssignment can spawn more than one Dispatch over time).
     * odometer_start/cost_per_km/driver_allowance are copied in at creation
     * (DispatchService::createFromAssignment); the rest are filled in by
     * DispatchService::markVehicleReturned() once the vehicle physically
     * returns. All nullable — historical dispatches and any dispatch whose
     * assignment never captured start_km stay null rather than 0, so
     * reports/dashboards can tell "not tracked" apart from "zero cost".
     */
    public function up(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->unsignedInteger('odometer_start')->nullable()->after('vehicle_returned_by');
            $table->unsignedInteger('odometer_end')->nullable()->after('odometer_start');
            $table->unsignedInteger('total_km')->nullable()->after('odometer_end');
            $table->decimal('cost_per_km', 10, 2)->nullable()->after('total_km');
            $table->decimal('transport_cost', 12, 2)->nullable()->after('cost_per_km');
            $table->decimal('driver_allowance', 10, 2)->nullable()->after('transport_cost');
            $table->decimal('toll_charges', 10, 2)->nullable()->after('driver_allowance');
            $table->decimal('other_expenses', 10, 2)->nullable()->after('toll_charges');
            $table->decimal('total_transport_expense', 12, 2)->nullable()->after('other_expenses');
        });
    }

    public function down(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropColumn([
                'odometer_start', 'odometer_end', 'total_km', 'cost_per_km', 'transport_cost',
                'driver_allowance', 'toll_charges', 'other_expenses', 'total_transport_expense',
            ]);
        });
    }
};
