<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CapitalizesNames;
use Illuminate\Foundation\Http\FormRequest;

class DispatchUpdateRequest extends FormRequest
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

    /**
     * Header-only fields — booking/quantity detail lives on dispatch_lines
     * and isn't editable after creation (a booking/qty mistake means
     * cancelling the line's Dispatch and re-creating from the plan).
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'driver_name' => ['nullable', 'string', 'max:150'],
            'driver_mobile' => ['nullable', 'string', 'max:15'],
            'lr_number' => ['nullable', 'string', 'max:100'],
            'dispatch_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:dispatch_date'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'gps_location' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:4096'],
            'signature' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
