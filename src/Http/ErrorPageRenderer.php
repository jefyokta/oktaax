<?php

namespace Oktaax\Http;

use InvalidArgumentException;
use Throwable;

/** Renders self-contained HTML pages without exposing request bodies or trace arguments. */
final class ErrorPageRenderer
{
    /**
     * Select optional absolute, readable PHP templates; omitted paths use the built-in pages.
     *
     * @throws InvalidArgumentException When a custom template is unreadable or is not a file.
     */
    public function __construct(
        private ?string $httpTemplate = null,
        private ?string $exceptionTemplate = null,
    ) {
        foreach ([$httpTemplate, $exceptionTemplate] as $template) {
            if (
                $template !== null &&
                (!is_file($template) || !is_readable($template))
            ) {
                throw new InvalidArgumentException(
                    'Error page template must be a readable file: ' . $template,
                );
            }
        }
    }

    public function http(int $status, Request $request): string
    {
        $titles = require dirname(__DIR__) . '/Utils/HttpError.php';
        $title = $titles[$status] ?? 'Request Error';
        $description = match ($status) {
            400
                => 'The request could not be understood. Check the information you sent and try again.',
            401
                => 'Please sign in with an account that has access to this page.',
            403 => 'You do not have permission to access this resource.',
            404
                => 'The page may have moved, or the address may be a little off. Let’s get you back on track.',
            405 => 'This address does not accept the request method you used.',
            408,
            504
                => 'The request took longer than expected. Please try again in a moment.',
            410 => 'This resource is no longer available at this address.',
            413
                => 'The request is larger than this server accepts. Try sending a smaller payload.',
            415
                => 'This endpoint cannot process the format of the data you sent.',
            422
                => 'Some of the information could not be processed. Check your input and try again.',
            429
                => 'A few too many requests arrived at once. Give it a moment, then try again.',
            500
                => 'Something unexpected happened while processing your request. Please try again shortly.',
            502,
            503
                => 'The service is temporarily unavailable. Please try again in a moment.',
            default
                => 'This request could not be completed. You can return home or try again later.',
        };
        return $this->render(
            $this->httpTemplate ?? $this->builtin('http-error.php'),
            [
                ...$this->requestData($request),
                'status' => $status,
                'title' => $title,
                'description' => $description,
            ],
        );
    }

    public function exception(Throwable $error, Request $request): string
    {
        $file = $error->getFile();
        $line = $error->getLine();
        $source = [];
        if (is_file($file) && is_readable($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            if ($lines !== false) {
                for (
                    $number = max(1, $line - 3);
                    $number <= min(count($lines), $line + 3);
                    $number++
                ) {
                    $source[$number] = $lines[$number - 1];
                }
            }
        }
        $frames = array_map(
            static fn(array $frame) => [
                'file' => $frame['file'] ?? '[internal]',
                'line' => $frame['line'] ?? null,
                'call' =>
                    ($frame['class'] ?? '') .
                    ($frame['type'] ?? '') .
                    ($frame['function'] ?? ''),
            ],
            $error->getTrace(),
        );
        return $this->render(
            $this->exceptionTemplate ?? $this->builtin('exception.php'),
            [
                ...$this->requestData($request),
                'status' => 500,
                'title' => 'Application Exception',
                'error' => $error,
                'exceptionClass' => $error::class,
                'message' => $error->getMessage(),
                'file' => $file,
                'line' => $line,
                'source' => $source,
                'frames' => $frames,
            ],
        );
    }

    /**
     * Expose only request method and path, excluding query, body, headers, and environment data.
     *
     * @return array{requestMethod: string, requestPath: string}
     */
    private function requestData(Request $request): array
    {
        return [
            'requestMethod' => $request->getMethod(),
            'requestPath' => $request->path(),
        ];
    }

    private function builtin(string $file): string
    {
        return dirname(__DIR__, 2) . '/resources/views/' . $file;
    }

    /**
     * Render template variables with an escaping helper and restore output-buffer depth even on failure.
     *
     * @param array<string, mixed> $data
     * @throws \Throwable When template execution fails.
     */
    private function render(string $template, array $data): string
    {
        $escape = static fn(mixed $value): string => htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
        extract($data, EXTR_SKIP);
        $level = ob_get_level();
        ob_start();
        try {
            require $template;
            return (string) ob_get_contents();
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }
}
