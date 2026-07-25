<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per checklist item per Daily Checklist — replaces the earlier
     * 4-boolean-column design now that the real morning/daily-report sheet
     * has ~25 items (UV, sterilizer, room-wise spray/mopping, media &
     * autoclave prep, stock checks, laminar airflow, fumigation, ...).
     * The item catalogue itself (key, label, section, whether it takes a
     * chemical) lives as a PHP const on LabDailyChecklist — same convention
     * as every other fixed-vocabulary list in this app (STATUSES, TYPES,
     * ...) — so this table only stores the per-record answers.
     */
    public function up(): void
    {
        Schema::create('lab_checklist_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lab_daily_checklist_id')->constrained('lab_daily_checklists')->cascadeOnDelete();
            $table->string('item_key');
            $table->boolean('is_done')->default(false);
            $table->string('time_recorded', 5)->nullable();
            $table->string('chemical_used')->nullable();

            $table->timestamps();

            $table->unique(['lab_daily_checklist_id', 'item_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_checklist_items');
    }
};
