<?php

return [
    'frontend' => [
        \AUS\SsiInclude\Middleware\InternalSsiRedirectMiddleware::class => [
            'target' => \AUS\SsiInclude\Middleware\InternalSsiRedirectMiddleware::class,
            'before' => [
                'typo3/cms-core/normalized-params-attribute'
            ],
            'after' => [
            ],
        ]
    ],
];
