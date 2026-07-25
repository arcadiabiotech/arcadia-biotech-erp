<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cumulative record of how much of this reservation has actually been
     * converted into a real stock issue — needed now that
     * StockReservationService::convert()/release() support partial amounts
     * (a farmer rejecting part of a delivery converts the accepted portion
     * and releases the rejected portion, rather than resolving the whole
     * reservation in one shot).
     */
    public function up(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->unsignedInteger('converted_qty')->default(0)->after('released_qty');
        });
    }

    public function down(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->dropColumn('converted_qty');
        });
    }
};
