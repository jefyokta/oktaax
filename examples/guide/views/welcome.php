<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Welcome · Oktaax</title></head>
<body>
    <h1>Hello, <?= htmlspecialchars(
        (string) $name,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8',
    ) ?>.</h1>
    <p>This page is rendered with <code>Oktaax\View\PhpView</code>.</p>
    <nav><a href="/json">JSON</a> · <a href="/stream">Stream</a> · <a href="/download">Download</a></nav>
</body>
</html>
