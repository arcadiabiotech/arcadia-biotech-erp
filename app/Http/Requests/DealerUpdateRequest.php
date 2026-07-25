<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CapitalizesNames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealerUpdateRequest extends FormRequest
{
    use CapitalizesNames;

    /**
     * Authorization is enforced by DealerPolicy via authorizeResource() in
     * the controller, which runs as route middleware before this request is
     * even resolved — returning true here just defers to that existing gate
     * instead of duplicating it.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->capitalizeFields(['firm_name', 'dealer_name']);
    }

    public function rules(): array
    {
        $dealerId = $this->route('dealer')?->id;

        return [
            'firm_name' => ['required', 'string', 'max:150'],
            'dealer_name' => ['required', 'string', 'max:150'],
            'mobile' => ['required', 'digits:10', Rule::unique('dealers', 'mobile')->ignore($dealerId)->where(fn ($q) => $q->whereNull('deleted_at'))],
            'whatsapp' => ['nullable', 'digits:10'],
            'email' => ['nullable', 'email', 'max:150'],
            'gst_number' => ['nullable', 'string', 'max:20'],
            'pan_number' => ['nullable', 'string', 'max:20'],
            'state_id' => ['nullable', Rule::exists('states', 'id')],
            'district_id' => ['nullable', Rule::exists('districts', 'id')],
            'taluka_id' => ['nullable', Rule::exists('talukas', 'id')],
            'village_id' => ['nullable', Rule::exists('villages', 'id')],
            'address' => ['nullable', 'string', 'max:500'],
            'pin_code' => ['nullable', 'digits:6'],
            'agreement_date' => ['nullable', 'date'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'firm_name.required' => 'Firm Name is required.',
            'dealer_name.required' => 'Dealer Name is required.',
            'mobile.required' => 'Mobile Number is required.',
            'mobile.digits' => 'Mobile Number must be 10 digits.',
            'mobile.unique' => 'This Mobile Number is already registered.',
            'pin_code.digits' => 'PIN code must be 6 digits.',
        ];
    }
}
