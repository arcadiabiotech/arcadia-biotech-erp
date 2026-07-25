<?php

namespace App\Support;

class TextCasing
{
    /**
     * Uppercases the first letter of every word (proper Title Case) while
     * leaving every other character untouched — so "state bank of india"
     * becomes "State Bank Of India" but existing mid-word casing like
     * "McArthur" or "pH Meter" (where that matters) is never lowercased out.
     */
    public static function titleCase(string $value): string
    {
        return preg_replace_callback('/(^|\s)(\S)/u', fn (array $m) => $m[1].mb_strtoupper($m[2]), $value);
    }

    /**
     * Uppercases only the very first character of the whole string, so
     * casing further into the value (e.g. "pH Meter") survives untouched.
     * Used for free-text item/product names where word-by-word Title Case
     * would incorrectly re-case abbreviations mid-value.
     */
    public static function capitalizeFirst(string $value): string
    {
        return mb_strtoupper(mb_substr($value, 0, 1)).mb_substr($value, 1);
    }
}
