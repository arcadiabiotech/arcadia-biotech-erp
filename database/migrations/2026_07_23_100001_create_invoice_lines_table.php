<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per Dispatch line an Invoice covers. qty/rate/amount are
     * frozen at generation time (same "freeze historical values" convention
     * Booking.plant_rate already uses) so a later rate change never
     * retroactively alters an issued invoice.
     */
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('dispatch_line_id')->constrained('dispatch_lines')->restrictOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('farmer_id')->constrained('farmers')->restrictOnDelete();

            $table->unsignedInteger('qty');
            $table->decimal('rate', 10, 2);
            $table->decimal('amount', 12, 2);

            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
