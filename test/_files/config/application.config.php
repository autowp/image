<?php

declare(strict_types=1);

return [
    'modules'                 => [
        'Zend\\Db',
        'Zend\\Router',
        'Autowp\\Image',
        'Autowp\\ZFComponents',
    ],
    'module_listener_options' => [
        'module_paths'      => [
            './vendor',
        ],
        'config_glob_paths' => [
            'test/_files/config/autoload/local.php',
        ],
    ],
];
