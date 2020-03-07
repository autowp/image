<?php

declare(strict_types=1);

namespace Autowp\Image\Processor;

use Zend\ServiceManager\AbstractPluginManager;
use Zend\ServiceManager\Exception\InvalidServiceException;

use function get_class;
use function gettype;
use function is_object;
use function sprintf;

class ProcessorPluginManager extends AbstractPluginManager
{
    /**
     * Default factory-based adapters
     *
     * @var array
     */
    protected $factories = [];

    /**
     * Whether or not to share by default (v3)
     *
     * @var bool
     */
    protected $sharedByDefault = false;

    /**
     * {inheritDoc}
     */
    protected $instanceOf = AbstractProcessor::class;

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
            is_object($instance) ? get_class($instance) : gettype($instance),
            AbstractProcessor::class
        ));
    }
}
