<?php

namespace App\Services;

use App\Models\LedgerEntry;

/**
 * Owns every ledger_entries write. The stored `balance` column is always the
 * dealer's cumulative running balance at the time of that entry — computed
 * from the previous entry for the same dealer — so a dealer statement can
 * display it directly without re-summing history on every page load.
 */
class LedgerService
{
    public function record(
        int $dealerId,
        ?int $farmerId,
        string $module,
        int $recordId,
        float $debit,
        float $credit,
        string $entryDate,
        ?string $remarks,
        ?int $actorId,
    ): LedgerEntry {
        $previousBalance = (float) (LedgerEntry::where('dealer_id', $dealerId)->latest('id')->value('balance') ?? 0);
        $balance = round($previousBalance + $debit - $credit, 2);

        return LedgerEntry::create([
            'dealer_id' => $dealerId,
            'farmer_id' => $farmerId,
            'module' => $module,
            'record_id' => $recordId,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'balance' => $balance,
            'entry_date' => $entryDate,
            'remarks' => $remarks,
            'created_by' => $actorId,
        ]);
    }

    /**
     * Dealer's current outstanding — the latest entry's running balance.
     */
    public function dealerBalance(int $dealerId): float
    {
        return (float) (LedgerEntry::where('dealer_id', $dealerId)->latest('id')->value('balance') ?? 0);
    }

    /**
     * Farmer's own outstanding — farmers don't get their own running-balance
     * column (only the dealer does), so this is summed directly from the
     * farmer-scoped rows.
     */
    public function farmerBalance(int $farmerId): float
    {
        return (float) LedgerEntry::where('farmer_id', $farmerId)
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) as net')
            ->value('net');
    }
}
