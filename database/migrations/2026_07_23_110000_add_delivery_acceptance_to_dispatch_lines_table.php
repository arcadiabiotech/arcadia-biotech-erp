<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recorded at "Mark Delivered" time, per line — lets a farmer reject
     * some (or all) of a line's shipped plants without blocking the
     * Dispatch from being marked delivered. Null until delivery is
     * actually recorded; accepted_qty + rejected_qty must equal the
     * line's total_qty once set (enforced in DispatchService::markDelivered()).
     */
    public function up(): void
    {
        Schema::table('dispatch_lines', function (Blueprint $table) {
            $table->unsignedInteger('accepted_qty')->nullable()->after('extra_qty');
            $table->unsignedInteger('rejected_qty')->nullable()->after('accepted_qty');
            $table->text('rejection_reason')->nullable()->after('rejected_qty');
        });
    }

    public function down(): void
    {
        Schema::table('dispatch_lines', function (Blueprint $table) {
            $table->dropColumn(['accepted_qty', 'rejected_qty', 'rejection_reason']);
        });
    }
};
