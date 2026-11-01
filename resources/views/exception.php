<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>500 · <?= $escape($exceptionClass) ?></title>
    <style><?php require __DIR__ . '/error-styles.php'; ?></style>
</head>
<body>
<div class="shell">
    <header class="masthead">
        <a class="brand" href="/" aria-label="Oktaax home"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="m6 6 6 6-6 6M13 6l6 6-6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>oktaax</a>
        <span class="label">Application diagnostics</span>
        <span class="status-badge"><span class="dot" aria-hidden="true"></span>HTTP 500</span>
    </header>
    <main class="debug-main">
        <div class="debug-topline"><p class="eyebrow">Request interrupted</p><span class="debug-flag">DEBUG MODE</span></div>
        <p class="exception-class"><?= $escape($exceptionClass) ?></p>
        <h1 class="exception-title"><?= $escape(
            $message !== '' ? $message : 'An unexpected exception was thrown.',
        ) ?></h1>
        <div class="debug-request"><span class="method"><?= $escape(
            $requestMethod,
        ) ?></span><span class="request-path"><?= $escape(
    $requestPath,
) ?></span></div>
        <div class="debug-grid">
            <section class="panel" aria-labelledby="source-heading">
                <div class="panel-header"><span id="source-heading">Where it happened</span><span class="panel-count">LINE <?= $escape(
                    $line,
                ) ?></span></div>
                <div class="file-location"><?= $escape(
                    $file,
                ) ?><br><span><?= $escape($exceptionClass) ?></span></div>
                <?php if ($source !== []): ?>
                    <div class="source-code" role="region" aria-label="Source code around the exception" tabindex="0">
                        <?php foreach ($source as $number => $code): ?>
                            <div class="source-line<?= $number === $line
                                ? ' active'
                                : '' ?>"><span class="line-number"><?= $escape(
    $number,
) ?></span><code><?= $escape($code) ?></code></div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="empty-source">Source code is unavailable for this location.</p>
                <?php endif; ?>
            </section>
            <section class="panel" aria-labelledby="trace-heading">
                <div class="panel-header"><span id="trace-heading">Stack trace</span><span class="panel-count"><?= count(
                    $frames,
                ) ?> FRAMES</span></div>
                <?php if ($frames === []): ?>
                    <p class="empty-source">This exception has no additional stack frames.</p>
                <?php else: ?>
                    <ol class="trace-list trace-scroll">
                        <?php foreach ($frames as $index => $frame): ?>
                            <li class="trace-item"><details<?= $index === 0
                                ? ' open'
                                : '' ?>><summary>#<?= $index ?> <?= $escape(
     $frame['call'],
 ) ?></summary><div class="trace-file"><?= $escape(
    $frame['file'] . ($frame['line'] !== null ? ':' . $frame['line'] : ''),
) ?></div></details></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
        </div>
        <p class="debug-note">You’re seeing diagnostics because <code>app.debug</code> is enabled. Disable it before serving public traffic. Request bodies, headers, and trace arguments are omitted.</p>
    </main>
    <footer class="footer"><span>Powered by Oktaax</span><a href="/">Back to home ↗</a></footer>
</div>
</body>
</html>
