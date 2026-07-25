<?php

namespace App\Http\Requests\Concerns;

use App\Support\TextCasing;

trait CapitalizesNames
{
    /**
     * Title-cases each listed field (every word's first letter capitalized)
     * — for proper-noun fields like person/firm/place names. Called from
     * prepareForValidation() so both the validated data and whatever the
     * controller reads back via $request->input() are already normalized.
     */
    protected function capitalizeFields(array $fields): void
    {
        $this->applyCasing($fields, TextCasing::titleCase(...));
    }

    /**
     * Capitalizes just the first character of each listed field — not full
     * Title Case — so casing further into the value (e.g. "pH Meter") isn't
     * mangled. For free-text item/product names, not proper nouns.
     */
    protected function capitalizeFirstLetterOnly(array $fields): void
    {
        $this->applyCasing($fields, TextCasing::capitalizeFirst(...));
    }

    private function applyCasing(array $fields, callable $caseFn): void
    {
        $updates = [];

        foreach ($fields as $field) {
            $value = $this->input($field);

            if (is_string($value) && $value !== '') {
                $updates[$field] = $caseFn($value);
            }
        }

        if ($updates !== []) {
            $this->merge($updates);
        }
    }
}
