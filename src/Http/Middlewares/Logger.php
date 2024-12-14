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

use Exception;
use Oktaax\Console;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use OpenSwoole\Coroutine;

class Logger
{


    public static function handle($path = "log")
    {

        return function (Request $request, Response $response, $next) use ($path) {
            try {
                $start = microtime(true);
                $next();
                $end = microtime(true);
                $took = floor(($end - $start) * 100) / 100;
                if ($response->status >= 400) {
                    if ($response->response->isWritable()) {
                        $title = require __DIR__ . "/../../Utils/HttpError.php";
                        ob_start();
                        $req = $request;
                        $status = $response->status;
                        $title = $title[$status];
                        require __DIR__ . "/../../Views/HttpError/index.php";
                        $content = ob_get_clean();
                        $response->end($content);
                    }
                 }
                Console::info("{$request->server['request_method']}{$request->server['request_uri']}..........[$response->status]  [took {$took}s]");
            } catch (\Throwable | Exception $th) {
                Console::error($th->getMessage());

                $file =  Coroutine::readFile($th->getFile());
                $lines = explode("\n", $file);
                $code = $lines[$th->getLine() - 1];

                ob_start();
                $message = $th->getMessage();
                $prevcode = $lines[$th->getLine() - 2] ?? '';
                $code = $code;
                $nextcode = $lines[$th->getLine()] ?? '';
                $error = $th;
                $req = $request;

                require __DIR__ . "/../../Views/Error/index.php";
                $content = ob_get_clean();


                return   $response->end($content);
            }
        };
    }
}
