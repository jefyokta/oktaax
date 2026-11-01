# Requests and validation

[Guide index](README.md) · [HTTP recipe](../examples/guide/http.php)

## Pick the input source explicitly

| Accessor                                        | Data source                                                     | No-key form               |
| ----------------------------------------------- | --------------------------------------------------------------- | ------------------------- |
| `query($key, $default)` / `get($key, $default)` | Query string                                                    | `query()` returns the map |
| `body($key, $default)`                          | JSON for JSON Content-Type, otherwise native parsed form fields | Entire decoded body       |
| `json($key, $default)`                          | Decode the complete raw body as JSON                            | Entire JSON value         |
| `cookie($key)`                                  | Request cookies                                                 | Key required              |
| `param($key, $default)`                         | Matched route parameters                                        | Key required              |
| `input($key, $default)`                         | Query, then body, then cookies                                  | Key required              |
| `all()`                                         | Query + body + cookies; first source wins                       | Merged map                |
| `parameters()`                                  | Route parameters + query + body; first source wins              | Merged map                |

For duplicate keys, the leftmost source wins. Route parameters do not enter
`input()` or `all()`. `has($key)` distinguishes a present null value from a missing
key; key-based defaults apply only to missing keys. Keys are literal: these
accessors do not implement nested dot-path lookup.

## JSON and form requests

Send `Content-Type: application/json` for JSON; `application/*+json` is supported.
JSON is decoded once per request. Empty objects and arrays decode to PHP arrays;
scalars and null are preserved. An absent raw body returns null. Invalid JSON
raises HTTP 400, including text before or after an otherwise valid JSON value.

```php
$app->post(
    '/echo',
    fn(\Oktaax\Http\Request $request) => [
        'name' => $request->body('name', 'anonymous'),
        'page' => $request->query('page', 1),
    ],
);
```

`json()` explicitly attempts JSON decoding even if Content-Type is different.
`body()` follows Content-Type. Form parsing is provided by the native Swoole
request; the framework does not reinterpret arbitrary raw text as form data.
`$request->request->rawContent()` accesses the native raw body.

## Validation

```php
$app->post('/users', function (\Oktaax\Http\Request $request) {
    return $request->validate([
        'name' => 'required|string',
        'email' => 'required|email',
        'role' => 'in:reader,editor',
        'code' => ['required', 'regex:/^(AB|CD){1,3}$/'],
    ]);
});
```

`validate($rules, $data = null)` reads the request body by default. Pass
`$request->query()` explicitly to validate query parameters. It returns only keys
present in both the input and rules. It does not cast values, create missing
fields, or recursively filter an array field's children. Validate nested payloads
explicitly when you need a nested allowlist.

A scalar JSON body cannot be validated as a field map and produces HTTP 400.
Failed rules raise `ValidationException` and the default response is HTTP 422:

```json
{ "errors": { "name": { "required": "name is required." } } }
```

The `errors` object maps field names to rule/message objects.

| Rule                            | Current semantics                                                           |
| ------------------------------- | --------------------------------------------------------------------------- |
| `required`                      | Rejects missing, null, blank strings, and empty arrays                      |
| `string`, `array`               | Checks PHP type when a non-null value is present                            |
| `email`                         | PHP email validation                                                        |
| `numeric`                       | `is_numeric()`; numeric strings are accepted                                |
| `integer`                       | Numeric value equal to its integer representation; no casting               |
| `boolean`                       | Booleans and string-like true/false/1/0 values; no casting                  |
| `min:n`, `max:n`, `between:a,b` | String byte length, not numeric bounds or array size                        |
| `in:a,b,c`                      | Membership in listed values, using the validator's loose comparison         |
| `regex:/pattern/`               | PHP regular expression; use an array rule list if it contains `             | `   |
| `required_if:other,value`       | Conditionally requires a nonblank scalar field                              |
| `date`                          | A value accepted by PHP `date_create()`                                     |
| `nullable`                      | Marker only; most built-ins already skip null; it does not stop later rules |

Rules run in order without automatic short circuiting or coercion. Use string
length and regex rules on known scalar values; invalid complex types may cause a
PHP type error rather than a validation message. These rules are not a Laravel
validation implementation.

## Custom rules

Register custom rules during bootstrap and avoid yielding inside them: the
validator service belongs to the application and resets its errors after each call.

```php
use Oktaax\Validation\Validator;

$validator = $app->container(Validator::class);
$validator->register('uppercase', function (array $data, string $field) use (
    $validator,
) {
    if (
        isset($data[$field]) &&
        (!is_string($data[$field]) ||
            $data[$field] !== strtoupper($data[$field]))
    ) {
        $validator->addError($field, 'uppercase', 'Use uppercase letters.');
    }
});
// A route can now validate ['code' => 'required|uppercase'].
```

A custom rule receives the data map, field name, and comma-separated parameters.
Use `addError()` to reject a value. Registering a built-in or existing rule name
throws `InvalidArgumentException`.

## Headers, metadata, uploads, and local state

`header($name)` and `hasHeader($name)` normalize header names. `getMethod()`,
`isMethod()`, `path()`, `host()`, `userAgent()`, `isJson()`, and `isAjax()` inspect
request metadata. `$request->server` and `$request->files` expose the native
Swoole arrays through the request wrapper.

For uploads, use `$request->files`, check upload error codes and size, and inspect
the actual temporary file before moving it. The current `file` and `mimes`
validator rules read PHP `$_FILES`; they do **not** validate Swoole's request-owned
file array automatically. Do not rely on those rules to authorize uploads.

You can set request-local properties such as `$request->user = $user`. Register
cleanup through `$request->defer($callback)`. To add reusable methods, register
`$app->inject(Request::class, 'methodName', $callable)` before boot. Helper
arguments are the arguments supplied to that method; the framework does not
prepend the request automatically. See [lifecycle](lifecycle.md).
