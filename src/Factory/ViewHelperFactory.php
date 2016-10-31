<?php

namespace Autowp\Image\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Autowp\Image\Storage;
use Autowp\Image\View\Helper\ImageStorage;

class ViewHelperFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new ImageStorage($container->get(Storage::class));
    }
}
