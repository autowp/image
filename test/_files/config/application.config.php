<?php

return [
    'modules' => [
        'Zend\Router',
        'Autowp\Image'
    ],
    'module_listener_options' => [
        'module_paths' => [
            './vendor',
        ],
        'config_glob_paths' => [
            'test/_files/config/autoload/local.php',
        ],
    ]
];
