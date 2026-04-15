<?php

/**
 * Streaming Responses Example
 *
 * This example demonstrates various streaming response patterns
 * including chunked responses, server-sent events, and async streaming.
 */

require 'vendor/autoload.php';

use Oktaax\Core\Promise\Promise;
use Oktaax\Oktaax;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use function Oktaax\Utils\async;
use function Oktaax\Utils\await;
use function Oktaax\Utils\setTimeout;

$app = new Oktaax();

// Chunked text streaming
$app->get('/stream/text', function (Request $request, Response $response) {
    $response->header('Content-Type', 'text/plain')
        ->header('Transfer-Encoding', 'chunked');

    // Simulate streaming text data
    $response->write("Starting text stream...\n");

    for ($i = 1; $i <= 10; $i++) {
        $response->write("Chunk $i: " . str_repeat('Line ' . $i . ' - ', 5) . "\n");
        sleep(1); // Simulate processing time
    }

    $response->write("Stream complete!\n");
    $response->end();
});

// JSON streaming (newline-delimited JSON)
$app->get('/stream/json', function (Request $request, Response $response) {
    $response->header('Content-Type', 'application/x-ndjson')
        ->header('Cache-Control', 'no-cache');

    $response->write("{\"type\":\"start\",\"message\":\"Starting JSON stream\"}\n");

    for ($i = 1; $i <= 5; $i++) {
        $data = [
            'type' => 'data',
            'id' => $i,
            'timestamp' => date('c'),
            'value' => rand(1, 100),
            'message' => "Data point $i"
        ];

        $response->write(json_encode($data) . "\n");
        sleep(2);
    }

    $response->write("{\"type\":\"end\",\"message\":\"Stream complete\"}\n");
    $response->end();
});

// Server-Sent Events (SSE)
$app->get('/stream/events', function (Request $request, Response $response) {
    $response->header('Content-Type', 'text/event-stream')
        ->header('Cache-Control', 'no-cache')
        ->header('Connection', 'keep-alive');

    $response->write("event: start\n");
    $response->write("data: Stream started\n\n");

    for ($i = 1; $i <= 10; $i++) {
        $response->write("event: message\n");
        $response->write("data: " . json_encode([
            'id' => $i,
            'time' => date('H:i:s'),
            'message' => "Event $i",
            'random' => rand(1, 1000)
        ]) . "\n\n");

        sleep(1);
    }

    $response->write("event: end\n");
    $response->write("data: Stream ended\n\n");
    $response->end();
});

// File streaming (large file download)
$app->get('/stream/file/{filename}', function (Request $request, Response $response) {
    $filename = $request->params['filename'];
    $filepath = __DIR__ . '/files/' . $filename;

    if (!file_exists($filepath)) {
        return $response->status(404)->json(['error' => 'File not found']);
    }

    $fileSize = filesize($filepath);
    $mimeType = mime_content_type($filepath);

    $response->header('Content-Type', $mimeType)
        ->header('Content-Length', $fileSize)
        ->header('Accept-Ranges', 'bytes');

    // Handle range requests for resumable downloads
    $rangeHeader = $request->header('Range');
    if ($rangeHeader && str_starts_with($rangeHeader, 'bytes=')) {
        $range = substr($rangeHeader, 6);
        list($start, $end) = explode('-', $range);

        $start = (int) $start;
        $end = $end ? (int) $end : $fileSize - 1;

        if ($start >= $fileSize || $end >= $fileSize) {
            return $response->status(416)->header('Content-Range', "bytes */$fileSize")->end();
        }

        $response->status(206)
            ->header('Content-Range', "bytes $start-$end/$fileSize")
            ->header('Content-Length', $end - $start + 1);

        $handle = fopen($filepath, 'rb');
        fseek($handle, $start);

        while (!feof($handle) && ftell($handle) <= $end) {
            $chunk = fread($handle, 8192);
            $response->write($chunk);
        }

        fclose($handle);
    } else {
        // Stream entire file
        $handle = fopen($filepath, 'rb');
        while (!feof($handle)) {
            $chunk = fread($handle, 8192);
            $response->write($chunk);
        }
        fclose($handle);
    }

    $response->end();
});

// Async streaming with promises
$app->get('/stream/async', async(function (Request $request, Response $response) {
    $response->header('Content-Type', 'text/plain')
        ->header('Transfer-Encoding', 'chunked');

    $response->write("Starting async stream...\n");

    // Simulate multiple async operations
    $promises = [];
    for ($i = 1; $i <= 3; $i++) {
        $promises[] = simulateAsyncWork("Task $i");
    }

    // Wait for all to complete
    $results = await(Promise::all($promises));

    foreach ($results as $result) {
        $response->write("Result: $result\n");
    }

    $response->write("Async stream complete!\n");
    $response->end();
}));

