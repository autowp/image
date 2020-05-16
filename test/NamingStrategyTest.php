<?php

declare(strict_types=1);

namespace AutowpTest;

use Autowp\Image\Storage\Exception;
use Autowp\Image\Storage\NamingStrategy\Pattern;
use PHPUnit\Framework\TestCase;

/**
 * @group Autowp_Image
 */
class NamingStrategyTest extends TestCase
{
    public static function patternsProvider(): array
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
     * @throws Exception
     * @dataProvider patternsProvider
     */
    public function testPatternStrategy(string $pattern, string $result, string $extension)
    {
        $strategy  = new Pattern([]);
        $generated = $strategy->generate([
            'pattern'   => $pattern,
            'extension' => $extension,
        ]);
        $this->assertSame($result, $generated);
    }
}
