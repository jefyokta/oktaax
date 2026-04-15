<?php

/**
 * JWT Authentication Example
 *
 * This example demonstrates JWT token-based authentication
 * using Firebase JWT library included with Oktaax.
 */

require 'vendor/autoload.php';

use Oktaax\Oktaax;
use Oktaax\Http\Request;
use Oktaax\Http\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$app = new Oktaax();

// JWT configuration
define('JWT_SECRET', 'your-super-secret-jwt-key-change-this-in-production');
define('JWT_ALGORITHM', 'HS256');
define('TOKEN_EXPIRY', 3600); // 1 hour

// Mock user database
$users = [
    ['id' => 1, 'username' => 'admin', 'password' => password_hash('admin123', PASSWORD_DEFAULT), 'role' => 'admin'],
    ['id' => 2, 'username' => 'user', 'password' => password_hash('user123', PASSWORD_DEFAULT), 'role' => 'user'],
    ['id' => 3, 'username' => 'john', 'password' => password_hash('john123', PASSWORD_DEFAULT), 'role' => 'user'],
];

// Helper function to find user by username
function findUserByUsername($username)
{
    global $users;
    foreach ($users as $user) {
        if ($user['username'] === $username) {
            return $user;
        }
    }
    return null;
}

// Helper function to find user by ID
function findUserById($id)
{
    global $users;
    foreach ($users as $user) {
        if ($user['id'] == $id) {
            return $user;
        }
    }
    return null;
}

// Login endpoint - generate JWT token
$app->post('/login', function (Request $request, Response $response) {
    $data = $request->body();

    if (empty($data['username']) || empty($data['password'])) {
        return $response->status(400)->json([
            'error' => 'Username and password are required'
        ]);
    }

    $user = findUserByUsername($data['username']);

    if (!$user || !password_verify($data['password'], $user['password'])) {
        return $response->status(401)->json([
            'error' => 'Invalid credentials'
        ]);
    }

    // Create JWT payload
    $payload = [
        'iss' => 'oktaax-jwt-example',
        'sub' => $user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'iat' => time(),
        'exp' => time() + TOKEN_EXPIRY
    ];

    $token = JWT::encode($payload, JWT_SECRET, JWT_ALGORITHM);

    $response->json([
        'message' => 'Login successful',
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ],
        'expires_in' => TOKEN_EXPIRY
    ]);
});


// JWT Authentication middleware
$authMiddleware = function (Request $request, Response $response, $next) {
    $authHeader = $request->header('Authorization');

    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        return $response->status(401)->json([
            'error' => 'Authorization header missing or invalid'
        ]);
    }

    $token = substr($authHeader, 7); // Remove "Bearer " prefix

    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, JWT_ALGORITHM));

        // Check if token is expired
        if ($decoded->exp < time()) {
            return $response->status(401)->json([
                'error' => 'Token expired'
            ]);
        }

        // Attach user to request
        $user = findUserById($decoded->sub);
        if (!$user) {
            return $response->status(401)->json([
                'error' => 'User not found'
            ]);
        }

        $request->user = $user;
        $next();
    } catch (Exception $e) {
        return $response->status(401)->json([
            'error' => 'Invalid token'
        ]);
    }
};

// Role-based authorization middleware
function requireRole($requiredRole)
{
    return function (Request $request, Response $response, $next) use ($requiredRole) {
        if (!isset($request->user)) {
            return $response->status(401)->json(['error' => 'Authentication required']);
        }

        if ($request->user['role'] !== $requiredRole) {
            return $response->status(403)->json([
                'error' => 'Insufficient permissions',
                'required_role' => $requiredRole,
                'user_role' => $request->user['role']
            ]);
        }

        $next();
    };
}

