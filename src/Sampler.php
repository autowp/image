<?php

namespace Autowp\Image;

use Imagick;
use ImagickDraw;
use ImagickPixel;

use Autowp\Image\Sampler\Format;
use Autowp\Image\Sampler\Exception;

class Sampler
{
    /**
     * @param Imagick $imagick
     * @param Format $format
     */
    private function _convertByInnerFit(Imagick $imagick,
        Format $format)
    {
        $srcWidth = $imagick->getImageWidth();
        $srcHeight = $imagick->getImageHeight();
        $srcRatio = $srcWidth / $srcHeight;

        $widthLess  = $format->getWidth()  && ($srcWidth  < $format->getWidth() );
        $heightLess = $format->getHeight() && ($srcHeight < $format->getHeight());
        $sizeLess = $widthLess || $heightLess;

        $ratio = $format->getWidth() / $format->getHeight();

        if ($format->getReduceOnly() && $sizeLess) {
            // dont crop
            if (!$heightLess) {
                // resize by height
                $scaleHeight = $format->getHeight();
                $scaleWidth = round($scaleHeight * $srcRatio);
                $imagick->scaleImage(
                    $scaleWidth, $scaleHeight, false
                );
            } elseif (!$widthLess) {
                // resize by width
                $scaleWidth = $format->getWidth();
                $scaleHeight = round($scaleWidth / $srcRatio);
                $imagick->scaleImage(
                    $scaleWidth, $scaleHeight, false
                );
            }
        } else {

            // высчитываем размеры обрезания
            if ($ratio < $srcRatio) {
                // широкая картинка
                $cropWidth = (int)round($srcHeight * $ratio);
                $cropHeight = $srcHeight;
                $cropLeft = (int)floor(($srcWidth - $cropWidth) / 2);
                $cropTop = 0;
            } else {
                // высокая картинка
                $cropWidth = $srcWidth;
                $cropHeight = (int)round($srcWidth / $ratio);
                $cropLeft = 0;
                $cropTop = (int)floor(($srcHeight - $cropHeight) / 2);
            }

            $imagick->setImagePage(0, 0, 0, 0);
            if (!$imagick->cropImage($cropWidth, $cropHeight, $cropLeft, $cropTop)) {
                return $this->_raise("Error crop");
            }

            $imagick->scaleImage(
                $format->getWidth(), $format->getHeight(), false
            );
        }
    }

    /**
     * @param Imagick $imagick
     * @param Format $format
     */
    private function _convertByOuterFit(Imagick $imagick,
        Format $format)
    {
        $srcWidth = $imagick->getImageWidth();
        $srcHeight = $imagick->getImageHeight();
        $srcRatio = $srcWidth / $srcHeight;

        $widthLess  = $format->getWidth()  && ($srcWidth  < $format->getWidth() );
        $heightLess = $format->getHeight() && ($srcHeight < $format->getHeight());
        $sizeLess = $widthLess || $heightLess;

        $ratio = $format->getWidth() / $format->getHeight();

        if ($format->getReduceOnly() && $sizeLess) {
            // dont crop
            if (!$heightLess) {
                // resize by height
                $scaleHeight = $format->getHeight();
                $scaleWidth = round($scaleHeight * $srcRatio);
                $imagick->scaleImage(
                    $scaleWidth, $scaleHeight, false
                );
            } elseif (!$widthLess) {
                // resize by width
                $scaleWidth = $format->getWidth();
                $scaleHeight = round($scaleWidth / $srcRatio);
                $imagick->scaleImage(
                    $scaleWidth, $scaleHeight, false
                );
            }
        } else {
            // высчитываем размеры обрезания
            if ($ratio < $srcRatio) {
                $scaleWidth = $format->getWidth();
                $scaleHeight = round($format->getWidth() / $srcRatio);// добавляем поля сверху и снизу
            } else {
                // добавляем поля по бокам
                $scaleWidth = round($format->getHeight() * $srcRatio);
                $scaleHeight = $format->getHeight();
            }

            $imagick->scaleImage(
                $scaleWidth, $scaleHeight, false
            );
        }

        // extend by bg-space
        $borderLeft = floor(($format->getWidth()  - $imagick->getImageWidth())  / 2);
        $borderTop  = floor(($format->getHeight() - $imagick->getImageHeight()) / 2);

        $imagick->extentImage(
            $format->getWidth(),
            $format->getHeight(),
            -$borderLeft,
            -$borderTop
        );
    }

