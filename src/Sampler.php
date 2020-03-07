<?php

declare(strict_types=1);

namespace Autowp\Image;

use Imagick;
use ImagickDraw;
use ImagickException;
use ImagickPixel;
use ImagickPixelIterator;

use function abs;
use function array_sum;
use function count;
use function floor;
use function is_array;
use function round;
use function sqrt;
use function trigger_error;

use const E_USER_WARNING;

class Sampler
{
    /**
     * @throws ImagickException
     */
    private function scaleImage(Imagick $imagick, int $width, int $height): Imagick
    {
        if ($imagick->getImageFormat() === 'GIF') {
            foreach ($imagick as $i) {
                $i->scaleImage($width, $height, false);
            }
        } else {
            $imagick->scaleImage($width, $height, false);
        }

        return $imagick;
    }

    /**
     * @throws ImagickException
     */
    private function convertByInnerFit(Imagick $imagick, Sampler\Format $format): Imagick
    {
        $srcWidth  = $imagick->getImageWidth();
        $srcHeight = $imagick->getImageHeight();
        $srcRatio  = $srcWidth / $srcHeight;

        $widthLess  = $format->getWidth() && ($srcWidth < $format->getWidth() );
        $heightLess = $format->getHeight() && ($srcHeight < $format->getHeight());
        $sizeLess   = $widthLess || $heightLess;

        $ratio = $format->getWidth() / $format->getHeight();

        if ($format->isReduceOnly() && $sizeLess) {
            // dont crop
            if (! $heightLess) {
                // resize by height
                $scaleHeight = $format->getHeight();
                $scaleWidth  = (int) round($scaleHeight * $srcRatio);
                $imagick     = $this->scaleImage($imagick, $scaleWidth, $scaleHeight);
            } elseif (! $widthLess) {
                // resize by width
                $scaleWidth  = $format->getWidth();
                $scaleHeight = (int) round($scaleWidth / $srcRatio);
                $imagick     = $this->scaleImage($imagick, $scaleWidth, $scaleHeight);
            }
        } else {
            // высчитываем размеры обрезания
            if ($ratio < $srcRatio) {
                // широкая картинка
                $cropWidth  = (int) round($srcHeight * $ratio);
                $cropHeight = $srcHeight;
                $cropLeft   = (int) floor(($srcWidth - $cropWidth) / 2);
                $cropTop    = 0;
            } else {
                // высокая картинка
                $cropWidth  = $srcWidth;
                $cropHeight = (int) round($srcWidth / $ratio);
                $cropLeft   = 0;
                $cropTop    = (int) floor(($srcHeight - $cropHeight) / 2);
            }

            $imagick = $this->crop($imagick, $cropWidth, $cropHeight, $cropLeft, $cropTop);
            $imagick = $this->scaleImage($imagick, $format->getWidth(), $format->getHeight());
        }

        return $imagick;
    }

    /**
     * @throws ImagickException
     */
    private function convertByOuterFit(Imagick $imagick, Sampler\Format $format): Imagick
    {
        $srcWidth  = $imagick->getImageWidth();
        $srcHeight = $imagick->getImageHeight();
        $srcRatio  = $srcWidth / $srcHeight;

        $widthLess  = $format->getWidth() && ($srcWidth < $format->getWidth() );
        $heightLess = $format->getHeight() && ($srcHeight < $format->getHeight());
        $sizeLess   = $widthLess || $heightLess;

        $ratio = $format->getWidth() / $format->getHeight();

        if ($format->isReduceOnly() && $sizeLess) {
            // dont crop
            if (! $heightLess) {
                // resize by height
                $scaleHeight = $format->getHeight();
                $scaleWidth  = (int) round($scaleHeight * $srcRatio);
                $imagick     = $this->scaleImage($imagick, $scaleWidth, $scaleHeight);
            } elseif (! $widthLess) {
                // resize by width
                $scaleWidth  = $format->getWidth();
                $scaleHeight = (int) round($scaleWidth / $srcRatio);
                $imagick     = $this->scaleImage($imagick, $scaleWidth, $scaleHeight);
            }
        } else {
            if ($ratio < $srcRatio) {
                $scaleWidth = $format->getWidth();
                // add top and bottom margins
                $scaleHeight = (int) round($format->getWidth() / $srcRatio);
            } else {
                // add left and right margins
                $scaleWidth  = (int) round($format->getHeight() * $srcRatio);
                $scaleHeight = $format->getHeight();
            }

            $imagick = $this->scaleImage($imagick, $scaleWidth, $scaleHeight);
        }

        // extend by bg-space
        $borderLeft = (int) floor(($format->getWidth() - $imagick->getImageWidth()) / 2);
        $borderTop  = (int) floor(($format->getHeight() - $imagick->getImageHeight()) / 2);

        $imagick->extentImage(
            $format->getWidth(),
            $format->getHeight(),
            -$borderLeft,
            -$borderTop
        );

        return $imagick;
    }

