<?php

use Oktaax\Console;
use Oktaax\Core\Configuration;

it("it store configuration", function () {
    Configuration::set("inertia.base_view", "app");

    $conf = Configuration::all();

    expect($conf)->toHaveKey("inertia");
    expect($conf['inertia']['base_view'])->toBe("app");
    // Console::log($conf);
});
