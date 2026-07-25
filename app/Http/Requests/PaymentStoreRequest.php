<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CapitalizesNames;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentStoreRequest extends FormRequest
{
    use CapitalizesNames;

    /**
     * Authorization is enforced by PaymentPolicy via authorizeResource() in
     * the controller, which runs as route middleware before this request is
     * even resolved — returning true here just defers to that existing gate.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->capitalizeFields(['bank_name']);
    }

    public function rules(): array
    {
        return [
            'invoice_id' => ['required', Rule::in($this->allowedInvoiceIds())],
            'payment_date' => ['required', 'date'],
            'payment_mode' => ['required', Rule::in(Payment::PAYMENT_MODES)],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_id.required' => 'Invoice is required.',
            'invoice_id.in' => 'Select a generated or partially paid invoice.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $invoice = Invoice::find($this->input('invoice_id'));

            if (! $invoice) {
                return;
            }

            $amount = (float) $this->input('amount');

            if ($amount > (float) $invoice->balance_amount) {
                $validator->errors()->add('amount', "Payment amount cannot exceed the outstanding balance (₹{$invoice->balance_amount}).");
            }

            if ($this->input('payment_mode') !== 'Cash' && ! $this->filled('reference_no')) {
                $validator->errors()->add('reference_no', 'Reference number is required for non-cash payment modes.');
            }
        });
    }

    /**
     * Invoices eligible to receive a payment: Generated or Partially Paid,
     * visible to this user (Dealer-role scoped to their own dealer_id) —
     * kept in sync with PaymentController::eligibleInvoices(), which is the
     * same scoping used to populate the create-form dropdown.
     */
    public function allowedInvoiceIds(): array
    {
        $user = $this->user();

        return Invoice::query()
            ->whereIn('status', ['generated', 'partially_paid'])
            ->when($user->hasRole('dealer'), fn ($q) => $q->where('dealer_id', $user->dealer_id))
            ->when(! $user->hasRole(['super-admin', 'admin', 'accounts', 'dealer']), fn ($q) => $q->whereRaw('1 = 0'))
            ->pluck('id')
            ->all();
    }
}
