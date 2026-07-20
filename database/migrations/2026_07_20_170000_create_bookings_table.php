<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();

            $table->string('booking_no')->unique();

            $table->foreignId('dealer_id')->constrained('dealers')->restrictOnDelete();
            $table->foreignId('farmer_id')->constrained('farmers')->restrictOnDelete();
            $table->foreignId('marketing_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accounts_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('variety');
            $table->date('booking_date');

            $table->unsignedInteger('plant_qty');
            $table->decimal('plant_rate', 10, 2);
            $table->decimal('booking_amount', 12, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('advance_amount', 10, 2)->default(0);
            $table->decimal('balance_amount', 12, 2)->default(0);

            $table->string('payment_status')->default('pending');
            $table->string('approval_status')->default('draft');
            $table->string('dispatch_status')->default('pending');
            $table->string('invoice_status')->default('pending');

            $table->text('remarks')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index('booking_date');
            $table->index('approval_status');
            $table->index('payment_status');
            $table->index('dispatch_status');
            $table->index('invoice_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
