<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original migration named this column villages_id, but the Dealer
     * model's village() relation (and every other location column here)
     * follows the singular {relation}_id convention — village_id was simply
     * never reachable. Renamed rather than left as a trap for the next
     * developer who wires up the village select.
     */
    public function up(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->renameColumn('villages_id', 'village_id');
        });

        Schema::table('dealers', function (Blueprint $table) {
            $table->decimal('credit_limit', 12, 2)->default(0)->after('agreement_date');
            $table->text('remarks')->nullable()->after('credit_limit');
            $table->foreignId('deleted_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();

            $table->index('status');
            $table->foreign('state_id')->references('id')->on('states')->nullOnDelete();
            $table->foreign('district_id')->references('id')->on('districts')->nullOnDelete();
            $table->foreign('taluka_id')->references('id')->on('talukas')->nullOnDelete();
            $table->foreign('village_id')->references('id')->on('villages')->nullOnDelete();

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropForeign(['state_id']);
            $table->dropForeign(['district_id']);
            $table->dropForeign(['taluka_id']);
            $table->dropForeign(['village_id']);
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn(['credit_limit', 'remarks']);
        });

        Schema::table('dealers', function (Blueprint $table) {
            $table->renameColumn('village_id', 'villages_id');
        });
    }
};
