<?php

namespace AutowpTest\Image;

use Autowp\Image\Storage\Exception;
use Autowp\Image\Storage\NamingStrategy\Pattern;
use PHPUnit\Framework\TestCase;

/**
 * @group Autowp_Image
 */
class NamingStrategyTest extends TestCase
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
     * @param string $pattern
     * @param string $result
     * @param string $extension
     * @throws Exception
     *
     * @dataProvider patternsProvider
     */
    public function testPatternStrategy(string $pattern, string $result, string $extension)
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
