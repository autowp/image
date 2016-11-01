<?php

namespace Autowp\Image\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Autowp\Image;

class ImageStorage extends AbstractHelper
{
    /**
     * @var Image\Storage
     */
    private $imageStorage;

    /**
     * @param Image\Storage $imageStorage
     */
    public function __construct(Image\Storage $imageStorage)
    {
        $this->imageStorage = $imageStorage;
    }

    /**
     * @return Image\Storage
     */
    public function __invoke()
    {
        return $this->imageStorage;
    }
}
