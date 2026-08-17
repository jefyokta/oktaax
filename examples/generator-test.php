<?php

use Swoole\Coroutine;

require_once __DIR__ . "/../vendor/autoload.php";

$app = new Oktaax\Oktaax;



$delay = async(function (string $name, int|float $seconds) {
    Coroutine::sleep($seconds);

    return [
        'name' => $name,
        'took' => $seconds,
    ];
});

$getUser = async(function (int $id) use ($delay) {
    $result = await($delay("get-user", 1));

    return [
        'id' => $id,
        'name' => 'Jefy Okta',
        'email' => 'jefy@example.com',
        'meta' => $result,
    ];
});

$getOrders = async(function (int $userId) use ($delay) {
    $result = await($delay("get-orders", 2));

    return [
        [
            'id' => 1001,
            'total' => 150_000,
        ],
        [
            'id' => 1002,
            'total' => 250_000,
        ],
        [
            'id' => 1003,
            'total' => 100_000,
        ],
        'meta' => $result,
    ];
});

$getNotifications = async(function (int $userId) use ($delay) {
    $result = await($delay("get-notifications", 1.5));

    return [
        [
            'id' => 1,
            'message' => 'Order #1001 shipped',
        ],
        [
            'id' => 2,
            'message' => 'Order #1002 completed',
        ],
        'meta' => $result,
    ];
});




$dashboard = async(function (int $userId) use (
    $getUser,
    $getOrders,
    $getNotifications
) {

    $user = await($getUser($userId));

    yield [
        'type' => 'user',
        'data' => $user,
    ];



    $orders = $getOrders($user['id']);
    $notifications = $getNotifications($user['id']);



    $orders = await($orders);

    yield [
        'type' => 'orders',
        'data' => $orders,
    ];


    $notifications = await($notifications);

    yield [
        'type' => 'notifications',
        'data' => $notifications,
    ];



    $orderItems = array_filter(
        $orders,
        fn ($item) => isset($item['total'])
    );

    $totalSpent = array_sum(
        array_column($orderItems, 'total')
    );

    return [
        'user_id' => $user['id'],
        'orders_count' => count($orderItems),
        'total_spent' => $totalSpent,
        'notifications_count' => count(
            array_filter(
                $notifications,
                fn ($item) => isset($item['message'])
            )
        ),
    ];
});




$generator = await($dashboard(123));

foreach ($generator as $event) {
    echo sprintf(
        "[%s]\n",
        $event['type']
    );

    print_r($event['data']);

    echo PHP_EOL;
}



$summary = $generator->getReturn();

echo "===== SUMMARY =====\n";

print_r($summary);