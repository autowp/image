<?php

declare(strict_types=1);

namespace Autowp\Image\Factory;

use Autowp\Image\Processor;
use Autowp\Image\Storage;
use Autowp\ZFComponents\Db\TableManager;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

use function array_rand;
use function is_array;

class ImageStorageFactory implements FactoryInterface
{
    /**
     * @param string $requestedName
     * @throws Storage\Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Storage
    {
        $config        = $container->has('Config') ? $container->get('Config') : [];
        $storageConfig = $config['imageStorage'] ?? [];

        $tables = $container->get(TableManager::class);

        // pick random endpoint
        if (isset($storageConfig['s3']['endpoint']) && is_array($storageConfig['s3']['endpoint'])) {
            $s3endpoints                     = $storageConfig['s3']['endpoint'];
            $storageConfig['s3']['endpoint'] = $s3endpoints[array_rand($s3endpoints)];
        }

        return new Storage(
            $storageConfig,
            $tables->get('image'),
            $tables->get('formated_image'),
            $tables->get('image_dir'),
            $container->get(Processor\ProcessorPluginManager::class)
        );
    }
}
