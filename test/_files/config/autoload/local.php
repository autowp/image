<?php

namespace Autowp\Image;

$imageDir = __DIR__ . '/../images/';

return [
    'imageStorage' => [
        'imageTableName' => 'image',
        'formatedImageTableName' => 'formated_image',
        'fileMode' => 0644,
        'dirMode' => 0755,
        
        'dirs' => [
            'format' => [
                'path' => $imageDir . "format",
                'url'  => 'http://localhost/image/format/',
                'namingStrategy' => [
                    'strategy' => 'pattern'
                ]
            ],
            'test' => [
                'path' => $imageDir . "test",
                'url'  => 'http://localhost/image/museum/',
                'namingStrategy' => [
                    'strategy' => 'serial',
                    'options'  => [
                        'deep' => 2
                    ]
                ]
            ],
            'naming' => [
                'path' => $imageDir . "naming",
                'url'  => 'http://localhost/image/naming/',
                'namingStrategy' => [
                    'strategy' => 'pattern'
                ]
            ],
        ],
        
        'formatedImageDirName' => 'format',
        
        'formats' => [
            'test'    => [
                'fitType'    => 0,
                'width'      => 160,
                'height'     => 120,
                'background' => '#fff',
                'strip'      => 1
            ],
        ]
    ],
    'db' => [
        'driver'         => 'Pdo',
        'pdodriver'      => 'mysql',
        'host'           => 'localhost',
        'charset'        => 'utf8',
        'dbname'         => 'autowp_image_test',
        'username'       => 'autowp_test',
        'password'       => 'test',
        'driver_options' => [
            \PDO::MYSQL_ATTR_INIT_COMMAND => "set time_zone = 'UTC'"
        ],
    ],
];
