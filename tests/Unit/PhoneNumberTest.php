<?php

use App\Support\PhoneNumber;

it('normalizes Egyptian mobiles to 01XXXXXXXXX', function (string $input) {
    expect(PhoneNumber::normalize($input))->toBe('01012345678');
})->with(['01012345678', '+201012345678', '00201012345678', '201012345678', '1012345678', '010 1234-5678']);

it('validates mobile prefixes', function (string $input, bool $valid) {
    expect(PhoneNumber::isValidMobile($input))->toBe($valid);
})->with([['01012345678', true], ['01512345678', true], ['01312345678', false], ['0223456789', false], ['abc', false]]);
