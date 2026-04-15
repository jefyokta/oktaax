<?php

namespace Oktaax\Tests;

use PHPUnit\Framework\TestCase;
use Oktaax\Core\Promise\Promise;

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

        $result = null;
        Promise::all([$p1, $p2, $p3])->then(function ($values) use (&$result) {
            $result = $values;
        });

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
        Promise::all([$p1, $p2, $p3])->catch(function ($reason) use (&$error) {
            $error = $reason;
        });

        $this->assertEquals("Failed", $error);
    }

    /**
     * Test promise chaining with then
     */
    public function testPromiseChaining()
    {
        $promise = new Promise(fn($res) => $res(5));

        $result = null;
        $promise
            ->then(fn($v) => $v * 2)
            ->then(fn($v) => $v + 3)
            ->then(function ($v) use (&$result) {
                $result = $v;
            });

        $this->assertEquals(13, $result);
    }

    /**
     * Test promise finally
     */
    public function testPromiseFinally()
    {
        $finallyCalled = false;
        $promise = new Promise(fn($res) => $res(42));

        $promise->finally(function () use (&$finallyCalled) {
            $finallyCalled = true;
        });

        $this->assertTrue($finallyCalled);
    }

    /**
     * Test promise with delayed resolution
     */
    public function testPromiseWithDelay()
    {
        $result = null;
        $promise = new Promise(function ($resolve) {
            usleep(100000);
            $resolve("delayed");
        });

        $promise->then(function ($value) use (&$result) {
            $result = $value;
        });

        $this->assertEquals("delayed", $result);
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
