<?php

return [
    'renderer' => 'elements',

    'renderers' => [
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'tryItCredentialsPolicy' => 'include',
        ],
    ],

    'api_path' => [
        'include' => [
            'api',
            'sanctum/csrf-cookie',
        ],
    ],
];
