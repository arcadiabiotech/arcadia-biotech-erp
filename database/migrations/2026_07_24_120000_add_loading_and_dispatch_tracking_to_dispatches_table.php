<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * loaded_at/loaded_by record the moment the one-click "Vehicle Loaded"
     * action (DispatchService::loadVehicle()) fires — the same moment the
     * challan is generated. dispatched_at/dispatched_by record the "Dispatch
     * Vehicle" action (DispatchService::vehicleOut()). Both are in addition
     * to (not a replacement for) the existing status column + ActivityLog
     * trail, giving a fast, indexed answer to "when/who" without joining
     * activity_logs.
     */
    public function up(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->timestamp('loaded_at')->nullable()->after('status');
            $table->foreignId('loaded_by')->nullable()->after('loaded_at')->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable()->after('loaded_by');
            $table->foreignId('dispatched_by')->nullable()->after('dispatched_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loaded_by');
            $table->dropConstrainedForeignId('dispatched_by');
            $table->dropColumn(['loaded_at', 'dispatched_at']);
        });
    }
};
