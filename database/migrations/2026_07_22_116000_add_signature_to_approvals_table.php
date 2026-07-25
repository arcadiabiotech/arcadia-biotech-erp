<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Captures the approver's digital signature (an uploaded/drawn image
     * path, same convention as Dispatch's photo/signature columns) for
     * modules that need it — first consumer is Lab Ops Supervisor Approval.
     * Nullable and additive: existing Approval rows/callers are unaffected.
     */
    public function up(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            $table->string('signature')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            $table->dropColumn('signature');
        });
    }
};
