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


namespace Oktaax\Trait;

use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Oktaax\Types\Laravel;
use Reflection;
use ReflectionClass;
use Swoole\Coroutine;
use Swoole\Http\Request as HttpRequest;
use Swoole\Http\Response;

/**
 * @deprecated 
 */
trait Laravelable
{


    private \Oktaax\Http\Request $request;
    private \Oktaax\Http\Response $response;

    /**
     * @var \Illuminate\Foundation\Application $app
     */
    private $app;

    /**
     * @var Laravel
     */

    private $laravel;

    private $chunkSize = 8192;

    



    /**
     * 
     * Register laravel application
     * @param Laravel $laravelApplication
     * 
     */
    public function laravelRegister(Laravel $laravel)
    {
        $this->laravel = $laravel;
        $this->laravel->loadVendor();
        $this->app = $this->laravel->getApplication();
        static::setCurrentApplication($this->app);

        $this->setServer('enable_static_handler', true);
        $this->setServer('document_root', $this->laravel->getPublicPath());
    }

    /**
     * 
     * boot request event
     * @param HttpRequest $request
     * @param Response $response
     * 
     */
    protected function bootRequestEvent(HttpRequest $request, Response $response)
    {
        $this->request = new \Oktaax\Http\Request($request);
        $this->response = new \Oktaax\Http\Response($response, $this->request, $this->config);
    }



    protected function onRequest()
    {

        //Swoole server request event from orignal class
        $this->server->on('request', function (HttpRequest $request, Response $response) {

            // Laravel::create($this->laravel->getDirectory())
            //     ->secure($this->https ?? false)
            //     ->terminate($request, $response);
            // return;
            //creatin request and response object
            $this->bootRequestEvent($request, $response);

            //laravel application
            try {
                $this->response->header("Host", $this->request->header['host']);
                $responseFromLaravel = $this->bootstrapLaravel();
                $this->response->status($responseFromLaravel->getStatusCode() ?? 500);
                return  $this->resolveResponse($responseFromLaravel);
            } catch (\Throwable $th) {
                return  $this->handleError($th);
            }
        });
    }

    public function withSSL($cert, $key) {
        parent::withSSL($cert,$key);
        $this->laravel->secure();
        return $this;
    }

    /**
     * 
     * Bootstrap laravel application
     * 
     * @return \Illuminate\Http\Response|mixed
     */

    protected function bootstrapLaravel()
    {
        $laravelRequest = \Illuminate\Http\Request::create(
            $this->request->server['request_uri'],
            $this->request->server['request_method'],
            $this->request->isMethod("GET") ? $this->request->get ?? [] : (json_decode($this->request->rawContent(), true) ?? []),
            $this->request->cookie ?? [],
            $this->request->files ?? [],
            $this->request->server ?? [],
            $this->request->rawContent()
        );
        try {

            $laravelRequest->headers->add($this->request->header);

            $kernel = $this->app
                ->make(\Illuminate\Contracts\Http\Kernel::class);
            $response = $kernel->handle($laravelRequest);

            $this->handleHeaders($response->headers->allPreserveCase());
        } catch (\Throwable $th) {
            $response = $this->app
                ->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
                ->render($laravelRequest, $th);

            \Oktaax\Console::error($th->getMessage());
        } finally {


            $kernel->terminate($laravelRequest, $response);
            return $response;
        }
    }


    /**
     * @param array $headers
     * 
     * @see \Symfony\Component\HttpFoundation\HeaderBag
     */
    private function handleHeaders($headers)
    {

        foreach ($headers as $key => $value) {
            $this->response->header($key, $value);
        }
        $this->response->header("Server", "Oktaax");
    }



    /**
     * 
     * Handling error of bootstraping laravel application
     * @param \Throwable $th
     * 
     */
    private function handleError(\Throwable $th)
    {
        \Oktaax\Console::error($th->getMessage());

        $file =  Coroutine::readFile($th->getFile());
        $lines = explode("\n", $file);
        $code = $lines[$th->getLine() - 1];

        ob_start();
        $message = $th->getMessage();
        $prevcode = $lines[$th->getLine() - 2] ?? '';
        // $code = $code;
        $nextcode = $lines[$th->getLine()] ?? '';
        $error = $th;
        $req = $this->request;
        require __DIR__ . "/../Views/Error/index.php";
        $content = ob_get_clean();
        $this->response->status(500);
        return  $this->response->end($content);
        throw $th;
    }

    protected static function setCurrentApplication(Application $app){
        $app->instance('app',$app);
        $app->instance(Container::class,$app);
        Container::setInstance($app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(($app));
    }





    private function resolveResponse($response)
    {

        try {

            if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
                $this->response->sendfile(
                    $response->getFile()->getContent(),
                    (new ReflectionClass(\Symfony\Component\HttpFoundation\BinaryFileResponse::class))
                        ->getProperty('offset')
                        ->getValue($response)
                );
                return;
            }

            if ($response instanceof \Illuminate\Http\RedirectResponse) {
                $this->response->redirect($response->getTargetUrl());
                return;
            }

            //todo

            // if ($response instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
            //     $streamed = new ReflectionClass(\Symfony\Component\HttpFoundation\StreamedResponse::class);
            //     $callback =  $streamed->getProperty('callback')->getValue($response);
            //     $this->response->end($callback());
            //     return;
            // }
            $content = $response->getContent();
            $contentLength = strlen($content);
            if ($contentLength == 0) {
                $this->response->end();
            }
            if ($this->canSendImmediatelly($contentLength)) {
                $this->response->end($content);
                return;
            }

            for ($i = 0; $i < $contentLength; $i += $this->chunkSize) {
                $this->response->write(substr($content, $i, $this->chunkSize));
            }
            $this->response->end();
        } catch (\Throwable $th) {
            return "unregistered laravel response";
        }
    }

    private function canSendImmediatelly(int $length): bool
    {
        return $length <= $this->chunkSize;;
    }
}
