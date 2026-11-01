<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $escape($status) ?> · <?= $escape($title) ?></title>
    <style><?php require __DIR__ . '/error-styles.php'; ?></style>
</head>
<body>
<div class="shell">
    <header class="masthead">
        <a class="brand" href="/" aria-label="Oktaax home"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="m6 6 6 6-6 6M13 6l6 6-6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>oktaax</a>
        <span class="label">A small pause in the journey</span>
        <span class="status-badge"><span class="dot" aria-hidden="true"></span>HTTP <?= $escape(
            $status,
        ) ?></span>
    </header>
    <main class="http-main">
        <div class="status-art" aria-hidden="true">
            <svg class="route-art" viewBox="0 0 460 320" fill="none"><path d="M25 260h85q25 0 25-25v-150q0-25 25-25h90q25 0 25 25v150q0 25 25 25h135" stroke="currentColor" stroke-width="1.5" stroke-dasharray="4 7"/><circle cx="25" cy="260" r="5" fill="currentColor"/><circle cx="435" cy="260" r="7" stroke="currentColor" stroke-width="1.5"/><circle cx="275" cy="85" r="24" fill="#d9ed9b"/><path d="m269 79 12 12m0-12-12 12" stroke="#245b43" stroke-width="2"/></svg>
            <span class="status-code"><?= $escape($status) ?></span>
            <span class="status-note">THE REQUEST ENDS HERE. YOUR JOURNEY DOESN’T.</span>
        </div>
        <section class="http-copy" aria-labelledby="error-title">
            <p class="eyebrow"><?= $escape($title) ?></p>
            <h1 id="error-title"><?= match ($status) {
                404 => 'A little off the map.',
                401 => 'Let’s get you signed in.',
                403 => 'This door is closed.',
                429 => 'Let’s take a breath.',
                503 => 'Back in a moment.',
                default => $status >= 500
                    ? 'A pause, not the end.'
                    : 'We couldn’t complete that.',
            } ?></h1>
            <p class="description"><?= $escape($description) ?></p>
            <div class="actions">
                <a class="button" href="/">Back to home <span aria-hidden="true">↗</span></a>
                <?php if (
                    $status >= 500 &&
                    str_starts_with($requestPath, '/') &&
                    !str_starts_with($requestPath, '//')
                ): ?>
                    <a class="text-link" href="<?= $escape(
                        $requestPath,
                    ) ?>">Try this page again</a>
                <?php endif; ?>
            </div>
            <div class="request-strip"><span class="method"><?= $escape(
                $requestMethod,
            ) ?></span><span class="request-path"><?= $escape(
    $requestPath,
) ?></span></div>
        </section>
    </main>
    <footer class="footer"><span>Powered by Oktaax</span><span><?= $escape(
        $status,
    ) ?> / <?= $escape($title) ?></span></footer>
</div>
</body>
</html>
