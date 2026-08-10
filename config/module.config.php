<?php

declare(strict_types=1);

namespace Laminas\ApiTools;

use Laminas\ServiceManager\Factory\InvokableFactory;

return [
    'asset_manager'   => [
        'resolver_configs' => [
            'paths' => [
                __DIR__ . '/../asset',
            ],
        ],
    ],
    'router'          => [
        'routes' => [
            'api-tools' => [
                'type'          => 'literal',
                'options'       => [
                    'route' => '/api-tools',
                ],
                'may_terminate' => false,
            ],
        ],
    ],
    'service_manager' => [
        'factories' => [
            MvcAuth\UnauthenticatedListener::class => InvokableFactory::class,
            MvcAuth\UnauthorizedListener::class    => InvokableFactory::class,
        ],
    ],
];
