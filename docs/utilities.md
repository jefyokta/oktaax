# Console and utility objects

[Guide index](README.md) · [API reference](api-reference.md)

The utilities are optional and independent from route/lifecycle composition.
`src/Utils/Types` remains because `Struct` uses its runtime guards.

## Console

```php
use Oktaax\Console;

Console::info('Listening on %s', '127.0.0.1:3000');
Console::warn('A retry is scheduled');
Console::error('Operation failed');
Console::table([['name' => 'Ada', 'role' => 'editor']]);
```

`log`, `info`, `warn`, `error`, and `debug` format terminal output. `dir($value,
$depth = 3)` inspects nested data; `group`/`groupEnd` group output; `time($label)`
and `timeEnd($label)` measure a labeled operation. Formatting supports `%s`, `%d`,
`%f`, `%o`, and `%j`. Console output is process-local terminal logging, not a
structured logging backend or a request correlation system.

Run `php examples/console.php` for the standalone demonstration.

## Struct and helpers

```php
use Oktaax\Utils\Struct;
use function Oktaax\Utils\_o;
use function Oktaax\Utils\getter;

$person = _o(
    first: 'Ada',
    last: 'Lovelace',
    fullName: getter(function () {
        return $this->first . ' ' . $this->last;
    }),
);
echo $person->fullName;
$person->first = 'Augusta';
$keys = Struct::keys($person);
```

`_o(...$properties)` constructs a `Struct` using named properties. Struct supports
property/array access, iteration, and JSON serialization. Function-valued
properties and getters bind to the Struct instance. Keep these objects in an
appropriate application/worker/request scope when they contain mutable state.

`Struct::strict($propertyTypes, ...$properties)` adds runtime type checks:

```php
$settings = Struct::strict(
    ['name' => 'string', 'count' => 'int'],
    name: 'Demo',
    count: 1,
);
```

`Utils\Types\ArrayOf::type('string')` is an array element guard. `Struct` also
provides `keys`, `values`, `entries`, `fromEntries`, `assign`, `hasOwn`, `clone`,
`preventExtensions`, `seal`, `freeze`, and property descriptor APIs. Freezing is
an object property restriction, not a concurrency/transaction mechanism.
The [standalone Struct example](../examples/struct.php) demonstrates nested
objects and methods.

`Oktaax\Utils\Boolean($value)` consults `CanConvertToBoolean` objects, otherwise
uses PHP truthiness. `Undefined::get()` returns the utility's undefined sentinel;
the helper file also defines the global `undefined` constant. These are not HTTP
input validation or missing-key checks.

## Invoker

`Utils\Invoker` is a separate reflection utility. Use `addContext($object)` for
typed context values, `setPositional($arguments)` for fallback arguments, and
`call($callback)` to invoke. Its invocation context resets after a call; populate
it again for another call. See [examples/invoker.php](../examples/invoker.php).
The HTTP routing pipeline does not use Invoker for automatic service injection.

Scoped `app()`, timer helpers, and `fetch()` are documented in
[lifecycle](lifecycle.md), [tasks/events](tasks-events.md), and
[HTTP client](http-client.md). `oktaax()` constructs an `Oktaax`. Register
WebSocket endpoints with `$app->websocket($path)`; no special factory or trait
is required.
