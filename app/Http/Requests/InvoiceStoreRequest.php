<?php

namespace App\Http\Requests;

use App\Models\Dispatch;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class InvoiceStoreRequest extends FormRequest
{
    /**
     * Authorization is enforced by InvoicePolicy via authorizeResource() in
     * the controller, which runs as route middleware before this request is
     * even resolved — returning true here just defers to that existing gate.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dispatch_id' => ['required', 'exists:dispatches,id'],
            'dealer_id' => ['required', 'exists:dealers,id'],
            'invoice_date' => ['required', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'dispatch_id.required' => 'Dispatch is required.',
        ];
    }

    /**
     * A Dispatch is now dealer-grouped, so eligibility is a (dispatch,
     * dealer) pair rather than a single dispatch_id — the same dispatch can
     * appear again for a different dealer that hasn't been invoiced yet.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $pair = $this->input('dispatch_id').':'.$this->input('dealer_id');

            if (! in_array($pair, $this->allowedPairs(), true)) {
                $validator->errors()->add('dispatch_id', 'Select a completed dispatch/dealer combination that does not already have an invoice.');
            }
        });
    }

    /**
     * Kept in sync with InvoiceController::eligibleDealerGroups(), which is
     * the same scoping used to populate the create-form dropdown.
     */
    public function allowedPairs(): array
    {
        $invoicedPairs = Invoice::withTrashed()->get(['dispatch_id', 'dealer_id'])
            ->map(fn ($invoice) => $invoice->dispatch_id.':'.$invoice->dealer_id);

        return Dispatch::where('status', 'completed')
            ->with('lines')
            ->get()
            ->flatMap(fn (Dispatch $dispatch) => $dispatch->lines->pluck('dealer_id')->unique()->map(fn ($dealerId) => "{$dispatch->id}:{$dealerId}"))
            ->diff($invoicedPairs)
            ->values()
            ->all();
    }
}
