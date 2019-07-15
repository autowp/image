<?php

namespace AutowpTest\Image;

use ImagickException;

use PHPUnit\Framework\TestCase;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Application;

use Autowp\Image\Storage;

class StorageS3Test extends TestCase
{
    const TEST_IMAGE_FILE = __DIR__ . '/_files/Towers_Schiphol_small.jpg';
    const TEST_IMAGE_FILE2 = __DIR__ . '/_files/mazda3_sedan_us-spec_11.jpg';

    private function getImageStorage(Application $app): Storage
    {
        $serviceManager = $app->getServiceManager();

        return $serviceManager->get(Storage::class);
    }

    /**
     * @group S3
     * @throws Storage\Exception
     */
    public function testS3AddImageFromFileChangeNameAndDelete2()
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $imageStorage = $this->getImageStorage($app);

        $imageId = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE, 'naming', [
            's3'      => true,
            'pattern' => 'folder/file'
        ]);

        $this->assertNotEmpty($imageId);

        $imageInfo = $imageStorage->getImage($imageId);

        $this->assertContains('folder/file', $imageInfo->getSrc());

        $blob = file_get_contents($imageInfo->getSrc());
        $this->assertEquals(filesize(self::TEST_IMAGE_FILE), strlen($blob));

        $imageStorage->changeImageName($imageId, [
            'pattern' => 'new-name/by-pattern'
        ]);

        $imageStorage->removeImage($imageId);

        $result = $imageStorage->getImage($imageId);

        $this->assertNull($result);
    }

    /**
     * @group S3
     * @throws Storage\Exception
     * @throws ImagickException
     */
    public function testAddImageFromBlobAndFormat()
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $imageStorage = $this->getImageStorage($app);

        $blob = file_get_contents(self::TEST_IMAGE_FILE);

        $imageId = $imageStorage->addImageFromBlob($blob, 'test', [
            's3' => true
        ]);

        $this->assertNotEmpty($imageId);

        $formatedImage = $imageStorage->getFormatedImage($imageId, 'test');

        $this->assertEquals(160, $formatedImage->getWidth());
        $this->assertEquals(120, $formatedImage->getHeight());
        $this->assertTrue($formatedImage->getFileSize() > 0);
        $this->assertNotEmpty($formatedImage->getSrc());
    }

    /**
     * @group S3
     * @throws Storage\Exception
     */
    public function testS3AddImageWithPrefferedName()
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $imageStorage = $this->getImageStorage($app);

        $imageId = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE, 'test', [
            'prefferedName' => 'zeliboba',
            's3' => true
        ]);

        $this->assertNotEmpty($imageId);

        $image = $imageStorage->getImage($imageId);

        $this->assertContains('zeliboba', $image->getSrc());
    }

    /**
     * @group S3
     * @throws ImagickException
     * @throws Storage\Exception
     */
    public function testAddImageAndCrop()
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $imageStorage = $this->getImageStorage($app);

        $imageId = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE2, 'naming', [
            's3' => true
        ]);
        $this->assertNotEmpty($imageId);

        $crop = [
            'left'   => 1024,
            'top'    => 768,
            'width'  => 1020,
            'height' => 500
        ];

        $imageStorage->setImageCrop($imageId, $crop);

        $this->assertEquals($crop, $imageStorage->getImageCrop($imageId));


        $imageInfo = $imageStorage->getImage($imageId);
        $blob = file_get_contents($imageInfo->getSrc());
        $this->assertEquals(filesize(self::TEST_IMAGE_FILE2), strlen($blob));

        $formatedImage = $imageStorage->getFormatedImage($imageId, 'picture-gallery');

        $this->assertEquals(1020, $formatedImage->getWidth());
        $this->assertEquals(500, $formatedImage->getHeight());
        $this->assertTrue($formatedImage->getFileSize() > 0);
        $this->assertNotEmpty($formatedImage->getSrc());

        $this->assertContains('0400030003fc01f4', $formatedImage->getSrc());
    }

    /**
     * @group S3
     * @throws ImagickException
     * @throws Storage\Exception
     */
    public function testFlopNormalizeAndMultipleRequest()
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $imageStorage = $this->getImageStorage($app);

        $imageId1 = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE, 'naming', [
            's3' => true
        ]);

        $this->assertNotEmpty($imageId1);

        $imageStorage->flop($imageId1);

        $imageId2 = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE2, 'naming', [
            's3' => true
        ]);

        $this->assertNotEmpty($imageId2);

        $imageStorage->normalize($imageId2);

        $images = $imageStorage->getImages([$imageId1, $imageId2]);

        $this->assertEquals(2, count($images));

        $formatedImages = $imageStorage->getFormatedImages([$imageId1, $imageId2], 'test',);

        $this->assertEquals(2, count($formatedImages));

        // re-request
        $formatedImages = $imageStorage->getFormatedImages([$imageId1, $imageId2], 'test');
        $this->assertEquals(2, count($formatedImages));
    }

    /**
     * @group S3
     * @throws ImagickException
     * @throws Storage\Exception
     */
    public function testRequestFormatedImageAgain()
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $imageStorage = $this->getImageStorage($app);

        $imageId = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE2, 'naming', [
            's3' => true
        ]);

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

    /**
     * @group S3
     * @throws ImagickException
     * @throws Storage\Exception
     */
    public function testTimeout()
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $serviceManager = $app->getServiceManager();

        $imageStorage = $this->getImageStorage($app);

        $imageId = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE2, 'naming', [
            's3' => true
        ]);

        $this->assertNotEmpty($imageId);

        $formatName = 'picture-gallery';

        $tables = $serviceManager->get('TableManager');
        /** @var TableGateway $formatedImageTable */
        $formatedImageTable = $tables->get('formated_image');

        $formatedImageTable->insert([
            'format'            => $formatName,
            'image_id'          => $imageId,
            'status'            => Storage::STATUS_PROCESSING,
            'formated_image_id' => null
        ]);

        $formatedImage = $imageStorage->getFormatedImage($imageId, $formatName);

        $this->assertEmpty($formatedImage);
    }

    /**
     * @group S3
     * @throws ImagickException
     * @throws Storage\Exception
     */
    public function testNormalizeProcessor()
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $imageStorage = $this->getImageStorage($app);

        $imageId = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE2, 'naming', [
            's3' => true
        ]);

        $this->assertNotEmpty($imageId);

        $formatName = 'with-processor';

        $formatedImage = $imageStorage->getFormatedImage($imageId, $formatName);

        $this->assertEquals(160, $formatedImage->getWidth());
        $this->assertEquals(120, $formatedImage->getHeight());
        $this->assertTrue($formatedImage->getFileSize() > 0);
        $this->assertNotEmpty($formatedImage->getSrc());
    }
}
