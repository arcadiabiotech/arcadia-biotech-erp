<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per day+route plan — the header a set of Vehicle Assignments
     * (one per vehicle) hang off. Created before any vehicle/booking is
     * assigned.
     */
    public function up(): void
    {
        Schema::create('dispatch_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_no')->unique();
            $table->date('plan_date');
            $table->string('route')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_plans');
    }
};
