<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100|unique:states,name',
            'code' => 'nullable|string|max:10|unique:states,code',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'State Name is required.',
            'name.unique'   => 'State already exists.',
            'code.unique'   => 'State Code already exists.',
        ];
    }
}