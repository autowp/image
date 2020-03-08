<?php

declare(strict_types=1);

namespace AutowpTest\Image;

use Autowp\Image\Sampler;
use Autowp\Image\Sampler\Format;
use Imagick;
use ImagickException;
use PHPUnit\Framework\TestCase;

use function dirname;

/**
 * @group Autowp_Image
 */
class SamplerTest extends TestCase
{
    /**
     * @throws Sampler\Exception
     * @throws ImagickException
     */
    public function testShouldResizeOddWidthPictureStrictlyToTargetWidthByOuterFitType(): void
    {
        $sampler = new Sampler();
        $file    = dirname(__FILE__) . '/_files/Towers_Schiphol_small.jpg';
        $imagick = new Imagick();
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_OUTER,
            'width'      => 102,
            'height'     => 149,
            'background' => 'red',
        ]);
        $this->assertSame($imagick->getImageWidth(), 102);
        $imagick->clear();
    }

    /**
     * @throws ImagickException
     * @throws Sampler\Exception
     */
    public function testShouldResizeOddHeightPictureStrictlyToTargetHeightByOuterFitType(): void
    {
        $sampler = new Sampler();
        $file    = dirname(__FILE__) . '/_files/Towers_Schiphol_small.jpg';
        $imagick = new Imagick();
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_OUTER,
            'width'      => 101,
            'height'     => 150,
            'background' => 'red',
        ]);
        $this->assertSame($imagick->getImageHeight(), 150);
        $imagick->clear();
    }

    /**
     * @throws ImagickException
     * @throws Sampler\Exception
     */
    public function testReduceOnlyWithInnerFitWorks(): void
    {
        $sampler = new Sampler();
        $file    = dirname(__FILE__) . '/_files/Towers_Schiphol_small.jpg';
        $imagick = new Imagick();
        // both size less
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_INNER,
            'width'      => 150,
            'height'     => 200,
            'reduceOnly' => true,
        ]);
        $this->assertSame($imagick->getImageWidth(), 101);
        $this->assertSame($imagick->getImageHeight(), 149);
        $imagick->clear();
        // width less
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_INNER,
            'width'      => 150,
            'height'     => 100,
            'reduceOnly' => true,
        ]);
        $this->assertSame($imagick->getImageWidth(), 68);
        $this->assertSame($imagick->getImageHeight(), 100);
        $imagick->clear();
        // height less
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_INNER,
            'width'      => 50,
            'height'     => 200,
            'reduceOnly' => true,
        ]);
        $this->assertSame($imagick->getImageWidth(), 50);
        $this->assertSame($imagick->getImageHeight(), 74);
        $imagick->clear();
        // not less
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_INNER,
            'width'      => 50,
            'height'     => 100,
            'reduceOnly' => true,
        ]);
        $this->assertSame($imagick->getImageWidth(), 50);
        $this->assertSame($imagick->getImageHeight(), 100);
        $imagick->clear();
        // both size less, reduceOnly off
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_INNER,
            'width'      => 150,
            'height'     => 200,
            'reduceOnly' => false,
        ]);
        $this->assertSame($imagick->getImageWidth(), 150);
        $this->assertSame($imagick->getImageHeight(), 200);
        $imagick->clear();
        // width less, reduceOnly off
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_INNER,
            'width'      => 150,
            'height'     => 100,
            'reduceOnly' => false,
        ]);
        $this->assertSame($imagick->getImageWidth(), 150);
        $this->assertSame($imagick->getImageHeight(), 100);
        $imagick->clear();
        // height less, reduceOnly off
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_INNER,
            'width'      => 50,
            'height'     => 200,
            'reduceOnly' => false,
        ]);
        $this->assertSame($imagick->getImageWidth(), 50);
        $this->assertSame($imagick->getImageHeight(), 200);
        $imagick->clear();
        // not less, reduceOnly off
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_INNER,
            'width'      => 50,
            'height'     => 100,
            'reduceOnly' => false,
        ]);
        $this->assertSame($imagick->getImageWidth(), 50);
        $this->assertSame($imagick->getImageHeight(), 100);
        $imagick->clear();
    }

    /**
     * @throws ImagickException
     * @throws Sampler\Exception
     */
    public function testReduceOnlyWithOuterFitWorks(): void
    {
        $sampler = new Sampler();
        $file    = dirname(__FILE__) . '/_files/Towers_Schiphol_small.jpg';
        $imagick = new Imagick();
        // both size less
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_OUTER,
            'width'      => 150,
            'height'     => 200,
            'reduceOnly' => true,
        ]);
        $this->assertSame($imagick->getImageWidth(), 150);
        $this->assertSame($imagick->getImageHeight(), 200);
        $imagick->clear();
        // width less
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_OUTER,
            'width'      => 150,
            'height'     => 100,
            'reduceOnly' => true,
        ]);
        $this->assertSame($imagick->getImageWidth(), 150);
        $this->assertSame($imagick->getImageHeight(), 100);
        $imagick->clear();
        // height less
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_OUTER,
            'width'      => 50,
            'height'     => 200,
            'reduceOnly' => true,
        ]);
        $this->assertSame($imagick->getImageWidth(), 50);
        $this->assertSame($imagick->getImageHeight(), 200);
        $imagick->clear();
        // not less
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_OUTER,
            'width'      => 50,
            'height'     => 100,
            'reduceOnly' => true,
        ]);
        $this->assertSame($imagick->getImageWidth(), 50);
        $this->assertSame($imagick->getImageHeight(), 100);
        $imagick->clear();
        // both size less, reduceOnly off
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_OUTER,
            'width'      => 150,
            'height'     => 200,
            'reduceOnly' => false,
        ]);
        $this->assertSame($imagick->getImageWidth(), 150);
        $this->assertSame($imagick->getImageHeight(), 200);
        $imagick->clear();
        // width less, reduceOnly off
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_OUTER,
            'width'      => 150,
            'height'     => 100,
            'reduceOnly' => false,
        ]);
        $this->assertSame($imagick->getImageWidth(), 150);
        $this->assertSame($imagick->getImageHeight(), 100);
        $imagick->clear();
        // height less, reduceOnly off
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_OUTER,
            'width'      => 50,
            'height'     => 200,
            'reduceOnly' => false,
        ]);
        $this->assertSame($imagick->getImageWidth(), 50);
        $this->assertSame($imagick->getImageHeight(), 200);
        $imagick->clear();
        // not less, reduceOnly off
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_OUTER,
            'width'      => 50,
            'height'     => 100,
            'reduceOnly' => false,
        ]);
        $this->assertSame($imagick->getImageWidth(), 50);
        $this->assertSame($imagick->getImageHeight(), 100);
        $imagick->clear();
    }

    /**
     * @throws ImagickException
     * @throws Sampler\Exception
     */
    public function testReduceOnlyWithMaximumFitWorks(): void
    {
        $sampler = new Sampler();
        $file    = dirname(__FILE__) . '/_files/Towers_Schiphol_small.jpg';
        $imagick = new Imagick();
        // both size less
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_MAXIMUM,
            'width'      => 150,
            'height'     => 200,
            'reduceOnly' => true,
        ]);
        $this->assertSame($imagick->getImageWidth(), 101);
        $this->assertSame($imagick->getImageHeight(), 149);
        $imagick->clear();
        // width less
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_MAXIMUM,
            'width'      => 150,
            'height'     => 100,
            'reduceOnly' => true,
        ]);
        $this->assertSame($imagick->getImageWidth(), 68);
        $this->assertSame($imagick->getImageHeight(), 100);
        $imagick->clear();
        // height less
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_MAXIMUM,
            'width'      => 50,
            'height'     => 200,
            'reduceOnly' => true,
        ]);
        $this->assertSame($imagick->getImageWidth(), 50);
        $this->assertSame($imagick->getImageHeight(), 74);
        $imagick->clear();
        // not less
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_MAXIMUM,
            'width'      => 50,
            'height'     => 100,
            'reduceOnly' => true,
        ]);
        $this->assertSame($imagick->getImageWidth(), 50);
        $this->assertSame($imagick->getImageHeight(), 74);
        $imagick->clear();
        // both size less, reduceOnly off
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_MAXIMUM,
            'width'      => 150,
            'height'     => 200,
            'reduceOnly' => false,
        ]);
        $this->assertSame($imagick->getImageWidth(), 136);
        $this->assertSame($imagick->getImageHeight(), 200);
        $imagick->clear();
        // width less, reduceOnly off
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_MAXIMUM,
            'width'      => 150,
            'height'     => 100,
            'reduceOnly' => false,
        ]);
        $this->assertSame($imagick->getImageWidth(), 68);
        $this->assertSame($imagick->getImageHeight(), 100);
        $imagick->clear();
        // height less, reduceOnly off
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_MAXIMUM,
            'width'      => 50,
            'height'     => 200,
            'reduceOnly' => false,
        ]);
        $this->assertSame($imagick->getImageWidth(), 50);
        $this->assertSame($imagick->getImageHeight(), 74);
        $imagick->clear();
        // not less, reduceOnly off
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'    => Format::FIT_TYPE_MAXIMUM,
            'width'      => 50,
            'height'     => 100,
            'reduceOnly' => false,
        ]);
        $this->assertSame($imagick->getImageWidth(), 50);
        $this->assertSame($imagick->getImageHeight(), 74);
        $imagick->clear();
    }

    /**
     * @throws ImagickException
     * @throws Sampler\Exception
     */
    public function testReduceOnlyByWidthWorks(): void
    {
        $sampler = new Sampler();
        $file    = dirname(__FILE__) . '/_files/Towers_Schiphol_small.jpg';
        $imagick = new Imagick();
        // width less
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'width'      => 150,
            'reduceOnly' => true,
        ]);
        $this->assertSame($imagick->getImageWidth(), 101);
        $this->assertSame($imagick->getImageHeight(), 149);
        $imagick->clear();
        // not less
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'width'      => 50,
            'reduceOnly' => true,
        ]);
        $this->assertSame($imagick->getImageWidth(), 50);
        $this->assertSame($imagick->getImageHeight(), 74);
        $imagick->clear();
        // width less, reduceOnly off
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'width'      => 150,
            'reduceOnly' => false,
        ]);
        $this->assertSame($imagick->getImageWidth(), 150);
        $this->assertSame($imagick->getImageHeight(), 221);
        $imagick->clear();
        // not less, reduceOnly off
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'width'      => 50,
            'reduceOnly' => false,
        ]);
        $this->assertSame($imagick->getImageWidth(), 50);
        $this->assertSame($imagick->getImageHeight(), 74);
        $imagick->clear();
    }

    /**
     * @throws ImagickException
     * @throws Sampler\Exception
     */
    public function testReduceOnlyByHeightWorks(): void
    {
        $sampler = new Sampler();
        $file    = dirname(__FILE__) . '/_files/Towers_Schiphol_small.jpg';
        $imagick = new Imagick();
        // height less
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'height'     => 200,
            'reduceOnly' => true,
        ]);
        $this->assertSame($imagick->getImageWidth(), 101);
        $this->assertSame($imagick->getImageHeight(), 149);
        $imagick->clear();
        // not less
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'height'     => 100,
            'reduceOnly' => true,
        ]);
        $this->assertSame($imagick->getImageWidth(), 68);
        $this->assertSame($imagick->getImageHeight(), 100);
        $imagick->clear();
        // height less, reduceOnly off
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'height'     => 200,
            'reduceOnly' => false,
        ]);
        $this->assertSame($imagick->getImageWidth(), 136);
        $this->assertSame($imagick->getImageHeight(), 200);
        $imagick->clear();
        // not less, reduceOnly off
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'height'     => 100,
            'reduceOnly' => false,
        ]);
        $this->assertSame($imagick->getImageWidth(), 68);
        $this->assertSame($imagick->getImageHeight(), 100);
        $imagick->clear();
    }

    /**
     * @throws ImagickException
     * @throws Sampler\Exception
     */
    public function testAnimationPreservedDueResample(): void
    {
        $file = dirname(__FILE__) . '/_files/icon-animation.gif';

        $imagick = new Imagick();
        $imagick->readImage($file);

        $sampler = new Sampler();

        $imagick = $sampler->convertImagick($imagick, null, [
            'fitType' => 0,
            'width'   => 200,
            'height'  => 200,
        ]);

        $this->assertGreaterThan(1, $imagick->getNumberImages());

        $this->assertSame($imagick->getImageWidth(), 200);
        $this->assertSame($imagick->getImageHeight(), 200);

        $imagick->clear();
    }

    /**
     * @throws ImagickException
     * @throws Sampler\Exception
     */
    public function testResizeGif(): void
    {
        $file = dirname(__FILE__) . '/_files/rudolp-jumping-rope.gif';

        $imagick = new Imagick();
        $imagick->readImage($file);

        $sampler = new Sampler();

        $imagick = $sampler->convertImagick($imagick, null, [
            'fitType'    => 0,
            'width'      => 80,
            'height'     => 80,
            'background' => 'transparent',
        ]);

        $this->assertGreaterThan(1, $imagick->getNumberImages());

        $this->assertSame($imagick->getImageWidth(), 80);
        $this->assertSame($imagick->getImageHeight(), 80);

        $imagick->clear();
    }

    /**
     * @throws ImagickException
     * @throws Sampler\Exception
     */
    public function testResizeGifWithProportionsConstraints(): void
    {
        $file = dirname(__FILE__) . '/_files/rudolp-jumping-rope.gif';

        $imagick = new Imagick();
        $imagick->readImage($file);

        $sampler = new Sampler();

        $imagick = $sampler->convertImagick($imagick, null, [
            'fitType'    => 0,
            'width'      => 456,
            'background' => '',
            'widest'     => 16 / 9,
            'highest'    => 9 / 16,
            'reduceOnly' => true,
        ]);

        $this->assertGreaterThan(1, $imagick->getNumberImages());

        $this->assertSame($imagick->getImageWidth(), 456);
        $this->assertSame($imagick->getImageHeight(), 342);

        $imagick->clear();
    }

    /**
     * @throws ImagickException
     * @throws Sampler\Exception
     */
    public function testVerticalProportional(): void
    {
        $sampler = new Sampler();
        $file    = dirname(__FILE__) . '/_files/mazda3_sedan_us-spec_11.jpg';
        $imagick = new Imagick();
        // both size less
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'          => Format::FIT_TYPE_INNER,
            'width'            => 200,
            'height'           => 200,
            'reduceOnly'       => true,
            'proportionalCrop' => true,
        ]);
        $this->assertSame($imagick->getImageWidth(), 200);
        $this->assertSame($imagick->getImageHeight(), 200);
        $imagick->clear();
    }

    /**
     * @throws ImagickException
     * @throws Sampler\Exception
     */
    public function testHorizonalProportional(): void
    {
        $sampler = new Sampler();
        $file    = dirname(__FILE__) . '/_files/mazda3_sedan_us-spec_11.jpg';
        $imagick = new Imagick();
        // both size less
        $imagick->readImage($file); //101x149
        $sampler->convertImagick($imagick, null, [
            'fitType'          => Format::FIT_TYPE_INNER,
            'width'            => 400,
            'height'           => 200,
            'reduceOnly'       => true,
            'proportionalCrop' => true,
        ]);
        $this->assertSame($imagick->getImageWidth(), 400);
        $this->assertSame($imagick->getImageHeight(), 200);
        $imagick->clear();
    }
}
