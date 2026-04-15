<?php

/**
 * Error Handling Example
 *
 * This example demonstrates how to handle exceptions and create custom error responses
 * in Oktaax applications.
 */

require 'vendor/autoload.php';

use Oktaax\Console;
use Oktaax\Oktaax;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Core\Application;
use Oktaax\Exception\HttpException;
use Oktaax\Exception\ValidationException;

$app = new Oktaax();

// Custom exception class
class CustomBusinessException extends Exception
{
    public function __construct($message, $code = 400)
    {
        parent::__construct($message, $code);
    }
}

// Routes that may throw exceptions
$app->get('/user/{id}', function (Request $request, Response $response) {
    $id = $request->params['id'];

    if (!is_numeric($id)) {
        throw new HttpException(message:'Invalid user ID format',statusCode: 400);
    }

    if ($id == '999') {
        throw new CustomBusinessException('User not found');
    }

    // Simulate successful response
    $response->json([
        'id' => $id,
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ]);
});

$app->post('/validate', function (Request $request, Response $response) {
    $data = $request->body();

    if (empty($data['email'])) {
        throw new ValidationException(['email' => 'Email is required']);
    }

    if (empty($data['password'])) {
        throw new ValidationException(['password' => 'Password is required']);
    }

    if (strlen($data['password']) < 8) {
        throw new ValidationException(['password' => 'Password must be at least 8 characters']);
    }

    $response->json(['message' => 'Validation passed']);
});

$app->get('/error-demo', function (Request $request, Response $response) {
    $type = $request->input('type', 'http');

    switch ($type) {
        case 'http':
            throw new HttpException(statusCode: 418, message: 'This is an HTTP exception');
        case 'validation':
            throw new ValidationException([
                'field1' => 'Field 1 error',
                'field2' => 'Field 2 error'
            ]);
        case 'custom':
            throw new CustomBusinessException('This is a custom business exception');
        case 'generic':
            throw new Exception('This is a generic exception');
        default:
            $response->json(['message' => 'Use ?type=http|validation|custom|generic']);
    }
});

// Global error handling setup
$app->listen(3000, '127.0.0.1', function ($url, Application $coreApp) {

    // Handle validation exceptions
    $coreApp->catch(ValidationException::class, function (ValidationException $e) {
        Application::getResponse()->status(422)->json([
            'error' => 'Validation failed',
            'errors' => $e->getErrors()
        ]);
    });

    // Handle HTTP exceptions
    $coreApp->catch(HttpException::class, function (HttpException $e) {
        Application::getResponse()->status($e->getStatusCode())->json([
            'error' => $e->getMessage(),
            'code' => $e->getStatusCode()
        ]);
    });

    // Handle custom business exceptions
    $coreApp->catch(CustomBusinessException::class, function (CustomBusinessException $e) {
        Application::getResponse()->status($e->getCode())->json([
            'error' => 'Business logic error',
            'message' => $e->getMessage()
        ]);
    });

    // Handle any other exceptions
    $coreApp->catch(Exception::class, function (Exception $e) {
        Console::error('Unhandled exception: ' . $e->getMessage());
        Console::error($e->getTraceAsString());

        Application::getResponse()->status(500)->json([
            'error' => 'Internal server error',
            'message' => 'Something went wrong'
        ]);
    });

    echo "Error handling server started at $url\n";
    echo "Try these URLs:\n";
    echo "  $url/user/123 (valid user)\n";
    echo "  $url/user/abc (invalid ID format)\n";
    echo "  $url/user/999 (user not found)\n";
    echo "  $url/validate (POST with empty data)\n";
    echo "  $url/error-demo?type=http\n";
    echo "  $url/error-demo?type=validation\n";
    echo "  $url/error-demo?type=custom\n";
    echo "  $url/error-demo?type=generic\n";
});
