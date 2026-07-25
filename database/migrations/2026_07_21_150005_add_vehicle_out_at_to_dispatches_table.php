<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distinct "vehicle actually left" timestamp — separate from
     * actual_delivery_date (set at Mark Delivered) and from created_at/
     * updated_at, which don't reliably capture the moment of departure.
     */
    public function up(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->timestamp('vehicle_out_at')->nullable()->after('challan_no');
        });
    }

    public function down(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropColumn('vehicle_out_at');
        });
    }
};