    /**
     * @param Imagick $imagick
     * @param Format $format
     */
    private function _convertByMaximumFit(Imagick $imagick,
        Format $format)
    {
        $srcWidth = $imagick->getImageWidth();
        $srcHeight = $imagick->getImageHeight();
        $srcRatio = $srcWidth / $srcHeight;

        $widthLess  = $format->getWidth()  && ($srcWidth  < $format->getWidth() );
        $heightLess = $format->getHeight() && ($srcHeight < $format->getHeight());
        $sizeLess = $widthLess || $heightLess;

        $ratio = $format->getWidth() / $format->getHeight();

        if ($format->getReduceOnly() && $sizeLess) {

            if (!$heightLess) {
                // resize by height
                $scaleHeight = $format->getHeight();
                $scaleWidth = round($scaleHeight * $srcRatio);
                $imagick->scaleImage(
                    $scaleWidth, $scaleHeight, false
                );
            } elseif (!$widthLess) {
                // resize by width
                $scaleWidth = $format->getWidth();
                $scaleHeight = round($scaleWidth / $srcRatio);
                $imagick->scaleImage(
                    $scaleWidth, $scaleHeight, false
                );
            }

        } else {

            // высчитываем размеры обрезания
            if ($ratio < $srcRatio) {
                $scaleWidth = $format->getWidth();
                $scaleHeight = round($format->getWidth() / $srcRatio);
            } else {
                // добавляем поля по бокам
                $scaleWidth = round($format->getHeight() * $srcRatio);
                $scaleHeight = $format->getHeight();
            }

            $imagick->scaleImage(
                $scaleWidth, $scaleHeight, false
            );

        }
    }

    /**
     * @param Imagick $imagick
     * @param Format $format
     */
    private function _convertByWidth(Imagick $imagick,
        Format $format)
    {
        $srcWidth = $imagick->getImageWidth();
        $srcRatio = $srcWidth / $imagick->getImageHeight();

        $widthLess = $srcWidth < $format->getWidth();

        if ($format->getReduceOnly() && $widthLess) {
            $scaleWidth = $srcWidth;
        } else {
            $scaleWidth = $format->getWidth();
        }

        $scaleHeight = round($scaleWidth / $srcRatio);

        $imagick->scaleImage($scaleWidth, $scaleHeight, false);
    }

    /**
     * @param Imagick $imagick
     * @param Format $format
     */
    private function _convertByHeight(Imagick $imagick,
        Format $format)
    {
        $srcHeight = $imagick->getImageHeight();
        $srcRatio = $imagick->getImageWidth() / $srcHeight;

        $heightLess = $format->getHeight() && ($srcHeight < $format->getHeight());

        $ratio = $format->getWidth() / $format->getHeight();

        if ($format->getReduceOnly() && $heightLess) {
            $scaleHeight = $srcHeight;
        } else {
            $scaleHeight = $format->getHeight();
        }

        $scaleWidth = round($scaleHeight * $srcRatio);

        $imagick->scaleImage(
            $scaleWidth, $scaleHeight, false
        );
    }

