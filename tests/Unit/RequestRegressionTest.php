<?php

use Oktaax\Exception\HttpException;
use Oktaax\Exception\ValidationException;
use Tests\Support\HttpFixture;

it(
    'decodes complete JSON bodies including empty containers and scalar values',
    function ($raw, $expected) {
        $http = new HttpFixture(
            body: $raw,
            headers: ['content-type' => 'application/json'],
        );

        expect($http->request->json())->toBe($expected);
        expect($http->request->json())->toBe($expected);
        expect($http->nativeRequest->bodyReads)->toBe(1);
    },
)->with([
    ['{}', []],
    ['[]', []],
    ['[1,2]', [1, 2]],
    ['false', false],
    ['0', 0],
    ['null', null],
    ['"text"', 'text'],
]);

it('rejects malformed JSON without accepting a valid suffix', function ($raw) {
    $http = new HttpFixture(
        body: $raw,
        headers: ['content-type' => 'application/json'],
    );
    try {
        $http->request->json();
        $this->fail('Expected malformed JSON to be rejected.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(400);
    }
})->with(['garbage {"ok":true}', '{"incomplete":', '[1,]']);

it(
    'uses one input precedence and keeps explicit accessors separate',
    function () {
        $http = new HttpFixture(
            method: 'POST',
            body: '{"role":"body","bodyOnly":false,"presentNull":null}',
            headers: [
                'content-type' => 'Application/Problem+Json; charset=utf-8',
            ],
            query: ['role' => 'query'],
            cookies: ['role' => 'cookie', 'cookieOnly' => 'value'],
        );
        $req = $http->request;
        $req->params = ['role' => 'route'];

        expect($req->input('role'))
            ->toBe('query')
            ->and($req->all()['role'])
            ->toBe('query');
        expect($req->query('role'))
            ->toBe('query')
            ->and($req->body('role'))
            ->toBe('body');
        expect($req->cookie('role'))
            ->toBe('cookie')
            ->and($req->param('role'))
            ->toBe('route');
        expect($req->parameters()['role'])->toBe('route');
        expect($req->input('bodyOnly', 'default'))->toBeFalse();
        expect($req->input('presentNull', 'default'))
            ->toBeNull()
            ->and($req->has('presentNull'))
            ->toBeTrue();
        expect($req->input('missing', 'default'))
            ->toBe('default')
            ->and($req->has('missing'))
            ->toBeFalse();
        expect($req('role'))
            ->toBe('query')
            ->and($req->json('missing', 'default'))
            ->toBe('default');
    },
);

it('reads form bodies and tolerates absent optional headers', function () {
    $req = (new HttpFixture(method: 'POST', post: ['name' => 'Ada']))->request;

    expect($req->body())
        ->toBe(['name' => 'Ada'])
        ->and($req->body('name'))
        ->toBe('Ada');
    expect($req->isJson())
        ->toBeFalse()
        ->and($req->isFormSubmission())
        ->toBeFalse();
    expect($req->wantsJson())
        ->toBeFalse()
        ->and($req->wantsJS())
        ->toBeFalse()
        ->and($req->isAjax())
        ->toBeFalse();
    $req->setHeader('Content-Type', 'application/x-www-form-urlencoded');
    expect($req->isFormSubmission())
        ->toBeTrue()
        ->and($req->hasHeader('CONTENT-TYPE'))
        ->toBeTrue();
});

it(
    'only returns validated fields and rejects scalar bodies for validation',
    function () {
        $req = (new HttpFixture(
            method: 'POST',
            post: ['name' => 'Ada', 'admin' => true],
        ))->request;
        expect($req->validate(['name' => 'required|string']))->toBe([
            'name' => 'Ada',
        ]);
        expect(fn() => $req->validate(['email' => 'required|email']))->toThrow(
            ValidationException::class,
        );

        $scalar = (new HttpFixture(
            body: 'false',
            headers: ['content-type' => 'application/json'],
        ))->request;
        expect(fn() => $scalar->validate(['name' => 'required']))->toThrow(
            HttpException::class,
        );
    },
);
