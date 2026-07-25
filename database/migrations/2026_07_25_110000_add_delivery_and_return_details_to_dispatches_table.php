<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dispatch Lifecycle spec additions: precise delivery-receiver capture
     * (existing actual_delivery_date is date-only and has no receiver
     * fields at all) and a "Vehicle Returned" step distinct from the fuller
     * Return Inspection (existing vehicle_returned_at/return_remarks stay
     * as-is — this only adds who returned it and the inspection's own
     * three-way remarks split + optional photos).
     */
    public function up(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->string('delivery_location')->nullable()->after('actual_delivery_date');
            $table->string('receiver_name')->nullable()->after('delivery_location');
            $table->string('receiver_mobile')->nullable()->after('receiver_name');
            $table->text('receiver_remarks')->nullable()->after('receiver_mobile');
            $table->timestamp('delivered_at')->nullable()->after('receiver_remarks');
            $table->foreignId('delivered_by')->nullable()->after('delivered_at')->constrained('users')->nullOnDelete();

            $table->foreignId('vehicle_returned_by')->nullable()->after('return_remarks')->constrained('users')->nullOnDelete();
            $table->text('damage_remarks')->nullable()->after('vehicle_returned_by');
            $table->text('driver_remarks')->nullable()->after('damage_remarks');
            $table->text('supervisor_remarks')->nullable()->after('driver_remarks');
            $table->json('return_photos')->nullable()->after('supervisor_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivered_by');
            $table->dropConstrainedForeignId('vehicle_returned_by');
            $table->dropColumn([
                'delivery_location', 'receiver_name', 'receiver_mobile', 'receiver_remarks', 'delivered_at',
                'damage_remarks', 'driver_remarks', 'supervisor_remarks', 'return_photos',
            ]);
        });
    }
};
