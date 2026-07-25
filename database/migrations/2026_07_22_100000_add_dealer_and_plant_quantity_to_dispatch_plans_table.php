<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plan-level summary fields — a rough "who/how much" estimate captured
     * at planning time, separate from the actual per-booking dealer/qty
     * tracked later on dispatch_plan_items once vehicles are assigned.
     */
    public function up(): void
    {
        Schema::table('dispatch_plans', function (Blueprint $table) {
            $table->foreignId('dealer_id')->nullable()->after('route')->constrained('dealers')->nullOnDelete();
            $table->unsignedInteger('plant_quantity')->nullable()->after('dealer_id');
        });
    }

    public function down(): void
    {
        Schema::table('dispatch_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dealer_id');
            $table->dropColumn('plant_quantity');
        });
    }
};
