<?php

namespace Autowp\Image\Factory;

use Interop\Container\ContainerInterface;
use Zend\Db\Adapter\AdapterInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Autowp\Image\Storage;

class ImageStorageFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->has('Config') ? $container->get('Config') : [];
        $storageConfig = isset($config['imageStorage']) ? $config['imageStorage'] : [];

        $db = $container->get(AdapterInterface::class);

        if (! $db instanceof AdapterInterface) {
            throw new Exception(sprintf("service %s not found", AdapterInterface::class));
        }

        $storageConfig['dbAdapter'] = $db;

        $storage = new Storage($storageConfig);

        $request = $container->get('Request');
        if ($request instanceof \Zend\Http\Request) {
            if ($request->getServer('HTTPS') || $request->getServer('HTTP_X_FORWARDED_PROTO') == 'https') {
                $storage->setForceHttps(true);
            }
        }

        return $storage;
    }
}
