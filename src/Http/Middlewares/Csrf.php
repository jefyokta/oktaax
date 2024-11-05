<?php

namespace Oktaax\Http\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Oktaax\Console;
use Oktaax\Http\Request;
use Oktaax\Http\Response;

class Csrf
{
    /**
     * @param string $appkey
     * 
     * @return callable
     */
    public static function handle($appkey)
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
    public static function generate($appkey, $exp)
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
    public static function generatetoken($appkey, $exp)
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
