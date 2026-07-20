<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {

            $table->id();

            $table->string('module',100);

            $table->unsignedBigInteger('record_id');

            $table->enum('action',[
                'create',
                'update',
                'delete',
                'approve',
                'reject',
                'hold',
                'unlock',
                'login',
                'logout'
            ]);

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('role_id')
                ->nullable()
                ->constrained('roles')
                ->nullOnDelete();

            $table->json('old_values')->nullable();

            $table->json('new_values')->nullable();

            $table->text('remarks')->nullable();

            $table->string('ip_address',45)->nullable();

            $table->string('device')->nullable();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};