<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One booking assigned to one vehicle assignment. "No duplicate booking
     * in multiple vehicles" is enforced at the query/service layer (excluding
     * bookings already in a non-cancelled item) rather than a hard DB
     * constraint — the same convention already used for "one active dispatch
     * per booking" in DispatchStoreRequest::allowedBookingIds().
     *
     * dispatch_id stays null until Loading is Approved for this item's
     * vehicle assignment, at which point a real Dispatch row is created and
     * linked here.
     */
    public function up(): void
    {
        Schema::create('dispatch_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_assignment_id')->constrained('vehicle_assignments')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings');
            $table->unsignedInteger('sequence')->default(0);
            $table->unsignedInteger('dispatch_qty')->nullable();
            $table->timestamp('loaded_at')->nullable();
            $table->foreignId('loaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dispatch_id')->nullable()->constrained('dispatches')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_plan_items');
    }
};
