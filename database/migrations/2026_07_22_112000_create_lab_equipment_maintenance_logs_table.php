<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_equipment_maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->string('log_no')->unique();

            $table->foreignId('lab_equipment_id')->constrained('lab_equipment')->restrictOnDelete();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();

            $table->date('maintenance_date');
            $table->string('maintenance_type')->default('routine');
            $table->text('description')->nullable();
            $table->date('next_due_date')->nullable();
            $table->decimal('downtime_hours', 6, 2)->nullable();
            $table->decimal('cost', 10, 2)->nullable();

            $table->string('photo')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status')->default('draft');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index('maintenance_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_equipment_maintenance_logs');
    }
};
