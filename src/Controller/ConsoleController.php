<?php

namespace Autowp\Image\Controller;

use InvalidArgumentException;

use Zend\Console\ColorInterface;
use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Autowp\Image\Storage;

/**
 * Class ConsoleController
 * @package Autowp\Image\Controller
 *
 * @method Storage imageStorage()
 */
class ConsoleController extends AbstractActionController
{
    public function listDirsAction(): void
    {
        $console = Console::getInstance();

        foreach ($this->imageStorage()->getDirs() as $name => $dir) {
            $console->writeLine($name . ': ' . $dir->getPath());
        }
    }

    /**
     * @throws Storage\Exception
     */
    public function flushFormatAction(): void
    {
        $format = $this->params('format');

        $this->imageStorage()->flush([
            'format' => $format
        ]);

        Console::getInstance()->writeLine("done", ColorInterface::GREEN);
    }

    /**
     * @throws Storage\Exception
     */
    public function flushImageAction(): void
    {
        $imageId = (int)$this->params('image');

        if (! $imageId) {
            throw new InvalidArgumentException("image id not provided");
        }

        $this->imageStorage()->flush([
            'image' => $imageId
        ]);

        Console::getInstance()->writeLine("done", ColorInterface::GREEN);
    }

    public function listBrokenFilesAction(): void
    {
        $this->imageStorage()->printBrokenFiles();

        Console::getInstance()->writeLine("done", ColorInterface::GREEN);
    }

    /**
     * @throws Storage\Exception
     */
    public function fixBrokenFilesAction(): void
    {
        $this->imageStorage()->fixBrokenFiles();

        Console::getInstance()->writeLine("done", ColorInterface::GREEN);
    }

    /**
     * @throws Storage\Exception
     */
    public function deleteBrokenFilesAction(): void
    {
        $dirname = $this->params('dirname');

        $this->imageStorage()->deleteBrokenFiles($dirname);

        Console::getInstance()->writeLine("done", ColorInterface::GREEN);
    }

    public function clearEmptyDirsAction(): void
    {
        $dirname = $this->params('dirname');

        Console::getInstance()->writeLine("Clear `$dirname`");
        $dir = $this->imageStorage()->getDir($dirname);
        if (! $dir) {
            throw new InvalidArgumentException("Dir '$dirname' not found");
        }

        $this->recursiveDirectory(realpath($dir->getPath()));

        Console::getInstance()->writeLine("done", ColorInterface::GREEN);
    }

    private function recursiveDirectory(string $dir): void
    {
        $stack[] = $dir;

        while ($stack) {
            $currentDir = array_pop($stack);

            if ($dh = opendir($currentDir)) {
                $count = 0;
                while (($file = readdir($dh)) !== false) {
                    if ($file !== '.' && $file !== '..') {
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
