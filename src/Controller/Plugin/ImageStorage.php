<?php

namespace Autowp\Image\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

use Autowp\Image;

class ImageStorage extends AbstractPlugin
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
