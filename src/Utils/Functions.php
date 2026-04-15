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

use Oktaax\Console;
use Oktaax\Core\Application;
use Oktaax\Core\Promise\Asynchronous;
use Oktaax\Core\Promise\Promise;
use Oktaax\Exception\PromiseException;
use Oktaax\Http\Client\Request as ClientRequest;
use Oktaax\Http\Client\RequestOptions;
use Oktaax\Http\Client\Response;
use Oktaax\Http\Headers;
use Oktaax\Http\Request;
use Oktaax\Oktaa;
use Oktaax\Oktaax;
use Oktaax\Trait\HasWebsocket;
use Oktaax\ServerBag;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\Client;
use Swoole\Timer;
use Throwable;

use TParams;
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
/**
 * @template TParams
 * @template TReturn
 * @param callable(...TParams):TReturn $fn
 * @return Asynchronous<TReturn,TParams>
 */
function async(callable $fn)
{
    return new Asynchronous($fn);
}


/**
 * 
 * @return Promise<Response>
 */

function fetch(string $url, array|RequestOptions $options = []): Promise
{
    return new Promise(function ($resolve, $reject) use ($url, $options) {

        spawn(function () use ($reject, $url, $resolve, $options) {
            try {
                if (!$options instanceof RequestOptions) {
                    $options = new RequestOptions(
                        method: $options['method'] ?? 'GET',
                        headers: $options['headers'] ?? [],
                        body: $options['body'] ?? null,
                        timeout: $options['timeout'] ?? 5
                    );
                }

                $u = parse_url($url);
                $host = $u['host'] ?? null;
                if (!$host) throw new \Exception("Invalid URL");

                $path = ($u['path'] ?? '/') . (isset($u['query']) ? '?' . $u['query'] : '');
                $port = $u['port'] ?? (($u['scheme'] ?? 'http') === 'https' ? 443 : 80);
                $ssl  = ($u['scheme'] ?? 'http') === 'https';

                $cli = new Client($ssl ? SWOOLE_SOCK_TCP | SWOOLE_SSL : SWOOLE_SOCK_TCP);

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
                        throw new \Exception("Connection closed while reading header");
                    }
                    $buffer .= $chunk;
                }

                [$head, $rest] = explode("\r\n\r\n", $buffer, 2);

                $lines = explode("\r\n", $head);

                if (!preg_match('#HTTP/\d\.\d\s+(\d+)#', array_shift($lines), $m)) {
                    throw new \Exception("Invalid HTTP response");
                }

                $status = intval($m[1]);

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
    });
}

function inCoroutine(): bool
{
    return Coroutine::getCid() > 0;
}

function spawn(callable $fn): void
{
    if (inCoroutine()) {
        Coroutine::create($fn);
    } else {
        run(function () use ($fn) {
            Coroutine::create($fn);
        });
    }
}

/**
 * @template T
 * @param Promise<T>
 * @return T
 * @throws PromiseException
 */
function await(Promise $promise): mixed
{
    $channel = new Channel(1);

    $promise->then(
        fn($v) => $channel->push(['ok', $v]),
        fn($r) => $channel->push(['err', $r]),
    );

    $wait = function () use ($channel) {
        [$status, $payload] = $channel->pop();
        $channel->close();

        if ($status === 'err') {
            throw $payload instanceof Throwable
                ? $payload
                : new PromiseException((string)$payload);
        }

        return $payload;
    };

    if (inCoroutine()) {
        return $wait();
    }
    return run($wait);
}

if (! function_exists('xcsrf_token')) {

    function xcsrf_token()
    {
        return xrequest()?->_token;
    }
}

if (! function_exists('xserver')) {
    /**
     * @deprecated 
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
