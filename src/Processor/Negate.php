<?php

namespace Autowp\Image\Processor;

use Imagick;

class Negate extends Processor
{
    public function process(Imagick $imagick)
    {
        $imagick->negateImage(true);
    }
}
