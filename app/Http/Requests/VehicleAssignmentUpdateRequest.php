<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CapitalizesNames;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleAssignmentUpdateRequest extends FormRequest
{
    use CapitalizesNames;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->capitalizeFields(['driver_name', 'helper_name']);
    }

    public function rules(): array
    {
        $assignment = $this->route('vehicleAssignment') ?? $this->route('vehicle_assignment');

        return [
            'vehicle_id' => [
                'required',
                Rule::exists('vehicles', 'id'),
                Rule::unique('vehicle_assignments', 'vehicle_id')->where('dispatch_plan_id', $assignment->dispatch_plan_id)->ignore($assignment->id),
            ],
            'marketing_user_id' => ['nullable', Rule::exists('users', 'id')],
            'driver_name' => ['nullable', 'string', 'max:150'],
            'driver_mobile' => ['nullable', 'string', 'max:15'],
            'helper_name' => ['nullable', 'string', 'max:150'],
            'estimated_departure_time' => ['nullable', 'date_format:H:i'],
            'start_km' => ['required', 'integer', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.unique' => 'This vehicle is already assigned to this plan.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $marketingUserId = $this->input('marketing_user_id');

            if ($marketingUserId && ! User::find($marketingUserId)?->hasRole('marketing')) {
                $validator->errors()->add('marketing_user_id', 'Selected user is not a Marketing Officer.');
            }
        });
    }
}
