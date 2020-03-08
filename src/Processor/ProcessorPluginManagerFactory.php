<?php

declare(strict_types=1);

namespace Autowp\Image\Processor;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Laminas\ServiceManager\Config;
use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

use function is_array;

class ProcessorPluginManagerFactory implements FactoryInterface
{
    /**
     * zend-servicemanager v2 support for invocation options.
     *
     * @var array
     */
    protected $creationOptions;

    /**
     * {@inheritDoc}
     *
     * @param string $name
     * @return ProcessorPluginManager
     */
    public function __invoke(ContainerInterface $container, $name, ?array $options = null)
    {
        $pluginManager = new ProcessorPluginManager($container, $options ?: []);

        // If we do not have a config service, nothing more to do
        if (! $container->has('config')) {
            return $pluginManager;
        }

        $config = $container->get('config');
        if (! isset($config['image_processors']) || ! is_array($config['image_processors'])) {
            return $pluginManager;
        }

        (new Config($config['image_processors']))->configureServiceManager($pluginManager);

        return $pluginManager;
    }

    /**
     * {@inheritDoc}
     *
     * @return mixed
     * @throws ContainerException
     */
    public function createService(ServiceLocatorInterface $container)
    {
        return $this($container, ProcessorPluginManager::class, $this->creationOptions);
    }

    /**
     * zend-servicemanager v2 support for invocation options.
     */
    public function setCreationOptions(array $options)
    {
        $this->creationOptions = $options;
    }
}
