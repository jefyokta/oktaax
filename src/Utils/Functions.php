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

use Oktaax\Application\ApplicationContext;
use JefyOkta\PhpPromise\Promise;
use Oktaax\HttpClient\Request as ClientRequest;
use Oktaax\HttpClient\RequestOptions;
use Oktaax\HttpClient\Response;
use Oktaax\Http\Headers;
use Oktaax\Oktaax;
use Oktaax\Utils\Interfaces\CanConvertToBoolean;
use Swoole\Coroutine\Client;
use Swoole\Timer;
use Throwable;

define('undefined', Undefined::get());

if (!function_exists('oktaax')) {
    /**
     * Create a process-server convenience application without starting a runtime.
     *
     * @return \Oktaax\Oktaax
     */
    function oktaax()
    {
        return new Oktaax();
    }
}

if (!function_exists('setTimeout')) {
    /**
     * Schedule one callback after a millisecond delay in the active application scope.
     *
     * @param callable(): mixed $func
     * @param int $ms
     * @return int|false
     * @throws \LogicException When no application scope is active.
     */
    function setTimeout($func, $ms)
    {
        $application = ApplicationContext::current();
        $timer = null;
        $timer = Timer::after($ms, function () use (
            $application,
            $func,
            &$timer,
        ) {
            $application->workers()->forgetTimer($timer);
            $application->within($func);
        });
        if ($timer !== false) {
            $application->workers()->trackTimer($timer);
        }
        return $timer;
    }
}

if (!function_exists('setInterval')) {
    /**
     * Schedule a recurring millisecond callback and register its timer with the active worker.
     *
     * @param callable(int): mixed $cb
     * @param int $ms
     * @return int|false
     * @throws \LogicException When no application scope is active.
     */
    function setInterval(callable $cb, int $ms)
    {
        $application = ApplicationContext::current();
        $timer = Timer::tick(
            $ms,
            fn(...$args) => $application->within(fn() => $cb(...$args)),
        );
        if ($timer !== false) {
            $application->workers()->trackTimer($timer);
        }
        return $timer;
    }
}

/**
 * Open a coroutine HTTP request; the response body must be consumed once or explicitly closed.
 *
 * @param array{method?: string, headers?: array<string, string|list<string>>|\Oktaax\Http\Headers, body?: mixed, timeout?: int}|\Oktaax\HttpClient\RequestOptions $options
 * @return \JefyOkta\PhpPromise\Promise<\Oktaax\HttpClient\Response>
 */

function fetch(string $url, array|RequestOptions $options = []): Promise
{
    return new Promise(function ($resolve, $reject) use ($url, $options) {
        try {
            if (!($options instanceof RequestOptions)) {
                $options = new RequestOptions(
                    method: $options['method'] ?? 'GET',
                    headers: $options['headers'] ?? [],
                    body: $options['body'] ?? null,
                    timeout: $options['timeout'] ?? 0,
                );
            }

            $u = parse_url($url);
            $host = $u['host'] ?? null;
            if (!$host) {
                throw new \Exception('Invalid URL');
            }

            $path =
                ($u['path'] ?? '/') .
                (isset($u['query']) ? '?' . $u['query'] : '');
            $port =
                $u['port'] ?? (($u['scheme'] ?? 'http') === 'https' ? 443 : 80);
            $ssl = ($u['scheme'] ?? 'http') === 'https';

            $cli = new Client(
                $ssl ? SWOOLE_SOCK_TCP | SWOOLE_SSL : SWOOLE_SOCK_TCP,
            );

            $cli->set([
                'timeout' => $options->timeout,
            ]);

            if (!$cli->connect($host, $port, $options->timeout)) {
                throw new \Exception($cli->errMsg, $cli->errCode);
            }

            $cli->send(new ClientRequest($path, $host, $options));

            $buffer = '';
            while (!str_contains($buffer, "\r\n\r\n")) {
                $chunk = $cli->recv();
                if ($chunk === '' || $chunk === false) {
                    throw new \Exception(
                        'Connection closed while reading header',
                    );
                }
                $buffer .= $chunk;
            }

            [$head, $rest] = explode("\r\n\r\n", $buffer, 2);

            $lines = explode("\r\n", $head);

            if (!preg_match('#HTTP/\d\.\d\s+(\d+)#', array_shift($lines), $m)) {
                throw new \Exception('Invalid HTTP response');
            }

            $status = \intval($m[1]);

            $headers = [];
            foreach ($lines as $line) {
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $headers[strtolower(trim($k))] = trim($v);
                }
            }

            $resolve(new Response($cli, $status, new Headers($headers), $rest));
        } catch (Throwable $e) {
            $reject($e);
        }
    });
}

if (!function_exists('clearInterval')) {
    /**
     * Clear a native timer and forget it from the active worker timer registry.
     *
     * @return bool
     * @throws \LogicException When no application scope is active.
     */
    function clearInterval(int $id)
    {
        ApplicationContext::current()->workers()->forgetTimer($id);
        return Timer::clear($id);
    }
}

if (!function_exists('clearTimeout')) {
    /**
     * Clear a native timer and forget it from the active worker timer registry.
     *
     * @return bool
     * @throws \LogicException When no application scope is active.
     */
    function clearTimeout(int $id)
    {
        ApplicationContext::current()->workers()->forgetTimer($id);
        return Timer::clear($id);
    }
}

if (!function_exists('_o')) {
    /**
     * Create a Struct from named property arguments.
     *
     * @param mixed ...$args
     * @return \Oktaax\Utils\Struct<object>
     */
    function _o(mixed ...$args): Struct
    {
        return new Struct(...$args);
    }
}

if (!function_exists('Boolean')) {
    /**
     * Use an object truthiness contract when available, otherwise PHP boolean conversion.
     *
     * @return bool
     */
    function Boolean(mixed $val)
    {
        if (\is_object($val) && $val instanceof CanConvertToBoolean) {
            return $val->getBooleanValue();
        }
        return !!$val;
    }
}

if (!function_exists('getter')) {
    /**
     * Wrap a zero-argument computed getter for later binding to its owning Struct.
     *
     * @param \Closure(): TValue $callback
     * @return \Oktaax\Utils\GetterProperty<TValue>
     * @template TValue
     */
    function getter(\Closure $callback)
    {
        return new GetterProperty($callback);
    }
}

/**
 * Return the explicitly active application; constructing an application does not select a global default.
 *
 * @throws \LogicException When no application scope is active.
 */
function app(): \Oktaax\Application
{
    return ApplicationContext::current();
}
