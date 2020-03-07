<?php

declare(strict_types=1);

namespace Autowp\Image\View\Helper;

use Autowp\Image;
use Zend\View\Helper\AbstractHelper;

class ImageStorage extends AbstractHelper
{
    /** @var Image\Storage */
    private $imageStorage;

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
