<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vehicle Master rates used to auto-calculate Transport Cost per
     * dispatch (Step 7A). Nullable at the DB level since existing vehicles
     * have none of this data yet — cost_per_km is "Mandatory" only at the
     * validation layer (VehicleStoreRequest/UpdateRequest), enforced the
     * next time a vehicle is created or edited.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->decimal('cost_per_km', 10, 2)->nullable()->after('transport_company');
            $table->decimal('driver_allowance', 10, 2)->nullable()->after('cost_per_km');
            $table->string('fuel_type', 50)->nullable()->after('driver_allowance');
            $table->decimal('average_mileage', 6, 2)->nullable()->after('fuel_type');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['cost_per_km', 'driver_allowance', 'fuel_type', 'average_mileage']);
        });
    }
};
