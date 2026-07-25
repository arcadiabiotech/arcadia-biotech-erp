<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time re-casing of existing proper-noun fields (person/firm/place
     * names) to match the app's Title Case convention going forward — see
     * App\Support\TextCasing::titleCase(). Not reversible: original casing
     * isn't recoverable, same as the vehicle_no uppercase migration before it.
     */
    public function up(): void
    {
        $columnsByTable = [
            'states' => ['name'],
            'districts' => ['name'],
            'talukas' => ['name'],
            'villages' => ['name'],
            'dealers' => ['dealer_name', 'firm_name'],
            'farmers' => ['farmer_name', 'father_name'],
            'vehicles' => ['driver_name', 'transport_company'],
            'vehicle_assignments' => ['driver_name', 'helper_name'],
            'dispatches' => ['driver_name'],
            'payments' => ['bank_name'],
            'users' => ['name'],
        ];

        foreach ($columnsByTable as $table => $columns) {
            DB::table($table)->orderBy('id')->select(array_merge(['id'], $columns))
                ->chunkById(500, function ($rows) use ($table, $columns) {
                    foreach ($rows as $row) {
                        $updates = [];

                        foreach ($columns as $column) {
                            $value = $row->$column;

                            if (is_string($value) && $value !== '') {
                                $updates[$column] = preg_replace_callback(
                                    '/(^|\s)(\S)/u',
                                    fn (array $m) => $m[1].mb_strtoupper($m[2]),
                                    $value
                                );
                            }
                        }

                        if ($updates !== []) {
                            DB::table($table)->where('id', $row->id)->update($updates);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        // Original casing isn't recoverable — title-casing is a one-way normalization.
    }
};
