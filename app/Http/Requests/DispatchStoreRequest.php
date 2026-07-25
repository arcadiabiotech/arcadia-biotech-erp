<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CapitalizesNames;
use App\Models\VehicleAssignment;
use App\Services\DispatchService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DispatchStoreRequest extends FormRequest
{
    use CapitalizesNames;

    /**
     * Authorization is enforced by DispatchPolicy via authorizeResource() in
     * the controller, which runs as route middleware before this request is
     * even resolved — returning true here just defers to that existing gate.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->capitalizeFields(['driver_name']);
    }

    public function rules(): array
    {
        return [
            'vehicle_assignment_id' => ['required', Rule::in($this->allowedAssignmentIds())],
            'driver_name' => ['nullable', 'string', 'max:150'],
            'driver_mobile' => ['nullable', 'string', 'max:15'],
            'lr_number' => ['nullable', 'string', 'max:100'],
            'dispatch_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:dispatch_date'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'gps_location' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:4096'],
            'signature' => ['nullable', 'image', 'max:2048'],
            'lines' => ['required', 'array'],
            'lines.*.qty' => ['nullable', 'integer', 'min:0'],
            'lines.*.extra_qty' => ['nullable', 'integer', 'min:0'],
            'lines.*.qty_per_crate' => ['nullable', 'integer', 'min:1'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:100'],
            'lines.*.plant_age' => ['nullable', 'string', 'max:50'],
            'lines.*.remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_assignment_id.required' => 'Select a vehicle to dispatch.',
            'vehicle_assignment_id.in' => 'Select a vehicle whose loading has been approved and still has quantity left to dispatch.',
        ];
    }

    /**
     * Per-line quantity is checked against that item's live remaining_qty —
     * the same safety net DispatchService::createFromAssignment() enforces,
     * duplicated here so the user sees a field-level error instead of a
     * generic 500/exception.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $assignment = VehicleAssignment::with('items.booking')->find($this->input('vehicle_assignment_id'));

            if (! $assignment) {
                return;
            }

            $itemsById = $assignment->items->keyBy('id');
            $lines = (array) $this->input('lines', []);
            $anyQty = false;

            foreach ($lines as $itemId => $line) {
                $qty = (int) ($line['qty'] ?? 0);

                if ($qty <= 0) {
                    continue;
                }

                $anyQty = true;
                $item = $itemsById->get((int) $itemId);

                if (! $item) {
                    $validator->errors()->add("lines.{$itemId}.qty", 'Invalid booking line.');

                    continue;
                }

                if ($qty > $item->remaining_qty) {
                    $validator->errors()->add("lines.{$itemId}.qty", "Cannot exceed the remaining {$item->remaining_qty} plants for {$item->booking->booking_no}.");
                }
            }

            if (! $anyQty) {
                $validator->errors()->add('lines', 'Select a quantity for at least one booking.');
            }
        });
    }

    /**
     * VehicleAssignments eligible to dispatch from — kept in sync with
     * DispatchService::eligibleAssignments(), which is the same scoping used
     * to populate the create-form picker.
     */
    public function allowedAssignmentIds(): array
    {
        return app(DispatchService::class)->eligibleAssignments($this->user())->pluck('id')->all();
    }
}
