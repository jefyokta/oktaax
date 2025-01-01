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

use Oktaax\Types\Laravel;
use OpenSwoole\Coroutine;
use OpenSwoole\Http\Request as HttpRequest;
use OpenSwoole\Http\Response;

trait Laravelable
{


    private \Oktaax\Http\Request $request;
    private \Oktaax\Http\Response $response;

    private $app;
    private $laravel;

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

        //Swoole server request event from Oktaax class
        $this->server->on('request', function (HttpRequest $request, Response $response) {

            //preparing request and response object
            $this->bootRequestEvent($request, $response);

            //laravel application
            try {
                $this->laravelPublic();
                $this->response->header("Host", $this->request->header['host']);
                $responseFromLaravel = $this->bootstrapLaravel();
                $this->response->status($responseFromLaravel->getStatusCode() ?? 500);
                return  $this->endResponse($responseFromLaravel);
            } catch (\Throwable $th) {
                return  $this->handleError($th);
            }
        });
    }

    /**
     * 
     * Bootstrap laravel application
     * 
     * @param Laravel $laravel
     * 
     * @return \Illuminate\Http\Response|mixed
     */

    protected function bootstrapLaravel()
    {
        try {
            $laravelRequest = \Illuminate\Http\Request::create(
                $this->request->server['request_uri'],
                $this->request->server['request_method'],
                $this->request->isMethod("GET") ? $this->request->get ?? [] : $this->request->bodies(),
                $this->request->cookie ?? [],
                $this->request->files ?? [],
                $this->request->server ?? [],
                $this->request->rawContent()
            );

            $laravelRequest->headers->add($this->request->header);

            $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
            $response = $kernel->handle($laravelRequest);
            $this->handleHeaders($response->headers->allPreserveCase());
        } catch (\Throwable $th) {
            $response = $this->app
                ->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
                ->render($laravelRequest, $th);

            \Oktaax\Console::error($th->getMessage());
        }
        $kernel->terminate($laravelRequest, $response);
        return $response;
    }

    /**
     * 
     * Serve public files
     * 
     * @return void
     */
    protected function laravelPublic()
    {

        $uri =   $this->request->uri;
        if ($uri !== '/' && file_exists($this->laravel->getPublicPath() . $uri)) {

            $mime  = require __DIR__ . "/../Utils/MimeTypes.php";
            $mime = $mime[pathinfo($this->laravel->getPublicPath() . $uri, PATHINFO_EXTENSION)];
            $this->response->header("Content-Type", $mime);
            return $this->response->end(file_get_contents($this->laravel->getPublicPath() . $uri));
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
        $code = $code;
        $nextcode = $lines[$th->getLine()] ?? '';
        $error = $th;
        $req = $this->request;
        require __DIR__ . "/../Views/Error/index.php";
        $content = ob_get_clean();
        $this->response->status(500);
        return  $this->response->end($content);
        throw $th;
    }

    /**
     * 
     * @param \Illuminate\Http\Response|mixed $response
     * 
     * 
     */
    protected function endResponse($response)
    {
        return  $this->response->end($this->matchResponse($response));
    }


    private function matchResponse($response)
    {
        if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
            return $response->getFile()->getContent();
        }
        if ($response instanceof \Illuminate\Http\Response) {
            return $response->getContent();
        }
    }
}
