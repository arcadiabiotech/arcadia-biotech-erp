<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only audit trail for rating changes. No code path ever
     * updates or deletes a row here — every manual override or reset
     * writes a new entry, so the log only ever grows.
     */
    public function up(): void
    {
        Schema::create('rating_histories', function (Blueprint $table) {
            $table->id();
            $table->string('module');
            $table->unsignedBigInteger('rateable_id');
            $table->string('action');
            $table->unsignedTinyInteger('old_score')->nullable();
            $table->unsignedTinyInteger('old_star')->nullable();
            $table->unsignedTinyInteger('new_score')->nullable();
            $table->unsignedTinyInteger('new_star')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['module', 'rateable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_histories');
    }
};
