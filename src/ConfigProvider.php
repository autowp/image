<?php

declare(strict_types=1);

namespace Autowp\Image;

use Zend\ServiceManager\Factory\InvokableFactory;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'console'            => $this->getConsoleConfig(),
            'controller_plugins' => $this->getControllerPluginConfig(),
            'controllers'        => $this->getControllersConfig(),
            'dependencies'       => $this->getDependencyConfig(),
            'tables'             => $this->getTablesConfig(),
            'view_helpers'       => $this->getViewHelperConfig(),
            'image_processors'   => $this->getImageProcessorsConfig(),
        ];
    }

    public function getImageProcessorsConfig(): array
    {
        return [
            'aliases'   => [
                'normalize' => Processor\Normalize::class,
                'negate'    => Processor\Negate::class,
            ],
            'factories' => [
                Processor\Normalize::class => InvokableFactory::class,
                Processor\Negate::class    => InvokableFactory::class,
            ],
        ];
    }

    public function getConsoleConfig(): array
    {
        return [
            'router' => [
                'routes' => [
                    'image-storage'              => [
                        'options' => [
                            'route'    => 'image-storage (list-broken-files|fix-broken-files|list-dirs):action',
                            'defaults' => [
                                'controller' => Controller\ConsoleController::class,
                            ],
                        ],
                    ],
                    'image-storage-format'       => [
                        'options' => [
                            'route'    => 'image-storage (flush-format):action <format>',
                            'defaults' => [
                                'controller' => Controller\ConsoleController::class,
                            ],
                        ],
                    ],
                    'image-storage-image'        => [
                        'options' => [
                            'route'    => 'image-storage (flush-image|move-to-s3):action <image>',
                            'defaults' => [
                                'controller' => Controller\ConsoleController::class,
                            ],
                        ],
                    ],
                    'image-storage-dir'          => [
                        'options' => [
                            'route'    => 'image-storage (delete-broken-files|clear-empty-dirs|move-dir-to-s3):action '
                                . '<dirname>',
                            'defaults' => [
                                'controller' => Controller\ConsoleController::class,
                            ],
                        ],
                    ],
                    'image-storage-extract-exif' => [
                        'options' => [
                            'route'    => 'image-storage extract-exif <dirname>',
                            'defaults' => [
                                'controller' => Controller\ConsoleController::class,
                                'action'     => 'extract-exif',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getControllerPluginConfig(): array
    {
        return [
            'aliases'   => [
                'imagestorage' => Controller\Plugin\ImageStorage::class,
                'imageStorage' => Controller\Plugin\ImageStorage::class,
                'ImageStorage' => Controller\Plugin\ImageStorage::class,
            ],
            'factories' => [
                Controller\Plugin\ImageStorage::class => Factory\ControllerPluginFactory::class,
            ],
        ];
    }

    public function getControllersConfig(): array
    {
        return [
            'factories' => [
                Controller\ConsoleController::class => InvokableFactory::class,
            ],
        ];
    }

    /**
     * Return application-level dependency configuration.
     */
    public function getDependencyConfig(): array
    {
        return [
            'aliases'   => [
                Storage::class => StorageInterface::class,
            ],
            'factories' => [
                StorageInterface::class                 => Factory\ImageStorageFactory::class,
                Processor\ProcessorPluginManager::class => Processor\ProcessorPluginManagerFactory::class,
            ],
        ];
    }

    public function getTablesConfig(): array
    {
        return [
            'image'          => [
                'sequences' => [
                    'id' => 'image_id_seq',
                ],
            ],
            'formated_image' => [
                'sequences' => [],
            ],
            'image_dir'      => [],
        ];
    }

    public function getViewHelperConfig(): array
    {
        return [
            'aliases'   => [
                'imagestorage' => View\Helper\ImageStorage::class,
                'imageStorage' => View\Helper\ImageStorage::class,
                'ImageStorage' => View\Helper\ImageStorage::class,
            ],
            'factories' => [
                View\Helper\ImageStorage::class => Factory\ViewHelperFactory::class,
            ],
        ];
    }
}
