<?php

declare(strict_types=1);

namespace Autowp\Image\Storage;

use Autowp\Image\Storage\NamingStrategy\AbstractStrategy;

use function is_array;
use function method_exists;
use function trim;
use function ucfirst;

class Dir
{
    private string $bucket = '';

    private AbstractStrategy $namingStrategy;

    /**
     * @throws Exception
     */
    public function __construct(array $options = [])
    {
        $this->setOptions($options);
    }

    /**
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
     * @param string|array|AbstractStrategy $strategy
     * @throws Exception
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

        $this->namingStrategy = $strategy;

        return $this;
    }

    public function getNamingStrategy(): AbstractStrategy
    {
        return $this->namingStrategy;
    }
}
