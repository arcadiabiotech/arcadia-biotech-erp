<?php

namespace App\Services;

/**
 * Shared {PREFIX}-{year}-{seq} numbering for the Lab Ops module — the same
 * MAX()+1, zero-padded, yearly-reset algorithm as BookingService::nextBookingNo()
 * and DispatchPlanningService::nextPlanNo(), factored out once since all 5
 * Lab workflow modules need an identical, stateless implementation of it.
 */
class LabNumberGenerator
{
    public function next(string $modelClass, string $column, string $prefix): string
    {
        $yearPrefix = "{$prefix}-".now()->year.'-';

        $maxNumber = $modelClass::withTrashed()
            ->where($column, 'like', "{$yearPrefix}%")
            ->selectRaw("MAX(CAST(SUBSTRING({$column}, ?) AS UNSIGNED)) as max_number", [strlen($yearPrefix) + 1])
            ->value('max_number');

        return $yearPrefix.str_pad(((int) $maxNumber) + 1, 6, '0', STR_PAD_LEFT);
    }
}
