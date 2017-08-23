<?php

namespace Autowp\Image\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Autowp\Image\Storage;
use Autowp\Image\Controller\Plugin\ImageStorage;

class ControllerPluginFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new ImageStorage($container->get(Storage::class));
    }
}
