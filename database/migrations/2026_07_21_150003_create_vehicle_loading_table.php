<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One-to-one with vehicle_assignments: tracks the Supervisor's loading
     * progress and the eventual Loading Approval for that vehicle.
     */
    public function up(): void
    {
        Schema::create('vehicle_loading', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_assignment_id')->unique()->constrained('vehicle_assignments')->cascadeOnDelete();
            $table->enum('status', ['pending', 'loading', 'completed'])->default('pending');
            $table->timestamp('loading_started_at')->nullable();
            $table->timestamp('loading_finished_at')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('photo')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_loading');
    }
};
