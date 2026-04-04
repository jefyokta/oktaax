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

namespace Oktaax\Utils;

use Oktaax\Core\Application;
use Oktaax\Core\Promise\Promise;
use Oktaax\Http\Request;
use Oktaax\Oktaa;
use Oktaax\Oktaax;
use Oktaax\Trait\HasWebsocket;
use Oktaax\ServerBag;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Timer;
use Throwable;

use function Swoole\Coroutine\run;

if (! function_exists('oktaa')) {
    function oktaa()
    {
        return new Oktaa;
    }
};



if (! function_exists('oktaax')) {
    /**
     * return a instance of oktaax. A http server
     * 
     */
    function oktaax()
    {
        return new Oktaax;
    }
};

if (! function_exists('xsocket')) {

    /**`
     * 
     * Get a instsance of Oktaax with websocket
     * @return Xsocket;
     */
    function xsocket()
    {
        return new  class extends Oktaax {
            use HasWebsocket;
        };
    }
};

if (! function_exists('setTimeout')) {
    function setTimeout($func, $ms)
    {
        return @Timer::after($ms, $func);
    }
}

if (! function_exists('setInterval')) {
    function setInterval($cb, $ms)
    {
        return Timer::tick($ms, $cb);
    }
}

/**
 * 
 * @return Request
 */
if (!function_exists('xrequest')) {
    function xrequest(): ?Request
    {
        return Application::context()->get(Request::class);
    }
}

function async(callable $fn): Promise
{
    return new Promise(function ($resolve, $reject) use ($fn): void {
        try {
            $resolve($fn());
        } catch (Throwable $e) {
            $reject($e);
        }
    });
}
function inCoroutine(): bool
{
    return Coroutine::getCid() > 0;
}

function spawn(callable $fn): void
{
    inCoroutine() ? Coroutine::create($fn) : run($fn);
}


function await(Promise $promise): mixed
{
    if (inCoroutine()) {
        $channel = new Channel(1);

        $promise->then(
            fn($v) => $channel->push(['ok',  $v]),
            fn($r) => $channel->push(['err', $r]),
        );

        [$status, $payload] = $channel->pop();
        $channel->close();

        if ($status === 'err') {
            throw $payload instanceof Throwable
                ? $payload
                : new \RuntimeException((string) $payload);
        }

        return $payload;
    }

    $result   = null;
    $error    = null;
    $hasError = false;

    run(function () use ($promise, &$result, &$error, &$hasError): void {
        $channel = new Channel(1);

        $promise->then(
            fn($v) => $channel->push(['ok',  $v]),
            fn($r) => $channel->push(['err', $r]),
        );

        [$status, $payload] = $channel->pop();
        $channel->close();

        if ($status === 'err') {
            $hasError = true;
            $error    = $payload;
        } else {
            $result = $payload;
        }
    });

    if ($hasError) {
        throw $error instanceof Throwable
            ? $error
            : new \RuntimeException((string) $error);
    }

    return $result;
}


if (! function_exists('xcsrf_token')) {

    function xcsrf_token()
    {
        return xrequest()?->_token;
    }
}

if (! function_exists('xserver')) {
    /**
     * @return \Swoole\Http\Server|\Swoole\Websocket\Server
     */
    function xserver(): \Swoole\Http\Server|\Swoole\Websocket\Server
    {
        return ServerBag::get();
    }
}

if (! function_exists('clearInterval')) {
    function clearInterval($id)
    {
        return Timer::clear($id);
    }
}

if (! function_exists('clearTimeout')) {
    function clearTimeout($id)
    {
        return Timer::clear($id);
    }
}
