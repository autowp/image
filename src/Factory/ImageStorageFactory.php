<?php

namespace Autowp\Image\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Autowp\Image\Storage;

class ImageStorageFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->has('Config') ? $container->get('Config') : [];
        $storageConfig = isset($config['imageStorage']) ? $config['imageStorage'] : [];
        
        $storageConfig['dbAdapter'] = $container->get(\Zend\Db\Adapter\AdapterInterface::class);

        $storage = new Storage($storageConfig);

        $request = $container->get('Request');
        if ($request instanceof \Zend\Http\Request) {
            if ($request->getServer('HTTPS')) {
                $storage->setForceHttps(true);
            }
        }

        return $storage;
    }
}
