<?php

declare(strict_types=1);

namespace Autowp\Image\Factory;

use Autowp\Image\Storage;
use Autowp\Image\View\Helper\ImageStorage;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ViewHelperFactory implements FactoryInterface
{
    /**
     * @param string $requestedName
     * @return ImageStorage
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        return new ImageStorage($container->get(Storage::class));
    }
}
