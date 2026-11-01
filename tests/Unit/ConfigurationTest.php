<?php

use Oktaax\Config\Configuration;

it('stores configuration on its instance', function () {
    $configuration = new Configuration();
    $configuration->set('inertia.base_view', 'app');
    expect($configuration->get('inertia.base_view'))->toBe('app');
    expect($configuration->all())->toBe(['inertia' => ['base_view' => 'app']]);
    expect((new Configuration())->has('inertia.base_view'))->toBeFalse();
});
