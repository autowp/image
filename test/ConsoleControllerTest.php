<?php

declare(strict_types=1);

namespace AutowpTest\Image;

use Autowp\Image\Controller\ConsoleController;
use Exception;
use Laminas\Test\PHPUnit\Controller\AbstractConsoleControllerTestCase;

class ConsoleControllerTest extends AbstractConsoleControllerTestCase
{
    /**
     * @throws Exception
     */
    public function testListDirs(): void
    {
        $this->setApplicationConfig(include __DIR__ . '/_files/config/application.config.php');

        $this->dispatch('image-storage list-dirs');

        $this->assertModuleName('autowp');
        $this->assertControllerName(ConsoleController::class);
        $this->assertMatchedRouteName('cron');
        $this->assertActionName('daily-maintenance');
    }
}
