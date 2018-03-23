<?php

namespace Autowp\Image\Processor;

use Imagick;

class Normalize extends Processor
{
    public function process(Imagick $imagick)
    {
        $imagick->normalizeImage();
    }
}
