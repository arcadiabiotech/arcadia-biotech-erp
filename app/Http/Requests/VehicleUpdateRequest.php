<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CapitalizesNames;
use App\Http\Requests\Concerns\UppercasesFields;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class VehicleUpdateRequest extends FormRequest
{
    use CapitalizesNames, UppercasesFields;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->capitalizeFields(['driver_name', 'transport_company']);
        $this->uppercaseFields(['vehicle_no']);
    }

    public function rules(): array
    {
        return [
            'vehicle_no' => ['required', 'string', 'max:50', Rule::unique('vehicles', 'vehicle_no')->ignore($this->route('vehicle'))],
            'vehicle_type' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'string', 'max:100'],
            'driver_name' => ['nullable', 'string', 'max:150'],
            'driver_mobile' => ['nullable', 'string', 'max:15'],
            'transport_company' => ['nullable', 'string', 'max:150'],
            'cost_per_km' => ['required', 'numeric', 'min:0'],
            'driver_allowance' => ['nullable', 'numeric', 'min:0'],
            'fuel_type' => ['nullable', 'string', 'max:50'],
            'average_mileage' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'boolean'],
        ];
    }
}
