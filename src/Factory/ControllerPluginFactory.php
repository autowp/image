<?php

declare(strict_types=1);

namespace Autowp\Image\Factory;

use Autowp\Image\Controller\Plugin\ImageStorage;
use Autowp\Image\Storage;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ControllerPluginFactory implements FactoryInterface
{
    /**
     * @param string $requestedName
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ImageStorage
    {
        return new ImageStorage($container->get(Storage::class));
    }
}
