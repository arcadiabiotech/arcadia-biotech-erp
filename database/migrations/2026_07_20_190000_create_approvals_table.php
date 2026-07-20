<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generic, reusable approval workflow ledger — one row per
     * (module_name, record_id, approval_level), so any module (Booking,
     * Dispatch, Payment, Invoice, Plantation, ...) can plug into the same
     * engine without its own approvals table. record_id is intentionally
     * not a foreign key: it points at whichever table module_name names,
     * so a single FK constraint can't express it — a composite index
     * covers lookups instead.
     */
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();

            $table->string('module_name');
            $table->unsignedBigInteger('record_id');
            $table->unsignedTinyInteger('approval_level')->default(1);
            $table->string('status')->default('draft');
            $table->text('remarks')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();

            $table->foreignId('hold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hold_at')->nullable();

            $table->foreignId('unlock_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('unlock_at')->nullable();

            $table->timestamps();

            $table->unique(['module_name', 'record_id', 'approval_level'], 'approvals_module_record_level_unique');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
