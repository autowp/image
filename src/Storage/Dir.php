<?php

namespace Autowp\Image\Storage;

use Autowp\Image\Storage\Exception;
use Autowp\Image\Storage\NamingStrategy\AbstractStrategy;

class Dir
{
    /**
     * @var string
     */
    protected $_path;

    /**
     * @var string
     */
    protected $_url;

    /**
     * @var AbstractStrategy
     */
    protected $_namingStrategy;

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
     * @return Dir
     * @throws Exception
     */
    public function setOptions(array $options)
    {
        foreach ($options as $key => $value) {
            $method = 'set' . ucfirst($key);

            if (method_exists($this, $method)) {
                $this->$method($value);
            } else {
                $this->_raise("Unexpected option '$key'");
            }
        }

        return $this;
    }

    /**
     * @param string $path
     * @return Dir
     */
    public function setPath($path)
    {
        if (!is_string($path)) {
            return $this->_raise("Path must be a string");
        }

        $path = trim($path);

        if (!$path) {
            return $this->_raise("Path cannot be empty, '$path' given");
        }

        $this->_path = rtrim($path, DIRECTORY_SEPARATOR);

        return $this;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->_path;
    }

    /**
     * @param string $url
     * @return Dir
     */
    public function setUrl($url)
    {
        if (isset($url)) {
            $this->_url = (string)$url;
        } else {
            $this->_url = null;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->_url;
    }

    /**
     * @param string|array|AbstractStrategy $strategy
     * @throws Exception
     * @return Dir
     */
    public function setNamingStrategy($strategy)
    {
        if (!$strategy instanceof AbstractStrategy) {
            if (is_array($strategy)) {
                $strategyName = $strategy['strategy'];
                $options = isset($strategy['options']) ? $strategy['options'] : array();
            } else {
                $strategyName = $strategy;
                $options = array();
            }

            $className = 'Autowp\\Image\\Storage\\NamingStrategy\\' . ucfirst($strategyName);
            $strategy = new $className($options);
            if (!$strategy instanceof AbstractStrategy) {
                return $this->_raise("$className is not naming strategy");
            }
        }

        $strategy->setDir($this->_path);

        $this->_namingStrategy = $strategy;

        return $this;
    }

    /**
     * @return AbstractStrategy
     */
    public function getNamingStrategy()
    {
        return $this->_namingStrategy;
    }

    /**
     * @param string $message
     * @throws Exception
     */
    protected function _raise($message)
    {
        throw new Exception($message);
    }
}