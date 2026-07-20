<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('dealer_id')->nullable()->constrained('dealers')->nullOnDelete();
            $table->foreignId('farmer_id')->nullable()->constrained('farmers')->nullOnDelete();

            $table->string('variety');
            $table->unsignedInteger('reserved_qty');
            $table->unsignedInteger('released_qty')->default(0);
            $table->string('status')->default('reserved');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('status');
            $table->index('variety');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
