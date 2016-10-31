<?php

namespace Autowp\Image\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Autowp\Image;

class ImageStorage extends AbstractHelper
{
    private $imageStorage;

    public function __construct(Image\Storage $imageStorage)
    {
        $this->imageStorage = $imageStorage;
    }

    public function __invoke()
    {
        return $this->imageStorage;
    }
}