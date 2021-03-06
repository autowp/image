<?php

declare(strict_types=1);

namespace AutowpTest;

use Autowp\Image\Sampler;
use Imagick;
use ImagickException;
use PHPUnit\Framework\TestCase;

use function dirname;

/**
 * @group Autowp_Image
 */
class RatioCropTest extends TestCase
{
    /**
     * @throws Sampler\Exception
     * @throws ImagickException
     */
    public function testWidest()
    {
        $sampler = new Sampler();

        $file    = dirname(__FILE__) . '/_files/wide-image.png';
        $imagick = new Imagick();
        // height less
        $imagick->readImage($file); //1000x229

        $imagick = $sampler->convertImagick($imagick, null, [
            'widest' => 4 / 3,
        ]);
        $this->assertSame($imagick->getImageWidth(), 305);
        $this->assertSame($imagick->getImageHeight(), 229);
        $imagick->clear();
    }

    /**
     * @throws ImagickException
     * @throws Sampler\Exception
     */
    public function testHighest()
    {
        $sampler = new Sampler();

        $file    = dirname(__FILE__) . '/_files/Towers_Schiphol_small.jpg';
        $imagick = new Imagick();
        // height less
        $imagick->readImage($file); //101x149

        $imagick = $sampler->convertImagick($imagick, null, [
            'highest' => 1 / 1,
        ]);
        $this->assertSame($imagick->getImageWidth(), 101);
        $this->assertSame($imagick->getImageHeight(), 101);
        $imagick->clear();
    }
}
