<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_chemical_usages', function (Blueprint $table) {
            $table->id();
            $table->string('usage_no')->unique();

            $table->foreignId('used_by')->constrained('users')->restrictOnDelete();
            $table->date('usage_date');

            $table->string('chemical_name');
            $table->string('purpose')->nullable();
            $table->decimal('quantity_used', 10, 2);
            $table->string('unit');
            $table->string('batch_number')->nullable();

            $table->string('photo')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status')->default('draft');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index('usage_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_chemical_usages');
    }
};
