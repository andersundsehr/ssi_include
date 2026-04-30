<?php

declare(strict_types=1);

use AUS\SsiInclude\Middleware\InternalSsiRedirectMiddleware;

return [
    'frontend' => [
        InternalSsiRedirectMiddleware::class => [
            'target' => InternalSsiRedirectMiddleware::class,
            'before' => [
                'typo3/cms-core/normalized-params-attribute'
            ],
            'after' => [
            ],
        ]
    ],
];
