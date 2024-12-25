<?php 
/**
 * Oktaax - Real-time Websocket and HTTP Server using Swoole
 *
 * @package Oktaax
 * @author Jefyokta
 * @license MIT License
 * 
 * @link https://github.com/jefyokta/oktaax
 *
 * @copyright Copyright (c) 2024, Jefyokta
 *
 * MIT License
 *
 * Copyright (c) 2024 Jefyokta
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 */
 


namespace Oktaax\Http\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Oktaax\Console;
use Oktaax\Http\Request;
use Oktaax\Http\Response;


/**
 * 
 * Csrf
 * for accept only from authorized requests
 * 
 * @package Oktaax\Http\Middleware
 * 
 */

class Csrf
{
    /**
     * @param string $appkey
     * 
     * @return callable
     */
    public static function handle($appkey): callable
    {

        return function (Request $request, Response $response, $next) use ($appkey) {

            if ($request->server['request_method'] !== "GET") {
                if ($request->body("_token") ?? false) {
                    $token = $request->body("_token");
                    try {
                        $dec =   JWT::decode($token, new Key($appkey, "HS512"));
                        $next();
                    } catch (\Throwable $th) {

                        return $response->status(403)->end();
                    }
                } else {
                    return $response->status(403)->end();
                }
            }
            $next();
        };
    }
    /**
     * @param string $appkey
     * @param int $exp
     * 
     * @return callable
     */
    public static function generate($appkey, $exp): callable
    {

        return function (Request $request, Response $response, $next) use ($appkey, $exp) {
            $token = self::generatetoken($appkey, $exp);


            $request->_token = $token;
            $next();
        };
    }


    /**
     * @param string $appkey
     * @param int $exp
     * 
     * @return string
     */
    public static function generatetoken($appkey, $exp):string
    {

        $key = $appkey;
        $payload = [
            'iat' => time(),
            'exp' => time() + $exp,
            'data' => ['requestid' => uniqid()]
        ];
        return JWT::encode($payload, $key, "HS512");
    }
}
