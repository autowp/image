<?php

declare(strict_types=1);

namespace Autowp\Image\Storage;

use Autowp\Image\Storage\NamingStrategy\AbstractStrategy;

use function is_array;
use function method_exists;
use function rtrim;
use function trim;
use function ucfirst;

use const DIRECTORY_SEPARATOR;

class Dir
{
    /** @var string */
    private $path = '';

    /** @var string */
    private $url = '';

    /** @var string */
    private $bucket = '';

    /** @var AbstractStrategy */
    private $namingStrategy;

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
    public function setOptions(array $options): self
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
     * @return $this
     */
    public function setBucket(string $bucket): self
    {
        $this->bucket = trim($bucket);

        return $this;
    }

    public function getBucket(): string
    {
        return $this->bucket;
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function setPath(string $path): self
    {
        $path = trim($path);

        if (! $path) {
            throw new Exception("Path cannot be empty, '$path' given");
        }

        $this->path = rtrim($path, DIRECTORY_SEPARATOR);

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return $this
     */
    public function setUrl(string $url): self
    {
        $this->url = isset($url) ? (string) $url : null;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @param string|array|AbstractStrategy $strategy
     * @throws Exception
     * @return $this
     */
    public function setNamingStrategy($strategy): self
    {
        if (! $strategy instanceof AbstractStrategy) {
            $strategyName = $strategy;
            $options      = [];
            if (is_array($strategy)) {
                $strategyName = $strategy['strategy'];
                $options      = $strategy['options'] ?? [];
            }

            $className = 'Autowp\\Image\\Storage\\NamingStrategy\\' . ucfirst($strategyName);
            $strategy  = new $className($options);
            if (! $strategy instanceof AbstractStrategy) {
                throw new Exception("$className is not naming strategy");
            }
        }

        $strategy->setDir($this->path);

        $this->namingStrategy = $strategy;

        return $this;
    }

    public function getNamingStrategy(): AbstractStrategy
    {
        return $this->namingStrategy;
    }
}
