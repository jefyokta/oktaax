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


namespace Oktaax\Types;

use ReflectionClass;
use Swoole\Http\Request;
use Swoole\Http\Response;

class Laravel
{
    private $directory;

    /**
     * @var \Illuminate\Foundation\Application 
     */
    private $app;

    private $interactWithSocket = false;

    private  $https;

    private $chunkSize = 8192;


    public function __construct($directory)
    {
        $this->directory = $directory;
    }

    public function _create()
    {
        return Laravel::create($this->directory)->secure();
    }

    public function terminate($request, $response)
    {
        try {
            $laravelResponse =  $this->boot($request);
            $this->booted(
                $response,
                $laravelResponse
            );
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    private function assertResponse(Response $response, $laravelResponse)
    {
        try {
            if ($laravelResponse instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
                $response->sendfile(
                    $laravelResponse->getFile()->getContent(),
                    (new ReflectionClass(\Symfony\Component\HttpFoundation\BinaryFileResponse::class))
                        ->getProperty('offset')
                        ->getValue($laravelResponse)
                );
                return;
            }

            if ($laravelResponse instanceof \Illuminate\Http\RedirectResponse) {
                $response->redirect($laravelResponse->getTargetUrl());
                return;
            }

            //todo

            // if ($response instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
            //     $streamed = new ReflectionClass(\Symfony\Component\HttpFoundation\StreamedResponse::class);
            //     $callback =  $streamed->getProperty('callback')->getValue($response);
            //     $this->response->end($callback());
            //     return;
            // }
            $content = $laravelResponse->getContent();
            $contentLength = strlen($content);
            if ($contentLength == 0) {
                $response->end();
            }
            if ($this->canSendImmediatelly($contentLength)) {
                $response->end($content);
                return;
            }

            for ($i = 0; $i < $contentLength; $i += $this->chunkSize) {
                $response->write(substr($content, $i, $this->chunkSize));
            }
            $response->end();
        } catch (\Throwable $th) {
            throw $th;
        }
    }
    private function canSendImmediatelly(int $length): bool
    {
        return $length <= $this->chunkSize;
    }

    private function make()
    {
        $this->app =  require $this->directory . "/bootstrap/app.php";
    }

    private function boot(Request $request)
    {
        $this->loadVendor();
        $req =  \Illuminate\Http\Request::create(
            $request->server['request_uri'],
            $request->server['request_method'],
            $request->server['request_uri'] == 'GET' ? $request->get ?? [] :  json_decode($request->rawContent(), true) ?? [],
            $request->cookie ?? [],
            $request->files ?? [],
            $request->server,
            $request->rawContent()
        );
        if (!$this->app) {
            $this->make();
        }
        $req->headers->add($request->header);

        return $this->getResponse($req);
    }
    private function booted(Response $response, $laravelResponse)
    {
        foreach ($laravelResponse->headers->allPreserveCase() as $key => $value) {
            $response->header($key, $value);
        }
        $response->status($laravelResponse->getStatusCode() ?? 500);
        return $this->assertResponse($response, $laravelResponse);
    }


    public static function create($laravelDir)
    {

        return (new static($laravelDir));
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    private function getResponse($request)
    {
        /**  @var \Illuminate\Contracts\Http\Kernel */
        $kernel = $this->app
            ->make(\Illuminate\Contracts\Http\Kernel::class);
        $response = $kernel->handle($request);

        return $response;
    }


    /**
     * 
     *  Laravel application can interact with socket
     * 
     * @return bool
     */


    public function isAbleToInteractWithSocket()
    {
        return $this->interactWithSocket;
    }
    /**
     * 
     *  Laravel application can interact with socket
     * 
     * @return Laravel
     */

    public function canInteractWithSocket()
    {
        $this->interactWithSocket = true;
        return $this;
    }

    /**
     * 
     * Get Laravel Application
     * 
     * @return \Illuminate\Foundation\Application
     */

    public function getApplication()
    {
        if (! $this->app) {
            $this->make();
        }
        if ($this->https ?? false) {
            $this->app->booting(function () {
                $this->app['url']->forceScheme("https");
            });
        }
        $this->app->terminating(function () {
            \Illuminate\Support\Facades\Auth::forgetGuards();
        });

        return $this->app;
    }

    /**
     * 
     * Load Laravel Vendor
     * 
     * 
     */

    public function loadVendor()
    {
        require_once $this->directory . "/vendor/autoload.php";;
    }

    /**
     * 
     * Get Laravel Public Path
     * 
     * @return string
     */

    public function getPublicPath()
    {

        return $this->directory . "/public";
    }

    /**
     * 
     * Get Laravel Storage Path
     * 
     * @return string
     */

    public function getStoragePath()
    {

        return $this->directory . "/storage";
    }



    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * 
     * Force Laravel Application to HTTPS Scheme
     * @return static
     * 
     */
    public function secure($value = true)
    {

        $this->https = $value;

        return $this;
    }
}
