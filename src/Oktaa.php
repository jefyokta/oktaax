<?php

namespace Oktaax;

use Error;
use Oktaax\Interfaces\Server as InterfacesServer;
use Oktaax\Trait\HasWebsocket as HasWebsocket;
use Swoole\Http\Server;
use Swoole\WebSocket\Server as WebSocketServer;

class Oktaa extends Oktaax implements InterfacesServer
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
