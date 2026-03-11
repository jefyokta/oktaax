<?php

namespace Oktaax\Core;

class URL
{

    public function __construct(
        public $host,
        public $port,
        public $scheme,
        private $hasWebsocket = false
    ) {}

    /**
     * Summary of getWebsocketUrl
     * @return string|null
     */
    public function getWebsocketUrl(): string|null
    {

        if (!$this->hasWebsocket) {
            return null;
        }
        return ($this->scheme == 'https' ?  "wss" : "ws") . "://" . $this->host . ($this->port == 80 || $this->port == 443   ? "" : ":" . $this->port);
    }
    /**
     * Summary of getHttpUrl
     * @return string
     */
    public  function getHttpUrl(): string
    {
        return $this->scheme . "://" . $this->host . ($this->port == 80 || $this->port == 443 ? "" : ":" . $this->port);
    }
    /**
     * Summary of isSecure
     * @return bool
     */
    public function isSecure(): bool
    {
        return $this->scheme === 'https';
    }
}