    /**
     * @param Imagick $source
     * @param array|Format $format
     * @throws Exception
     */
    public function convertImagick(Imagick $imagick, $format)
    {
        if (!$format instanceof Format) {
            if (is_array($format)) {
                $format = new Format($format);
            } else {
                return $this->_raise("Unexpected type of format");
            }
        }

        if ($quality = $format->getQuality()) {
            $imagick->setImageCompressionQuality($quality);
        }

        $crop = false;
        if (!$format->getIgnoreCrop()) {
            $crop = $format->getCrop();
        }

        if ($crop) {
            $cropSet = isset($crop['width'], $crop['height'], $crop['left'], $crop['top']);
            if (!$cropSet) {
                return $this->_raise('Crop parameters not properly set');
            }

            $cropWidth  = (int)$crop['width'];
            $cropHeight = (int)$crop['height'];
            $cropLeft   = (int)$crop['left'];
            $cropTop    = (int)$crop['top'];

            $width = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();

            $leftValid = ($cropLeft >= 0) && ($cropLeft < $width );
            if (!$leftValid) {
                return $this->_raise("Crop left out of bounds ('$cropLeft')");
            }

            $topValid = ($cropTop >= 0) && ($cropTop < $height);
            if (!$topValid) {
                return $this->_raise("Crop top out of bounds ('$cropTop')");
            }

            $right = $cropLeft + $cropWidth;
            $widthValid  = ($cropWidth > 0) && ($right <= $width );
            if (!$widthValid) {
                return $this->_raise("Crop width out of bounds ('$cropLeft + $cropWidth' ~ '$width x $height')");
            }

            $bottom = $cropTop + $cropHeight;
            $heightValid = ($cropHeight > 0) && ($bottom <= $height);
            if (!$heightValid) {
                return $this->_raise("Crop height out of bounds ('$cropTop + $cropHeight' ~ '$width x $height')");
            }

            $fWidth = $format->getWidth();
            $fHeight = $format->getHeight();
            if ($format->getProportionalCrop() && $fWidth && $fHeight) {
                // extend crop to format proportions
                $fRatio = $fWidth / $fHeight;
                $cRatio = $cropWidth / $cropHeight;

                if ($cRatio > $fRatio) {
                    // crop wider than format, need more height
                    $targetHeight = round($cropWidth / $fRatio);
                    if ($targetHeight > $height) {
                        $targetHeight = $height;
                    }
                    $addedHeight = $targetHeight - $cropHeight;
                    $cropTop -= round($addedHeight / 2);
                    if ($cropTop < 0) {
                        $cropTop = 0;
                    }
                    $cropHeight = $targetHeight;
                } else {
                    // crop higher than format, need more width
                    $targetWidth = round($cropHeight * $fRatio);
                    if ($targetWidth > $width) {
                        $targetWidth = $width;
                    }
                    $addedWidth = $targetWidth - $cropWidth;
                    $cropLeft -= round($addedWidth / 2);
                    if ($cropLeft < 0) {
                        $cropLeft = 0;
                    }
                    $cropWidth = $targetWidth;
                }
            }

            $imagick->cropImage($cropWidth, $cropHeight, $cropLeft, $cropTop);
        }

        // check for monotone background extend posibility
        $fWidth = $format->getWidth();
        $fHeight = $format->getHeight();
        if ($format->getProportionalCrop() && $fWidth && $fHeight) {

            $fRatio = $format->getWidth() / $format->getHeight();
            $cRatio = $imagick->getImageWidth() / $imagick->getImageHeight();

            $ratioDiff = abs($fRatio - $cRatio);

            if ($ratioDiff > 0.001) {
                if ($cRatio > $fRatio) {
                    $this->_extendVertical($imagick, $format);
                } else {
                    $this->_extendHorizontal($imagick, $format);
                }
            }
        }

        $bg = $format->getBackground();
        if (!$bg) {
            $bg = 'transparent';
        }
        $imagick->setBackgroundColor($bg);
        $imagick->setImageBackgroundColor($bg);


        if ($format->getWidth() && $format->getHeight()) {
            switch ($format->getFitType()) {
                case Format::FIT_TYPE_INNER:
                    $this->_convertByInnerFit($imagick, $format);
                    break;

                case Format::FIT_TYPE_OUTER:
                    $this->_convertByOuterFit($imagick, $format);
                    break;

                case Format::FIT_TYPE_MAXIMUM:
                    $this->_convertByMaximumFit($imagick, $format);
                    break;

                default:
                    $this->_raise("Unexpected FIT_TYPE `{$format->getFitType()}`");
            }
        } else {

            if ($format->getWidth()) {
                $this->_convertByWidth($imagick, $format);
            } elseif ($format->getHeight()) {
                $this->_convertByHeight($imagick, $format);
            }

        }

        if ($format->getStrip()) {
            $imagick->stripImage();
        }

        if ($imageFormat = $format->getFormat()) {
            $imagick->setImageFormat($imageFormat);
        }
    }

    /**
     * @param Imagick|string $source
     * @param array|Format $format
     * @throws Exception
     */
    public function convertToFile($source, $destFile, $format)
    {
        if ($source instanceof Imagick) {
            $imagick = clone $source; // to prevent modifying source
        } else {
            $imagick = new Imagick();
            if (!$imagick->readImage($source)) {
                return $this->_raise("Error read image from `$source`");
            }
        }

        if (!$destFile) {
            return $this->_raise("Dest file not set");
        }

        $this->convertImagick($imagick, $format);

        if (!$imagick->writeImage($destFile)) {
            return $this->_raise("Error write image to `$destFile`");
        }

        $imagick->clear();
    }

    private function standardDeviation(array $a, $sample = false)
    {
        $n = count($a);
        if ($n === 0) {
            trigger_error("The array has zero elements", E_USER_WARNING);
            return false;
        }
        if ($sample && $n === 1) {
            trigger_error("The array has only 1 element", E_USER_WARNING);
            return false;
        }
        $mean = array_sum($a) / $n;
        $carry = 0.0;
        foreach ($a as $val) {
            $d = ((double) $val) - $mean;
            $carry += $d * $d;
        };
        if ($sample) {
            --$n;
        }
        return sqrt($carry / $n);
    }

