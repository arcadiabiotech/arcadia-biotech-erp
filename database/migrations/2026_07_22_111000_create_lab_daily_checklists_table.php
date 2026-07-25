<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per employee per day — the Morning Checklist (UV, Sterilizer,
     * Mopping, Cleaning). Plugs into the generic Approval engine
     * (module_name = 'lab-checklists') for the Supervisor Approval step
     * rather than owning its own approval columns.
     */
    public function up(): void
    {
        Schema::create('lab_daily_checklists', function (Blueprint $table) {
            $table->id();
            $table->string('checklist_no')->unique();

            $table->foreignId('employee_id')->constrained('users')->restrictOnDelete();
            $table->date('checklist_date');

            $table->boolean('uv_light_checked')->default(false);
            $table->string('uv_light_remarks')->nullable();
            $table->boolean('sterilizer_checked')->default(false);
            $table->string('sterilizer_remarks')->nullable();
            $table->boolean('mopping_done')->default(false);
            $table->boolean('cleaning_done')->default(false);

            $table->text('general_remarks')->nullable();
            $table->string('photo')->nullable();
            $table->string('status')->default('draft');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['employee_id', 'checklist_date'], 'lab_checklists_employee_date_unique');
            $table->index('checklist_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_daily_checklists');
    }
};
