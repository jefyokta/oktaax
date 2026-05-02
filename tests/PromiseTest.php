<?php

namespace Oktaax\Tests;

use PHPUnit\Framework\TestCase;
use Oktaax\Core\Promise\Promise;
use Oktaax\Exception\AggregateError;

use function Oktaax\Utils\async;
use function Oktaax\Utils\await;
use function Swoole\Coroutine\run;

class PromiseTest extends TestCase
{
    /**
     * Test basic promise resolution
     */
    public function testPromiseResolves()
    {
        $promise = new Promise(function ($resolve, $reject) {
            $resolve(42);
        });

        $result = null;
        $promise->then(function ($value) use (&$result) {
            $result = $value;
        });

        $this->assertEquals(42, $result);
    }

    /**
     * Test promise rejection
     */
    public function testPromiseRejects()
    {
        $promise = new Promise(function ($resolve, $reject) {
            $reject("Error message");
        });

        $error = null;
        $promise->catch(function ($reason) use (&$error) {
            $error = $reason;
        });

        $this->assertEquals("Error message", $error);
    }

    /**
     * Test Promise::all with multiple promises
     */
    public function testPromiseAll()
    {
        $p1 = new Promise(fn($res) => $res(1));
        $p2 = new Promise(fn($res) => $res(2));
        $p3 = new Promise(fn($res) => $res(3));

        $result = Promise::all([$p1, $p2, $p3])->wait();

        $this->assertEquals([1, 2, 3], $result);
    }

    /**
     * Test Promise::all with rejection
     */
    public function testPromiseAllWithRejection()
    {
        $p1 = new Promise(fn($res) => $res(1));
        $p2 = new Promise(fn($res, $rej) => $rej("Failed"));
        $p3 = new Promise(fn($res) => $res(3));

        $error = null;

        try {
            Promise::all([$p1, $p2, $p3])->wait();
        } catch (\Throwable $th) {
            $error = $th;
        }

        $this->assertEquals("Uncaught in promise: Failed", $error->getMessage());
    }

    /**
     * Test promise chaining with then
     */
    public function testPromiseChaining()
    {
        $promise = new Promise(fn($res) => $res(5));

        $result = $promise
            ->then(fn($v) => $v * 2)
            ->then(fn($v) => $v + 3)
            ->wait();

        $this->assertEquals(13, $result);
    }

    /**
     * Test promise finally
     */
    public function testPromiseFinally()
    {
        $finallyCalled = false;
        $promise = new Promise(fn($res) => $res(42));

        $result = $promise->finally(function () use (&$finallyCalled) {
            $finallyCalled = true;
        })->wait();

        $this->assertTrue($finallyCalled);
        $this->assertEquals(42, $result);
    }

    public function testPromiseFinallyReturnsPromise()
    {
        $finallyCalled = false;
        $promise = new Promise(fn($res) => $res(42));

        $result = $promise->finally(function () use (&$finallyCalled) {
            $finallyCalled = true;
            return new Promise(fn($res) => $res('done'));
        })->wait();

        $this->assertTrue($finallyCalled);
        $this->assertEquals(42, $result);
    }

    public function testPromiseWaitMethod()
    {
        $promise = new Promise(fn($res) => $res('ok'));
        $this->assertEquals('ok', $promise->wait());
    }

    public function testPromiseWaitMethodError()
    {
        $promise = new Promise(fn($res, $rej) => $rej('fail'));

        $this->expectException(\Oktaax\Exception\PromiseException::class);
        $this->expectExceptionMessage('Uncaught in promise: fail');

        $promise->wait();
    }

    public function testPromiseStaticResolveReject()
    {
        $resolved = Promise::resolve(123);
        $this->assertEquals(123, $resolved->wait());

        $rejected = Promise::reject('bad');

        $this->expectException(\Oktaax\Exception\PromiseException::class);
        $this->expectExceptionMessage('Uncaught in promise: bad');

        $rejected->wait();
    }

    public function testPromiseAllEmptyResolvesImmediately()
    {
        $result = Promise::all([])->wait();

        $this->assertEquals([], $result);
    }

    public function testPromiseRaceReturnsFirstResolved()
    {
        $result = null;

        run(function () use (&$result) {
            $p1 = new Promise(function ($res) {
                usleep(100000);
                $res('slow');
            });
            $p2 = new Promise(fn($res) => $res('fast'));

            $result = Promise::race([$p1, $p2])->wait();
        });

        $this->assertEquals('fast', $result);
    }

    public function testPromiseAnyRejectsAggregateError()
    {
        $p1 = new Promise(fn($res, $rej) => $rej('bad1'));
        $p2 = new Promise(fn($res, $rej) => $rej('bad2'));

        $error = null;

        try {
            Promise::any([$p1, $p2])->wait();
        } catch (AggregateError $th) {
            $error = $th;
        }

        $this->assertInstanceOf(AggregateError::class, $error);
        $this->assertEquals(['bad1', 'bad2'], $error->errors);
    }

    public function testAwaitInsideCoroutine()
    {
        $value = null;

        run(function () use (&$value) {
            $promise = new Promise(fn($res) => $res('inside'));
            $value = await($promise);
        });

        $this->assertEquals('inside', $value);
    }

    /**
     * Test promise with delayed resolution
     */
    public function testPromiseWithDelay()
    {
        $promise = new Promise(function ($resolve) {
            usleep(100000);
            $resolve("delayed");
        });

        $this->assertEquals("delayed", $promise->wait());
    }

    public function testAwaitTest()
    {
        $promise = new Promise(fn($res) => $res(42));
        $res = await($promise);
        $this->assertEquals(42, $res);
    }

    public function testAwaitTestError()
    {
        $asyncronous = async(function () {
            try {
                $promise = new Promise(fn($res, $rej) => $rej("oops"));
                await($promise);
                $this->fail("Exception not thrown");
            } catch (\Throwable $th) {
                $this->assertEquals("Uncaught in promise: oops", $th->getMessage());
            }
        });
        $asyncronous();
    }
}
