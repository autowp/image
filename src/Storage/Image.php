<?php

namespace Autowp\Image\Storage;

class Image
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var int
     */
    private $width;

    /**
     * @var int
     */
    private $height;

    /**
     * @var int
     */
    private $filesize;

    /**
     * @var string
     */
    private $src;

    /**
     * @param array $options
     */
    public function __construct(array $options)
    {
        $defaults = [
            'id'       => null,
            'width'    => null,
            'height'   => null,
            'filesize' => null,
            'src'      => null
        ];

        $options = array_merge($defaults, $options);

        $this->id       = (int)$options['id'];
        $this->width    = (int)$options['width'];
        $this->height   = (int)$options['height'];
        $this->filesize = (int)$options['filesize'];
        $this->src      = (string)$options['src'];
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'id'       => $this->id,
            'width'    => $this->width,
            'height'   => $this->height,
            'filesize' => $this->filesize,
            'src'      => $this->src
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
