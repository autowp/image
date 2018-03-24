<?php

namespace AutowpTest\Image;

use Zend\Mvc\Application;

use Autowp\Image;

class StorageTest extends \PHPUnit\Framework\TestCase
{
    const TEST_IMAGE_FILE = __DIR__ . '/_files/Towers_Schiphol_small.jpg';
    const TEST_IMAGE_FILE2 = __DIR__ . '/_files/mazda3_sedan_us-spec_11.jpg';

    public function testAddImageFromFileChangeNameAndDelete()
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $serviceManager = $app->getServiceManager();

        $imageStorage = $serviceManager->get(Image\Storage::class);

        $imageId = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE, 'naming');

        $this->assertNotEmpty($imageId);

        $filePath = $imageStorage->getImageFilepath($imageId);
        $this->assertTrue(file_exists($filePath));
        $this->assertEquals(filesize(self::TEST_IMAGE_FILE), filesize($filePath));

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

    public function testAddImageWithPrefferedName()
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $serviceManager = $app->getServiceManager();

        $imageStorage = $serviceManager->get(Image\Storage::class);

        $imageId = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE, 'test', [
            'prefferedName' => 'zeliboba'
        ]);

        $this->assertNotEmpty($imageId);

        $image = $imageStorage->getImage($imageId);

        $this->assertContains('zeliboba', $image->getSrc());
    }

    public function testIptcAndExif()
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $serviceManager = $app->getServiceManager();

        $imageStorage = $serviceManager->get(Image\Storage::class);

        $blob = file_get_contents(self::TEST_IMAGE_FILE2);

        $imageId = $imageStorage->addImageFromBlob($blob, 'test');

        $this->assertNotEmpty($imageId);

        $iptc = $imageStorage->getImageIPTC($imageId);
        $this->assertNotEmpty($iptc);

        $exif = $imageStorage->getImageEXIF($imageId);
        $this->assertNotEmpty($exif);

        $resolution = $imageStorage->getImageResolution($imageId);
        $this->assertNotEmpty($resolution);
    }

    public function testAddImageAndCrop()
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $serviceManager = $app->getServiceManager();

        $imageStorage = $serviceManager->get(Image\Storage::class);

        $imageId = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE2, 'naming');
        $this->assertNotEmpty($imageId);

        $crop = [
            'left'   => 1024,
            'top'    => 768,
            'width'  => 1020,
            'height' => 500
        ];

        $imageStorage->setImageCrop($imageId, $crop);

        $this->assertEquals($crop, $imageStorage->getImageCrop($imageId));



        $filePath = $imageStorage->getImageFilepath($imageId);
        $this->assertTrue(file_exists($filePath));
        $this->assertEquals(filesize(self::TEST_IMAGE_FILE2), filesize($filePath));

        $formatedImage = $imageStorage->getFormatedImage($imageId, 'picture-gallery');

        $this->assertEquals(1020, $formatedImage->getWidth());
        $this->assertEquals(500, $formatedImage->getHeight());
        $this->assertTrue($formatedImage->getFileSize() > 0);
        $this->assertNotEmpty($formatedImage->getSrc());

        $this->assertContains('0400030003fc01f4', $formatedImage->getSrc());
    }

    public function testFlopNormalizeAndMultipleRequest()
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $serviceManager = $app->getServiceManager();

        $imageStorage = $serviceManager->get(Image\Storage::class);

        $imageId1 = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE, 'naming');

        $this->assertNotEmpty($imageId1);

        $imageStorage->flop($imageId1);

        $imageId2 = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE2, 'naming');

        $this->assertNotEmpty($imageId2);

        $imageStorage->normalize($imageId2);

        $images = $imageStorage->getImages([$imageId1, $imageId2]);

        $this->assertEquals(2, count($images));

        $formatedImages = $imageStorage->getFormatedImages([$imageId1, $imageId2], 'test');

        $this->assertEquals(2, count($formatedImages));

        // re-request
        $formatedImages = $imageStorage->getFormatedImages([$imageId1, $imageId2], 'test');
        $this->assertEquals(2, count($formatedImages));
    }

    public function testGetImageReturnsNull()
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $serviceManager = $app->getServiceManager();

        $imageStorage = $serviceManager->get(Image\Storage::class);

        $result = $imageStorage->getImage(999999999);

        $this->assertNull($result);
    }

    public function testRequestFormatedImageAgain()
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $serviceManager = $app->getServiceManager();

        $imageStorage = $serviceManager->get(Image\Storage::class);

        $imageId = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE2, 'naming');

        $this->assertNotEmpty($imageId);

        $formatName = 'test';

        $formatedImage = $imageStorage->getFormatedImage($imageId, $formatName);

        $this->assertEquals(160, $formatedImage->getWidth());
        $this->assertEquals(120, $formatedImage->getHeight());
        $this->assertTrue($formatedImage->getFileSize() > 0);
        $this->assertNotEmpty($formatedImage->getSrc());

        $formatedImage = $imageStorage->getFormatedImage($imageId, $formatName);

        $this->assertEquals(160, $formatedImage->getWidth());
        $this->assertEquals(120, $formatedImage->getHeight());
        $this->assertTrue($formatedImage->getFileSize() > 0);
        $this->assertNotEmpty($formatedImage->getSrc());
    }

    public function testTimeout()
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $serviceManager = $app->getServiceManager();

        $imageStorage = $serviceManager->get(Image\Storage::class);

        $imageId = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE2, 'naming');

        $this->assertNotEmpty($imageId);

        $formatName = 'picture-gallery';

        $tables = $serviceManager->get('TableManager');
        $formatedImageTable = $tables->get('formated_image');

        $formatedImageTable->insert([
            'format'            => $formatName,
            'image_id'          => $imageId,
            'status'            => \Autowp\Image\Storage::STATUS_PROCESSING,
            'formated_image_id' => null
        ]);

        $formatedImage = $imageStorage->getFormatedImage($imageId, $formatName);

        $this->assertEmpty($formatedImage);
    }

    public function testNormalizeProcessor()
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $serviceManager = $app->getServiceManager();

        $imageStorage = $serviceManager->get(Image\Storage::class);

        $imageId = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE2, 'naming');

        $this->assertNotEmpty($imageId);

        $formatName = 'with-processor';

        $formatedImage = $imageStorage->getFormatedImage($imageId, $formatName);

        $this->assertEquals(160, $formatedImage->getWidth());
        $this->assertEquals(120, $formatedImage->getHeight());
        $this->assertTrue($formatedImage->getFileSize() > 0);
        $this->assertNotEmpty($formatedImage->getSrc());
    }
}
