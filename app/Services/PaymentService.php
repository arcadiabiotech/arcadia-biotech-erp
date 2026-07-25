<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Owns payment_no generation and "Receive Payment" — the one place a
 * payment is created, an invoice's paid_amount/balance/status are updated,
 * and the dealer's ledger is credited (Automation: Payment Received ->
 * Ledger Credit -> Outstanding Auto Calculate).
 */
class PaymentService
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    /**
     * PAY-YYYY-000001, sequence resets each calendar year. Payments have no
     * soft deletes, so no withTrashed() is needed to avoid reissuing a number.
     */
    public function nextPaymentNo(): string
    {
        $prefix = 'PAY-'.now()->year.'-';

        $maxNumber = Payment::query()
            ->where('payment_no', 'like', "{$prefix}%")
            ->selectRaw('MAX(CAST(SUBSTRING(payment_no, ?) AS UNSIGNED)) as max_number', [strlen($prefix) + 1])
            ->value('max_number');

        return $prefix.str_pad(((int) $maxNumber) + 1, 6, '0', STR_PAD_LEFT);
    }

    public function receive(Invoice $invoice, array $data, User $actor): Payment
    {
        if (! in_array($invoice->status, ['generated', 'partially_paid'], true)) {
            throw ValidationException::withMessages(['invoice_id' => 'Payments can only be received against a generated or partially paid invoice.']);
        }

        $amount = round((float) $data['amount'], 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
        }

        if ($amount > (float) $invoice->balance_amount) {
            throw ValidationException::withMessages(['amount' => 'Payment amount cannot exceed the outstanding balance.']);
        }

        $payment = Payment::create([
            'payment_no' => $this->nextPaymentNo(),
            'invoice_id' => $invoice->id,
            'dealer_id' => $invoice->dealer_id,
            'farmer_id' => null,
            'payment_date' => $data['payment_date'],
            'payment_mode' => $data['payment_mode'],
            'reference_no' => $data['reference_no'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'amount' => $amount,
            'remarks' => $data['remarks'] ?? null,
            'created_by' => $actor->id,
        ]);

        $originalInvoice = $invoice->only('paid_amount', 'balance_amount', 'status');

        $newBalance = round((float) $invoice->grand_total - ((float) $invoice->paid_amount + $amount), 2);

        $invoice->paid_amount = round((float) $invoice->paid_amount + $amount, 2);
        $invoice->status = $newBalance <= 0 ? 'paid' : 'partially_paid';
        $invoice->updated_by = $actor->id;
        $invoice->save();

        ActivityLog::record('payments', $payment->id, 'create', [], $payment->toArray());
        ActivityLog::record(
            'invoices',
            $invoice->id,
            'update',
            $originalInvoice,
            $invoice->fresh()->only('paid_amount', 'balance_amount', 'status'),
            "Payment received — {$payment->payment_no} ₹".number_format($amount, 2)
        );

        $this->ledger->record(
            $invoice->dealer_id,
            null,
            'payments',
            $payment->id,
            0,
            $amount,
            $data['payment_date'],
            "Payment {$payment->payment_no} received against {$invoice->invoice_no}",
            $actor->id
        );

        if ($invoice->status === 'paid') {
            foreach ($invoice->lines as $line) {
                $line->booking()->update(['invoice_status' => 'completed', 'updated_by' => $actor->id]);
            }
        }

        return $payment;
    }
}
