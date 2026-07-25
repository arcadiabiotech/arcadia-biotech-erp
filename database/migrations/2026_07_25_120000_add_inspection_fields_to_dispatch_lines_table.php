<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dispatch Lifecycle spec's Return Inspection wants a finer-grained
     * crate/plant breakdown than the existing crates_returned/damage_qty
     * pair — "Missing" and "Broken" as distinct crate categories from
     * "Damaged" (damage_qty, untouched), and "Dead"/"Extra" as distinct
     * plant categories from the existing extra_qty (bonus plants sent,
     * a different concept — hence extra_returned_qty here, not a reuse).
     * "Returned Plants" reuses the existing rejected_qty column; "Total
     * Crates Sent/Returned" reuse the existing crate_count/crates_returned
     * accessors — no new columns needed for either.
     */
    public function up(): void
    {
        Schema::table('dispatch_lines', function (Blueprint $table) {
            $table->decimal('missing_qty', 8, 2)->default(0)->after('crates_returned');
            $table->decimal('broken_qty', 8, 2)->default(0)->after('missing_qty');
            $table->unsignedInteger('dead_plant_qty')->default(0)->after('broken_qty');
            $table->unsignedInteger('extra_returned_qty')->default(0)->after('dead_plant_qty');
        });
    }

    public function down(): void
    {
        Schema::table('dispatch_lines', function (Blueprint $table) {
            $table->dropColumn(['missing_qty', 'broken_qty', 'dead_plant_qty', 'extra_returned_qty']);
        });
    }
};
