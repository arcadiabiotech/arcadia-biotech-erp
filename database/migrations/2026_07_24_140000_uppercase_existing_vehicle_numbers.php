<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('vehicles')->update(['vehicle_no' => DB::raw('UPPER(vehicle_no)')]);
    }

    public function down(): void
    {
        // Original casing isn't recoverable — uppercasing is a one-way normalization.
    }
};
