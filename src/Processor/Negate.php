<?php

declare(strict_types=1);

namespace Autowp\Image\Processor;

use Imagick;

class Negate extends AbstractProcessor
{
    public function process(Imagick $imagick): void
    {
        $imagick->negateImage(true);
    }
}