    /**
     * @throws ImagickException
     */
    private function convertByMaximumFit(Imagick $imagick, Sampler\Format $format): Imagick
    {
        $srcWidth  = $imagick->getImageWidth();
        $srcHeight = $imagick->getImageHeight();
        $srcRatio  = $srcWidth / $srcHeight;

        $widthLess  = $format->getWidth() && ($srcWidth < $format->getWidth() );
        $heightLess = $format->getHeight() && ($srcHeight < $format->getHeight());
        $sizeLess   = $widthLess || $heightLess;

        $ratio = $format->getWidth() / $format->getHeight();

        if ($format->isReduceOnly() && $sizeLess) {
            if (! $heightLess) {
                // resize by height
                $scaleHeight = $format->getHeight();
                $scaleWidth  = (int) round($scaleHeight * $srcRatio);
                $imagick     = $this->scaleImage($imagick, $scaleWidth, $scaleHeight);
            } elseif (! $widthLess) {
                // resize by width
                $scaleWidth  = $format->getWidth();
                $scaleHeight = (int) round($scaleWidth / $srcRatio);
                $imagick     = $this->scaleImage($imagick, $scaleWidth, $scaleHeight);
            }
        } else {
            // высчитываем размеры обрезания
            if ($ratio < $srcRatio) {
                $scaleWidth  = $format->getWidth();
                $scaleHeight = (int) round($format->getWidth() / $srcRatio);
            } else {
                // добавляем поля по бокам
                $scaleWidth  = (int) round($format->getHeight() * $srcRatio);
                $scaleHeight = $format->getHeight();
            }

            $imagick = $this->scaleImage($imagick, $scaleWidth, $scaleHeight);
        }

        return $imagick;
    }

    /**
     * @throws ImagickException
     */
    private function convertByWidth(Imagick $imagick, Sampler\Format $format): Imagick
    {
        $srcWidth = $imagick->getImageWidth();
        $srcRatio = $srcWidth / $imagick->getImageHeight();

        $widthLess = $srcWidth < $format->getWidth();

        if ($format->isReduceOnly() && $widthLess) {
            $scaleWidth = $srcWidth;
        } else {
            $scaleWidth = $format->getWidth();
        }

        $scaleHeight = (int) round($scaleWidth / $srcRatio);

        return $this->scaleImage($imagick, $scaleWidth, $scaleHeight);
    }

    /**
     * @throws ImagickException
     */
    private function convertByHeight(Imagick $imagick, Sampler\Format $format): Imagick
    {
        $srcHeight = $imagick->getImageHeight();
        $srcRatio  = $imagick->getImageWidth() / $srcHeight;

        $heightLess = $format->getHeight() && ($srcHeight < $format->getHeight());

        if ($format->isReduceOnly() && $heightLess) {
            $scaleHeight = $srcHeight;
        } else {
            $scaleHeight = $format->getHeight();
        }

        $scaleWidth = (int) round($scaleHeight * $srcRatio);

        return $this->scaleImage($imagick, $scaleWidth, $scaleHeight);
    }

