<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dispatch_lines', function (Blueprint $table) {
            $table->decimal('damage_qty', 10, 2)->nullable()->after('crates_returned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dispatch_lines', function (Blueprint $table) {
            $table->dropColumn('damage_qty');
        });
    }
};
