<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StateUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('state')->id;

        return [

            'name' => 'required|string|max:100|unique:states,name,' . $id,

            'code' => 'nullable|string|max:10|unique:states,code,' . $id,

        ];
    }
}