    private function crop(Imagick $imagick, int $width, int $height, int $left, int $top): Imagick
    {
        if ($imagick->getImageFormat() === 'GIF') {
            foreach ($imagick as $i) {
                $i->cropImage($width, $height, $left, $top);
                $i->setImagePage($width, $height, 0, 0);
            }
        } else {
            $imagick->setImagePage(0, 0, 0, 0);
            $imagick->cropImage($width, $height, $left, $top);
        }

        return $imagick;
    }

    /**
     * @throws Sampler\Exception
     */
    private function cropImage(Imagick $imagick, array $crop, Sampler\Format $format): Imagick
    {
        $cropSet = isset($crop['width'], $crop['height'], $crop['left'], $crop['top']);
        if (! $cropSet) {
            throw new Sampler\Exception('Crop parameters not properly set');
        }

        $cropWidth  = (int) $crop['width'];
        $cropHeight = (int) $crop['height'];
        $cropLeft   = (int) $crop['left'];
        $cropTop    = (int) $crop['top'];

        $width  = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();

        $leftValid = ($cropLeft >= 0) && ($cropLeft < $width );
        if (! $leftValid) {
            throw new Sampler\Exception("Crop left out of bounds ('$cropLeft')");
        }

        $topValid = ($cropTop >= 0) && ($cropTop < $height);
        if (! $topValid) {
            throw new Sampler\Exception("Crop top out of bounds ('$cropTop')");
        }

        $right      = $cropLeft + $cropWidth;
        $widthValid = ($cropWidth > 0) && ($right <= $width );
        if (! $widthValid) {
            throw new Sampler\Exception(
                "Crop width out of bounds ('$cropLeft + $cropWidth' ~ '$width x $height')"
            );
        }

        // try to fix height overflow
        $bottom   = $cropTop + $cropHeight;
        $overflow = $bottom - $height;
        if ($overflow > 0 && $overflow <= 1) {
            $cropHeight -= $overflow;
        }

        $bottom      = $cropTop + $cropHeight;
        $heightValid = ($cropHeight > 0) && ($bottom <= $height);
        if (! $heightValid) {
            throw new Sampler\Exception(
                "Crop height out of bounds ('$cropTop + $cropHeight' ~ '$width x $height')"
            );
        }

        $fWidth  = $format->getWidth();
        $fHeight = $format->getHeight();
        if ($format->isProportionalCrop() && $fWidth && $fHeight) {
            // extend crop to format proportions
            $fRatio = $fWidth / $fHeight;
            $cRatio = $cropWidth / $cropHeight;

            if ($cRatio > $fRatio) {
                // crop wider than format, need more height
                $targetHeight = (int) round($cropWidth / $fRatio);
                if ($targetHeight > $height) {
                    $targetHeight = $height;
                }
                $addedHeight = $targetHeight - $cropHeight;
                $cropTop    -= (int) round($addedHeight / 2);
                if ($cropTop < 0) {
                    $cropTop = 0;
                }
                $cropHeight = $targetHeight;
            } else {
                // crop higher than format, need more width
                $targetWidth = (int) round($cropHeight * $fRatio);
                if ($targetWidth > $width) {
                    $targetWidth = $width;
                }
                $addedWidth = $targetWidth - $cropWidth;
                $cropLeft  -= (int) round($addedWidth / 2);
                if ($cropLeft < 0) {
                    $cropLeft = 0;
                }
                $cropWidth = $targetWidth;
            }
        }

        return $this->crop($imagick, $cropWidth, $cropHeight, $cropLeft, $cropTop);
    }

