<?php

use App\Support\TextCasing;

test('titleCase capitalizes every word without disturbing existing mid-word casing', function () {
    expect(TextCasing::titleCase('ramesh kumar patel'))->toBe('Ramesh Kumar Patel');
    expect(TextCasing::titleCase('state bank of india'))->toBe('State Bank Of India');
    expect(TextCasing::titleCase('McArthur'))->toBe('McArthur');
    expect(TextCasing::titleCase('pH Meter'))->toBe('PH Meter');
});

test('capitalizeFirst only uppercases the very first character', function () {
    expect(TextCasing::capitalizeFirst('pH meter'))->toBe('PH meter');
    expect(TextCasing::capitalizeFirst('mcarthur'))->toBe('Mcarthur');
});
