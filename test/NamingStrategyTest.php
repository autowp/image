<?php

namespace AutowpTest\Image;

use Autowp\Image\Storage\NamingStrategy\Pattern;

/**
 * @group Autowp_Image
 */
class NamingStrategyTest extends \PHPUnit_Framework_TestCase
{
    public static function patternsProvider()
    {
        return [
            ['',                   '0.jpg',         'jpg'],
            ['just.test',          'just.test.jpg', 'jpg'],
            ['./test/./test/.',    'test/test.jpg', 'jpg'],
            ['../test/../test/..', 'test/test.jpg', 'jpg'],
            ['../test////test/..', 'test/test.jpg', 'jpg'],
        ];
    }

    /**
     * @dataProvider patternsProvider
     */
    public function testPatternStrategy($pattern, $result, $extension)
    {
        $strategy = new Pattern([
            'dir' => sys_get_temp_dir()
        ]);
        $generated = $strategy->generate([
            'pattern'   => $pattern,
            'extension' => $extension
        ]);
        $this->assertSame($result, $generated);
    }
}
