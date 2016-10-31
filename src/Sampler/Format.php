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
    private $fitType;

    /**
     * @var int
     */
    private $width;

    /**
     * @var int
     */
    private $height;

    /**
     * @var string
     */
    private $background;

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
     * @var boolean
     */
    private $ignoreCrop = false;

    /**
     * @var boolean
     */
    private $proportionalCrop = false;

    /**
     * @bool
     */
    private $reduceOnly = false;

    /**
     * @var bool
     */
    private $strip = false;

    /**
     * @var int
     */
    private $quality = 0;

    /**
     * @var string
     */
    private $format = null;

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
                $this->raise("Unexpected option '$key'");
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
        $this->format = $value ? (string)$value : null;

        return $this;
    }

    /**
     * @return string|NULL
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * @return string|NULL
     */
    public function getFormatExtension()
    {
        if ($this->format) {
            switch ($this->format) {
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
                    $this->raise("Unsupported format `{$this->format}`");
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
            return $this->raise("Compression quality must be >= 0 and <= 100");
        }

        $this->quality = $value;

        return $this;
    }

    /**
     * @return int
     */
    public function getQuality()
    {
        return $this->quality;
    }

    /**
     * @param bool $value
     * @return Format
     */
    public function setStrip($value)
    {
        $this->strip = (bool)$value;

        return $this;
    }

    /**
     * @return bool
     */
    public function getStrip()
    {
        return $this->strip;
    }

    /**
     * @param bool $reduceOnly
     * @return Format
     */
    public function setReduceOnly($reduceOnly)
    {
        $this->reduceOnly = (bool)$reduceOnly;

        return $this;
    }

    /**
     * @return bool
     */
    public function getReduceOnly()
    {
        return $this->reduceOnly;
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
                $this->fitType = $fitType;
                break;

            default:
                $message = "Unexpected fit type `$fitType`";
                $this->raise($message);
        }

        return $this;
    }

    /**
     * @return int
     */
    public function getFitType()
    {
        return $this->fitType;
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
            $this->raise($message);
        }
        $this->width = $width;

        return $this;
    }

    /**
     * @return int
     */
    public function getWidth()
    {
        return $this->width;
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
            $this->raise($message);
        }
        $this->height = $height;

        return $this;
    }

    /**
     * @return int
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * @param string $color
     * @throws Exception
     * @return Format
     */
    public function setBackground($color)
    {
        $this->background = $color;

        return $this;
    }

    /**
     * @return string
     */
    public function getBackground()
    {
        return $this->background;
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
        return array(
            'left'   => $this->cropLeft,
            'top'    => $this->cropTop,
            'width'  => $this->cropWidth,
            'height' => $this->cropHeight
        );
    }

    /**
     * @param boolean $value
     * @return Format
     */
    public function setIgnoreCrop($value)
    {
        $this->ignoreCrop = (bool)$value;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getIgnoreCrop()
    {
        return $this->ignoreCrop;
    }

    /**
     * @param boolean $value
     * @return Format
     */
    public function setProportionalCrop($value)
    {
        $this->proportionalCrop = (bool)$value;

        return $this;
    }

    /**
     * @return bool
     */
    public function getProportionalCrop()
    {
        return $this->proportionalCrop;
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