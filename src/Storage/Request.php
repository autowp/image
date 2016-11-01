<?php

namespace Autowp\Image\Storage;

use Autowp\Image\Sampler\Format;
use Autowp\Image\Storage\Exception;

class Request
{
    private $imageId;

    /**
     * @var int
     */
    private $cropLeft;

    /**
     * @var int
     */
    private $cropTop;

    /**
     * @var int
     */
    private $cropWidth;

    /**
     * @var int
     */
    private $cropHeight;

    /**
     * @param array $options
     * @throws Exception
     */
    public function __construct(array $options = [])
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

            if (!method_exists($this, $method)) {
                $this->raise("Unexpected option '$key'");
            }
            
            $this->$method($value);
        }

        return $this;
    }

    /**
     * @param int $imageId
     * @return Request
     */
    public function setImageId($imageId)
    {
        $this->imageId = (int)$imageId;

        return $this;
    }

    /**
     * @return int
     */
    public function getImageId()
    {
        return $this->imageId;
    }

    /**
     * @param array $crop
     * @return Format
     */
    public function setCrop(array $crop)
    {
        if (!isset($crop['left'])) {
            return $this->raise("Crop left not provided");
        }
        $this->setCropLeft($crop['left']);

        if (!isset($crop['top'])) {
            return $this->raise("Crop top not provided");
        }
        $this->setCropTop($crop['top']);

        if (!isset($crop['width'])) {
            return $this->raise("Crop width not provided");
        }
        $this->setCropWidth($crop['width']);

        if (!isset($crop['height'])) {
            return $this->raise("Crop height not provided");
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
            return $this->raise("Crop left cannot be lower than 0");
        }
        $this->cropLeft = $value;

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
            return $this->raise("Crop top cannot be lower than 0");
        }
        $this->cropTop = $value;

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
            return $this->raise("Crop width cannot be lower than 0");
        }
        $this->cropWidth = $value;

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
            return $this->raise("Crop height cannot be lower than 0");
        }
        $this->cropHeight = $value;

        return $this;
    }

    /**
     * @return array|bool
     */
    public function getCrop()
    {
        if (!isset($this->cropLeft, $this->cropTop, $this->cropWidth, $this->cropHeight)) {
            return false;
        }
        return [
            'left'   => $this->cropLeft,
            'top'    => $this->cropTop,
            'width'  => $this->cropWidth,
            'height' => $this->cropHeight
        ];
    }

    /**
     * @param string $message
     * @throws Exception
     */
    private function raise($message)
    {
        throw new Exception($message);
    }
}
