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
    private ?int $width;

    /** @var int */
    private ?int $height;

    /** @var string */
    private ?string $background;

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
    private string $format = '';

    /** @var float|null */
    private ?float $widest;

    /** @var float|null */
    private ?float $highest;

    /** @var array */
    private array $processors = [];

    /**
     * @throws Exception
     */
    public function __construct(array $options = [])
    {
        $this->width      = null;
        $this->height     = null;
        $this->background = null;
        $this->widest     = null;
        $this->highest    = null;
        $this->setOptions($options);
    }

    /**
     * @throws Exception
     */
    public function setOptions(array $options): self
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

    public function setFormat(string $value): self
    {
        $this->format = $value;

        return $this;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * @throws Exception
     */
    public function getFormatExtension(): ?string
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
     * @throws Exception
     */
    public function setFitType(int $fitType): self
    {
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
     * @throws Exception
     */
    public function setWidth(int $width): self
    {
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
     * @throws Exception
     */
    public function setHeight(int $height): self
    {
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

    public function setBackground(?string $color): self
    {
        $this->background = $color;

        return $this;
    }

    public function getBackground(): ?string
    {
        return $this->background;
    }

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
     * @throws Exception
     */
    public function setWidest(?float $value): self
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
     * @throws Exception
     */
    public function setHighest(?float $value): self
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
