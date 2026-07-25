<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dispatch Plan creation moves from a rough Dealer->Farmer->estimated-qty
     * breakdown to picking real Bookings: each row now points at the Booking
     * it was planned from (booking_id) and records whether the chosen
     * quantity used up the booking's full remaining balance at save time
     * (dispatch_type). Nullable because existing plans were created before
     * this column existed and were never tied to a real Booking — they keep
     * displaying as before, just without a booking link. plant_quantity is
     * kept as the column name (now representing "Dispatch Quantity") to
     * avoid a disruptive rename across every place that already sums it.
     */
    public function up(): void
    {
        Schema::table('dispatch_plan_farmer_estimates', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->after('farmer_id')->constrained('bookings')->nullOnDelete();
            $table->string('dispatch_type')->nullable()->after('plant_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('dispatch_plan_farmer_estimates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_id');
            $table->dropColumn('dispatch_type');
        });
    }
};
