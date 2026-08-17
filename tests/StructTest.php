<?php

use Oktaax\Utils\Undefined;
use Oktaax\Utils\Struct;
use function Oktaax\Utils\_o;

describe('Struct basic behavior', function () {

    it('creates struct with named arguments', function () {
        $user = _o(name: 'Jefy', age: 22);

        expect($user->name)->toBe('Jefy')
            ->and($user->age)->toBe(22);
    });

    it('throws exception when using positional arguments', function () {
        new Struct('Jefy');
    })->throws(InvalidArgumentException::class);

    it('returns undefined for undefined property', function () {
        $user = _o(name: 'Jefy');

        expect($user->unknown)->toBe(undefined);
    });

    it('supports isset and unset', function () {
        $user = _o(name: 'Jefy');

        expect(isset($user->name))->toBeTrue();

        unset($user->name);

        expect(isset($user->name))->toBeFalse();
    });
});

describe('Closure binding', function () {

    it('binds this correctly inside closure', function () {
        $user = _o(
            name: 'Jefy',
            greet: function () {
                return 'Hello, ' . $this->name;
            }
        );

        expect($user->greet())->toBe('Hello, Jefy');
    });

    it('allows closure to modify object properties', function () {
        $counter = _o(
            count: 0,
            increment: function () {
                $this->count++;
            }
        );

        $counter->increment();
        $counter->increment();

        expect($counter->count)->toBe(2);
    });

    it('supports nested closure calls', function () {
        $user = _o(
            name: 'Jefy',
            getName: function () {
                return $this->name;
            },
            greet: function () {
                return 'Hi ' . $this->getName();
            }
        );

        expect($user->greet())->toBe('Hi Jefy');
    });
});

describe('ArrayAccess behavior', function () {

    it('supports array syntax for get and set', function () {
        $user = _o(name: 'Jefy');

        $user['age'] = 22;

        expect($user['name'])->toBe('Jefy')
            ->and($user['age'])->toBe(22);
    });

    it('supports array push via reference', function () {
        $user = _o(friends: []);

        $user->friends[] = 'Alwik';
        $user->friends[] = 'Rangguy';

        expect($user->friends)->toBe(['Alwik', 'Rangguy']);
    });

    it('throws exception when offset is null', function () {
        $user = _o();

        $user[] = 'invalid';
    })->throws(InvalidArgumentException::class, 'Struct requires string keys.');
});

describe('Object static helpers', function () {

    it('returns keys values and entries', function () {
        $user = _o(name: 'Jefy', age: 22);

        expect(Struct::keys($user))->toBe(['name', 'age'])
            ->and(Struct::values($user))->toBe(['Jefy', 22])
            ->and(Struct::entries($user))->toBe([
                'name' => 'Jefy',
                'age' => 22,
            ]);
    });

    it('checks hasOwn property', function () {
        $user = _o(name: 'Jefy');

        expect(Struct::hasOwn($user, 'name'))->toBeTrue()
            ->and(Struct::hasOwn($user, 'unknown'))->toBeFalse();
    });

    it('assigns properties from multiple sources', function () {
        $target = _o(name: 'Target');
        $source1 = _o(age: 25);
        $source2 = _o(city: 'Pekanbaru');

        Struct::assign($target, $source1, $source2);

        expect($target->name)->toBe('Target')
            ->and($target->age)->toBe(25)
            ->and($target->city)->toBe('Pekanbaru');
    });

    it('overwrites existing properties during assign', function () {
        $target = _o(name: 'Old');
        $source = _o(name: 'New');

        Struct::assign($target, $source);

        expect($target->name)->toBe('New');
    });

    it('creates object from entries', function () {
        $entries = [
            'name' => 'From Entries',
            'age' => 30,
        ];

        $object = Struct::fromEntries($entries);

        expect($object->name)->toBe('From Entries')
            ->and($object->age)->toBe(30);
    });
});

describe('Object.is behavior', function () {

    it('compares identical primitive values', function () {
        expect(Struct::is(1, 1))->toBeTrue()
            ->and(Struct::is('a', 'a'))->toBeTrue()
            ->and(Struct::is(true, true))->toBeTrue();
    });

    it('distinguishes different primitive values', function () {
        expect(Struct::is(1, 2))->toBeFalse()
            ->and(Struct::is('a', 'b'))->toBeFalse()
            ->and(Struct::is(true, false))->toBeFalse();
    });

    it('handles positive and negative zero correctly', function () {
        expect(Struct::is(0.0, -0.0))->toBeFalse()
            ->and(Struct::is(0.0, 0.0))->toBeTrue()
            ->and(Struct::is(-0.0, -0.0))->toBeTrue();
    });

    it('treats NaN as equal to NaN', function () {
        expect(Struct::is(NAN, NAN))->toBeTrue();
    });

    it('compares object references strictly', function () {
        $a = _o(name: 'Jefy');
        $b = _o(name: 'Jefy');
        $c = $a;

        expect(Struct::is($a, $b))->toBeFalse()
            ->and(Struct::is($a, $c))->toBeTrue();
    });
});

