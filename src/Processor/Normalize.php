<?php

declare(strict_types=1);

namespace Autowp\Image\Processor;

use Imagick;

class Normalize extends AbstractProcessor
{
    public function process(Imagick $imagick)
    {
        $imagick->normalizeImage();
    }
}
