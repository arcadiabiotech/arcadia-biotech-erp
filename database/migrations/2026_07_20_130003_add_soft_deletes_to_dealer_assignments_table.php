<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Soft deletes turn "unassign a dealer" into a reversible, audited action
     * instead of losing the assignment history. That conflicts with the plain
     * unique(dealer_id) constraint added at table creation: MySQL enforces
     * uniqueness against every row regardless of deleted_at, so re-assigning a
     * dealer after a soft-deleted (unassigned) row would be blocked forever.
     * "One active assignment per dealer" is therefore enforced at the
     * application layer instead; dealer_id keeps a plain index for lookups
     * and to keep supporting its foreign key.
     */
    public function up(): void
    {
        Schema::table('dealer_assignments', function (Blueprint $table) {
            $table->softDeletes();
            $table->index('status');
            $table->index('dealer_id');
        });

        Schema::table('dealer_assignments', function (Blueprint $table) {
            $table->dropUnique(['dealer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('dealer_assignments', function (Blueprint $table) {
            $table->unique('dealer_id');
        });

        Schema::table('dealer_assignments', function (Blueprint $table) {
            $table->dropIndex(['dealer_id']);
            $table->dropIndex(['status']);
            $table->dropSoftDeletes();
        });
    }
};
