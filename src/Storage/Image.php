<?php

namespace Autowp\Image\Storage;

class Image
{
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
            'width'    => null,
            'height'   => null,
            'filesize' => null,
            'src'      => null
        ];

        $options = array_merge($defaults, $options);

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
            'width'    => $this->width,
            'height'   => $this->height,
            'filesize' => $this->filesize,
            'src'      => $this->src
        ];
    }

    /**
     * @return string
     */
    public function getSrc()
    {
        return $this->src;
    }

    /**
     * @return int
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * @return int
     */
    public function getFileSize()
    {
        return $this->filesize;
    }
}
