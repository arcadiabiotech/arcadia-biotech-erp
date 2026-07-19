<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealers', function (Blueprint $table) {

            $table->id();

            // Dealer Code
            $table->string('dealer_code')->unique();

            // Basic Details
            $table->string('firm_name');
            $table->string('dealer_name');

            $table->string('mobile',10)->unique();
            $table->string('whatsapp',10)->nullable();

            $table->string('email')->nullable();

            $table->string('gst_number')->nullable();
            $table->string('pan_number')->nullable();

            // Address
            $table->text('address')->nullable();

            $table->unsignedBigInteger('state_id')->nullable();
            $table->unsignedBigInteger('district_id')->nullable();
            $table->unsignedBigInteger('taluka_id')->nullable();
            $table->unsignedBigInteger('villages_id')->nullable();

            $table->string('pin_code',6)->nullable();

            // Agreement
            $table->date('agreement_date')->nullable();

            // Status
            $table->boolean('status')->default(true);

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dealers');
    }
};