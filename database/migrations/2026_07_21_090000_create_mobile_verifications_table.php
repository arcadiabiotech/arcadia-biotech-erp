<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_verifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('mobile', 15);
            $table->string('otp');
            $table->enum('purpose', ['registration', 'login']);
            $table->enum('status', ['pending', 'verified', 'expired', 'failed'])->default('pending');

            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at');

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('resend_count')->default(0);

            $table->string('ip_address', 45)->nullable();
            $table->string('device')->nullable();

            $table->timestamps();

            $table->index(['mobile', 'purpose', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_verifications');
    }
};
