<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealer_assignments', function (Blueprint $table) {
            $table->id();

            // One Dealer belongs to only one Marketing user -> dealer_id is unique.
            $table->foreignId('dealer_id')->unique()->constrained('dealers')->cascadeOnDelete();

            // One Marketing user can manage multiple Dealers.
            $table->foreignId('marketing_user_id')->constrained('users')->cascadeOnDelete();

            // Admin who created the assignment.
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();

            $table->date('assigned_date');

            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dealer_assignments');
    }
};
