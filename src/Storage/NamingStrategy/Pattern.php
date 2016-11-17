<?php

namespace Autowp\Image\Storage\NamingStrategy;

use Autowp\Image\Storage\NamingStrategy\AbstractStrategy;
use Autowp\Image\Storage\Exception;
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
            if (! in_array($component, self::$notAllowedParts)) {
                if ($component) {
                    $filtered = $filter->filter($component);
                    $result[] = $filtered;
                }
            }
        }

        return implode('/', $result);
    }

    /**
     * @param string $dir
     * @param array $options
     * @see AbstractStrategy::generate()
     */
    public function generate(array $options = [])
    {
        $defaults = [
            'pattern'   => null,
            'extension' => null
        ];
        $options = array_merge($defaults, $options);

        $ext = (string)$options['extension'];
        $pattern = self::normalizePattern($options['pattern']);

        $dir = $this->getDir();
        if (! $dir) {
            throw new Exception("`dir` not initialized");
        }

        $idx = 0;
        do {
            $nameComponents = [];
            if ($pattern) {
                $nameComponents[] = $pattern;
            }
            if ($idx or (! $pattern)) {
                $nameComponents[] = $idx;
            }
            $filename = implode('_', $nameComponents) . ($ext ? '.' . $ext : '');
            $filePath = $dir . DIRECTORY_SEPARATOR . $filename;
            $idx++;
        } while (file_exists($filePath));

        return $filename;
    }
}
