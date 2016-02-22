<?php

namespace Autowp\Image\Sampler;

use Autowp\Image\Sampler\Exception;

/**
 * @author dima
 *
 * @desc Represents a image formating rules
 */
class Format
{
    const
        FIT_TYPE_INNER = '0', // вписать
        FIT_TYPE_OUTER = '1', // описать
        FIT_TYPE_MAXIMUM = '2';

    /**
     * @var int
     */
    private $_fitType;

    /**
     * @var int
     */
    private $_width;

    /**
     * @var int
     */
    private $_height;

    /**
     * @var string
     */
    private $_background;

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
     * @var boolean
     */
    private $_ignoreCrop = false;

    /**
     * @var boolean
     */
    private $_proportionalCrop = false;

    /**
     * @bool
     */
    private $_reduceOnly = false;

    /**
     * @var bool
     */
    private $_strip = false;

    /**
     * @var int
     */
    private $_quality = 0;

    /**
     * @var string
     */
    private $_format = null;

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
     * @param string $value
     * @return Format
     */
    public function setFormat($value)
    {
        $this->_format = $value ? (string)$value : null;

        return $this;
    }

    /**
     * @return string|NULL
     */
    public function getFormat()
    {
        return $this->_format;
    }

    /**
     * @return string|NULL
     */
    public function getFormatExtension()
    {
        if ($this->_format) {
            switch ($this->_format) {
                case 'jpg':
                case 'jpeg':
                    return 'jpeg';

                case 'png':
                    return 'png';

                case 'gif':
                    return 'gif';

                case 'bmp';
                    return 'bmp';

                default:
                    $this->_raise("Unsupported format `{$this->_format}`");
            }
        }

        return null;
    }

    /**
     * @param int $value
     * @return Format
     */
    public function setQuality($value)
    {
        $value = (int)$value;
        if ($value < 0 || $value > 100) {
            return $this->_raise("Compression quality must be >= 0 and <= 100");
        }

        $this->_quality = $value;

        return $this;
    }

    /**
     * @return int
     */
    public function getQuality()
    {
        return $this->_quality;
    }

    /**
     * @param bool $value
     * @return Format
     */
    public function setStrip($value)
    {
        $this->_strip = (bool)$value;

        return $this;
    }

    /**
     * @return bool
     */
    public function getStrip()
    {
        return $this->_strip;
    }

    /**
     * @param bool $reduceOnly
     * @return Format
     */
    public function setReduceOnly($reduceOnly)
    {
        $this->_reduceOnly = (bool)$reduceOnly;

        return $this;
    }

    /**
     * @return bool
     */
    public function getReduceOnly()
    {
        return $this->_reduceOnly;
    }

    /**
     * @param int $fitType
     * @throws Exception
     * @return Format
     */
    public function setFitType($fitType)
    {
        $fitType = (int)$fitType;
        switch ($fitType) {
            case self::FIT_TYPE_INNER:
            case self::FIT_TYPE_OUTER:
            case self::FIT_TYPE_MAXIMUM:
                $this->_fitType = $fitType;
                break;

            default:
                $message = "Unexpected fit type `$fitType`";
                $this->_raise($message);
        }

        return $this;
    }

    /**
     * @return int
     */
    public function getFitType()
    {
        return $this->_fitType;
    }

    /**
     * @param int $width
     * @throws Exception
     * @return Format
     */
    public function setWidth($width)
    {
        $width = (int)$width;
        if ($width < 0) {
            $message = "Unexpected width `$width`";
            $this->_raise($message);
        }
        $this->_width = $width;

        return $this;
    }

    /**
     * @return int
     */
    public function getWidth()
    {
        return $this->_width;
    }

    /**
     * @param int $height
     * @throws Exception
     * @return Format
     */
    public function setHeight($height)
    {
        $height = (int)$height;
        if ($height < 0) {
            $message = "Unexpected height `$height`";
            $this->_raise($message);
        }
        $this->_height = $height;

        return $this;
    }

    /**
     * @return int
     */
    public function getHeight()
    {
        return $this->_height;
    }

    /**
     * @param string $color
     * @throws Exception
     * @return Format
     */
    public function setBackground($color)
    {
        $this->_background = $color;

        return $this;
    }

    /**
     * @return string
     */
    public function getBackground()
    {
        return $this->_background;
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
     * @param boolean $value
     * @return Format
     */
    public function setIgnoreCrop($value)
    {
        $this->_ignoreCrop = (bool)$value;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getIgnoreCrop()
    {
        return $this->_ignoreCrop;
    }

    /**
     * @param boolean $value
     * @return Format
     */
    public function setProportionalCrop($value)
    {
        $this->_proportionalCrop = (bool)$value;

        return $this;
    }

    /**
     * @return bool
     */
    public function getProportionalCrop()
    {
        return $this->_proportionalCrop;
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