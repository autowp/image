<?php

namespace Autowp\Image\Storage;

class Image
{
    /**
     * @var int
     */
    private $_width;

    /**
     * @var int
     */
    private $_height;

    /**
     * @var int
     */
    private $_filesize;

    /**
     * @var string
     */
    private $_src;

    /**
     * @param array $options
     */
    public function __construct(array $options)
    {
        $defaults = array(
            'width'    => null,
            'height'   => null,
            'filesize' => null,
            'src'      => null
        );

        $options = array_merge($defaults, $options);

        $this->_width    =    (int)$options['width'];
        $this->_height   =    (int)$options['height'];
        $this->_filesize =    (int)$options['filesize'];
        $this->_src      = (string)$options['src'];
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return array(
            'width'    => $this->_width,
            'height'   => $this->_height,
            'filesize' => $this->_filesize,
            'src'      => $this->_src
        );
    }

    /**
     * @return string
     */
    public function getSrc()
    {
        return $this->_src;
    }

    /**
     * @return int
     */
    public function getWidth()
    {
        return $this->_width;
    }

    /**
     * @return int
     */
    public function getHeight()
    {
        return $this->_height;
    }

    /**
     * @return int
     */
    public function getFileSize()
    {
        return $this->_filesize;
    }
}