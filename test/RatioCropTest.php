<?php

namespace AutowpTest\Image;

use Autowp\Image\Sampler;

use Imagick;

/**
 * @group Autowp_Image
 */
class RatioCropTest extends \PHPUnit_Framework_TestCase
{
    public function testWidest()
    {
        $sampler = new Sampler();
        
        $file = dirname(__FILE__) . '/_files/wide-image.png';
        $imagick = new Imagick();
        // height less
        $imagick->readImage($file); //1000x229

        $sampler->convertImagick($imagick, [
            'widest' => 4/3
        ]);
        $this->assertSame($imagick->getImageWidth(), 305);
        $this->assertSame($imagick->getImageHeight(), 229);
        $imagick->clear();
    }
    
    public function testHighest()
    {
        $sampler = new Sampler();
    
        $file = dirname(__FILE__) . '/_files/Towers_Schiphol_small.jpg';
        $imagick = new Imagick();
        // height less
        $imagick->readImage($file); //101x149
    
        $sampler->convertImagick($imagick, [
            'highest' => 1/1
        ]);
        $this->assertSame($imagick->getImageWidth(), 101);
        $this->assertSame($imagick->getImageHeight(), 101);
        $imagick->clear();
    }
}