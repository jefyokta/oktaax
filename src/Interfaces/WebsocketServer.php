<?php



namespace Oktaax\Interfaces;


interface WebSocketServer
{
    /**
     * Broadcast Data
     * @param mixed $data
     */
    public function broadcast(mixed $data, int $delay = 0): void;

    /**
     * Choosing receiver(s) message, single or multiple
     * 
     * @param int|array $fds
     */
    public function to(int|array  $fds): static;

    /**
     * Choose Channel
     * 
     * @param Channel $channel
     */
    public function toChannel(Channel $channel): static;
};
