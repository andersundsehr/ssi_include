<?php

use Composer\InstalledVersions;

/** @var string $_EXTKEY */
$EM_CONF[$_EXTKEY] = [
    'title' => 'SSI Include - Render your includes',
    'description' => 'Allows to periodically create ssi includes from anders und sehr GmbH',
    'category' => 'fe',
    'author' => 'Matthias Vogel',
    'author_email' => 'm.vogel@andersundsehr.com',
    'author_company' => 'anders und sehr GmbH',
    'state' => 'stable',
    'version' =>  InstalledVersions::getPrettyVersion('andersundsehr/ssi-include'),
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-11.99.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
