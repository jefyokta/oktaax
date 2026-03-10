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

    function getWebsocketUrl()
    {

        if (!$this->hasWebsocket) {
            return null;
        }
        return ($this->scheme == 'https' ?  "wss" : "ws") . "://" . $this->host . ($this->port == 80 || $this->port == 443   ? "" : ":" . $this->port);
    }
    function getHttpUrl()
    {
        return $this->scheme . "://" . $this->host . ($this->port == 80 || $this->port == 443 ? "" : ":" . $this->port);
    }

    function isSecure()
    {
        return $this->scheme === 'https';
    }
}
