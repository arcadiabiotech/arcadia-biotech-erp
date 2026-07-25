<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only audit trail for loading events — item-level "loaded"
     * toggles and vehicle-level status transitions. Same convention as the
     * existing RatingHistory table: no update/delete call site anywhere.
     */
    public function up(): void
    {
        Schema::create('loading_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_assignment_id')->constrained('vehicle_assignments')->cascadeOnDelete();
            $table->foreignId('dispatch_plan_item_id')->nullable()->constrained('dispatch_plan_items')->nullOnDelete();
            $table->string('action');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('vehicle_assignment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loading_history');
    }
};
