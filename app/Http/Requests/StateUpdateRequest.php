<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CapitalizesNames;
use Illuminate\Foundation\Http\FormRequest;

class StateUpdateRequest extends FormRequest
{
    use CapitalizesNames;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->capitalizeFields(['name']);
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