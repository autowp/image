<?php

namespace Autowp\Image\Storage\NamingStrategy;

use Autowp\Image\Storage\Exception;
use Autowp\Image\Storage\NamingStrategy\AbstractStrategy;
use Autowp\ZFComponents\Filter\FilenameSafe;

class Serial extends AbstractStrategy
{
    const ITEM_PER_DIR = 1000;

    /**
     * @var int
     */
    private $deep = 0;

    /**
     * @param int $deep
     * @throws Exception
     * @return Serial
     */
    public function setDeep($deep)
    {
        $deep = (int)$deep;
        if ($deep < 0) {
            throw new Exception("Deep cannot be < 0");
        }
        $this->deep = $deep;

        return $this;
    }

    /**
     * @return int
     */
    public function getDeep()
    {
        return $this->deep;
    }

    /**
     * Return the complete directory path of a filename (including hashedDirectoryStructure)
     *
     * @param  string $id Cache id
     * @return string Complete directory path
     */
    private function path($index, $deep)
    {
        $chars = strlen(self::ITEM_PER_DIR - 1); // use log10, fkn n00b
        $path = '';
        if ($deep > 0) {
            $cur = floor($index / self::ITEM_PER_DIR);
            for ($i = 0; $i < $deep; $i++) {
                $div = floor($cur / self::ITEM_PER_DIR);
                $mod = $cur - $div * self::ITEM_PER_DIR;
                $path = sprintf('%0'.$chars.'d', $mod) . DIRECTORY_SEPARATOR . $path;
                $cur = $div;
            }
        }
        return $path;
    }

    /**
     * @param string $dir
     * @param array $options
     * @see AbstractStrategy::generate()
     */
    public function generate(array $options = [])
    {
        $defaults = [
            'extenstion'    => null,
            'count'         => null,
            'prefferedName' => null,
            'index'         => null
        ];
        $options = array_merge($defaults, $options);

        $count = (int)$options['count'];
        $ext = (string)$options['extension'];
        $index = (int)$options['index'];

        $fileIndex = $count + 1;

        $dirPath = $this->path($fileIndex, $this->deep);

        $filter = new FilenameSafe();

        $fileBasename = $fileIndex;
        if ($options['prefferedName']) {
            $fileBasename = $filter->filter($options['prefferedName']);
        }

        $suffix = $index ? '_' . $index : '';
        $filename = $fileBasename . $suffix . ($ext ? '.' . $ext : '');

        return $dirPath . $filename;
    }
}
