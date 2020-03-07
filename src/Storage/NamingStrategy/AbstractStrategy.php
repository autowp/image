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
    private string $dir;

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

    public function setDir(string $dir): self
    {
        $this->dir = rtrim($dir, DIRECTORY_SEPARATOR);

        return $this;
    }

    public function getDir(): string
    {
        return $this->dir;
    }

    abstract public function generate(array $options = []): string;
}
