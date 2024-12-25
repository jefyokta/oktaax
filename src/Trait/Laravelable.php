<?php

namespace Oktaax\Trait;

use Illuminate\Contracts\Foundation\Application;
use Oktaax\Console;
use Oktaax\Support\SessionTable;
use Oktaax\Types\Laravel;
use Oktaax\Utils\Security;
use OpenSwoole\Coroutine;
use OpenSwoole\Http\Request as HttpRequest;
use OpenSwoole\Http\Response;

trait Laravelable
{


    private $laravelApplications = [];
    private \Oktaax\Http\Request $request;
    private \Oktaax\Http\Response $response;

    /**
     * 
     * @param Laravel ...$laravelApplications
     */
    public function laravelRegister(Laravel ...$laravelApplications)
    {

        foreach ($laravelApplications as $laravel) {
            if (isset($this->laravelApplications[$laravel->domain])) {
                throw new \InvalidArgumentException("Domain {$laravel->domain} is already registered.");
            }
            $this->laravelApplications[$laravel->domain] = $laravel;
        };
    }


    private function parseHost($host)
    {
        if (!str_contains($host, ":")) {
            return $host;
        }
        $host = explode(":", $host);
        return $host[0];
    }



    protected function onRequest()
    {

        $this->server->on('request', function (HttpRequest $request, Response $response) {
            $this->request = new \Oktaax\Http\Request($request);
            $this->response = new \Oktaax\Http\Response($response, $this->request, $this->config);

            $laravel = $this->laravelApplications[$this->parseHost($this->request->header['host'])] ?? null;

            if (is_null($laravel)) {
                $this->response->status(502);
                if ($this->response->response->isWritable()) {
                    $title = require __DIR__ . "/../Utils/HttpError.php";
                    ob_start();
                    $req = $this->request;
                    $status = $this->response->status;
                    $title = $title[$status];
                    require __DIR__ . "/../Views/HttpError/index.php";
                    $content = ob_get_clean();
                    return $response->end($content);
                }
            } else {
                try {
                    $this->response->header("Host", $this->request->header['host']);
                    $this->laravelPublic($laravel);

                    $response = $this->bootstrapLaravel($laravel);
                    $this->response->status($response->getStatusCode() ?? 500);
                    return   $this->response->end($response->getContent());
                } catch (\Throwable $th) {
                    return  $this->handleError($th);
                }
            }
        });
    }

    /**
     * 
     * @param Laravel $laravel
     * 
     * @return \Illuminate\Http\Response
     */

    protected function bootstrapLaravel(Laravel $laravel)
    {
        if (!$this->request) {
            throw new \Exception("Request object is not initialized.");
        }
        $this->overWriteGlobals($this->request);
        $this->setServer('document_root', $laravel->getPublicPath() . "/");

        $laravel->loadVendor();

        // laravel application
        $app = $laravel->getApplication();

        // $this->handleSession($app);

        // laravel application request
        $laravelRequest = \Illuminate\Http\Request::create(
            $this->request->server['request_uri'],
            $this->request->server['request_method'],
            $this->request->parameters(),
            $this->request->cookie ?? [],
            $this->request->files ?? [],
            $this->request->server ?? [],
            $this->request->rawContent()
        );


        $laravelRequest->headers->add($this->request->header);

        $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);


        $response = $kernel->handle($laravelRequest);

        $this->handleHeaders($response->headers->allPreserveCase());



        $kernel->terminate($laravelRequest, $response);
        return $response;
    }


    protected function laravelPublic(Laravel $laravel)
    {

        $uri =   $this->request->server['request_uri'];
        if ($uri !== '/' && file_exists($laravel->getPublicPath() . $uri)) {

            $mime  = require __DIR__ . "/../Utils/MimeTypes.php";
            $mime = $mime[pathinfo($laravel->getPublicPath() . $uri, PATHINFO_EXTENSION)];
            $this->response->header("Content-Type", $mime);
            return  $this->response->end(file_get_contents($laravel->getPublicPath() . $uri));
        }
    }

    /**
     * 
     * send cookies from laravel application
     *  @param \Symfony\Component\HttpFoundation\Cookies[] $cookies
     */
    private function handleCookies($cookies)
    {
        foreach ($cookies as $cookie) {

            $this->response->cookie(
                $cookie->getName(),
                $cookie->getValue(),
                $cookie->getExpiresTime(),
                $cookie->getPath(),
                $cookie->getDomain(),
                $cookie->isSecure(),
                $cookie->isHttpOnly(),
                $cookie->getSameSite()
            );
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

    private function overWriteGlobals(\Oktaax\Http\Request $request)
    {
        // $_GET = $request->get;
        // $_POST = $request->post;
        // $_FILES = $request->files;
        // $_SERVER = array_change_key_case($request->server, CASE_UPPER);
        // $_COOKIE = $request->cookie;
    }

    /**
     * 
     * @param Application $app
     */

    private function handleSession(&$app)
    {
        $app->singleton('session', function (&$app) {
            $handler = new SessionTable();
            $manager = new \Illuminate\Session\SessionManager($app);
            $manager->buildSession($handler);
            $manager->extend('swoole', function () use ($handler) {
                return $handler;
            });
            return $manager;
        });

        $app->singleton('session.store', function (&$app) {
            $handler = new SessionTable();
            return new \Illuminate\Session\Store(config('session.cookie'), $handler);
        });
    }


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
}
