<?php

declare(strict_types=1);

namespace Autowp\Image\View\Helper;

use Autowp\Image;
use Zend\View\Helper\AbstractHelper;

class ImageStorage extends AbstractHelper
{
    /** @var Image\Storage */
    private Image\Storage $imageStorage;

    public function __construct(Image\Storage $imageStorage)
    {
        $this->imageStorage = $imageStorage;
    }

    public function __invoke(): Image\Storage
    {
        return $this->imageStorage;
    }
}
