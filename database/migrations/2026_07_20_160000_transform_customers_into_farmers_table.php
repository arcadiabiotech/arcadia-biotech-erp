<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The "customers" table was a never-finished earlier attempt at farmer
     * records (already had farmer_name/aadhaar, no views ever existed for
     * it, 0 rows in the live DB). Replaced rather than duplicated, per the
     * same in-place-upgrade approach Phases 2 and 3 used for users/dealers.
     */
    public function up(): void
    {
        Schema::rename('customers', 'farmers');

        // MySQL renames the mobile unique index to match the table's new
        // name on RENAME TABLE; SQLite leaves it as "customers_mobile_unique".
        // Whichever it actually is, it must be dropped explicitly before the
        // column — SQLite errors trying to rebuild a stale index mid-drop.
        $existingIndexes = Schema::getIndexListing('farmers');
        $mobileIndexName = in_array('farmers_mobile_unique', $existingIndexes, true)
            ? 'farmers_mobile_unique'
            : 'customers_mobile_unique';

        Schema::table('farmers', function (Blueprint $table) use ($mobileIndexName) {
            $table->dropUnique($mobileIndexName);
        });

        Schema::table('farmers', function (Blueprint $table) {
            $table->dropColumn(['village', 'taluka', 'district', 'state', 'aadhaar', 'mobile']);
        });

        Schema::table('farmers', function (Blueprint $table) {
            $table->string('farmer_code')->unique()->after('id');
            $table->foreignId('dealer_id')->after('farmer_code')->constrained('dealers')->restrictOnDelete();

            $table->string('father_name')->nullable()->after('farmer_name');

            $table->string('mobile', 10)->unique()->after('father_name');
            $table->string('alternate_mobile', 10)->nullable()->after('mobile');
            $table->string('aadhaar_no', 12)->nullable()->unique()->after('alternate_mobile');

            $table->foreignId('state_id')->nullable()->after('aadhaar_no')->constrained('states')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->after('state_id')->constrained('districts')->nullOnDelete();
            $table->foreignId('taluka_id')->nullable()->after('district_id')->constrained('talukas')->nullOnDelete();
            $table->foreignId('village_id')->after('taluka_id')->constrained('villages')->restrictOnDelete();

            $table->text('address')->nullable()->after('village_id');
            $table->string('pincode', 6)->nullable()->after('address');

            $table->decimal('farm_area', 10, 2)->nullable()->after('pincode');
            $table->string('soil_type')->nullable()->after('farm_area');
            $table->string('irrigation_type')->nullable()->after('soil_type');

            $table->boolean('status')->default(true)->after('irrigation_type');
            $table->text('remarks')->nullable()->after('status');

            $table->foreignId('created_by')->nullable()->after('remarks')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn(['remarks', 'status', 'irrigation_type', 'soil_type', 'farm_area', 'pincode', 'address']);
            $table->dropConstrainedForeignId('village_id');
            $table->dropConstrainedForeignId('taluka_id');
            $table->dropConstrainedForeignId('district_id');
            $table->dropConstrainedForeignId('state_id');
            $table->dropColumn(['aadhaar_no', 'alternate_mobile', 'mobile', 'father_name']);
            $table->dropConstrainedForeignId('dealer_id');
            $table->dropColumn('farmer_code');
        });

        // Rename back to "customers" before recreating the legacy columns,
        // so the fresh mobile unique index is born as "customers_mobile_unique"
        // — matching the true original schema — instead of "farmers_mobile_unique".
        Schema::rename('farmers', 'customers');

        Schema::table('customers', function (Blueprint $table) {
            $table->string('mobile', 15)->unique();
            $table->string('village');
            $table->string('taluka');
            $table->string('district');
            $table->string('state');
            $table->string('aadhaar', 20)->nullable();
        });
    }
};
