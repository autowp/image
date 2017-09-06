<?php

namespace Autowp\Image\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

use Autowp\Image;

class ImageStorage extends AbstractPlugin
{
    /**
     * @var Image\StorageInterface
     */
    private $imageStorage;

    /**
     * @param Image\StorageInterface $imageStorage
     */
    public function __construct(Image\StorageInterface $imageStorage)
    {
        $this->imageStorage = $imageStorage;
    }

    /**
     * @return Image\StorageInterface
     */
    public function __invoke()
    {
        return $this->imageStorage;
    }
}
