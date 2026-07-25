<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Superseded by dispatch_lines.dispatch_plan_item_id — an item is no
     * longer 1:1 with a single Dispatch (partial dispatch means the same
     * item can be fulfilled across more than one Dispatch over time), so a
     * single dispatch_id column on the item no longer makes sense.
     */
    public function up(): void
    {
        Schema::table('dispatch_plan_items', function (Blueprint $table) {
            $table->dropForeign(['dispatch_id']);
            $table->dropColumn('dispatch_id');
        });
    }

    public function down(): void
    {
        Schema::table('dispatch_plan_items', function (Blueprint $table) {
            $table->foreignId('dispatch_id')->nullable()->constrained('dispatches')->nullOnDelete();
        });
    }
};
