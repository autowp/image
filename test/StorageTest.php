<?php

declare(strict_types=1);

namespace AutowpTest;

use Autowp\Image\Storage;
use ImagickException;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Application;
use PHPUnit\Framework\TestCase;

use function count;
use function file_get_contents;
use function filesize;
use function strlen;

class StorageTest extends TestCase
{
    private const TEST_IMAGE_FILE  = __DIR__ . '/_files/Towers_Schiphol_small.jpg';
    private const TEST_IMAGE_FILE2 = __DIR__ . '/_files/mazda3_sedan_us-spec_11.jpg';

    private function getImageStorage(Application $app): Storage
    {
        $serviceManager = $app->getServiceManager();

        return $serviceManager->get(Storage::class);
    }

    /**
     * @throws Storage\Exception
     */
    public function testAddImageFromFileChangeNameAndDelete(): void
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $imageStorage = $this->getImageStorage($app);

        $imageId = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE, 'naming');

        $this->assertNotEmpty($imageId);

        $imageInfo = $imageStorage->getImage($imageId);
        $this->assertEquals(filesize(self::TEST_IMAGE_FILE), $imageInfo->toArray()['filesize']);

        $blob = $imageStorage->getImageBlob($imageId);
        $this->assertStringEqualsFile(self::TEST_IMAGE_FILE, $blob);

        $imageStorage->changeImageName($imageId, [
            'pattern' => 'new-name/by-pattern',
        ]);

        $imageStorage->removeImage($imageId);

        $result = $imageStorage->getImage($imageId);

        $this->assertNull($result);
    }

    /**
     * @throws Storage\Exception
     * @throws ImagickException
     */
    public function testAddImageFromBlobAndFormat(): void
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $imageStorage = $this->getImageStorage($app);

        $blob = file_get_contents(self::TEST_IMAGE_FILE);

        $imageId = $imageStorage->addImageFromBlob($blob, 'test');

        $this->assertNotEmpty($imageId);

        $formatedImage = $imageStorage->getFormatedImage($imageId, 'test');
        $this->assertEquals(160, $formatedImage->getWidth());
        $this->assertEquals(120, $formatedImage->getHeight());
        $this->assertTrue($formatedImage->getFileSize() > 0);
        $this->assertNotEmpty($formatedImage->getSrc());
    }

    /**
     * @throws Storage\Exception
     */
    public function testAddImageWithPrefferedName(): void
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $imageStorage = $this->getImageStorage($app);

        $imageId = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE, 'test', [
            'prefferedName' => 'zeliboba',
        ]);

        $this->assertNotEmpty($imageId);

        $image = $imageStorage->getImage($imageId);

        $this->assertStringContainsString('zeliboba', $image->getSrc());
    }

    /**
     * @throws ImagickException
     * @throws Storage\Exception
     */
    public function testIptcAndExif(): void
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $imageStorage = $this->getImageStorage($app);

        $blob = file_get_contents(self::TEST_IMAGE_FILE2);

        $imageId = $imageStorage->addImageFromBlob($blob, 'test');

        $this->assertNotEmpty($imageId);

        $exif = $imageStorage->getImageEXIF($imageId);
        $this->assertNotEmpty($exif);
        $this->assertEquals('Adobe Photoshop CS3 Macintosh', $exif['IFD0']['Software']);

        $resolution = $imageStorage->getImageResolution($imageId);
        $this->assertNotEmpty($resolution);
    }

    /**
     * @throws ImagickException
     * @throws Storage\Exception
     */
    public function testAddImageAndCrop(): void
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $imageStorage = $this->getImageStorage($app);

        $imageId = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE2, 'naming');
        $this->assertNotEmpty($imageId);

        $crop = [
            'left'   => 1024,
            'top'    => 768,
            'width'  => 1020,
            'height' => 500,
        ];

        $imageStorage->setImageCrop($imageId, $crop);

        $this->assertEquals($crop, $imageStorage->getImageCrop($imageId));

        $fileContents = $imageStorage->getImageBlob($imageId);
        $this->assertEquals(filesize(self::TEST_IMAGE_FILE2), strlen($fileContents));

        $formatedImage = $imageStorage->getFormatedImage($imageId, 'picture-gallery');

        $this->assertEquals(1020, $formatedImage->getWidth());
        $this->assertEquals(500, $formatedImage->getHeight());
        $this->assertTrue($formatedImage->getFileSize() > 0);
        $this->assertNotEmpty($formatedImage->getSrc());

        $this->assertStringContainsString('0400030003fc01f4', $formatedImage->getSrc());
    }

    /**
     * @throws ImagickException
     * @throws Storage\Exception
     */
    public function testFlopNormalizeAndMultipleRequest(): void
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $imageStorage = $this->getImageStorage($app);

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

    /**
     * @throws Storage\Exception
     */
    public function testGetImageReturnsNull(): void
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $imageStorage = $this->getImageStorage($app);

        $result = $imageStorage->getImage(999999999);

        $this->assertNull($result);
    }

    /**
     * @throws ImagickException
     * @throws Storage\Exception
     */
    public function testRequestFormatedImageAgain(): void
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $imageStorage = $this->getImageStorage($app);

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

    /**
     * @throws ImagickException
     * @throws Storage\Exception
     */
    public function testTimeout(): void
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $serviceManager = $app->getServiceManager();

        $imageStorage = $this->getImageStorage($app);

        $imageId = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE2, 'naming');

        $this->assertNotEmpty($imageId);

        $formatName = 'picture-gallery';

        $tables = $serviceManager->get('TableManager');
        /** @var TableGateway $formatedImageTable */
        $formatedImageTable = $tables->get('formated_image');

        $formatedImageTable->insert([
            'format'            => $formatName,
            'image_id'          => $imageId,
            'status'            => Storage::STATUS_PROCESSING,
            'formated_image_id' => null,
        ]);

        $formatedImage = $imageStorage->getFormatedImage($imageId, $formatName);

        $this->assertEmpty($formatedImage);
    }

    /**
     * @throws ImagickException
     * @throws Storage\Exception
     */
    public function testNormalizeProcessor(): void
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $imageStorage = $this->getImageStorage($app);

        $imageId = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE2, 'naming');

        $this->assertNotEmpty($imageId);

        $formatName = 'with-processor';

        $formatedImage = $imageStorage->getFormatedImage($imageId, $formatName);

        $this->assertEquals(160, $formatedImage->getWidth());
        $this->assertEquals(120, $formatedImage->getHeight());
        $this->assertTrue($formatedImage->getFileSize() > 0);
        $this->assertNotEmpty($formatedImage->getSrc());
    }

    /**
     * @throws Storage\Exception
     */
    public function testExtractAllEXIF(): void
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $imageStorage = $this->getImageStorage($app);

        $imageStorage->extractAllEXIF('test');

        $this->assertTrue(true);
    }

    public function testSrcOverride(): void
    {
        $app = Application::init(require __DIR__ . '/_files/config/application.config.php');

        $imageStorage = $this->getImageStorage($app);
        $imageStorage->setSrcOverride([
            'host'   => 'example.com',
            'port'   => '8888',
            'scheme' => 'https',
        ]);

        $imageId = $imageStorage->addImageFromFile(self::TEST_IMAGE_FILE2, 'naming');

        $this->assertNotEmpty($imageId);

        $image = $imageStorage->getImage($imageId);

        $this->assertStringContainsString('https://example.com:8888', $image->getSrc());
    }
}
