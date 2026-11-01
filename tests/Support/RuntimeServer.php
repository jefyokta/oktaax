<?php

namespace Tests\Support;

final class RuntimeServer
{
    private mixed $process = null;
    private string $output;
    private string $nativeLog;
    private string $ready;
    private string $lastLogs = '';
    public int $port;
    public bool $gracefulShutdown = false;

    public function __construct(
        private string $mode,
        private ?string $example = null,
        private bool $debug = false,
        private ?string $entrypoint = null,
    ) {
        $this->output = tempnam(sys_get_temp_dir(), 'oktaax-http-');
        $this->nativeLog = tempnam(sys_get_temp_dir(), 'oktaax-swoole-');
        $this->ready = tempnam(sys_get_temp_dir(), 'oktaax-ready-');
        unlink($this->ready);
    }

    public function start(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $error, $message);
        if ($socket === false) {
            throw new \RuntimeException($message);
        }
        $this->port = (int) substr(
            strrchr(stream_socket_get_name($socket, false), ':'),
            1,
        );
        fclose($socket);
        $entrypoint =
            $this->entrypoint ??
            ($this->example === null
                ? 'runtime-server.php'
                : 'guide-server.php');
        $this->process = proc_open(
            [
                PHP_BINARY,
                __DIR__ . '/' . $entrypoint,
                $this->example ?? $this->mode,
                (string) $this->port,
                $this->nativeLog,
                $this->ready,
                $this->debug ? '1' : '0',
                $this->mode,
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['file', $this->output, 'a'],
                2 => ['file', $this->output, 'a'],
            ],
            $pipes,
            dirname(__DIR__, 2),
        );
        if (!is_resource($this->process)) {
            throw new \RuntimeException('Could not start integration server.');
        }
        fclose($pipes[0]);
        for ($attempt = 0; $attempt < 100; $attempt++) {
            clearstatcache(true, $this->ready);
            if (is_file($this->ready)) {
                return;
            }
            if (!proc_get_status($this->process)['running']) {
                break;
            }
            usleep(20000);
        }
        throw new \RuntimeException('Server did not start: ' . $this->logs());
    }

    public function request(
        string $path,
        string $method = 'GET',
        ?string $body = null,
        array $headers = [],
    ): array {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'content' => $body ?? '',
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout' => 5,
                'follow_location' => 0,
            ],
        ]);
        $content = file_get_contents(
            'http://127.0.0.1:' . $this->port . $path,
            false,
            $context,
        );
        if ($content === false) {
            throw new \RuntimeException(
                'HTTP request failed: ' . $this->logs(),
            );
        }
        $rawHeaders = http_get_last_response_headers();
        preg_match('/\s(\d{3})\s/', $rawHeaders[0], $match);
        $responseHeaders = [];
        foreach (array_slice($rawHeaders, 1) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $responseHeaders[strtolower($name)] = trim($value);
            }
        }
        return [(int) $match[1], $responseHeaders, $content];
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            for (
                $attempt = 0;
                $attempt < 100 && proc_get_status($this->process)['running'];
                $attempt++
            ) {
                usleep(20000);
            }
            if (proc_get_status($this->process)['running']) {
                proc_terminate($this->process, 9);
            }
            proc_close($this->process);
            $this->process = null;
        }
        $this->gracefulShutdown =
            $this->gracefulShutdown || is_file($this->ready . '.stopped');
        $this->lastLogs = $this->logs();
        foreach (
            [
                $this->output,
                $this->nativeLog,
                $this->ready,
                $this->ready . '.stopped',
            ]
            as $file
        ) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function logs(): string
    {
        if (!is_file($this->output) || !is_file($this->nativeLog)) {
            return $this->lastLogs;
        }
        return file_get_contents($this->output) .
            file_get_contents($this->nativeLog);
    }
    public function __destruct()
    {
        $this->stop();
    }
}