    private function cropToWidest(Imagick $imagick, float $widestRatio): Imagick
    {
        $srcWidth  = $imagick->getImageWidth();
        $srcHeight = $imagick->getImageHeight();

        $srcRatio = $srcWidth / $srcHeight;

        $ratioDiff = $srcRatio - $widestRatio;

        if ($ratioDiff > 0) {
            $dstWidth = (int) round($widestRatio * $srcHeight);
            $imagick  = $this->crop($imagick, $dstWidth, $srcHeight, (int) round(($srcWidth - $dstWidth) / 2), 0);
        }

        return $imagick;
    }

    private function cropToHighest(Imagick $imagick, float $highestRatio): Imagick
    {
        $srcWidth  = $imagick->getImageWidth();
        $srcHeight = $imagick->getImageHeight();

        $srcRatio = $srcWidth / $srcHeight;

        $ratioDiff = $srcRatio - $highestRatio;

        if ($ratioDiff < 0) {
            $dstHeight = (int) round($srcWidth / $highestRatio);
            $imagick   = $this->crop($imagick, $srcWidth, $dstHeight, 0, ($srcHeight - $dstHeight) / 2);
        }

        return $imagick;
    }

    /**
     * @param Sampler\Format|array $format
     * @throws ImagickException
     * @throws Sampler\Exception
     */
    public function convertImagick(Imagick $imagick, ?array $crop, $format): Imagick
    {
        if (! $format instanceof Sampler\Format) {
            if (! is_array($format)) {
                throw new Sampler\Exception("Unexpected type of format");
            }
            $format = new Sampler\Format($format);
        }

        if ($quality = $format->getQuality()) {
            $imagick->setImageCompressionQuality($quality);
        }

        if ($crop && ! $format->isIgnoreCrop()) {
            $this->cropImage($imagick, $crop, $format);
        }

        if ($imagick->getImageFormat() === 'GIF') {
            $decomposited = $imagick->coalesceImages();
            $imagick->clear();
            unset($imagick);
        } else {
            $decomposited = $imagick;
        }

        // fit by widest
        $widest = $format->getWidest();
        if ($widest) {
            $this->cropToWidest($decomposited, $widest);
        }

        // fit by highest
        $highest = $format->getHighest();
        if ($highest) {
            $this->cropToHighest($decomposited, $highest);
        }

        // check for monotone background extend posibility
        $fWidth  = $format->getWidth();
        $fHeight = $format->getHeight();
        if ($format->isProportionalCrop() && $fWidth && $fHeight) {
            $fRatio = $format->getWidth() / $format->getHeight();
            $cRatio = $decomposited->getImageWidth() / $decomposited->getImageHeight();

            $ratioDiff = abs($fRatio - $cRatio);

            if ($ratioDiff > 0.001) {
                if ($cRatio > $fRatio) {
                    $this->extendVertical($decomposited, $format);
                } else {
                    $this->extendHorizontal($decomposited, $format);
                }
            }
        }

        $background = $format->getBackground();
        if ($background) {
            $decomposited->setBackgroundColor($background);
            $decomposited->setImageBackgroundColor($background);
        }

        if ($format->getWidth() && $format->getHeight()) {
            switch ($format->getFitType()) {
                case Sampler\Format::FIT_TYPE_INNER:
                    $decomposited = $this->convertByInnerFit($decomposited, $format);
                    break;

                case Sampler\Format::FIT_TYPE_OUTER:
                    $decomposited = $this->convertByOuterFit($decomposited, $format);
                    break;

                case Sampler\Format::FIT_TYPE_MAXIMUM:
                    $decomposited = $this->convertByMaximumFit($decomposited, $format);
                    break;

                default:
                    throw new Sampler\Exception("Unexpected FIT_TYPE `{$format->getFitType()}`");
            }
        } else {
            if ($format->getWidth()) {
                $decomposited = $this->convertByWidth($decomposited, $format);
            } elseif ($format->getHeight()) {
                $decomposited = $this->convertByHeight($decomposited, $format);
            }
        }

        if ($decomposited->getImageFormat() === 'GIF') {
            $decomposited->optimizeImageLayers();
            $imagick = $decomposited->deconstructImages();
            $decomposited->clear();
            unset($decomposited);
        } else {
            $imagick = $decomposited;
        }

        if ($format->isStrip()) {
            $imagick->stripImage();
        }

        if ($imageFormat = $format->getFormat()) {
            $imagick->setImageFormat($imageFormat);
        }

        return $imagick;
    }

