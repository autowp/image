<?php

namespace Autowp\Image\Storage;

use Autowp\Image\Storage\NamingStrategy\AbstractStrategy;

class Dir
{
    /**
     * @var string
     */
    private $path = '';

    /**
     * @var string
     */
    private $url = '';

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
    public function setOptions(array $options): Dir
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
     * @param string $path
     * @return Dir
     * @throws Exception
     */
    public function setPath(string $path): Dir
    {
        $path = trim($path);

        if (! $path) {
            throw new Exception("Path cannot be empty, '$path' given");
        }

        $this->path = rtrim($path, DIRECTORY_SEPARATOR);

        return $this;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param string $url
     * @return Dir
     */
    public function setUrl(string $url): Dir
    {
        $this->url = isset($url) ? (string)$url : null;

        return $this;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @param string|array|AbstractStrategy $strategy
     * @throws Exception
     * @return Dir
     */
    public function setNamingStrategy($strategy): Dir
    {
        if (! $strategy instanceof AbstractStrategy) {
            $strategyName = $strategy;
            $options = [];
            if (is_array($strategy)) {
                $strategyName = $strategy['strategy'];
                $options = isset($strategy['options']) ? $strategy['options'] : [];
            }

            $className = 'Autowp\\Image\\Storage\\NamingStrategy\\' . ucfirst($strategyName);
            $strategy = new $className($options);
            if (! $strategy instanceof AbstractStrategy) {
                throw new Exception("$className is not naming strategy");
            }
        }

        $strategy->setDir($this->path);

        $this->namingStrategy = $strategy;

        return $this;
    }

    /**
     * @return AbstractStrategy
     */
    public function getNamingStrategy(): AbstractStrategy
    {
        return $this->namingStrategy;
    }
}