    /**
     * @param ImagickPixelIterator $iterator
     * @return ImagickPixel
     */
    private function _extendEdgeColor(ImagickPixelIterator $iterator)
    {
        $sum = array(
            'r' => array(),
            'g' => array(),
            'b' => array()
        );

        foreach ($iterator as $row) {
            foreach ($row as $pixel) {
                $color = $pixel->getColor(true);
                $sum['r'][] = $color['r'];
                $sum['g'][] = $color['g'];
                $sum['b'][] = $color['b'];
            }
        }

        $count = count($sum['r']);

        $r = $this->standardDeviation($sum['r']);
        $g = $this->standardDeviation($sum['g']);
        $b = $this->standardDeviation($sum['b']);

        $limit = 0.01;
        if ($r > $limit || $g > $limit || $b > $limit) {
            return false;
        }

        $avgR = array_sum($sum['r']) / $count;
        $avgG = array_sum($sum['g']) / $count;
        $avgB = array_sum($sum['b']) / $count;

        $color = new ImagickPixel('#000000');
        $color->setColorValue(Imagick::COLOR_RED,   $avgR);
        $color->setColorValue(Imagick::COLOR_GREEN, $avgG);
        $color->setColorValue(Imagick::COLOR_BLUE,  $avgB);

        return $color;
    }

    /**
     * @param Imagick $imagick
     * @return ImagickPixel
     */
    private function _extendTopColor(Imagick $imagick)
    {
        $iterator = $imagick->getPixelRegionIterator(0, 0, $imagick->getImageWidth(), 1);

        return $this->_extendEdgeColor($iterator);
    }

    /**
     * @param Imagick $imagick
     * @return ImagickPixel
     */
    private function _extendBottomColor(Imagick $imagick)
    {
        $iterator = $imagick->getPixelRegionIterator(0, $imagick->getImageHeight()-1, $imagick->getImageWidth(), 1);

        return $this->_extendEdgeColor($iterator);
    }

    /**
     * @param Imagick $imagick
     * @return ImagickPixel
     */
    private function _extendLeftColor(Imagick $imagick)
    {
        $iterator = $imagick->getPixelRegionIterator(0, 0, 1, $imagick->getImageHeight());

        return $this->_extendEdgeColor($iterator);
    }

    /**
     * @param Imagick $imagick
     * @return ImagickPixel
     */
    private function _extendRightColor(Imagick $imagick)
    {
        $iterator = $imagick->getPixelRegionIterator($imagick->getImageWidth()-1, 0, 1, $imagick->getImageHeight());

        return $this->_extendEdgeColor($iterator);
    }

    /**
     * @param Imagick $imagick
     * @param Format $format
     */
    private function _extendVertical(Imagick $imagick, Format $format)
    {
        $fRatio = $format->getWidth() / $format->getHeight();

        $srcWidth = $imagick->getImageWidth();
        $srcHeight = $imagick->getImageHeight();

        $topColor = $this->_extendTopColor($imagick);
        $bottomColor = $this->_extendBottomColor($imagick);

        if ($topColor || $bottomColor) {

            $targetWidth = $srcWidth;
            $targetHeight = round($targetWidth / $fRatio);

            $needHeight = $targetHeight - $srcHeight;
            $topHeight = 0;
            $bottomHeight = 0;
            if ($topColor && $bottomColor) {
                $topHeight = round($needHeight / 2);
                $bottomHeight = $needHeight - $topHeight;
            } elseif ($topColor) {
                $topHeight = round($needHeight);
            } elseif ($bottomColor) {
                $bottomHeight = round($needHeight);
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

    /**
     * @param Imagick $imagick
     * @param Format $format
     */
    private function _extendHorizontal(Imagick $imagick, Format $format)
    {
        $fRatio = $format->getWidth() / $format->getHeight();

        $srcWidth = $imagick->getImageWidth();
        $srcHeight = $imagick->getImageHeight();

        $leftColor = $this->_extendLeftColor($imagick);
        $rightColor = $this->_extendRightColor($imagick);

        if ($leftColor || $rightColor) {

            $targetHeight = $srcHeight;
            $targetWidth = round($targetHeight * $fRatio);

            $needWidth = $targetWidth - $srcWidth;
            $leftWidth = 0;
            $rightWidth = 0;
            if ($leftColor && $rightColor) {
                $leftWidth = round($needWidth / 2);
                $rightWidth = $needWidth - $leftWidth;
            } elseif ($leftColor) {
                $leftWidth = round($needWidth);
            } elseif ($rightColor) {
                $rightWidth = round($needWidth);
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


    /**
     * @param string $message
     * @throws Exception
     */
    private function _raise($message)
    {
        throw new Exception($message);
    }
}