    /**
     * @param Imagick|string       $source
     * @param array|Sampler\Format $format
     * @throws Sampler\Exception
     * @throws ImagickException
     */
    public function convertToFile($source, string $destFile, $format): void
    {
        if ($source instanceof Imagick) {
            $imagick = clone $source; // to prevent modifying source
        } else {
            $imagick = new Imagick();
            if (! $imagick->readImage($source)) {
                throw new Sampler\Exception("Error read image from `$source`");
            }
        }

        if (! $destFile) {
            throw new Sampler\Exception("Dest file not set");
        }

        $imagick = $this->convertImagick($imagick, null, $format);

        if (! $imagick->writeImages($destFile, true)) {
            throw new Sampler\Exception("Error write image to `$destFile`");
        }

        $imagick->clear();
    }

    /**
     * @param bool $sample
     * @return bool|float
     */
    private function standardDeviation(array $values, $sample = false)
    {
        $count = count($values);
        if ($count === 0) {
            trigger_error("The array has zero elements", E_USER_WARNING);
            return false;
        }
        if ($sample && $count === 1) {
            trigger_error("The array has only 1 element", E_USER_WARNING);
            return false;
        }
        $mean  = array_sum($values) / $count;
        $carry = 0.0;
        foreach ($values as $val) {
            $diff   = ((double) $val) - $mean;
            $carry += $diff * $diff;
        }
        if ($sample) {
            --$count;
        }
        return sqrt($carry / $count);
    }

    private function extendEdgeColor(ImagickPixelIterator $iterator): ?ImagickPixel
    {
        $sum = [
            'r' => [],
            'g' => [],
            'b' => [],
        ];

        foreach ($iterator as $row) {
            foreach ($row as $pixel) {
                $color      = $pixel->getColor(true);
                $sum['r'][] = $color['r'];
                $sum['g'][] = $color['g'];
                $sum['b'][] = $color['b'];
            }
        }

        $count = count($sum['r']);

        $red   = $this->standardDeviation($sum['r']);
        $green = $this->standardDeviation($sum['g']);
        $blue  = $this->standardDeviation($sum['b']);

        $limit = 0.01;
        if ($red > $limit || $green > $limit || $blue > $limit) {
            return null;
        }

        $avgR = array_sum($sum['r']) / $count;
        $avgG = array_sum($sum['g']) / $count;
        $avgB = array_sum($sum['b']) / $count;

        $color = new ImagickPixel('#000000');
        $color->setColorValue(Imagick::COLOR_RED, $avgR);
        $color->setColorValue(Imagick::COLOR_GREEN, $avgG);
        $color->setColorValue(Imagick::COLOR_BLUE, $avgB);

        return $color;
    }

    /**
     * @return ImagickPixel
     */
    private function extendTopColor(Imagick $imagick)
    {
        $iterator = $imagick->getPixelRegionIterator(0, 0, $imagick->getImageWidth(), 1);

        return $this->extendEdgeColor($iterator);
    }

    /**
     * @return ImagickPixel
     */
    private function extendBottomColor(Imagick $imagick)
    {
        $iterator = $imagick->getPixelRegionIterator(0, $imagick->getImageHeight() - 1, $imagick->getImageWidth(), 1);

        return $this->extendEdgeColor($iterator);
    }

    /**
     * @return ImagickPixel
     */
    private function extendLeftColor(Imagick $imagick)
    {
        $iterator = $imagick->getPixelRegionIterator(0, 0, 1, $imagick->getImageHeight());

        return $this->extendEdgeColor($iterator);
    }

