<?php

/**
 * Oktaax - Real-time Websocket and HTTP Server using Swoole
 *
 * @package Oktaax
 * @author Jefyokta
 * @license MIT License
 * 
 * @link https://github.com/jefyokta/oktaax
 *
 * @copyright Copyright (c) 2024, Jefyokta
 *
 * MIT License
 *
 * Copyright (c) 2024 Jefyokta
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 */

namespace Oktaax\Http\Middleware;

use Oktaax\Console;
use Oktaax\Contracts\Middleware;
use Oktaax\Http\Request;
use Oktaax\Http\Response;

class Logger implements Middleware
{
    public function handle(Request $request, Response $response, $next): mixed
    {
        try {
            $start = microtime(true);

            $result = $next();

            $end  = microtime(true);
            $took = floor(($end - $start) * 1000) / 1000;

            $method = $request->server['request_method'] ?? 'UNKNOWN';
            $uri    = $request->server['request_uri']    ?? '/';
            $status = $response->getStatus()              ?? 200;

            Console::info("{$method} {$uri} ..........[$status] [took {$took}s]");

            return $result;
        } catch (\Throwable $th) {
            return $this->handleException($th, $request, $response);
        }
    }

    private function handleException(\Throwable $th, Request $request, Response $response): mixed
    {
        Console::error("[{$th->getCode()}] {$th->getMessage()} in {$th->getFile()} on line {$th->getLine()}");

        $lines    = $this->readFileLines($th->getFile());
        $line     = $th->getLine();
        $code     = $lines[$line - 1]  ?? '';
        $prevcode = $lines[$line - 2]  ?? '';
        $nextcode = $lines[$line]       ?? '';
        $message  = $th->getMessage();
        $error    = $th;
        $req      = $request;

        ob_start();

        require __DIR__ . "/../../Views/Error/index.php";

        $content = ob_get_clean();

        return $response->end($content ?: 'Internal Server Error');
    }

    /**
     * @return array<int, string>
     */
    private function readFileLines(string $filepath): array
    {
        $contents = file_get_contents($filepath);

        if ($contents === false) {
            Console::error("Logger: could not read file {$filepath}");
            return [];
        }

        return explode("\n", $contents);
    }
}
