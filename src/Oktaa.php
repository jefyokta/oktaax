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



namespace Oktaax;

use Error;
use Oktaax\Interfaces\Server as InterfacesServer;
use Oktaax\Interfaces\WithBlade;
use Oktaax\Interfaces\Xsocket;
use Oktaax\Trait\HasWebsocket as HasWebsocket;
use OpenSwoole\Http\Server;
use OpenSwoole\WebSocket\Server as WebSocketServer;

/**
 * 
 * A class to make application server
 * 
 * @package Oktaax
 * 
 * 
 */
class Oktaa extends Oktaax implements InterfacesServer,  WithBlade
{

    use HasWebsocket;

    /**
     * @override
     * @var Server|WebSocketServer|null
     */
    protected Server|WebSocketServer|null $server = null;

    /**
     * Configuration settings for the application.
     *
     * @var array
     * 
     * @override
     */

    protected array $config = [
        "viewsDir" => "views/",
        "logDir" => "log",
        "render_engine" => null,
        "blade" => [
            "cacheDir" => null,
            "functionsDir" => null
        ],
        "useOktaMiddleware" => true,
        "sock_type" => null,
        "mode" => null,
        "withWebsocket" => false,
        "publicDir" => "public",
        "app" => [
            "key" => null,
            "name" => "oktaax",
            "useCsrf" => false,
            "csrfExp" => (60 * 5)
        ]
    ];

    /**
     * 
     * Enabling Websocket server
     * 
     * @return static
     * 
     */

    public function enableWebsocket()
    {

        $this->config['withWebsocket'] = true;

        return $this;
    }

    /**
     * 
     * Overrided
     * 
     */
    private function init()
    {
        if (is_int($this->config['mode'] && !is_int($this->config['sock_type']))) {
            $this->server = new Server($this->host, $this->port, $this->config['mode'], $this->config['sock_type']);
        } else {
            if ($this->config['withWebsocket']) {
                $this->server = new WebSocketServer($this->host, $this->port);
            } else {
                $this->server = new Server($this->host, $this->port);
            }
        }
    }
}
