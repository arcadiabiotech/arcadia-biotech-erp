<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Minimal actual-stock ledger, one row per variety. The Stock
     * Reservation engine needs a real "actual stock" number to compute
     * Available Stock (Actual - Reserved) against and to cap reservations
     * at — no Inventory module exists yet, so this is deliberately small
     * (variety + quantity) rather than a full stock/batch system.
     */
    public function up(): void
    {
        Schema::create('variety_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('variety')->unique();
            $table->unsignedInteger('actual_qty')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variety_stocks');
    }
};
