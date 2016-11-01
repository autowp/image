<?php

namespace Autowp\Image\Storage\NamingStrategy;

use Autowp\Image\Storage\Exception;
use Autowp\Image\Storage\NamingStrategy\AbstractStrategy;
use Autowp\ZFComponents\Filter\FilenameSafe;

class Serial
    extends AbstractStrategy
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
            for ($i=0 ; $i < $deep ; $i++) {
                $div = floor($cur / self::ITEM_PER_DIR);
                $mod = $cur - $div * self::ITEM_PER_DIR;
                $path = sprintf('%0'.$chars.'d', $mod) . DIRECTORY_SEPARATOR . $path;
                $cur = $div;
                //$root = $root . substr($hash, 0, $i + 1) . DIRECTORY_SEPARATOR;
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
        ];
        $options = array_merge($defaults, $options);

        $count = (int)$options['count'];
        $ext = (string)$options['extension'];

        $index = $count + 1;

        $dir = $this->getDir();
        if (!$dir) {
            throw new Exception("`dir` not initialized");
        }

        $dirPath = $this->path($index, $this->deep);

        $filter = new FilenameSafe();

        if ($options['prefferedName']) {
            $fileBasename = $filter->filter($options['prefferedName']);
        } else {
            $fileBasename = $index;
        }

        $idx = 0;
        do {
            $suffix = $idx ? '_' . $idx : '';
            $filename = $fileBasename . $suffix . ($ext ? '.' . $ext : '');
            $filePath = $dir . DIRECTORY_SEPARATOR . $dirPath . $filename;
            $idx++;
        } while (file_exists($filePath));

        return $dirPath . $filename;
    }
}
