<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no')->unique();

            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('dealer_id')->constrained('dealers')->restrictOnDelete();
            $table->foreignId('farmer_id')->constrained('farmers')->restrictOnDelete();

            $table->date('payment_date');
            $table->string('payment_mode');
            $table->string('reference_no')->nullable();
            $table->string('bank_name')->nullable();
            $table->decimal('amount', 12, 2);
            $table->text('remarks')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('payment_date');
            $table->index('payment_mode');
            $table->index('dealer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
