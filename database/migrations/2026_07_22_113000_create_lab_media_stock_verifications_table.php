<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_media_stock_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('verification_no')->unique();

            $table->foreignId('verified_by')->constrained('users')->restrictOnDelete();
            $table->date('verification_date');

            $table->string('media_name');
            $table->string('unit');
            $table->decimal('opening_stock', 10, 2)->default(0);
            $table->decimal('received_qty', 10, 2)->default(0);
            $table->decimal('consumed_qty', 10, 2)->default(0);
            $table->decimal('closing_stock', 10, 2)->default(0);
            $table->decimal('reorder_level', 10, 2)->nullable();

            $table->string('photo')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status')->default('draft');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index('verification_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_media_stock_verifications');
    }
};
