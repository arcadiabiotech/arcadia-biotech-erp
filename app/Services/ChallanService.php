<?php

namespace App\Services;

use App\Models\Challan;
use App\Models\Dispatch;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Splits a Dispatch's per-dealer lines into a Master Dealer Challan plus one
 * Farmer Challan per farmer, but only when a dealer actually has 2+ farmers
 * in this dispatch — a single-farmer dealer is already fully represented by
 * the existing dispatches.challan_no + combined print view, so generating a
 * redundant Master/Farmer pair for it would just be noise.
 */
class ChallanService
{
    /**
     * Called once, at Vehicle Out time (DispatchService::vehicleOut()) —
     * the same moment the existing challan_no is issued.
     */
    public function generateForDispatch(Dispatch $dispatch, User $actor): void
    {
        $dispatch->loadMissing('lines.dealer', 'lines.farmer');

        foreach ($dispatch->lines->groupBy('dealer_id') as $dealerId => $dealerLines) {
            $farmerGroups = $dealerLines->groupBy('farmer_id')
                ->sortBy(fn ($lines) => $lines->first()->farmer?->farmer_name);

            if ($farmerGroups->count() < 2) {
                continue;
            }

            $dealerChallan = Challan::create([
                'dispatch_id' => $dispatch->id,
                'dealer_id' => $dealerId,
                'type' => Challan::TYPE_DEALER,
                'challan_no' => $this->nextDealerChallanNo(),
                'created_by' => $actor->id,
            ]);

            $index = 1;
            foreach ($farmerGroups as $farmerId => $farmerLines) {
                Challan::create([
                    'dispatch_id' => $dispatch->id,
                    'parent_challan_id' => $dealerChallan->id,
                    'dealer_id' => $dealerId,
                    'farmer_id' => $farmerId,
                    'type' => Challan::TYPE_FARMER,
                    'challan_no' => $this->farmerChallanNo($dealerChallan->challan_no, $index),
                    'created_by' => $actor->id,
                ]);
                $index++;
            }
        }
    }

    /**
     * DC-2026-000125 — one shared sequence across every dealer challan ever
     * issued (mirrors DispatchService::nextDispatchNo()'s pattern).
     */
    public function nextDealerChallanNo(): string
    {
        $prefix = 'DC-'.now()->year.'-';

        $maxNumber = Challan::where('type', Challan::TYPE_DEALER)
            ->where('challan_no', 'like', "{$prefix}%")
            ->selectRaw('MAX(CAST(SUBSTRING(challan_no, ?) AS UNSIGNED)) as max_number', [strlen($prefix) + 1])
            ->value('max_number');

        return $prefix.str_pad(((int) $maxNumber) + 1, 6, '0', STR_PAD_LEFT);
    }

    /**
     * FC-2026-000125-01 — reuses its parent dealer challan's own sequence
     * number rather than a separate counter, so the two stay visibly tied
     * together; only the per-farmer suffix increments.
     */
    private function farmerChallanNo(string $dealerChallanNo, int $index): string
    {
        return 'FC-'.Str::after($dealerChallanNo, 'DC-').'-'.str_pad($index, 2, '0', STR_PAD_LEFT);
    }
}
