<?php

declare(strict_types=1);

namespace Autowp\Image;

use PDO;

use function getenv;

if (getenv('PDODRIVER') === 'pgsql') {
    $db = [
        'driver'         => 'Pdo',
        'pdodriver'      => 'pgsql',
        'host'           => 'localhost',
        'charset'        => 'utf8',
        'dbname'         => 'autowp_image_test',
        'username'       => 'postgres',
        'password'       => '',
        'driver_options' => [
            PDO::ATTR_PERSISTENT => true,
        ],
    ];
} else {
    $db = [
        'driver'         => 'Pdo',
        'pdodriver'      => 'mysql',
        'host'           => 'localhost',
        'charset'        => 'utf8',
        'dbname'         => 'autowp_image_test',
        'username'       => 'autowp_test',
        'password'       => 'test',
        'driver_options' => [
            PDO::MYSQL_ATTR_INIT_COMMAND => "set time_zone = 'UTC'",
        ],
    ];
}

return [
    'imageStorage' => [
        'imageTableName'         => 'image',
        'formatedImageTableName' => 'formated_image',
        'dirs'                   => [
            'format' => [
                'namingStrategy' => [
                    'strategy' => 'pattern',
                ],
                'bucket'         => 'test-format',
            ],
            'test'   => [
                'namingStrategy' => [
                    'strategy' => 'serial',
                    'options'  => [
                        'deep' => 2,
                    ],
                ],
                'bucket'         => 'test-test',
            ],
            'naming' => [
                'namingStrategy' => [
                    'strategy' => 'pattern',
                ],
                'bucket'         => 'test-naming',
            ],
        ],
        'formatedImageDirName'   => 'format',
        'formats'                => [
            'test'            => [
                'fitType'    => 0,
                'width'      => 160,
                'height'     => 120,
                'background' => '#fff',
                'strip'      => true,
            ],
            'picture-gallery' => [
                'fitType'    => 2,
                'width'      => 1024,
                'height'     => 768,
                'reduceOnly' => true,
                'strip'      => true,
                'format'     => 'jpeg',
            ],
            'with-processor'  => [
                'fitType'    => 0,
                'width'      => 160,
                'height'     => 120,
                'background' => '#fff',
                'strip'      => true,
                'processors' => [
                    'normalize',
                    'negate',
                ],
            ],
        ],
        's3'                     => [
            'region'                  => '',
            'version'                 => 'latest',
            'endpoint'                => getenv('S3_ENDPOINT'),
            'credentials'             => [
                'key'    => getenv('S3_KEY'),
                'secret' => getenv('S3_SECRET'),
            ],
            'use_path_style_endpoint' => true,
        ],
    ],
    'db'           => $db,
];
