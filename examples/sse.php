
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Oktaax\Http\Support\SSEResponse;

$app = new Oktaax\Oktaax();

$app->get('/stream', function () {
    return new SSEResponse(
        callback: static function () {
            static $progress = 0;

            if ($progress > 100) {
                return [
                    'comment' => 'Completed',
                ];
            }

            $progress += 10;

            return [
                'event' => 'progress',
                'id' => (string) $progress,
                'retry' => 3000,
                'data' => [
                    'progress' => min($progress, 100),
                    'message' => $progress >= 100
                        ? 'Completed'
                        : 'Processing...',
                ],
            ];
        },
        interval: 1,
    );
});

$app->get('/', function () {
    $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oktaax SSE Demo</title>
    <style>
        body {
            max-width: 600px;
            margin: 60px auto;
            padding: 0 20px;
            font-family: system-ui, sans-serif;
        }

        progress {
            width: 100%;
            height: 24px;
        }

        #status {
            color: #666;
        }
    </style>
</head>
<body>
    <h1>SSE Progress Demo</h1>

    <progress id="progress" value="0" max="100"></progress>
    <p id="percentage">0%</p>
    <p id="status">Connecting...</p>

    <script>
        const progress = document.querySelector('#progress');
        const percentage = document.querySelector('#percentage');
        const status = document.querySelector('#status');

        const source = new EventSource('/stream');

        source.addEventListener('progress', (event) => {
            const data = JSON.parse(event.data);

            progress.value = data.progress;
            percentage.textContent = `${data.progress}%`;
            status.textContent = data.message;

            if (data.progress >= 100) {
                source.close();
                status.textContent = 'Completed';
            }
        });

        source.onopen = () => {
            status.textContent = 'Connected';
        };

        source.onerror = () => {
            status.textContent = source.readyState === EventSource.CLOSED
                ? 'Connection closed'
                : 'Reconnecting...';
        };
    </script>
</body>
</html>
HTML;

    return $html;
});


$app->listen(3000);