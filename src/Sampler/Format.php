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
        FIT_TYPE_INNER   = 0, // вписать
        FIT_TYPE_OUTER   = 1, // описать
        FIT_TYPE_MAXIMUM = 2;

    private int $fitType;

    private ?int $width;

    private ?int $height;

    private ?string $background;

    private bool $ignoreCrop = false;

    private bool $proportionalCrop = false;

    private bool $reduceOnly = false;

    private bool $strip = false;

    private int $quality = 0;

    private string $format = '';

    private ?float $widest;

    private ?float $highest;

    private array $processors = [];

    private const FORMAT_EXT = [
        'jpg'  => 'jpeg',
        'jpeg' => 'jpeg',
        'png'  => 'png',
        'gif'  => 'gif',
        'bmp'  => 'bmp',
    ];

    /**
     * @throws Exception
     */
    public function __construct(array $options = [])
    {
        $this->fitType    = self::FIT_TYPE_INNER;
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
        $this->processors = $value;

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
        if (! $this->format) {
            return null;
        }

        if (! isset(self::FORMAT_EXT[$this->format])) {
            throw new Exception("Unsupported format `{$this->format}`");
        }

        return self::FORMAT_EXT[$this->format];
    }

    /**
     * @throws Exception
     */
    public function setQuality(int $value): self
    {
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
        $this->strip = $value;

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
        return $this->isStrip();
    }

    public function setReduceOnly(bool $reduceOnly): self
    {
        $this->reduceOnly = $reduceOnly;

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
        return $this->isReduceOnly();
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
        return $this->isIgnoreCrop();
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
        return $this->isProportionalCrop();
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

        if ($value <= 0) {
            throw new Exception("widest value must be > 0");
        }

        $this->widest = $value;

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

        if ($value <= 0) {
            throw new Exception("highest value must be > 0");
        }
        $this->highest = $value;

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
