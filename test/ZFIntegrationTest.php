<?php

namespace AutowpTest\Image;

use Autowp\Image\Controller\ConsoleController;
use Autowp\Image\Storage;
use PHPUnit\Framework\TestCase;
use Zend\Mvc\Application;

/**
 * @group Autowp_Image
 */
class ZFIntegrationTest extends TestCase
{
    public function testViewHelperRegistered()
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $serviceManager = $app->getServiceManager();

        $view = $serviceManager->get('ViewRenderer');

        $imageStorage = $view->imageStorage();

        $this->assertInstanceOf(Storage::class, $imageStorage);
    }

    public function testControllerPluginRegistered()
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $serviceManager = $app->getServiceManager();

        $pluginManager = $serviceManager->get('ControllerPluginManager');

        $imageStorage = $pluginManager->get('imageStorage');

        $this->assertInstanceOf(Storage::class, $imageStorage());
    }

    public function testControllerRegistered()
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $serviceManager = $app->getServiceManager();

        $controllerManager = $serviceManager->get('ControllerManager');

        $controller = $controllerManager->get(ConsoleController::class);

        $this->assertInstanceOf(ConsoleController::class, $controller);
    }
}
