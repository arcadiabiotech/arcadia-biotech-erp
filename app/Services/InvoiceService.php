<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Invoice;
use Illuminate\Validation\ValidationException;

/**
 * Owns invoice_no generation and every status transition in the
 * Draft -> Generated -> Partially Paid -> Paid -> Cancelled lifecycle
 * (plus Unlock). Centralizing the state machine here keeps InvoiceController
 * thin, the same way DispatchService/BookingService do for their modules.
 *
 * Only one invoice is allowed per (dispatch, dealer) pair — enforced by the
 * unique(dispatch_id, dealer_id) index and InvoiceStoreRequest — so
 * generate() always posts exactly one ledger debit per invoice, even though
 * a multi-dealer Dispatch now produces more than one Invoice overall.
 */
class InvoiceService
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    /**
     * INV-YYYY-000001, sequence resets each calendar year. withTrashed()
     * avoids reissuing a number that belongs to a soft-deleted invoice.
     */
    public function nextInvoiceNo(): string
    {
        $prefix = 'INV-'.now()->year.'-';

        $maxNumber = Invoice::withTrashed()
            ->where('invoice_no', 'like', "{$prefix}%")
            ->selectRaw('MAX(CAST(SUBSTRING(invoice_no, ?) AS UNSIGNED)) as max_number', [strlen($prefix) + 1])
            ->value('max_number');

        return $prefix.str_pad(((int) $maxNumber) + 1, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Accounts: "Generate Invoice" — finalizes a Draft invoice. This is the
     * one place the ledger is debited and each covered Booking's
     * denormalized invoice field is synced (Automation: Invoice Generated ->
     * Ledger Debit). farmer_id on the ledger entry is null: an invoice is
     * dealer-scoped and can now cover bookings from more than one farmer.
     */
    public function generate(Invoice $invoice, int $actorId): void
    {
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only a draft invoice can be generated.']);
        }

        $original = $invoice->only('status');

        $invoice->status = 'generated';
        $invoice->updated_by = $actorId;
        $invoice->save();

        foreach ($invoice->lines as $line) {
            $line->booking()->update(['invoice_status' => 'generated', 'updated_by' => $actorId]);
        }

        $this->ledger->record(
            $invoice->dealer_id,
            null,
            'invoices',
            $invoice->id,
            (float) $invoice->grand_total,
            0,
            $invoice->invoice_date->toDateString(),
            "Invoice {$invoice->invoice_no} generated",
            $actorId
        );

        ActivityLog::record(
            'invoices',
            $invoice->id,
            'update',
            $original,
            $invoice->fresh()->only('status'),
            'Invoice generated — ledger debited ₹'.number_format((float) $invoice->grand_total, 2)
        );
    }

    /**
     * Admin: "Cancel Invoice". Reason required. A generated invoice with an
     * outstanding balance gets a reversing ledger credit so the dealer's
     * running balance isn't left overstated.
     */
    public function cancel(Invoice $invoice, string $reason, int $actorId): void
    {
        if (in_array($invoice->status, ['paid', 'cancelled'], true)) {
            throw ValidationException::withMessages(['status' => 'A paid or already-cancelled invoice cannot be cancelled.']);
        }

        $original = $invoice->only('status', 'remarks');
        $wasGenerated = $invoice->status !== 'draft';
        $outstanding = (float) $invoice->balance_amount;

        $invoice->status = 'cancelled';
        $invoice->remarks = trim(($invoice->remarks ? $invoice->remarks."\n" : '')."Cancelled: {$reason}");
        $invoice->updated_by = $actorId;
        $invoice->save();

        if ($wasGenerated && $outstanding > 0) {
            $this->ledger->record(
                $invoice->dealer_id,
                null,
                'invoices',
                $invoice->id,
                0,
                $outstanding,
                now()->toDateString(),
                "Invoice {$invoice->invoice_no} cancelled — outstanding reversed",
                $actorId
            );
        }

        ActivityLog::record('invoices', $invoice->id, 'update', $original, $invoice->fresh()->only('status', 'remarks'), "Cancelled — {$reason}");
    }

    /**
     * Admin: "Unlock Invoice" — brings a cancelled invoice back to Draft so
     * it can be corrected and regenerated.
     */
    public function unlock(Invoice $invoice, int $actorId): void
    {
        if ($invoice->status !== 'cancelled') {
            throw ValidationException::withMessages(['status' => 'Only a cancelled invoice can be unlocked.']);
        }

        $original = $invoice->only('status');

        $invoice->status = 'draft';
        $invoice->updated_by = $actorId;
        $invoice->save();

        ActivityLog::record('invoices', $invoice->id, 'update', $original, $invoice->fresh()->only('status'), 'Unlocked for editing');
    }
}
