<?php

declare(strict_types=1);

namespace Autowp\Image\Storage\NamingStrategy;

use Autowp\Image\Storage\Exception;
use Autowp\ZFComponents\Filter\FilenameSafe;

use function array_merge;
use function floor;
use function sprintf;
use function strlen;

use const DIRECTORY_SEPARATOR;

class Serial extends AbstractStrategy
{
    private const ITEM_PER_DIR = 1000;

    private int $deep = 0;

    /**
     * @throws Exception
     */
    public function setDeep(int $deep): self
    {
        if ($deep < 0) {
            throw new Exception("Deep cannot be < 0");
        }
        $this->deep = $deep;

        return $this;
    }

    public function getDeep(): int
    {
        return $this->deep;
    }

    /**
     * Return the complete directory path of a filename (including hashedDirectoryStructure)
     */
    private function path(int $index, int $deep): string
    {
        $chars = strlen((string) (self::ITEM_PER_DIR - 1)); // use log10, fkn n00b
        $path  = '';
        if ($deep > 0) {
            $cur = floor($index / self::ITEM_PER_DIR);
            for ($i = 0; $i < $deep; $i++) {
                $div  = floor($cur / self::ITEM_PER_DIR);
                $mod  = $cur - $div * self::ITEM_PER_DIR;
                $path = sprintf('%0' . $chars . 'd', $mod) . DIRECTORY_SEPARATOR . $path;
                $cur  = $div;
            }
        }
        return $path;
    }

    /**
     * @see AbstractStrategy::generate()
     */
    public function generate(array $options = []): string
    {
        $defaults = [
            'extension'     => null,
            'count'         => null,
            'prefferedName' => null,
            'index'         => null,
        ];
        $options  = array_merge($defaults, $options);

        $count = (int) $options['count'];
        $ext   = (string) $options['extension'];
        $index = (int) $options['index'];

        $fileIndex = $count + 1;

        $dirPath = $this->path($fileIndex, $this->deep);

        $filter = new FilenameSafe();

        $fileBasename = $fileIndex;
        if ($options['prefferedName']) {
            $fileBasename = $filter->filter($options['prefferedName']);
        }

        $suffix   = $index ? '_' . $index : '';
        $filename = $fileBasename . $suffix . ($ext ? '.' . $ext : '');

        return $dirPath . $filename;
    }
}
