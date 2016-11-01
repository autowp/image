<?php

namespace Autowp\Image\Storage;

use Autowp\Image\Storage\Exception;
use Autowp\Image\Storage\NamingStrategy\AbstractStrategy;

class Dir
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $url;

    /**
     * @var AbstractStrategy
     */
    private $namingStrategy;

    /**
     * @param array $options
     * @throws Exception
     */
    public function __construct(array $options = [])
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

            if (!method_exists($this, $method)) {
                $this->raise("Unexpected option '$key'");
            }
            
            $this->$method($value);
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
            return $this->raise("Path must be a string");
        }

        $path = trim($path);

        if (!$path) {
            return $this->raise("Path cannot be empty, '$path' given");
        }

        $this->path = rtrim($path, DIRECTORY_SEPARATOR);

        return $this;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $url
     * @return Dir
     */
    public function setUrl($url)
    {
        if (isset($url)) {
            $this->url = (string)$url;
        } else {
            $this->url = null;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
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
                $options = isset($strategy['options']) ? $strategy['options'] : [];
            } else {
                $strategyName = $strategy;
                $options = [];
            }

            $className = 'Autowp\\Image\\Storage\\NamingStrategy\\' . ucfirst($strategyName);
            $strategy = new $className($options);
            if (!$strategy instanceof AbstractStrategy) {
                return $this->raise("$className is not naming strategy");
            }
        }

        $strategy->setDir($this->path);

        $this->namingStrategy = $strategy;

        return $this;
    }

    /**
     * @return AbstractStrategy
     */
    public function getNamingStrategy()
    {
        return $this->namingStrategy;
    }

    /**
     * @param string $message
     * @throws Exception
     */
    private function raise($message)
    {
        throw new Exception($message);
    }
}
