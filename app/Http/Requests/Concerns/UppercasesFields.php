<?php

namespace App\Http\Requests\Concerns;

trait UppercasesFields
{
    /**
     * Uppercases each listed field. Called from prepareForValidation() so
     * both the validated data and old() redisplay after a failed submit are
     * already normalized — the Vehicle model also uppercases vehicle_no on
     * save, but doing it here too keeps the unique-check and old() input
     * consistent with what will actually end up stored.
     */
    protected function uppercaseFields(array $fields): void
    {
        $updates = [];

        foreach ($fields as $field) {
            $value = $this->input($field);

            if (is_string($value) && $value !== '') {
                $updates[$field] = mb_strtoupper($value);
            }
        }

        if ($updates !== []) {
            $this->merge($updates);
        }
    }
}
