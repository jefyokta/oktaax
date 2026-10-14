
<?php

use Oktaax\Console;
use Oktaax\Core\Container;
use Oktaax\Event\Event;
use Oktaax\Event\EventEmitter;
use Oktaax\Http\Request;
use Oktaax\Http\Support\SSEResponse;
use Oktaax\Oktaax;

require_once __DIR__ . "/../vendor/autoload.php";

$app = new Oktaax;

//while using singleton of EventEmitter, it suggest to use single worker so each request handled by the same worker and same EventEmitter instance
$app->setServer("worker_num", 1);

// SSE endpoint
$app->get("/stream", function (Request $req) {
    $sse = new SSEResponse();
    $emitter = Container::get(EventEmitter::class);

    $listener =  function (Event $event) use ($sse) {
        $sse->send($event->data, [
            "event" => $event->name,
        ]);
    };
    $emitter->on("test", $listener);
    $req->onClose(function () use ($emitter, $listener, $sse) {
        $emitter->off("test", $listener);
        $sse->stop();
    });


    $sse->on("start", function (Event $event) use ($emitter, $listener, $sse) {
        $sse->send('sse started', ["event" => "connected"]);
        echo $emitter->listenerCount('test') . "\n";
        echo "SSE started\n";
    });

    $sse->on("close", function (Event $event) use ($emitter, $listener) {
        $emitter->off("test", $listener);
        Console::log($event);
        echo "SSE client closed\n";
    });

    $sse->start();
});

// Trigger event
$app->get("/test", function () {
    $emitter = Container::get(EventEmitter::class);

    $emitter->emit("test",        [
        "message" => "Hello from Oktaax!",
        "time" => date("H:i:s"),
    ]);

    return "Event dispatched";
});

$app->get("/", function () {
    return <<<'HTML'
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oktaax SSE Test</title>
    <style>
        body {
            font-family: system-ui, sans-serif;
            max-width: 640px;
            margin: 60px auto;
            padding: 0 20px;
            color: #222;
        }

        button {
            padding: 10px 16px;
            border: 0;
            border-radius: 6px;
            background: #2563eb;
            color: white;
            cursor: pointer;
        }

        button:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        pre {
            margin-top: 20px;
            padding: 16px;
            min-height: 150px;
            overflow: auto;
            background: #f3f4f6;
            border-radius: 8px;
            white-space: pre-wrap;
        }

        #status {
            margin: 16px 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <h1>Oktaax SSE Test</h1>
    <p>Test real-time event menggunakan Server-Sent Events.</p>

    <div id="status">Menghubungkan...</div>

    <button id="trigger" disabled>Kirim Event</button>
    <button id="clear">Bersihkan Log</button>

    <pre id="output"></pre>

    <script>
        const output = document.querySelector("#output");
        const status = document.querySelector("#status");
        const trigger = document.querySelector("#trigger");

        function log(message) {
            output.textContent += message + "\n";
        }

        const source = new EventSource("/stream");

        source.onopen = () => {
            status.textContent = "Terhubung";
            trigger.disabled = false;
            log("[SSE] Connected");
        };

        source.onerror = () => {
            status.textContent = "Koneksi terputus / mencoba menghubungkan ulang...";
            trigger.disabled = true;
        };

        source.addEventListener("test", (event) => {
            const data = JSON.parse(event.data);
            log("[EVENT: test] " + JSON.stringify(data, null, 2));
        });

        trigger.addEventListener("click", async () => {
            trigger.disabled = true;

            try {
                const response = await fetch("/test");
                log("[HTTP] " + await response.text());
            } catch (error) {
                log("[ERROR] " + error.message);
            } finally {
                trigger.disabled = source.readyState !== EventSource.OPEN;
            }
        });

        document.querySelector("#clear").addEventListener("click", () => {
            output.textContent = "";
        });
    </script>
</body>
</html>
HTML;
});

$app->listen(3000);
