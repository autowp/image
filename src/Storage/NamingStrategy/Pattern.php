<?php

declare(strict_types=1);

namespace Autowp\Image\Storage\NamingStrategy;

use Autowp\ZFComponents\Filter\FilenameSafe;

use function array_merge;
use function implode;
use function in_array;
use function preg_split;

class Pattern extends AbstractStrategy
{
    private static array $notAllowedParts = ['.', '..'];

    private static function normalizePattern(string $pattern): string
    {
        $filter = new FilenameSafe();

        $result            = [];
        $patternComponents = preg_split('|[\\/]+|isu', $pattern);
        foreach ($patternComponents as $component) {
            if (! in_array($component, self::$notAllowedParts) && $component) {
                $filtered = $filter->filter($component);
                $result[] = $filtered;
            }
        }

        return implode('/', $result);
    }

    /**
     * @see AbstractStrategy::generate()
     */
    public function generate(array $options = []): string
    {
        $defaults = [
            'pattern'   => '',
            'extension' => null,
            'index'     => null,
        ];
        $options  = array_merge($defaults, $options);

        $ext     = (string) $options['extension'];
        $pattern = self::normalizePattern($options['pattern']);
        $index   = (int) $options['index'];

        $nameComponents = [];
        if ($pattern) {
            $nameComponents[] = $pattern;
        }
        if ($index || (! $pattern)) {
            $nameComponents[] = $index;
        }
        return implode('_', $nameComponents) . ($ext ? '.' . $ext : '');
    }
}
