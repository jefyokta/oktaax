<?php

namespace Oktaax\Websocket;

use Oktaax\Interfaces\Channel;
use Stringable;
/**
 * @experimental
 * @dontuse
 */
abstract class Event implements Stringable
{

    public $client;
    public $server;
    protected $delay = 0;
    protected $opcode = 1;
    protected  $flag = 1;

    protected function message()
    {
        return "";
    }
    final  public function __toString(): string
    {
        return json_encode([
            "event" => self::name(),
            "message" => $this->message(),
        ]);
    }

    public static function name()
    {
        return basename(str_replace('\\', '.', static::class));
    }

    protected function channel(): Channel|false
    {
        return false;
    }

    final public function broadcast()
    {
        $server = $this->server;
        if ($chan = $this->channel()) {
            $server = $server->toChannel($chan);
        }
        $server->broadcast($this->__toString(), $this->delay, $this->opcode, $this->flag);
    }
}
