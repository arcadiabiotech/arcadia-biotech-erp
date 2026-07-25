<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks the vehicle's return trip: how many (of the crates sent out
     * with this dispatch) have come back, and when the vehicle returned.
     * crates_returned is cumulative and re-editable, since crates can come
     * back in more than one partial batch.
     */
    public function up(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->decimal('crates_returned', 8, 2)->nullable()->after('qty_per_crate');
            $table->date('vehicle_returned_at')->nullable()->after('actual_delivery_date');
            $table->string('return_remarks', 500)->nullable()->after('crates_returned');
        });
    }

    public function down(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropColumn(['crates_returned', 'vehicle_returned_at', 'return_remarks']);
        });
    }
};
