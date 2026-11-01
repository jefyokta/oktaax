<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Inertia payload · Oktaax</title></head>
<body>
    <div id="app" data-page="<?= htmlspecialchars(
        json_encode($page, JSON_THROW_ON_ERROR),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8',
    ) ?>">
        <h1>Inertia page payload</h1>
        <p>This example provides the server payload. Mount your own Inertia frontend on this element.</p>
        <pre><?= htmlspecialchars(
            json_encode($page, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        ) ?></pre>
    </div>
</body>
</html>