// Real-time progress streaming
$app->post('/stream/progress', async(function (Request $request, Response $response) {
    $response->header('Content-Type', 'text/event-stream')
        ->header('Cache-Control', 'no-cache');

    $data = $request->body();
    $steps = isset($data['steps']) ? (int) $data['steps'] : 10;

    $response->write("event: start\n");
    $response->write("data: " . json_encode(['message' => 'Process started', 'total_steps' => $steps]) . "\n\n");

    for ($i = 1; $i <= $steps; $i++) {
        // Simulate work
        await(simulateAsyncWork("Step $i"));

        $progress = round(($i / $steps) * 100, 1);

        $response->write("event: progress\n");
        $response->write("data: " . json_encode([
            'step' => $i,
            'total_steps' => $steps,
            'progress' => $progress,
            'message' => "Completed step $i of $steps"
        ]) . "\n\n");
    }

    $response->write("event: complete\n");
    $response->write("data: " . json_encode(['message' => 'Process completed successfully']) . "\n\n");
    $response->end();
}));

// CSV streaming
$app->get('/stream/csv', function (Request $request, Response $response) {
    $response->header('Content-Type', 'text/csv')
        ->header('Content-Disposition', 'attachment; filename="data.csv"');

    // Write CSV header
    $response->write("id,name,email,created_at\n");

    // Simulate streaming CSV data
    for ($i = 1; $i <= 1000; $i++) {
        $row = [
            $i,
            "User $i",
            "user$i@example.com",
            date('Y-m-d H:i:s', time() - rand(0, 365 * 24 * 3600))
        ];

        $response->write('"' . implode('","', $row) . "\"\n");

        // Small delay to simulate real streaming
        usleep(10000); // 10ms
    }

    $response->end();
});

// WebSocket-like streaming over HTTP (polling simulation)
$streamData = [];
$app->get('/stream/live', function (Request $request, Response $response) use (&$streamData) {
    $lastId = (int) $request->input('last_id', 0);

    $response->header('Content-Type', 'application/json')
        ->header('Cache-Control', 'no-cache');

    $newData = array_filter($streamData, function ($item) use ($lastId) {
        return $item['id'] > $lastId;
    });

    if (empty($newData)) {
        // Simulate waiting for new data
        sleep(1);
        $newData = [['id' => time(), 'message' => 'No new data, this is a poll']];
    }

    $response->json([
        'data' => array_values($newData),
        'last_id' => end($newData)['id']
    ]);
});

// Add data to live stream (for testing)
$app->post('/stream/live', function (Request $request, Response $response) use (&$streamData) {
    $data = $request->body();

    if (empty($data['message'])) {
        return $response->status(400)->json(['error' => 'Message is required']);
    }

    $newItem = [
        'id' => time() . rand(1000, 9999),
        'message' => $data['message'],
        'timestamp' => date('c'),
        'user' => $data['user'] ?? 'Anonymous'
    ];

    $streamData[] = $newItem;

    // Keep only last 100 items
    if (count($streamData) > 100) {
        array_shift($streamData);
    }

    $response->json([
        'message' => 'Data added to stream',
        'item' => $newItem
    ]);
});

// Helper function for async work simulation
function simulateAsyncWork($task)
{
    return new Promise(function ($resolve, $reject) use ($task) {
        setTimeout(function () use ($resolve, $task) {
            $result = "$task completed at " . date('H:i:s');
            $resolve($result);
        }, rand(500, 1500)); // Random delay between 0.5-1.5 seconds
    });
}

$app->listen(3000, '127.0.0.1', function ($url) {
    echo "Streaming server started at $url\n";
    echo "\nStreaming endpoints:\n";
    echo "  GET $url/stream/text - Chunked text streaming\n";
    echo "  GET $url/stream/json - Newline-delimited JSON streaming\n";
    echo "  GET $url/stream/events - Server-Sent Events\n";
    echo "  GET $url/stream/file/{filename} - File streaming with range support\n";
    echo "  GET $url/stream/async - Async streaming with promises\n";
    echo "  POST $url/stream/progress - Progress streaming\n";
    echo "  GET $url/stream/csv - CSV streaming\n";
    echo "  GET $url/stream/live - Live data polling\n";
    echo "  POST $url/stream/live - Add data to live stream\n";
});
