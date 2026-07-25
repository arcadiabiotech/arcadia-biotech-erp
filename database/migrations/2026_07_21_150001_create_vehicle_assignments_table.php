<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per vehicle within a Dispatch Plan — the "Vehicle GJ-23-AB-1234"
     * card in the Vehicle Wise Grouping board. Bookings (dispatch_plan_items)
     * are grouped under whichever vehicle assignment they're dragged onto.
     */
    public function up(): void
    {
        Schema::create('vehicle_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_plan_id')->constrained('dispatch_plans')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->foreignId('marketing_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('driver_name')->nullable();
            $table->string('driver_mobile')->nullable();
            $table->string('helper_name')->nullable();
            $table->time('estimated_departure_time')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['dispatch_plan_id', 'vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_assignments');
    }
};
