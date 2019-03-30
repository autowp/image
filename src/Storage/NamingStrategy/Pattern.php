<?php

namespace Autowp\Image\Storage\NamingStrategy;

use Autowp\ZFComponents\Filter\FilenameSafe;

class Pattern extends AbstractStrategy
{
    private static $notAllowedParts = ['.', '..'];

    /**
     * @param string $pattern
     * @return string
     */
    private static function normalizePattern($pattern)
    {
        $filter = new FilenameSafe();

        $result = [];
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
     * @param array $options
     * @see AbstractStrategy::generate()
     *
     * @return string
     */
    public function generate(array $options = [])
    {
        $defaults = [
            'pattern'   => null,
            'extension' => null,
            'index'     => null
        ];
        $options = array_merge($defaults, $options);

        $ext = (string)$options['extension'];
        $pattern = self::normalizePattern($options['pattern']);
        $index = (int)$options['index'];

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
