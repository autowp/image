<?php

namespace Autowp\Image\Factory;

use Interop\Container\ContainerInterface;
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

        $tables = $container->get(\Autowp\ZFComponents\Db\TableManager::class);

        $storage = new Storage(
            $storageConfig,
            $tables->get('image'),
            $tables->get('formated_image'),
            $tables->get('image_dir')
        );

        $request = $container->get('Request');
        if ($request instanceof \Zend\Http\Request) {
            if ($request->getServer('HTTPS') || $request->getServer('HTTP_X_FORWARDED_PROTO') == 'https') {
                $storage->setForceHttps(true);
            }
        }

        return $storage;
    }
}
