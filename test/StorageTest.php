<?php

namespace AutowpTest\Image;

use Zend\Mvc\Application;

use Autowp\Image;

class StorageTest extends \PHPUnit_Framework_TestCase
{
    const TEST_IMAGE_FILE = __DIR__ . '/_files/Towers_Schiphol_small.jpg';
    
    public function testAddImageFromFileChangeNameAndDelete()
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');
        
        $serviceManager = $app->getServiceManager();
        
        $imageStorage = $serviceManager->get(Image\Storage::class);
        
        $imageId = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE, 'naming');
        
        $this->assertNotEmpty($imageId);
        
        $imageStorage->changeImageName($imageId, [
            'pattern' => 'new-name/by-pattern'
        ]);
        
        $imageStorage->removeImage($imageId);
        
        $result = $imageStorage->getImage($imageId);
        
        $this->assertNull($result);
    }
    
    public function testAddImageFromBlobAndFormat()
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');
    
        $serviceManager = $app->getServiceManager();
    
        $imageStorage = $serviceManager->get(Image\Storage::class);
        
        $blob = file_get_contents(self::TEST_IMAGE_FILE);
    
        $imageId = $imageStorage->addImageFromBlob($blob, 'test');
    
        $this->assertNotEmpty($imageId);
        
        $formatedImage = $imageStorage->getFormatedImage($imageId, 'test');
        
        $this->assertEquals(160, $formatedImage->getWidth());
        $this->assertEquals(120, $formatedImage->getHeight());
        $this->assertTrue($formatedImage->getFileSize() > 0);
        $this->assertNotEmpty($formatedImage->getSrc());
    }
}

