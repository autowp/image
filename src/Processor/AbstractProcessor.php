<?php

declare(strict_types=1);

namespace Autowp\Image\Processor;

use Imagick;

abstract class AbstractProcessor
{
    abstract public function process(Imagick $imagick): void;
}
