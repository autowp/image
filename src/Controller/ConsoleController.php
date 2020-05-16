<?php

declare(strict_types=1);

namespace Autowp\Image\Controller;

use Autowp\Image\Storage;
use InvalidArgumentException;
use Laminas\Console\ColorInterface;
use Laminas\Console\Console;
use Laminas\Mvc\Controller\AbstractActionController;

use function array_keys;

/**
 * @method Storage imageStorage()
 */
class ConsoleController extends AbstractActionController
{
    public function listDirsAction(): void
    {
        $console = Console::getInstance();

        foreach (array_keys($this->imageStorage()->getDirs()) as $name) {
            $console->writeLine($name);
        }
    }

    /**
     * @throws Storage\Exception
     */
    public function flushFormatAction(): void
    {
        $format = $this->params('format');

        $this->imageStorage()->flush([
            'format' => $format,
        ]);

        Console::getInstance()->writeLine("done", ColorInterface::GREEN);
    }

    /**
     * @throws Storage\Exception
     */
    public function flushImageAction(): void
    {
        $imageId = (int) $this->params('image');

        if (! $imageId) {
            throw new InvalidArgumentException("image id not provided");
        }

        $this->imageStorage()->flush([
            'image' => $imageId,
        ]);

        Console::getInstance()->writeLine("done", ColorInterface::GREEN);
    }

    /**
     * @throws Storage\Exception
     */
    public function extractExifAction()
    {
        $dir = (string) $this->params('dirname');

        if (! $dir) {
            throw new InvalidArgumentException("dir not provided");
        }

        $this->imageStorage()->extractAllEXIF($dir);

        Console::getInstance()->writeLine("done", ColorInterface::GREEN);
    }
}
