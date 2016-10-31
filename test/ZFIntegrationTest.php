<?php

namespace AutowpTest\Image;

/**
 * @group Autowp_Image
 */
class ZFIntegrationTest extends \PHPUnit_Framework_TestCase
{
    public function testViewHelperRegistered()
    {
        $app = \Zend\Mvc\Application::init(require __DIR__ . '/_files/config/application.config.php');
        
        $serviceManager = $app->getServiceManager();
        
        $view = $serviceManager->get('ViewRenderer');
        
        $imageStorage = $view->imageStorage();
        
        $this->assertInstanceOf(\Autowp\Image\Storage::class, $imageStorage);
    }

    public function testControllerPluginRegistered()
    {
        $app = \Zend\Mvc\Application::init(require __DIR__ . '/_files/config/application.config.php');
        
        $serviceManager = $app->getServiceManager();
        
        $pluginManager = $serviceManager->get('ControllerPluginManager');
        
        $imageStorage = $pluginManager->get('imageStorage');
        
        $this->assertInstanceOf(\Autowp\Image\Storage::class, $imageStorage());
    }
}
