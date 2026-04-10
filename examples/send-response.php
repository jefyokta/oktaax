<?php
require __DIR__ . "/../vendor/autoload.php";

use Oktaax\Contracts\Invokable;
use Oktaax\Core\Application;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Oktaax;

$app = new Oktaax();

// Simple text response
$app->get('/text', function (Request $request, Response $response) {
    $response->end('Hello from Oktaax text response!');
});

// JSON response
$app->get('/json', function (Request $request, Response $response) {
    $response->json([
        'status' => 'ok',
        'message' => 'This response was sent with $response->json()',
        'timestamp' => time(),
    ]);
});

// HTTP status + JSON response
$app->get('/error', function (Request $request, Response $response) {
    $response->status(422)->json([
        'error' => 'validation_failed',
        'message' => 'The request did not pass validation.',
    ]);
});

// Redirect response
$app->get('/redirect', function (Request $request, Response $response) {
    $response->redirect('/json');
});

// Set cookie and send text
$app->get('/cookie', function (Request $request, Response $response) {
    $response
        ->cookie('oktaax_demo', 'true', time() + 3600, '/')
        ->end('Cookie has been set.');
});

// Custom headers with response body
$app->get('/headers', function (Request $request, Response $response) {
    $response
        ->header('X-Demo-Header', 'OktaaxExample')
        ->json(['message' => 'Headers were sent with the response']);
});

// Use Response directly instead of returning a value
$app->get('/manual', function (Request $request, Response $response) {
    $response->status(201);
    $response->header('Content-Type', 'text/plain');
    $response->end('Created. Response sent directly with Response methods.');
});

// Return a plain string from the handler
$app->get('/return-string', function () {
    return 'This response is returned as a plain string.';
});

// Return an array and let the dispatcher JSON-encode it
$app->get('/return-array', function () {
    return [
        'status' => 'ok',
        'message' => 'This response was returned as an array.',
    ];
});

// Define a custom return type
class CustomReturn
{
    public function __construct(public array $payload) {}
}


$app->get('/return-custom', function () {
    return new CustomReturn([
        'message' => 'This response is returned as a custom object.',
        'created_at' => date('c'),
    ]);
});


//injecting method to response
class CustomResponse extends Invokable
{
    public function __invoke()
    {
        Application::context()->response()->end("custom response");
    }
}

$app->get("custom-response-method", function (Response $response) {
    $response->custom();
});

// Return JSON while also showing Response header usage
$app->get('/return-and-response', function (Response $response) {
    $response->header('X-Returned', 'true');
    return [
        'status' => 'ok',
        'message' => 'Returned value is still processed by the dispatcher.',
    ];
});

$app->listen(8005, function (Application $application) {
    $application->respond(CustomReturn::class, function (CustomReturn $result, Request $request, Response $response) {
        $response->status(202)
            ->header('X-Custom-Return', 'true')
            ->json([
                'type' => 'custom-return',
                'payload' => $result->payload,
            ]);
    });
    $application->inject(Response::class, 'custom', CustomResponse::class);
});
