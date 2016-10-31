<?php

namespace Autowp\Image;

class ConfigProvider
{
    /**
     * @return array
     */
    public function __invoke()
    {
        return [
            'dependencies'       => $this->getDependencyConfig(),
            'view_helpers'       => $this->getViewHelperConfig(),
            'controller_plugins' => $this->getControllerPluginConfig()
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
