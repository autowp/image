<?php

namespace Autowp\Image;

use Zend\ServiceManager\Factory\InvokableFactory;

class ConfigProvider
{
    /**
     * @return array
     */
    public function __invoke()
    {
        return [
            'console'            => $this->getConsoleConfig(),
            'controller_plugins' => $this->getControllerPluginConfig(),
            'controllers'        => $this->getControllersConfig(),
            'dependencies'       => $this->getDependencyConfig(),
            'view_helpers'       => $this->getViewHelperConfig(),
        ];
    }

    /**
     * @return array
     */
    public function getConsoleConfig()
    {
        return [
            'router' => [
                'routes' => [
                    'image-storage' => [
                        'options' => [
                            'route'    => 'image-storage (list-broken-files|fix-broken-files):action',
                            'defaults' => [
                                'controller' => Controller\ConsoleController::class,
                            ]
                        ]
                    ],
                    'image-storage-format' => [
                        'options' => [
                            'route'    => 'image-storage (flush-format):action <format>',
                            'defaults' => [
                                'controller' => Controller\ConsoleController::class
                            ]
                        ]
                    ],
                    'image-storage-image' => [
                        'options' => [
                            'route'    => 'image-storage (flush-image):action <image>',
                            'defaults' => [
                                'controller' => Controller\ConsoleController::class
                            ]
                        ]
                    ],
                    'image-storage-dir' => [
                        'options' => [
                            'route'    => 'image-storage (delete-broken-files|clear-empty-dirs):action <dirname>',
                            'defaults' => [
                                'controller' => Controller\ConsoleController::class,
                            ]
                        ]
                    ],
                ]
            ]
        ];
    }

    /**
     * @return array
     */
    public function getControllerPluginConfig()
    {
        return [
            'aliases' => [
                'imagestorage' => Controller\Plugin\ImageStorage::class,
                'imageStorage' => Controller\Plugin\ImageStorage::class,
                'ImageStorage' => Controller\Plugin\ImageStorage::class,
            ],
            'factories' => [
                Controller\Plugin\ImageStorage::class => Factory\ControllerPluginFactory::class,
            ],
        ];
    }

    /**
     * @return array
     */
    public function getControllersConfig()
    {
        return [
            'factories' => [
                Controller\ConsoleController::class => InvokableFactory::class,
            ]
        ];
    }

    /**
     * Return application-level dependency configuration.
     *
     * @return array
     */
    public function getDependencyConfig()
    {
        return [
            'factories' => [
                Storage::class => Factory\ImageStorageFactory::class
            ]
        ];
    }

    /**
     * @return array
     */
    public function getViewHelperConfig()
    {
        return [
            'aliases' => [
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
