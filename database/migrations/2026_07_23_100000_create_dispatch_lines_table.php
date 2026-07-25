<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per booking within a Dispatch (vehicle trip) — the line-item
     * table that lets a single Dispatch carry multiple bookings across
     * multiple dealers/farmers on one vehicle. dispatch_plan_item_id is
     * deliberately NOT nullable: every line must trace back to a planned
     * booking, which is the structural enforcement of "no direct dispatch
     * without a Dispatch Planning record." Not unique on
     * dispatch_plan_item_id — the same planned item can be fulfilled across
     * more than one Dispatch over time (partial dispatch).
     */
    public function up(): void
    {
        Schema::create('dispatch_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dispatch_id')->constrained('dispatches')->cascadeOnDelete();
            $table->foreignId('dispatch_plan_item_id')->constrained('dispatch_plan_items')->restrictOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('dealer_id')->constrained('dealers')->restrictOnDelete();
            $table->foreignId('farmer_id')->constrained('farmers')->restrictOnDelete();

            $table->unsignedInteger('dispatch_qty');
            $table->unsignedInteger('extra_qty')->default(0);
            $table->unsignedInteger('qty_per_crate')->nullable();
            $table->decimal('crates_returned', 10, 2)->nullable();
            $table->string('batch_number')->nullable();
            $table->string('plant_age')->nullable();
            $table->unsignedInteger('remaining_qty')->default(0);
            $table->text('remarks')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index('dispatch_id');
            $table->index('booking_id');
            $table->index('dealer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_lines');
    }
};
