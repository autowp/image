<?php

declare(strict_types=1);

namespace Autowp\Image\Storage;

use function array_merge;

class Image
{
    /** @var int */
    private int $id;

    /** @var int */
    private int $width;

    /** @var int */
    private int $height;

    /** @var int */
    private int $filesize;

    /** @var string */
    private string $src;

    public function __construct(array $options)
    {
        $defaults = [
            'id'       => null,
            'width'    => null,
            'height'   => null,
            'filesize' => null,
            'src'      => null,
        ];

        $options = array_merge($defaults, $options);

        $this->id       = (int) $options['id'];
        $this->width    = (int) $options['width'];
        $this->height   = (int) $options['height'];
        $this->filesize = (int) $options['filesize'];
        $this->src      = (string) $options['src'];
    }

    public function toArray(): array
    {
        return [
            'id'       => $this->id,
            'width'    => $this->width,
            'height'   => $this->height,
            'filesize' => $this->filesize,
            'src'      => $this->src,
        ];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSrc(): string
    {
        return $this->src;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getFileSize(): int
    {
        return $this->filesize;
    }
}
