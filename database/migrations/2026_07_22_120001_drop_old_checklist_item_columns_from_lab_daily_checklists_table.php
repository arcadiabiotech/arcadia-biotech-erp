<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Superseded by the lab_checklist_items table — the 4-item checklist
     * (UV/Sterilizer/Mopping/Cleaning) is replaced by the full ~25-item
     * morning/daily-report sheet, which needs a per-item done+time(+chemical)
     * row rather than a fixed set of columns.
     */
    public function up(): void
    {
        Schema::table('lab_daily_checklists', function (Blueprint $table) {
            $table->dropColumn([
                'uv_light_checked',
                'uv_light_remarks',
                'sterilizer_checked',
                'sterilizer_remarks',
                'mopping_done',
                'cleaning_done',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('lab_daily_checklists', function (Blueprint $table) {
            $table->boolean('uv_light_checked')->default(false);
            $table->string('uv_light_remarks')->nullable();
            $table->boolean('sterilizer_checked')->default(false);
            $table->string('sterilizer_remarks')->nullable();
            $table->boolean('mopping_done')->default(false);
            $table->boolean('cleaning_done')->default(false);
        });
    }
};
