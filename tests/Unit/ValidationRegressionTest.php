<?php

use Oktaax\Validation\Validator;

it('uses all allowed values and both between bounds', function () {
    $validator = new Validator();
    expect(
        $validator->validate(
            ['color' => 'blue', 'name' => 'Ada'],
            [
                'color' => 'in:red,blue',
                'name' => 'between:2,5',
            ],
        ),
    )->toBe([]);

    $errors = $validator->validate(
        ['color' => 'green', 'name' => 'A'],
        [
            'color' => 'in:red,blue',
            'name' => 'between:2,5',
        ],
    );
    expect($errors)->toHaveKeys(['color', 'name']);
});

it(
    'supports regex rules containing commas or pipes using an array rule list',
    function () {
        $validator = new Validator();
        expect(
            $validator->validate(
                ['code' => 'AB'],
                ['code' => ['required', 'regex:/^(AB|[A-Z]{2,3})$/']],
            ),
        )->toBe([]);
        expect(
            $validator->validate(
                ['code' => 'A'],
                ['code' => ['regex:/^[A-Z]{2,3}$/']],
            ),
        )->toHaveKey('code');
    },
);

it(
    'handles required array values without throwing and clears errors after a rule exception',
    function () {
        $validator = new Validator();
        expect(
            $validator->validate(
                ['items' => ['one']],
                ['items' => 'required|array'],
            ),
        )->toBe([]);
        expect(
            $validator->validate(
                ['items' => []],
                ['items' => 'required|array'],
            ),
        )->toHaveKey('items');
        expect(
            fn() => $validator->validate([], ['name' => 'required|unknown']),
        )->toThrow(InvalidArgumentException::class);
        expect(
            $validator->validate(['name' => 'Ada'], ['name' => 'required']),
        )->toBe([]);
    },
);
