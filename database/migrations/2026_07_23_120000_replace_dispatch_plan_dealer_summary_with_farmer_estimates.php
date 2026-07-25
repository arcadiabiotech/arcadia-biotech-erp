<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces the single dealer_id/plant_quantity "rough estimate" columns
     * on dispatch_plans with a proper multi-dealer, multi-farmer breakdown —
     * a plan can now span several dealers, each with several farmers and
     * their own plant quantity. Total plant quantity is derived (sum) rather
     * than stored.
     */
    public function up(): void
    {
        Schema::create('dispatch_plan_farmer_estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dealer_id')->constrained('dealers')->restrictOnDelete();
            $table->foreignId('farmer_id')->constrained('farmers')->restrictOnDelete();
            $table->unsignedInteger('plant_quantity');
            $table->timestamps();

            $table->unique(['dispatch_plan_id', 'farmer_id']);
        });

        Schema::table('dispatch_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dealer_id');
            $table->dropColumn('plant_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('dispatch_plans', function (Blueprint $table) {
            $table->foreignId('dealer_id')->nullable()->after('route')->constrained('dealers')->nullOnDelete();
            $table->unsignedInteger('plant_quantity')->nullable()->after('dealer_id');
        });

        Schema::dropIfExists('dispatch_plan_farmer_estimates');
    }
};
