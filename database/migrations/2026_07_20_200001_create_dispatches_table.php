<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('dispatch_no')->unique();

            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('dealer_id')->constrained('dealers')->restrictOnDelete();
            $table->foreignId('farmer_id')->constrained('farmers')->restrictOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();

            $table->string('driver_name')->nullable();
            $table->string('driver_mobile')->nullable();

            $table->date('dispatch_date');
            $table->date('expected_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();

            $table->unsignedInteger('dispatch_qty');
            $table->unsignedInteger('remaining_qty')->default(0);

            $table->string('status')->default('draft');

            $table->string('challan_no')->nullable()->unique();
            $table->string('invoice_no')->nullable();

            $table->text('remarks')->nullable();
            $table->string('photo')->nullable();
            $table->string('signature')->nullable();
            $table->string('gps_location')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index('dispatch_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatches');
    }
};
