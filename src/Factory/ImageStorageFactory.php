<?php

declare(strict_types=1);

namespace Autowp\Image\Factory;

use Autowp\Image\Processor;
use Autowp\Image\Storage;
use Autowp\ZFComponents\Db\TableManager;
use Interop\Container\ContainerInterface;
use Zend\Http\Request;
use Zend\ServiceManager\Factory\FactoryInterface;

class ImageStorageFactory implements FactoryInterface
{
    /**
     * @param string $requestedName
     * @return Storage|object
     * @throws Storage\Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config        = $container->has('Config') ? $container->get('Config') : [];
        $storageConfig = $config['imageStorage'] ?? [];

        $tables = $container->get(TableManager::class);

        $storage = new Storage(
            $storageConfig,
            $tables->get('image'),
            $tables->get('formated_image'),
            $tables->get('image_dir'),
            $container->get(Processor\ProcessorPluginManager::class)
        );

        $request = $container->get('Request');
        if ($request instanceof Request) {
            if ($request->getServer('HTTPS') || $request->getServer('HTTP_X_FORWARDED_PROTO') === 'https') {
                $storage->setForceHttps(true);
            }
        }

        return $storage;
    }
}
