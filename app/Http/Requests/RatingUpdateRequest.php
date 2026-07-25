<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RatingUpdateRequest extends FormRequest
{
    /**
     * Authorization is enforced by the role:super-admin route middleware,
     * which runs before this request is resolved.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'score' => ['required', 'integer', 'min:0', 'max:10'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'score.required' => 'Manual score is required.',
            'reason.required' => 'A reason for the manual change is required.',
        ];
    }
}
