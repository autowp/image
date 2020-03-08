<?php

declare(strict_types=1);

namespace Autowp\Image\Controller\Plugin;

use Autowp\Image;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class ImageStorage extends AbstractPlugin
{
    /** @var Image\StorageInterface */
    private Image\StorageInterface $imageStorage;

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
