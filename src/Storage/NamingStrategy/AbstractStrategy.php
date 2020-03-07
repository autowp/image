<?php

declare(strict_types=1);

namespace Autowp\Image\Storage\NamingStrategy;

use Autowp\Image\Storage\Exception;

use function method_exists;
use function rtrim;
use function ucfirst;

use const DIRECTORY_SEPARATOR;

abstract class AbstractStrategy
{
    /** @var string */
    private $dir;

    /**
     * @throws Exception
     */
    public function __construct(array $options = [])
    {
        $this->setOptions($options);
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function setOptions(array $options)
    {
        foreach ($options as $key => $value) {
            $method = 'set' . ucfirst($key);

            if (! method_exists($this, $method)) {
                throw new Exception("Unexpected option '$key'");
            }

            $this->$method($value);
        }

        return $this;
    }

    /**
     * @param string $dir
     * @return $this
     */
    public function setDir($dir)
    {
        $this->dir = rtrim($dir, DIRECTORY_SEPARATOR);

        return $this;
    }

    /**
     * @return string
     */
    public function getDir()
    {
        return $this->dir;
    }

    /**
     * @return string
     */
    abstract public function generate(array $options = []);
}
