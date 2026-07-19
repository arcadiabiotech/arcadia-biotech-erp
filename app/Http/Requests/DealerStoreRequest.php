<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DealerStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation Rules
     */
    public function rules(): array
    {
        return [

            'firm_name' => 'required|string|max:150',

            'dealer_name' => 'required|string|max:150',

            'mobile' => 'required|digits:10|unique:dealers,mobile',

            'whatsapp' => 'nullable|digits:10',

            'email' => 'nullable|email',

            'gst_number' => 'nullable|max:20',

            'pan_number' => 'nullable|max:20',

            'address' => 'nullable|max:500',

        ];
    }

    /**
     * Custom Messages
     */
    public function messages(): array
    {
        return [

            'firm_name.required' => 'Firm Name is required.',

            'dealer_name.required' => 'Dealer Name is required.',

            'mobile.required' => 'Mobile Number is required.',

            'mobile.digits' => 'Mobile Number must be 10 digits.',

            'mobile.unique' => 'This Mobile Number is already registered.',

        ];
    }
}