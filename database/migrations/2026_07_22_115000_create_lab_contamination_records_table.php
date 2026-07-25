<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_contamination_records', function (Blueprint $table) {
            $table->id();
            $table->string('contamination_no')->unique();

            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->date('contamination_date');

            $table->string('culture_batch_number')->nullable();
            $table->string('contamination_type')->default('unknown');
            $table->string('severity')->default('low');
            $table->unsignedInteger('affected_qty')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('corrective_action')->nullable();

            $table->string('photo')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status')->default('draft');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index('contamination_date');
            $table->index('status');
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_contamination_records');
    }
};
