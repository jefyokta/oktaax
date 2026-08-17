<?php

use function Oktaax\Utils\Boolean;

it("boolean on boolean", function () {

    expect(Boolean(true))->toBe(true);
    expect(Boolean(false))->toBe(false);
});



it("array  on boolean", function () {

    expect(Boolean([1]))->toBe(true);
    expect(Boolean([]))->toBe(false);
});

it("integer on boolean", function () {

    expect(Boolean(1))->toBe(true);
    expect(Boolean(0))->toBe(false);
});
it("string  on boolean", function () {
    expect(Boolean("string"))->toBe(true);
    expect(Boolean(""))->toBe(false);
});

it("convertable to boolean object  on boolean", function () {
    expect(Boolean(undefined))->toBe(false);
    expect((bool) undefined)->toBeTrue();
});
