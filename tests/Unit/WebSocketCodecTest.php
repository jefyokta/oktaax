<?php

use Oktaax\Websocket\JsonCodec;
use Oktaax\Websocket\Message;
use Oktaax\Websocket\ProtocolException;

it(
    'decodes only the application payload and preserves correlation IDs and false values',
    function () {
        $codec = new JsonCodec();
        $message = $codec->decode(
            '{"event":"echo","data":false,"id":"req-1"}',
            1,
        );
        expect($message->event())
            ->toBe('echo')
            ->and($message->data())
            ->toBeFalse()
            ->and($message->id())
            ->toBe('req-1');
        expect(json_decode($codec->encode($message), true))->toBe([
            'event' => 'echo',
            'data' => false,
            'id' => 'req-1',
        ]);
        expect($codec->decode('{"event":"ping"}', 1)->data())->toBeNull();
        expect(fn() => $codec->encode(new Message('bad', [NAN])))->toThrow(
            JsonException::class,
        );
    },
);

it('rejects malformed or ambiguous envelopes', function (
    string $payload,
    int $opcode,
    int $code,
) {
    try {
        (new JsonCodec())->decode($payload, $opcode);
        $this->fail('Expected invalid message');
    } catch (ProtocolException $error) {
        expect($error->closeCode)->toBe($code);
    }
})->with([
    ['garbage', 1, 1007],
    ['[]', 1, 1008],
    ['"event"', 1, 1008],
    ['{"event":[]}', 1, 1008],
    ['{"event":""}', 1, 1008],
    ['{"event":"test","id":1}', 1, 1008],
    ['{"event":"test","extra":true}', 1, 1008],
    ['{"event":"test"}', 2, 1003],
    ['{"event":"te\\nst"}', 1, 1008],
]);
