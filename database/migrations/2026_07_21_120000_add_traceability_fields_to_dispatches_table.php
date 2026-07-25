<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->string('lr_number')->nullable()->after('driver_mobile');
            $table->string('batch_number')->nullable()->after('qty_per_crate');
            $table->string('plant_age')->nullable()->after('batch_number');
        });
    }

    public function down(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropColumn(['lr_number', 'batch_number', 'plant_age']);
        });
    }
};