describe('defineProperty and defineProperties', function () {

    it('defines read only property', function () {
        $user = _o(name: 'Jefy');

        Struct::defineProperty($user, 'id', [
            'value' => 123,
            'writable' => false,
            'configurable' => false,
            'enumerable' => true,
        ]);

        expect($user->id)->toBe(123);

        $user->id = 456;
    })->throws(Error::class, "Cannot assign to read only property 'id'.");

    it('defines non configurable property', function () {
        $user = _o(name: 'Jefy');

        Struct::defineProperty($user, 'id', [
            'value' => 123,
            'configurable' => false,
        ]);

        unset($user->id);
    })->throws(Error::class, "Cannot delete non-configurable property 'id'.");

    it('defines non enumerable property', function () {
        $user = _o(name: 'Jefy');

        Struct::defineProperty($user, 'secret', [
            'value' => 'hidden',
            'enumerable' => false,
        ]);
        expect(Struct::keys($user))->toBe(['name'])
            ->and(json_encode($user))->toBe('{"name":"Jefy"}');
    });

    it('defines multiple properties at once', function () {
        $user = _o();

        Struct::defineProperties($user, [
            'name' => ['value' => 'Jefy'],
            'age' => ['value' => 22],
        ]);

        Struct::defineProperties($user, _o(
            email: _o(
                value: "jefyokta50@gmail.com",
            ),
            phone_number: _o(
                value: "082255267294"
            )
        ));

        expect($user->name)->toBe('Jefy')
            ->and($user->age)->toBe(22)
            ->and($user->email)->toBe("jefyokta50@gmail.com");
    });
});

describe('Object immutability', function () {

    it('prevents extensions', function () {
        $object = _o(name: 'Test');

        Struct::preventExtensions($object);

        expect(Struct::isExtensible($object))->toBeFalse();

        $object->newProp = 'Hello';
    })->throws(Error::class, "Cannot add property 'newProp', object is not extensible.");

    it('still allows modifying existing properties after preventExtensions', function () {
        $object = _o(name: 'Test');

        Struct::preventExtensions($object);

        $object->name = 'Updated';

        expect($object->name)->toBe('Updated');
    });

    it('seals object', function () {
        $object = _o(name: 'Seal Test');

        Struct::seal($object);

        expect(Struct::isSealed($object))->toBeTrue();

        unset($object->name);
    })->throws(Error::class, "Cannot delete non-configurable property 'name'.");

    it('still allows modifying writable properties after seal', function () {
        $object = _o(name: 'Seal Test');

        Struct::seal($object);

        $object->name = 'Updated';

        expect($object->name)->toBe('Updated');
    });

    it('freezes object', function () {
        $object = _o(name: 'Freeze Test', age: 20);

        Struct::freeze($object);

        expect(Struct::isFrozen($object))->toBeTrue();

        $object->age = 21;
    })->throws(Error::class, 'Cannot modify frozen object.');

    it('prevents deleting properties after freeze', function () {
        $object = _o(name: 'Freeze Test');

        Struct::freeze($object);

        unset($object->name);
    })->throws(Error::class, 'Cannot modify frozen object.');
});

describe('Iteration and serialization', function () {

    it('iterates only enumerable properties', function () {
        $user = _o(name: 'Jefy');

        Struct::defineProperty($user, 'secret', [
            'value' => 'hidden',
            'enumerable' => false,
        ]);

        $result = [];

        foreach ($user as $key => $value) {
            $result[$key] = $value;
        }

        expect($result)->toBe(['name' => 'Jefy']);
    });

    it('serializes to json correctly', function () {
        $user = _o(name: 'Jefy', age: 22);

        expect(json_encode($user))->toBe('{"name":"Jefy","age":22}');
    });

    it('casts to string as pretty json', function () {
        $user = _o(name: 'Jefy');

        expect((string) $user)->toContain('"name"')
            ->toContain('Jefy');
    });

    it('returns debug info without internal properties', function () {
        $user = _o(name: 'Jefy');

        expect($user->__debugInfo())->toBe(['name' => 'Jefy']);
    });
});
