<?php

namespace Autowp\Image\Processor;

use Zend\ServiceManager\AbstractPluginManager;
use Zend\ServiceManager\Exception\InvalidServiceException;
use Zend\ServiceManager\Factory\InvokableFactory;

class ProcessorPluginManager extends AbstractPluginManager
{

    /**
     * Default factory-based adapters
     *
     * @var array
     */
    protected $factories = [
        ArraySerializable::class                => InvokableFactory::class,
        ClassMethods::class                     => InvokableFactory::class,
        DelegatingHydrator::class               => DelegatingHydratorFactory::class,
        ObjectProperty::class                   => InvokableFactory::class,
        Reflection::class                       => InvokableFactory::class,
    ];

    /**
     * Whether or not to share by default (v3)
     *
     * @var bool
     */
    protected $sharedByDefault = false;

    /**
     * Whether or not to share by default (v2)
     *
     * @var bool
     */
    protected $shareByDefault = false;

    /**
     * {inheritDoc}
     */
    protected $instanceOf = Processor::class;

    /**
     * Validate the plugin is of the expected type (v3).
     *
     * Checks that the filter loaded is either a valid callback or an instance
     * of FilterInterface.
     *
     * @param mixed $instance
     * @throws InvalidServiceException
     */
    public function validate($instance)
    {
        if ($instance instanceof $this->instanceOf) {
            // we're okay
            return;
        }

        throw new InvalidServiceException(sprintf(
            'Plugin of type %s is invalid; must implement %s',
            (is_object($instance) ? get_class($instance) : gettype($instance)),
            Processor::class
        ));
    }

    /**
     * {@inheritDoc} (v2)
     */
    public function validatePlugin($plugin)
    {
        try {
            $this->validate($plugin);
        } catch (InvalidServiceException $e) {
            throw new Exception\RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