// Protected routes
$app->middleware([$authMiddleware], function ($router) {

    // Get current user profile
    $router->get('/profile', function (Request $request, Response $response) {
        $response->json([
            'user' => [
                'id' => $request->user['id'],
                'username' => $request->user['username'],
                'role' => $request->user['role']
            ]
        ]);
    });

    // Update profile
    $router->put('/profile', function (Request $request, Response $response) {
        $data = $request->body();

        // In a real app, you'd update the database here
        $response->json([
            'message' => 'Profile updated',
            'user' => [
                'id' => $request->user['id'],
                'username' => $request->user['username'],
                'role' => $request->user['role']
            ]
        ]);
    });

    // Admin-only routes
    $router->middleware([requireRole('admin')], function ($subRouter) {

        // List all users (admin only)
        $subRouter->get('/users', function (Request $request, Response $response) {
            global $users;

            $userList = array_map(function ($user) {
                return [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role']
                ];
            }, $users);

            $response->json([
                'users' => $userList,
                'total' => count($users)
            ]);
        });

        // Delete user (admin only)
        $subRouter->delete('/users/{id}', function (Request $request, Response $response) {
            $id = (int) $request->params['id'];

            if ($id === $request->user['id']) {
                return $response->status(400)->json(['error' => 'Cannot delete yourself']);
            }

            global $users;
            $index = array_search($id, array_column($users, 'id'));

            if ($index === false) {
                return $response->status(404)->json(['error' => 'User not found']);
            }

            array_splice($users, $index, 1);

            $response->json(['message' => 'User deleted successfully']);
        });
    });
});

// Refresh token endpoint
$app->post('/refresh', function (Request $request, Response $response) {
    $authHeader = $request->header('Authorization');

    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        return $response->status(401)->json(['error' => 'Authorization header required']);
    }

    $token = substr($authHeader, 7);

    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, JWT_ALGORITHM));

        // Generate new token with fresh expiry
        $newPayload = [
            'iss' => 'oktaax-jwt-example',
            'sub' => $decoded->sub,
            'username' => $decoded->username,
            'role' => $decoded->role,
            'iat' => time(),
            'exp' => time() + TOKEN_EXPIRY
        ];

        $newToken = JWT::encode($newPayload, JWT_SECRET, JWT_ALGORITHM);

        $response->json([
            'message' => 'Token refreshed',
            'token' => $newToken,
            'expires_in' => TOKEN_EXPIRY
        ]);
    } catch (Exception $e) {
        return $response->status(401)->json(['error' => 'Invalid token']);
    }
});

// Public route to verify token (for testing)
$app->post('/verify', function (Request $request, Response $response) {
    $data = $request->body();

    if (empty($data['token'])) {
        return $response->status(400)->json(['error' => 'Token is required']);
    }

    try {
        $decoded = JWT::decode($data['token'], new Key(JWT_SECRET, JWT_ALGORITHM));

        $response->json([
            'valid' => true,
            'payload' => [
                'user_id' => $decoded->sub,
                'username' => $decoded->username,
                'role' => $decoded->role,
                'issued_at' => date('c', $decoded->iat),
                'expires_at' => date('c', $decoded->exp)
            ]
        ]);
    } catch (Exception $e) {
        $response->json([
            'valid' => false,
            'error' => $e->getMessage()
        ]);
    }
});

$app->listen(3000, '127.0.0.1', function ($url) {
    echo "JWT Authentication server started at $url\n";
    echo "\nTest accounts:\n";
    echo "  admin/admin123 (admin role)\n";
    echo "  user/user123 (user role)\n";
    echo "  john/john123 (user role)\n";
    echo "\nEndpoints:\n";
    echo "  POST $url/login - Login and get JWT token\n";
    echo "  POST $url/register - Register new user\n";
    echo "  POST $url/verify - Verify JWT token\n";
    echo "  POST $url/refresh - Refresh JWT token\n";
    echo "  GET $url/profile - Get user profile (requires auth)\n";
    echo "  PUT $url/profile - Update profile (requires auth)\n";
    echo "  GET $url/users - List all users (admin only)\n";
    echo "  DELETE $url/users/{id} - Delete user (admin only)\n";
});
