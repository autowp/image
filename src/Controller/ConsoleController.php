<?php

namespace Autowp\Image\Controller;

use Zend\Console\ColorInterface;
use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Autowp\Image;

class ConsoleController extends AbstractActionController
{
    public function listDirsAction()
    {
        $this->imageStorage()->

        $dirs = $this->imageStorage()->getDirs();

        $console = Console::getInstance();

        foreach ($dirs as $name => $dir) {
            $console->writeLine($name . ': ' . $dir->getPath());
        }
    }

    public function flushFormatAction()
    {
        $format = $this->params('format');

        $this->imageStorage()->flush([
            'format' => $format
        ]);

        Console::getInstance()->writeLine("done", ColorInterface::GREEN);
    }

    public function flushImageAction()
    {
        $imageId = (int)$this->params('image');

        if (! $imageId) {
            throw new \InvalidArgumentException("image id not provided");
        }

        $this->imageStorage()->flush([
            'image' => $imageId
        ]);

        Console::getInstance()->writeLine("done", ColorInterface::GREEN);
    }

    public function listBrokenFilesAction()
    {
        $this->imageStorage()->printBrokenFiles();

        Console::getInstance()->writeLine("done", ColorInterface::GREEN);
    }

    public function fixBrokenFilesAction()
    {
        $this->imageStorage()->fixBrokenFiles();

        Console::getInstance()->writeLine("done", ColorInterface::GREEN);
    }

    public function deleteBrokenFilesAction()
    {
        $dirname = $this->params('dirname');

        $this->imageStorage()->deleteBrokenFiles($dirname);

        Console::getInstance()->writeLine("done", ColorInterface::GREEN);
    }

    public function clearEmptyDirsAction()
    {
        $dirname = $this->params('dirname');

        Console::getInstance()->writeLine("Clear `$dirname`");
        $dir = $this->imageStorage()->getDir($dirname);
        if (! $dir) {
            throw new \InvalidArgumentException("Dir '$dirname' not found");
        }

        $this->recursiveDirectory(realpath($dir->getPath()));

        Console::getInstance()->writeLine("done", ColorInterface::GREEN);
    }

    private function recursiveDirectory($dir)
    {
        $stack[] = $dir;

        while ($stack) {
            $currentDir = array_pop($stack);

            if ($dh = opendir($currentDir)) {
                $count = 0;
                while (($file = readdir($dh)) !== false) {
                    if ($file !== '.' and $file !== '..') {
                        $count++;
                        $currentFile = $currentDir . DIRECTORY_SEPARATOR . $file;
                        if (is_dir($currentFile)) {
                            $stack[] = $currentFile;
                        }
                    }
                }

                if ($count <= 0) {
                    Console::getInstance()->writeLine($currentDir . ' - empty');
                    rmdir($currentDir);
                }
            }
        }
    }
}
