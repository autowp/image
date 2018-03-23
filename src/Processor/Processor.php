<?php

namespace Autowp\Image\Processor;

use Imagick;

abstract class Processor
{
    abstract public function process(Imagick $imagick);
}
