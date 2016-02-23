<?php

namespace Autowp\Image\Storage;

use Autowp\Image\Sampler\Format;
use Autowp\Image\Storage\Exception;

class Request
{
    private $_imageId;

    /**
     * @var int
     */
    private $_cropLeft;

    /**
     * @var int
     */
    private $_cropTop;

    /**
     * @var int
     */
    private $_cropWidth;

    /**
     * @var int
     */
    private $_cropHeight;

    /**
     * @param array $options
     * @throws Exception
     */
    public function __construct(array $options = array())
    {
        $this->setOptions($options);
    }

    /**
     * @param array $options
     * @return Format
     * @throws Exception
     */
    public function setOptions(array $options)
    {
        foreach ($options as $key => $value) {
            $method = 'set' . ucfirst($key);

            if (method_exists($this, $method)) {
                $this->$method($value);
            } else {
                $this->_raise("Unexpected option '$key'");
            }
        }

        return $this;
    }

    /**
     * @param int $imageId
     * @return Request
     */
    public function setImageId($imageId)
    {
        $this->_imageId = (int)$imageId;

        return $this;
    }

    /**
     * @return int
     */
    public function getImageId()
    {
        return $this->_imageId;
    }

    /**
     * @param array $crop
     * @return Format
     */
    public function setCrop(array $crop)
    {
        if (!isset($crop['left'])) {
            return $this->_raise("Crop left not provided");
        }
        $this->setCropLeft($crop['left']);

        if (!isset($crop['top'])) {
            return $this->_raise("Crop top not provided");
        }
        $this->setCropTop($crop['top']);

        if (!isset($crop['width'])) {
            return $this->_raise("Crop width not provided");
        }
        $this->setCropWidth($crop['width']);

        if (!isset($crop['height'])) {
            return $this->_raise("Crop height not provided");
        }
        $this->setCropHeight($crop['height']);

        return $this;
    }

    /**
     * @param int $value
     * @return Format
     */
    public function setCropLeft($value)
    {
        $value = (int)$value;
        if ($value < 0) {
            return $this->_raise("Crop left cannot be lower than 0");
        }
        $this->_cropLeft = $value;

        return $this;
    }

    /**
     * @param int $value
     * @return Format
     */
    public function setCropTop($value)
    {
        $value = (int)$value;
        if ($value < 0) {
            return $this->_raise("Crop top cannot be lower than 0");
        }
        $this->_cropTop = $value;

        return $this;
    }

    /**
     * @param int $value
     * @return Format
     */
    public function setCropWidth($value)
    {
        $value = (int)$value;
        if ($value < 0) {
            return $this->_raise("Crop width cannot be lower than 0");
        }
        $this->_cropWidth = $value;

        return $this;
    }

    /**
     * @param int $value
     * @return Format
     */
    public function setCropHeight($value)
    {
        $value = (int)$value;
        if ($value < 0) {
            return $this->_raise("Crop height cannot be lower than 0");
        }
        $this->_cropHeight = $value;

        return $this;
    }

    /**
     * @return array|bool
     */
    public function getCrop()
    {
        if (!isset($this->_cropLeft, $this->_cropTop, $this->_cropWidth, $this->_cropHeight)) {
            return false;
        }
        return array(
            'left'   => $this->_cropLeft,
            'top'    => $this->_cropTop,
            'width'  => $this->_cropWidth,
            'height' => $this->_cropHeight
        );
    }

    /**
     * @param string $message
     * @throws Exception
     */
    protected function _raise($message)
    {
        throw new Exception($message);
    }
}