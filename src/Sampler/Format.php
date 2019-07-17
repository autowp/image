<?php

namespace Autowp\Image\Sampler;

/**
 * @author dima
 *
 * @desc Represents a image formatting rules
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
     * @var float|null
     */
    private $widest = null;

    /**
     * @var float|null
     */
    private $highest = null;

    /**
     * @var array
     */
    private $processors = [];

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

            if (! method_exists($this, $method)) {
                throw new Exception("Unexpected option '$key'");
            }

            $this->$method($value);
        }

        return $this;
    }

    public function setProcessors(array $value): Format
    {
        $this->processors = (array)$value;

        return $this;
    }

    public function getProcessors(): array
    {
        return $this->processors;
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
     * @return string|null
     * @throws Exception
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

                case 'bmp':
                    return 'bmp';

                default:
                    throw new Exception("Unsupported format `{$this->format}`");
            }
        }

        return null;
    }

    /**
     * @param $value
     * @return $this
     * @throws Exception
     */
    public function setQuality($value)
    {
        $value = (int)$value;
        if ($value < 0 || $value > 100) {
            throw new Exception("Compression quality must be >= 0 and <= 100");
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
    public function setStrip(bool $value)
    {
        $this->strip = (bool)$value;

        return $this;
    }

    /**
     * @return bool
     */
    public function isStrip(): bool
    {
        return $this->strip;
    }

    /**
     * @return bool
     * @deprecated
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getStrip(): bool
    {
        return $this->strip;
    }

    /**
     * @param bool $reduceOnly
     * @return Format
     */
    public function setReduceOnly(bool $reduceOnly)
    {
        $this->reduceOnly = (bool)$reduceOnly;

        return $this;
    }

    /**
     * @return bool
     */
    public function isReduceOnly(): bool
    {
        return $this->reduceOnly;
    }

    /**
     * @return bool
     * @deprecated
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getReduceOnly(): bool
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
                throw new Exception("Unexpected fit type `$fitType`");
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
            throw new Exception("Unexpected width `$width`");
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
            throw new Exception("Unexpected height `$height`");
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
     * @param boolean $value
     * @return Format
     */
    public function setIgnoreCrop(bool $value)
    {
        $this->ignoreCrop = (bool)$value;

        return $this;
    }

    /**
     * @return boolean
     * @deprecated
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getIgnoreCrop(): bool
    {
        return $this->ignoreCrop;
    }

    /**
     * @return boolean
     */
    public function isIgnoreCrop(): bool
    {
        return $this->ignoreCrop;
    }

    /**
     * @param boolean $value
     * @return Format
     */
    public function setProportionalCrop(bool $value)
    {
        $this->proportionalCrop = (bool)$value;

        return $this;
    }

    /**
     * @return bool
     */
    public function isProportionalCrop(): bool
    {
        return $this->proportionalCrop;
    }

    /**
     * @return bool
     * @deprecated
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getProportionalCrop(): bool
    {
        return $this->proportionalCrop;
    }

    /**
     * @param float|null $value
     * @throws Exception
     * @return Format
     */
    public function setWidest($value)
    {
        if ($value === null) {
            $this->widest = null;
            return $this;
        }

        $widest = (float)$value;
        if ($widest <= 0) {
            throw new Exception("widest value must be > 0");
        }

        $this->widest = $widest;

        return $this;
    }

    /**
     * @param float|null $value
     * @throws Exception
     * @return Format
     */
    public function setHighest($value)
    {
        if ($value === null) {
            $this->highest = null;
            return $this;
        }

        $highest = (float)$value;
        if ($highest <= 0) {
            throw new Exception("highest value must be > 0");
        }
        $this->highest = $highest;

        return $this;
    }

    /**
     * @return float|null
     */
    public function getWidest()
    {
        return $this->widest;
    }

    /**
     * @return float|null
     */
    public function getHighest()
    {
        return $this->highest;
    }
}
