<?php

/**
 * Custom Middleware Example
 *
 * This example demonstrates how to create and use custom middleware classes
 * including authentication, rate limiting, logging, and CORS handling.
 */

require 'vendor/autoload.php';

use Oktaax\Console;
use Oktaax\Oktaax;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Oktaax\Contracts\Middleware;
use Oktaax\Core\Promise\Promise;
use Oktaax\Http\Router;

use function Oktaax\Utils\async;
use function Oktaax\Utils\await;
use function Oktaax\Utils\setTimeout;

$app = new Oktaax();

// Custom Authentication Middleware
class AuthMiddleware implements Middleware
{
    private $users = [
        'admin' => ['password' => 'admin123', 'role' => 'admin'],
        'user' => ['password' => 'user123', 'role' => 'user'],
    ];

    public function handle(Request $request, Response $response, $next)
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Basic ')) {
            return $response->status(401)
                ->header('WWW-Authenticate', 'Basic realm="API"')
                ->json(['error' => 'Authentication required']);
        }

        $credentials = base64_decode(substr($authHeader, 6));
        list($username, $password) = explode(':', $credentials, 2);

        if (!isset($this->users[$username]) || $this->users[$username]['password'] !== $password) {
            return $response->status(401)->json(['error' => 'Invalid credentials']);
        }

        $request->user = [
            'username' => $username,
            'role' => $this->users[$username]['role']
        ];

        $next();
    }
}

// Rate Limiting Middleware
class RateLimitMiddleware implements Middleware
{
    private $requests = [];
    private $maxRequests;
    private $windowSeconds;

    public function __construct($maxRequests = 10, $windowSeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }

    public function handle(Request $request, Response $response, $next)
    {
        $ip = $request->header('X-Forwarded-For') ?? $request->header('X-Real-IP') ?? '127.0.0.1';
        $now = time();

        if (!isset($this->requests[$ip])) {
            $this->requests[$ip] = [];
        }

        // Remove old requests outside the window
        $this->requests[$ip] = array_filter($this->requests[$ip], function ($time) use ($now) {
            return ($now - $time) < $this->windowSeconds;
        });

        if (count($this->requests[$ip]) >= $this->maxRequests) {
            return $response->status(429)
                ->header('X-RateLimit-Limit', $this->maxRequests)
                ->header('X-RateLimit-Remaining', 0)
                ->header('X-RateLimit-Reset', $now + $this->windowSeconds)
                ->header('Retry-After', $this->windowSeconds)
                ->json([
                    'error' => 'Too many requests',
                    'retry_after' => $this->windowSeconds
                ]);
        }

        $this->requests[$ip][] = $now;

        $remaining = $this->maxRequests - count($this->requests[$ip]);
        $response->header('X-RateLimit-Limit', $this->maxRequests)
            ->header('X-RateLimit-Remaining', $remaining)
            ->header('X-RateLimit-Reset', $now + $this->windowSeconds);

        return  $next();
    }
}



// CORS Middleware
class CorsMiddleware implements Middleware
{
    private $allowedOrigins;
    private $allowedMethods;
    private $allowedHeaders;
    private $allowCredentials;

