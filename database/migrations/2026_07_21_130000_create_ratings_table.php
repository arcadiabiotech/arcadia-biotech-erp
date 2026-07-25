<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per rated entity (Dealer, Farmer, or User), shared across all
     * three via a polymorphic relation rather than duplicating the same six
     * columns on three separate tables.
     */
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->string('rateable_type');
            $table->unsignedBigInteger('rateable_id');
            $table->unsignedTinyInteger('auto_score')->nullable();
            $table->unsignedTinyInteger('auto_star')->nullable();
            $table->boolean('is_manual_override')->default(false);
            $table->unsignedTinyInteger('manual_score')->nullable();
            $table->unsignedTinyInteger('manual_star')->nullable();
            $table->text('manual_reason')->nullable();
            $table->foreignId('manual_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('manual_at')->nullable();
            $table->timestamps();

            $table->unique(['rateable_type', 'rateable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
