<?php
require_once __DIR__ . "/../vendor/autoload.php";


use Oktaax\Console;
// this class is simmiliar to jsavascript console
Console::group("Booting Oktaax Server");

Console::info("Environment: %s", "development");
Console::info("Port: %d", 8000);

Console::time("startup");

usleep(150000);

Console::timeEnd("startup");

Console::groupEnd();




Console::group("WebSocket Connection");

$client = [
    "id" => 101,
    "ip" => "192.168.1.10",
    "agent" => "Chrome",
    "status" => "connected"
];

Console::log("Client connected: %o", $client);

Console::groupEnd();



Console::group("Incoming Message");

$message = [
    "type" => "chat",
    "from" => "user_101",
    "payload" => "Hello server!"
];

Console::debug("Raw message: %j", $message);

Console::time("handle_message");

usleep(200000);

Console::log("Parsing message...");
usleep(100000);

Console::log("Routing message...");
usleep(100000);

Console::timeEnd("handle_message");

Console::groupEnd();


Console::group("Business Logic");

$user = [
    "id" => 101,
    "name" => "Jefy",
    "roles" => ["admin", "developer"],
    "active" => true
];

Console::log("User loaded: %o", $user);

if (!$user["active"]) {
    Console::warn("User inactive!");
} else {
    Console::info("User is active");
}

Console::groupEnd();



Console::group("Database Query");

Console::time("db_query");

$users = [
    ["id" => 1, "name" => "okta", "role" => "admin"],
    ["id" => 2, "name" => "jefy", "role" => "dev"],
    ["id" => 3, "name" => "andi", "role" => "guest"],
];

usleep(120000);

Console::timeEnd("db_query");

Console::table($users);

Console::groupEnd();




Console::group(" Error Handling");

try {
    throw new Exception("Database connection lost");
} catch (Throwable $e) {
    Console::error("Error: %s", $e->getMessage());
}

Console::groupEnd();




Console::group("Sending Response");

$response = [
    "status" => "ok",
    "data" => $users
];

Console::log("Response JSON: %j", $response);

Console::groupEnd();



Console::group("Broadcasting");

$clients = [
    ["id" => 101, "ip" => "192.168.1.10"],
    ["id" => 102, "ip" => "192.168.1.11"],
    ["id" => 103, "ip" => "192.168.1.12"],
];

foreach ($clients as $c) {
    Console::log("Send to client %d (%s)", $c["id"], $c["ip"]);
}

Console::groupEnd();




Console::group("Deep Debug");

$complex = [
    "server" => [
        "uptime" => 12345,
        "memory" => [
            "used" => "32MB",
            "free" => "128MB"
        ]
    ],
    "clients" => $clients
];

Console::dir($complex);

Console::groupEnd();




Console::group("Shutdown");

Console::warn("Server stopping...");
Console::time("shutdown");

usleep(100000);

Console::timeEnd("shutdown");

Console::groupEnd();



