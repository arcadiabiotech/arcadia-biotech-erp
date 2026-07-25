<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Dispatch can now cover multiple dealers, so Invoice moves from
     * "one per dispatch" to "one per (dispatch, dealer)" — booking_id and
     * farmer_id leave the header entirely (a dealer can span multiple
     * bookings/farmers within one dispatch; that detail now lives in
     * invoice_lines).
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // MySQL refuses to drop an index while a foreign key still relies
            // on it, so the dispatch_id FK has to come down before its
            // unique index can. farmer_id's explicit index() also has to go
            // before the column itself — SQLite (used by the test suite)
            // fails the column drop otherwise since it tries to rebuild the
            // table with a dangling index still pointing at the old column.
            $table->dropForeign(['dispatch_id']);
            $table->dropUnique(['dispatch_id']);
            $table->dropForeign(['booking_id']);
            $table->dropForeign(['farmer_id']);
            $table->dropIndex(['farmer_id']);
            $table->dropColumn(['booking_id', 'farmer_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unique(['dispatch_id', 'dealer_id']);
            $table->foreign('dispatch_id')->references('id')->on('dispatches')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['dispatch_id']);
            $table->dropUnique(['dispatch_id', 'dealer_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->after('dispatch_id')->constrained('bookings')->nullOnDelete();
            $table->foreignId('farmer_id')->nullable()->after('dealer_id')->constrained('farmers')->nullOnDelete();
            $table->unique('dispatch_id');
            $table->foreign('dispatch_id')->references('id')->on('dispatches')->restrictOnDelete();
        });
    }
};