    public function __construct($config = [])
    {
        $this->allowedOrigins = $config['origins'] ?? ['*'];
        $this->allowedMethods = $config['methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
        $this->allowedHeaders = $config['headers'] ?? ['Content-Type', 'Authorization', 'X-Requested-With'];
        $this->allowCredentials = $config['credentials'] ?? false;
    }

    public function handle(Request $request, Response $response, $next)
    {
        $origin = $request->header('Origin');

        // Check if origin is allowed
        if (in_array('*', $this->allowedOrigins) || in_array($origin, $this->allowedOrigins)) {
            $response->header('Access-Control-Allow-Origin', $origin ?: '*');
        }

        $response->header('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->header('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->header('Access-Control-Max-Age', '86400'); // 24 hours

        if ($this->allowCredentials) {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }

        // Handle preflight requests
        if ($request->method() === 'OPTIONS') {
            $response->status(200)->end();
            return;
        }

        $next();
    }
}


// Async Processing Middleware
class AsyncProcessingMiddleware implements Middleware
{
    public function handle(Request $request, Response $response, $next)
    {
        return  async(function () use ($next) {
            return $next();
        })();
    }
}

class CompressionMiddleware implements Middleware
{
    public function handle(Request $request, Response $response, $next)
    {
        $acceptEncoding = $request->header('Accept-Encoding');

        if (str_contains($acceptEncoding, 'gzip')) {
            $response->header('Content-Encoding', 'gzip');
        }

        $next();
    }
}


$app->use(new CorsMiddleware([
    'origins' => ['http://localhost:3000', 'http://localhost:8080'],
    'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
    'credentials' => true
]));
$app->use(new RateLimitMiddleware(5, 60)); // 5 requests per minute

// Public routes
$app->get('/', function (Request $request, Response $response) {
    $response->json([
        'message' => 'Welcome to the API',
        'middleware' => ['Logging', 'CORS', 'RateLimit']
    ]);
});

$app->get('/health', function (Request $request, Response $response) {
    $response->json([
        'status' => 'healthy',
        'timestamp' => date('c')
    ]);
});

// Protected routes with authentication
$app->middleware([new AuthMiddleware()], function ($router) {

    $router->get('/profile', function (Request $request, Response $response) {
        $response->json([
            'user' => $request->user,
            'middleware' => ['Auth']
        ]);
    });

    $router->middleware([function ($req, $res, $next) {
        if ($req->user['role'] !== 'admin') {
            return $res->status(403)->json(['error' => 'Admin access required']);
        }
        $next();
    }], function (Router $subRouter) {

        $subRouter->get('/admin/users', function (Request $request, Response $response) {
            $response->json([
                'users' => [
                    ['id' => 1, 'username' => 'admin'],
                    ['id' => 2, 'username' => 'user']
                ],
                'middleware' => ['Auth', 'AdminCheck']
            ]);
        });
    });
});


// Async routes
$app->middleware([new AsyncProcessingMiddleware()], function ($router) {
    $router->get('/async/data', function (Request $request, Response $response) {
        // Simulate async work
        await(new Promise(function ($resolve) {
            setTimeout(function () use ($resolve) {
                $resolve('Async data loaded');
            }, 1000);
        }));

        $response->json([
            'message' => 'Async processing completed',
            'middleware' => ['AsyncProcessing']
        ]);
    });
});

// Routes with compression
$app->middleware([new CompressionMiddleware()], function ($router) {
    $router->get('/compressed', function (Request $request, Response $response) {
        $largeData = array_fill(0, 1000, 'This is a large dataset that could benefit from compression.');
        $response->json([
            'data' => $largeData,
            'middleware' => ['Compression']
        ]);
    });
});

// Middleware stack demonstration
$app->get('/middleware-stack', function (Request $request, Response $response) {
    $response->json([
        'message' => 'This response went through all middleware',
        'middleware_stack' => [
            'CorsMiddleware',
            'RateLimitMiddleware',
            'Route handler'
        ],
        'headers' => [
            'X-RateLimit-Limit' => $request->header('X-RateLimit-Limit'),
            'X-RateLimit-Remaining' => $request->header('X-RateLimit-Remaining'),
            'Access-Control-Allow-Origin' => $request->header('Access-Control-Allow-Origin')
        ]
    ]);
});

$app->listen(3000, '127.0.0.1', function ($url) {
    echo "Custom Middleware server started at $url\n";
    echo "\nTest accounts for authentication:\n";
    echo "  admin/admin123 (admin role)\n";
    echo "  user/user123 (user role)\n";
    echo "\nEndpoints:\n";
    echo "  GET $url/ - Public endpoint\n";
    echo "  GET $url/health - Health check\n";
    echo "  GET $url/profile - Protected profile (requires auth)\n";
    echo "  GET $url/admin/users - Admin users list\n";
    echo "  POST $url/admin/users - Create user (with validation)\n";
    echo "  GET $url/cached/time - Cached time endpoint\n";
    echo "  GET $url/cached/data - Cached data endpoint\n";
    echo "  GET $url/async/data - Async processing\n";
    echo "  GET $url/compressed - Compression demo\n";
    echo "  GET $url/middleware-stack - See all middleware in action\n";
    echo "\nUse Authorization: Basic <base64-encoded-credentials>\n";
});
