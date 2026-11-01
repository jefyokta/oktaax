<?php

namespace Oktaax\Application;

class ServerAddress
{
    public function __construct(
        public $host,
        public $port,
        public $scheme,
        private $hasWebsocket = false,
    ) {}

    /**
     * Build a WS(S) base URL when the attached native server supports WebSocket.
     */
    public function getWebsocketUrl(): string|null
    {
        if (!$this->hasWebsocket) {
            return null;
        }
        return ($this->scheme == 'https' ? 'wss' : 'ws') .
            '://' .
            $this->host .
            ($this->port == 80 || $this->port == 443 ? '' : ':' . $this->port);
    }
    /**
     * Build the configured HTTP(S) base URL, omitting default ports.
     */
    public function getHttpUrl(): string
    {
        return $this->scheme .
            '://' .
            $this->host .
            ($this->port == 80 || $this->port == 443 ? '' : ':' . $this->port);
    }
    /**
     * Return whether the configured scheme is HTTPS.
     */
    public function isSecure(): bool
    {
        return $this->scheme === 'https';
    }
}
