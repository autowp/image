<?php

declare(strict_types=1);

namespace Autowp\Image\Sampler;

use function method_exists;
use function ucfirst;

/**
 * @desc Represents a image formatting rules
 */
class Format
{
    public const
        FIT_TYPE_INNER   = '0', // вписать
        FIT_TYPE_OUTER   = '1', // описать
        FIT_TYPE_MAXIMUM = '2';

    /** @var int */
    private int $fitType;

    /** @var int */
    private ?int $width = null;

    /** @var int */
    private ?int $height = null;

    /** @var string */
    private ?string $background = null;

    /** @var bool */
    private bool $ignoreCrop = false;

    /** @var bool */
    private bool $proportionalCrop = false;

    /** @bool */
    private bool $reduceOnly = false;

    /** @var bool */
    private bool $strip = false;

    /** @var int */
    private int $quality = 0;

    /** @var string */
    private string $format;

    /** @var float|null */
    private ?float $widest = null;

    /** @var float|null */
    private ?float $highest = null;

    /** @var array */
    private array $processors = [];

    /**
     * @throws Exception
     */
    public function __construct(array $options = [])
    {
        $this->setOptions($options);
    }

    /**
     * @return $this
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

    public function setProcessors(array $value): self
    {
        $this->processors = (array) $value;

        return $this;
    }

    public function getProcessors(): array
    {
        return $this->processors;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setFormat($value)
    {
        $this->format = $value ? (string) $value : null;

        return $this;
    }

    /**
     * @return string|null
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
     * @return $this
     * @throws Exception
     */
    public function setQuality(int $value): self
    {
        $value = (int) $value;
        if ($value < 0 || $value > 100) {
            throw new Exception("Compression quality must be >= 0 and <= 100");
        }

        $this->quality = $value;

        return $this;
    }

    public function getQuality(): int
    {
        return $this->quality;
    }

    /**
     * @return $this
     */
    public function setStrip(bool $value): self
    {
        $this->strip = (bool) $value;

        return $this;
    }

    public function isStrip(): bool
    {
        return $this->strip;
    }

    /**
     * @deprecated
     *
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getStrip(): bool
    {
        return $this->strip;
    }

    /**
     * @return $this
     */
    public function setReduceOnly(bool $reduceOnly): self
    {
        $this->reduceOnly = (bool) $reduceOnly;

        return $this;
    }

    public function isReduceOnly(): bool
    {
        return $this->reduceOnly;
    }

    /**
     * @deprecated
     *
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getReduceOnly(): bool
    {
        return $this->reduceOnly;
    }

    /**
     * @param int $fitType
     * @throws Exception
     * @return $this
     */
    public function setFitType($fitType): self
    {
        $fitType = (int) $fitType;
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

    public function getFitType(): int
    {
        return $this->fitType;
    }

    /**
     * @param int $width
     * @throws Exception
     * @return $this
     */
    public function setWidth($width): self
    {
        $width = (int) $width;
        if ($width < 0) {
            throw new Exception("Unexpected width `$width`");
        }
        $this->width = $width;

        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    /**
     * @param int $height
     * @throws Exception
     * @return $this
     */
    public function setHeight($height): self
    {
        $height = (int) $height;
        if ($height < 0) {
            throw new Exception("Unexpected height `$height`");
        }
        $this->height = $height;

        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    /**
     * @param string $color
     * @return $this
     */
    public function setBackground($color): self
    {
        $this->background = $color;

        return $this;
    }

    public function getBackground(): ?string
    {
        return $this->background;
    }

    /**
     * @return $this
     */
    public function setIgnoreCrop(bool $value): self
    {
        $this->ignoreCrop = (bool) $value;

        return $this;
    }

    /**
     * @deprecated
     *
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getIgnoreCrop(): bool
    {
        return $this->ignoreCrop;
    }

    public function isIgnoreCrop(): bool
    {
        return $this->ignoreCrop;
    }

    /**
     * @return $this
     */
    public function setProportionalCrop(bool $value): self
    {
        $this->proportionalCrop = (bool) $value;

        return $this;
    }

    public function isProportionalCrop(): bool
    {
        return $this->proportionalCrop;
    }

    /**
     * @deprecated
     *
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getProportionalCrop(): bool
    {
        return $this->proportionalCrop;
    }

    /**
     * @param float|null $value
     * @throws Exception
     * @return $this
     */
    public function setWidest($value): self
    {
        if ($value === null) {
            $this->widest = null;
            return $this;
        }

        $widest = (float) $value;
        if ($widest <= 0) {
            throw new Exception("widest value must be > 0");
        }

        $this->widest = $widest;

        return $this;
    }

    /**
     * @param float|null $value
     * @throws Exception
     * @return $this
     */
    public function setHighest($value): self
    {
        if ($value === null) {
            $this->highest = null;
            return $this;
        }

        $highest = (float) $value;
        if ($highest <= 0) {
            throw new Exception("highest value must be > 0");
        }
        $this->highest = $highest;

        return $this;
    }

    public function getWidest(): ?float
    {
        return $this->widest;
    }

    public function getHighest(): ?float
    {
        return $this->highest;
    }
}