    /**
     * @return ImagickPixel
     */
    private function extendRightColor(Imagick $imagick)
    {
        $iterator = $imagick->getPixelRegionIterator($imagick->getImageWidth() - 1, 0, 1, $imagick->getImageHeight());

        return $this->extendEdgeColor($iterator);
    }

    private function extendVertical(Imagick $imagick, Sampler\Format $format)
    {
        $fRatio = $format->getWidth() / $format->getHeight();

        $srcWidth  = $imagick->getImageWidth();
        $srcHeight = $imagick->getImageHeight();

        $topColor    = $this->extendTopColor($imagick);
        $bottomColor = $this->extendBottomColor($imagick);

        if ($topColor || $bottomColor) {
            $targetWidth  = $srcWidth;
            $targetHeight = (int) round($targetWidth / $fRatio);

            $needHeight   = $targetHeight - $srcHeight;
            $topHeight    = 0;
            $bottomHeight = 0;
            if ($topColor && $bottomColor) {
                $topHeight    = (int) round($needHeight / 2);
                $bottomHeight = $needHeight - $topHeight;
            } elseif ($topColor) {
                $topHeight = (int) round($needHeight);
            } elseif ($bottomColor) {
                $bottomHeight = (int) round($needHeight);
            }

            $imagick->extentImage(
                $targetWidth,
                $targetHeight,
                0,
                -$topHeight
            );

            if ($topColor) {
                $draw = new ImagickDraw();
                $draw->setFillColor($topColor);
                $draw->setStrokeColor($topColor);
                $draw->rectangle(
                    0,
                    0,
                    $imagick->getImageWidth(),
                    $topHeight
                );
                $imagick->drawImage($draw);
            }

            if ($bottomColor) {
                $draw = new ImagickDraw();
                $draw->setFillColor($bottomColor);
                $draw->setStrokeColor($bottomColor);
                $draw->rectangle(
                    0,
                    $imagick->getImageHeight() - $bottomHeight,
                    $imagick->getImageWidth(),
                    $imagick->getImageHeight()
                );
                $imagick->drawImage($draw);
            }
        }
    }

    private function extendHorizontal(Imagick $imagick, Sampler\Format $format)
    {
        $fRatio = $format->getWidth() / $format->getHeight();

        $srcWidth  = $imagick->getImageWidth();
        $srcHeight = $imagick->getImageHeight();

        $leftColor  = $this->extendLeftColor($imagick);
        $rightColor = $this->extendRightColor($imagick);

        if ($leftColor || $rightColor) {
            $targetHeight = $srcHeight;
            $targetWidth  = (int) round($targetHeight * $fRatio);

            $needWidth  = $targetWidth - $srcWidth;
            $leftWidth  = 0;
            $rightWidth = 0;
            if ($leftColor && $rightColor) {
                $leftWidth  = (int) round($needWidth / 2);
                $rightWidth = $needWidth - $leftWidth;
            } elseif ($leftColor) {
                $leftWidth = (int) round($needWidth);
            } elseif ($rightColor) {
                $rightWidth = (int) round($needWidth);
            }

            $imagick->extentImage(
                $targetWidth,
                $targetHeight,
                -$leftWidth,
                0
            );

            if ($leftColor) {
                $draw = new ImagickDraw();
                $draw->setFillColor($leftColor);
                $draw->setStrokeColor($leftColor);
                $draw->rectangle(
                    0,
                    0,
                    $leftWidth,
                    $imagick->getImageHeight()
                );
                $imagick->drawImage($draw);
            }

            if ($rightColor) {
                $draw = new ImagickDraw();
                $draw->setFillColor($rightColor);
                $draw->setStrokeColor($rightColor);
                $draw->rectangle(
                    $imagick->getImageWidth() - $rightWidth,
                    0,
                    $imagick->getImageWidth(),
                    $imagick->getImageHeight()
                );
                $imagick->drawImage($draw);
            }
        }
    }
}
