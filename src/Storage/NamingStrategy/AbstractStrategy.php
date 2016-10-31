<?php

namespace Autowp\Image\Storage\NamingStrategy;

use Autowp\Image\Storage\Exception;

abstract class AbstractStrategy
{
    /**
     * @var string
     */
    private $dir = null;

    /**
     * @param array $options
     * @throws Exception
     */
    public function __construct(array $options = array())
    {
        $this->setOptions($options);
    }

    /**
     * @param array $options
     * @return AbstractStrategy
     * @throws Exception
     */
    public function setOptions(array $options)
    {
        foreach ($options as $key => $value) {
            $method = 'set' . ucfirst($key);

            if (method_exists($this, $method)) {
                $this->$method($value);
            } else {
                throw new Exception("Unexpected option '$key'");
            }
        }

        return $this;
    }

    /**
     * @param string $dir
     * @return AbstractStrategy
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
     * @param array $options
     * @return string
     */
    abstract public function generate(array $options = array());
}