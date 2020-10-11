<?php

declare(strict_types=1);

namespace Autowp\Image;

use ArrayObject;
use Aws\S3\S3Client;
use Closure;
use Exception;
use Imagick;
use ImagickException;
use Laminas\Db\ResultSet\ResultSetInterface;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Uri\UriFactory;

use function array_replace;
use function count;
use function exif_read_data;
use function fclose;
use function filesize;
use function fopen;
use function getimagesize;
use function implode;
use function is_array;
use function is_resource;
use function json_decode;
use function json_encode;
use function method_exists;
use function mime_content_type;
use function pathinfo;
use function pow;
use function random_int;
use function round;
use function sleep;
use function sprintf;
use function strlen;
use function strpos;
use function strtolower;
use function ucfirst;

use const DIRECTORY_SEPARATOR;
use const IMAGETYPE_GIF;
use const IMAGETYPE_JPEG;
use const IMAGETYPE_PNG;
use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_THROW_ON_ERROR;
use const PATHINFO_EXTENSION;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class Storage implements StorageInterface
{
    private const EXTENSION_DEFAULT = 'jpg';

    private const INSERT_MAX_ATTEMPTS = 15;

    public const STATUS_DEFAULT    = 0,
                 STATUS_PROCESSING = 1,
                 STATUS_FAILED     = 2;

    private const TIMEOUT = 15;

    private TableGateway $imageTable;

    private string $imageTableName = 'image';

    private TableGateway $formatedImageTable;

    private string $formatedImageTableName = 'formated_image';

    private TableGateway $dirTable;

    private array $dirs = [];

    private array $formats = [];

    private string $formatedImageDirName;

    private Sampler $imageSampler;

    private Processor\ProcessorPluginManager $processors;

    private S3Client $s3;

    private array $s3Options = [];

    private iterable $srcOverride = [];

    /**
     * @throws Storage\Exception
     */
    public function __construct(
        array $options,
        TableGateway $imageTable,
        TableGateway $formatedImageTable,
        TableGateway $dirTable,
        Processor\ProcessorPluginManager $processors
    ) {
        $this->setOptions($options);

        $this->imageTable         = $imageTable;
        $this->formatedImageTable = $formatedImageTable;
        $this->dirTable           = $dirTable;
        $this->processors         = $processors;
    }

    /**
     * @throws Storage\Exception
     */
    public function setOptions(array $options): self
    {
        foreach ($options as $key => $value) {
            $method = 'set' . ucfirst($key);

            if (! method_exists($this, $method)) {
                throw new Storage\Exception("Unexpected option '$key'");
            }

            $this->$method($value);
        }

        return $this;
    }

    public function setSrcOverride(iterable $value): self
    {
        $this->srcOverride = $value;

        return $this;
    }

    public function setS3(array $options): self
    {
        $this->s3Options = $options;

        return $this;
    }

    private function getS3Client(): S3Client
    {
        if (! isset($this->s3)) {
            $this->s3 = new S3Client($this->s3Options);
        }

        return $this->s3;
    }

    public function setImageTableName(string $tableName): self
    {
        $this->imageTableName = $tableName;

        return $this;
    }

    /**
     * @param array|Sampler $options
     * @throws Storage\Exception
     */
    public function setImageSampler($options): self
    {
        if (is_array($options)) {
            $options = new Sampler();
        }

        if (! $options instanceof Sampler) {
            $message = "Unexpected imageSampler options. Array or object expected";
            throw new Storage\Exception($message);
        }

        $this->imageSampler = $options;

        return $this;
    }

    /**
     * @throws Storage\Exception
     */
    public function getImageSampler(): Sampler
    {
        if (! isset($this->imageSampler)) {
            $this->setImageSampler([]);
        }
        return $this->imageSampler;
    }

    public function setFormatedImageTableName(string $tableName): self
    {
        $this->formatedImageTableName = $tableName;

        return $this;
    }

    /**
     * @throws Storage\Exception
     */
    public function setDirs(array $dirs): StorageInterface
    {
        $this->dirs = [];

        foreach ($dirs as $dirName => $dir) {
            $this->addDir($dirName, $dir);
        }

        return $this;
    }

    /**
     * @param Storage\Dir|array $dir
     * @throws Storage\Exception
     */
    public function addDir(string $dirName, $dir): StorageInterface
    {
        if (isset($this->dirs[$dirName])) {
            throw new Storage\Exception("Dir '$dirName' already registered");
        }
        if (! $dir instanceof Storage\Dir) {
            $dir = new Storage\Dir($dir);
        }
        $this->dirs[$dirName] = $dir;

        return $this;
    }

    public function getDir(string $dirName): ?Storage\Dir
    {
        return $this->dirs[$dirName] ?? null;
    }

    /**
     * @return Storage\Dir[]
     */
    public function getDirs(): array
    {
        return $this->dirs;
    }

    /**
     * @throws Sampler\Exception
     * @throws Storage\Exception
     */
    public function setFormats(array $formats): StorageInterface
    {
        $this->formats = [];

        foreach ($formats as $formatName => $format) {
            $this->addFormat($formatName, $format);
        }

        return $this;
    }

    /**
     * @param Sampler\Format|array $format
     * @throws Sampler\Exception
     * @throws Storage\Exception
     */
    public function addFormat(string $formatName, $format): StorageInterface
    {
        if (isset($this->formats[$formatName])) {
            throw new Storage\Exception("Format '$formatName' already registered");
        }
        if (! $format instanceof Sampler\Format) {
            $format = new Sampler\Format($format);
        }
        $this->formats[$formatName] = $format;

        return $this;
    }

    public function getFormat(string $formatName): ?Sampler\Format
    {
        return $this->formats[$formatName] ?? null;
    }

    public function setFormatedImageDirName(string $dirName): self
    {
        $this->formatedImageDirName = $dirName;

        return $this;
    }

    /**
     * @param array|ArrayObject $imageRow
     * @throws Storage\Exception
     */
    private function buildImageResult($imageRow): Storage\Image
    {
        $dir = $this->getDir($imageRow['dir']);
        if (! $dir) {
            throw new Storage\Exception("Dir '{$imageRow['dir']}' not defined");
        }

        $url = $this->getS3Client()->getObjectUrl($dir->getBucket(), $imageRow['filepath']);
        $uri = UriFactory::factory($url);
        foreach ($this->srcOverride as $key => $value) {
            switch ($key) {
                case 'host':
                    $uri->setHost($value);
                    break;
                case 'port':
                    $uri->setPort($value);
                    break;
                case 'scheme':
                    $uri->setScheme($value);
                    break;
            }
        }
        $uri->setHost('127.0.0.1');

        return new Storage\Image([
            'id'       => $imageRow['id'],
            'width'    => $imageRow['width'],
            'height'   => $imageRow['height'],
            'src'      => $url,
            'filesize' => $imageRow['filesize'],
        ]);
    }

    /**
     * @param array|ArrayObject $imageRow
     * @throws Storage\Exception
     */
    private function buildImageBlobResult($imageRow): string
    {
        $dir = $this->getDir($imageRow['dir']);
        if (! $dir) {
            throw new Storage\Exception("Dir '{$imageRow['dir']}' not defined");
        }

        $object = $this->getS3Client()->getObject([
            'Bucket' => $dir->getBucket(),
            'Key'    => $imageRow['filepath'],
        ]);

        return $object['Body']->getContents();
    }

    /**
     * @return array|ArrayObject|null
     * @throws Storage\Exception
     */
    private function getImageRow(int $imageId)
    {
        $imageRow = $this->imageTable->select([
            'id' => $imageId,
        ])->current();

        return $imageRow ? $imageRow : null;
    }

    /**
     * @return array|ArrayObject
     */
    private function getImageRows(array $imageIds)
    {
        $result = [];
        if (count($imageIds)) {
            $rows = $this->imageTable->select([
                new Sql\Predicate\In('id', $imageIds),
            ]);
            foreach ($rows as $row) {
                $result[] = $row;
            }
        }

        return $result;
    }

    /**
     * @throws Storage\Exception
     */
    public function getImage(int $imageId): ?Storage\Image
    {
        $imageRow = $this->getImageRow($imageId);

        return $imageRow ? $this->buildImageResult($imageRow) : null;
    }

    /**
     * @throws Storage\Exception
     */
    public function getImages(array $imageIds): array
    {
        $result = [];
        foreach ($this->getImageRows($imageIds) as $imageRow) {
            $result[$imageRow['id']] = $this->buildImageResult($imageRow);
        }

        return $result;
    }

    /**
     * @return resource|null
     * @throws Storage\Exception
     */
    public function getImageBlobStream(int $imageId)
    {
        $imageRow = $this->getImageRow($imageId);
        if (! $imageRow) {
            return null;
        }

        $dir = $this->getDir($imageRow['dir']);
        if (! $dir) {
            throw new Storage\Exception("Dir '{$imageRow['dir']}' not defined");
        }

        $object = $this->getS3Client()->getObject([
            'Bucket' => $dir->getBucket(),
            'Key'    => $imageRow['filepath'],
        ]);

        return $object['Body']->detach();
    }

    /**
     * @throws Storage\Exception
     */
    public function getImageBlob(int $imageId): ?string
    {
        $imageRow = $this->getImageRow($imageId);

        return $imageRow ? $this->buildImageBlobResult($imageRow) : null;
    }

    private function isDuplicateKeyException(Exception $e): bool
    {
        return strpos($e->getMessage(), 'Duplicate entry') !== false ||
            strpos($e->getMessage(), 'duplicate key') !== false;
    }

    /**
     * @param ResultSetInterface|array $row
     */
    private function getRowCrop($row): ?array
    {
        if ($row['crop_width'] <= 0 || $row['crop_height'] <= 0) {
            return null;
        }

        return [
            'left'   => $row['crop_left'],
            'top'    => $row['crop_top'],
            'width'  => $row['crop_width'],
            'height' => $row['crop_height'],
        ];
    }

    /**
     * @throws ImagickException
     * @throws Storage\Exception
     * @throws Exception
     */
    private function doFormatImage(int $imageId, string $formatName): int
    {
        // find source image
        $imageRow = $this->imageTable->select([
            'id' => $imageId,
        ])->current();
        if (! $imageRow) {
            throw new Storage\Exception("Image '$imageId' not defined");
        }

        $imagick = new Imagick();
        try {
            $dir = $this->getDir($imageRow['dir']);
            if (! $dir) {
                throw new Storage\Exception("Dir '{$imageRow['dir']}' not defined");
            }
            $object = $this->getS3Client()->getObject([
                'Bucket' => $dir->getBucket(),
                'Key'    => $imageRow['filepath'],
            ]);

            $imagick->readImageBlob($object['Body']->getContents());
        } catch (ImagickException $e) {
            throw new Storage\Exception('Imagick: ' . $e->getMessage());
            //continue;
        }

        // format
        $format = $this->getFormat($formatName);
        if (! $format) {
            throw new Storage\Exception("Format `$formatName` not found");
        }
        $cFormat = clone $format;

        $sampler = $this->getImageSampler();

        try {
            $this->formatedImageTable->insert([
                'format'            => $formatName,
                'image_id'          => $imageId,
                'status'            => self::STATUS_PROCESSING,
                'formated_image_id' => null,
            ]);
        } catch (Exception $e) {
            if (! $this->isDuplicateKeyException($e)) {
                throw $e;
            }

            // wait until done
            $done             = false;
            $formatedImageRow = null;
            for ($i = 0; $i < self::TIMEOUT && ! $done; $i++) {
                $formatedImageRow = $this->formatedImageTable->select([
                    'format'   => $formatName,
                    'image_id' => $imageId,
                ])->current();

                $done = (int) $formatedImageRow['status'] !== self::STATUS_PROCESSING;

                if (! $done) {
                    sleep(1);
                }
            }

            if (! $done) {
                // mark as failed
                $this->formatedImageTable->update([
                    'status' => self::STATUS_FAILED,
                ], [
                    'format'   => $formatName,
                    'image_id' => $imageId,
                    'status'   => self::STATUS_PROCESSING,
                ]);
            }

            if (! $formatedImageRow) {
                throw new Storage\Exception("Failed to format image");
            }

            return (int) $formatedImageRow['formated_image_id'];
        }

        try {
            $crop = $this->getRowCrop($imageRow);

            $cropSuffix = '';
            if ($crop) {
                $cropSuffix = '_' . sprintf(
                    "%04x%04x%04x%04x",
                    $crop['left'],
                    $crop['top'],
                    $crop['width'],
                    $crop['height']
                );
            }

            $imagick = $sampler->convertImagick($imagick, $crop, $cFormat);

            foreach ($cFormat->getProcessors() as $processorName) {
                $processor = $this->processors->get($processorName);
                $processor->process($imagick);
            }

            // store result
            $newPath         = implode(DIRECTORY_SEPARATOR, [
                $imageRow['dir'],
                $formatName,
                $imageRow['filepath'],
            ]);
            $pi              = pathinfo($newPath);
            $formatExt       = $cFormat->getFormatExtension();
            $extension       = $formatExt ? $formatExt : $pi['extension'];
            $formatedImageId = $this->addImageFromImagick(
                $imagick,
                $this->formatedImageDirName,
                [
                    'extension' => $extension,
                    'pattern'   => $pi['dirname'] . DIRECTORY_SEPARATOR . $pi['filename'] . $cropSuffix,
                ]
            );

            $imagick->clear();

            $this->formatedImageTable->update([
                'formated_image_id' => $formatedImageId,
                'status'            => self::STATUS_DEFAULT,
            ], [
                'format'   => $formatName,
                'image_id' => $imageId,
            ]);
        } catch (Exception $e) {
            $this->formatedImageTable->update([
                'status' => self::STATUS_FAILED,
            ], [
                'format'   => $formatName,
                'image_id' => $imageId,
            ]);

            throw $e;
        }

        return $formatedImageId;
    }

    /**
     * @throws ImagickException
     * @throws Storage\Exception
     */
    private function getFormatedImageRows(array $imagesId, string $formatName): array
    {
        $destImageRows = [];
        if (count($imagesId)) {
            $select = $this->imageTable->getSql()->select()
                ->join(
                    ['f' => $this->formatedImageTableName],
                    $this->imageTableName . '.id = f.formated_image_id',
                    ['image_id']
                )
                ->where([
                    new Sql\Predicate\In('f.image_id', $imagesId),
                    'f.format' => $formatName,
                ]);
            foreach ($this->imageTable->selectWith($select) as $row) {
                $destImageRows[] = $row;
            }
        }

        $result = [];

        foreach ($imagesId as $key => $imageId) {
            $imageId      = (int) $imageId;
            $destImageRow = null;
            foreach ($destImageRows as $row) {
                if ((int) $row['image_id'] === $imageId) {
                    $destImageRow = $row;
                    break;
                }
            }

            if (! $destImageRow) {
                $formatedImageId = $this->doFormatImage($imageId, $formatName);
                // result
                $destImageRow = $this->imageTable->select([
                    'id' => $formatedImageId,
                ])->current();
            }

            $result[$key] = $destImageRow;
        }

        return $result;
    }

    /**
     * @return mixed|null
     * @throws ImagickException
     * @throws Storage\Exception
     */
    private function getFormatedImageRow(int $imageId, string $formatName)
    {
        $result = $this->getFormatedImageRows([$imageId], $formatName);

        if (! isset($result[0])) {
            //throw new Storage\Exception("getFormatedImageRows fails");
            return null;
        }

        return $result[0];
    }

    /**
     * @throws ImagickException
     * @throws Storage\Exception
     */
    public function getFormatedImageBlob(int $imageId, string $formatName): ?string
    {
        $row = $this->getFormatedImageRow($imageId, $formatName);

        return $row === null ? null : $this->buildImageBlobResult($row);
    }

    /**
     * @throws ImagickException
     * @throws Storage\Exception
     */
    public function getFormatedImage(int $imageId, string $formatName): ?Storage\Image
    {
        $row = $this->getFormatedImageRow($imageId, $formatName);
        return $row === null ? null : $this->buildImageResult($row);
    }

    /**
     * @throws ImagickException
     * @throws Storage\Exception
     */
    public function getFormatedImages(array $imagesId, string $formatName): array
    {
        $result = [];
        foreach ($this->getFormatedImageRows($imagesId, $formatName) as $key => $row) {
            $result[$key] = $row === null ? null : $this->buildImageResult($row);
        }

        return $result;
    }

    /**
     * @throws Storage\Exception
     */
    public function removeImage(int $imageId): StorageInterface
    {
        $imageRow = $this->imageTable->select([
            'id' => $imageId,
        ])->current();

        if (! $imageRow) {
            throw new Storage\Exception("Image '$imageId' not found");
        }

        $this->flush([
            'image' => $imageRow['id'],
        ]);

        // to save remove formated image
        $this->formatedImageTable->delete([
            'formated_image_id' => $imageRow['id'],
        ]);

        // important to delete row first
        $this->imageTable->delete([
            'id' => $imageRow['id'],
        ]);

        $dir = $this->getDir($imageRow['dir']);
        if (! $dir) {
            throw new Storage\Exception("Dir '{$imageRow['dir']}' not defined");
        }

        $this->getS3Client()->deleteObject([
            'Bucket' => $dir->getBucket(),
            'Key'    => $imageRow['filepath'],
        ]);

        return $this;
    }

    /**
     * @throws Storage\Exception
     */
    private function createImagePath(string $dirName, array $options = []): string
    {
        $dir = $this->getDir($dirName);
        if (! $dir) {
            throw new Storage\Exception("Dir '$dirName' not defined");
        }

        $namingStrategy = $dir->getNamingStrategy();

        $options = array_replace([
            'count' => $this->getDirCounter($dirName),
        ], $options);

        if (! isset($options['extension'])) {
            $options['extension'] = self::EXTENSION_DEFAULT;
        }

        return $namingStrategy->generate($options);
    }

    /**
     * @throws ImagickException
     * @throws Storage\Exception
     */
    public function addImageFromBlob(string $blob, string $dirName, array $options = []): int
    {
        $imagick = new Imagick();
        $imagick->readImageBlob($blob);
        $imageId = $this->addImageFromImagick($imagick, $dirName, $options);
        $imagick->clear();

        return $imageId;
    }

    /**
     * @throws Exception
     */
    private function indexByAttempt(int $attempt): int
    {
        $from = (int) pow(10, $attempt - 1);
        $to   = (int) pow(10, $attempt) - 1;

        return random_int($from, $to);
    }

    /**
     * @throws Storage\Exception
     * @throws Exception
     */
    private function generateLockWrite(string $dirName, array $options, int $width, int $height, Closure $callback): int
    {
        $insertAttemptException = null;
        $imageId                = 0;
        $attemptIndex           = 0;
        do {
            $this->incDirCounter($dirName);

            $destFileName = $this->createImagePath($dirName, array_replace($options, [
                'index' => $this->indexByAttempt($attemptIndex),
            ]));

            $insertAttemptException = null;

            try {
                // store to db
                $this->imageTable->insert([
                    'width'       => $width,
                    'height'      => $height,
                    'dir'         => $dirName,
                    'filesize'    => 0,
                    'filepath'    => $destFileName,
                    'date_add'    => new Sql\Expression('now()'),
                    'crop_left'   => 0,
                    'crop_top'    => 0,
                    'crop_width'  => 0,
                    'crop_height' => 0,
                    's3'          => 1,
                ]);

                $id = (int) $this->imageTable->getLastInsertValue();

                $callback($destFileName);

                $imageId = $id;
            } catch (Exception $e) {
                // duplicate or other error
                $insertAttemptException = $e;
            }

            $attemptIndex++;
        } while (($attemptIndex < self::INSERT_MAX_ATTEMPTS) && $insertAttemptException);

        if ($insertAttemptException) {
            throw $insertAttemptException;
        }

        return $imageId;
    }

    /**
     * @throws Storage\Exception
     * @throws ImagickException
     */
    public function addImageFromImagick(Imagick $imagick, string $dirName, array $options = []): int
    {
        $width  = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();

        if (! $width || ! $height) {
            throw new Storage\Exception("Failed to get image size ($width x $height)");
        }

        $format = $imagick->getImageFormat();

        switch (strtolower($format)) {
            case 'gif':
                $options['extension'] = 'gif';
                break;
            case 'jpeg':
                $options['extension'] = 'jpg';
                break;
            case 'png':
                $options['extension'] = 'png';
                break;
            default:
                throw new Storage\Exception("Unsupported image type `$format`");
        }

        $dir = $this->getDir($dirName);
        if (! $dir) {
            throw new Storage\Exception("Dir '$dirName' not defined");
        }

        $blob = $imagick->getImagesBlob();
        $id   = $this->generateLockWrite(
            $dirName,
            $options,
            $width,
            $height,
            function (string $fileName) use ($dir, $blob, $imagick) {
                $this->getS3Client()->putObject([
                    'Key'         => $fileName,
                    'Body'        => $blob,
                    'Bucket'      => $dir->getBucket(),
                    'ACL'         => 'public-read',
                    'ContentType' => $imagick->getImageMimeType(),
                ]);
            }
        );

        $filesize = strlen($blob);
        $exif     = $this->extractEXIF($id);
        if ($exif) {
            $exif = json_encode($exif, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
            if ($exif === false) {
                throw new Storage\Exception("Failed to encode exif");
            }
        }

        $this->imageTable->update([
            'filesize' => $filesize,
            'exif'     => $exif,
        ], [
            'id' => $id,
        ]);

        return $id;
    }

    /**
     * @throws Storage\Exception
     */
    public function addImageFromFile(string $file, string $dirName, array $options = []): int
    {
        $imageInfo = getimagesize($file);

        $width  = (int) $imageInfo[0];
        $height = (int) $imageInfo[1];
        $type   = $imageInfo[2];

        if (! $width || ! $height) {
            throw new Storage\Exception("Failed to get image size of '$file' ($width x $height)");
        }

        if (! isset($options['extension'])) {
            $ext = null;
            switch ($type) {
                case IMAGETYPE_GIF:
                    $ext = 'gif';
                    break;
                case IMAGETYPE_JPEG:
                    $ext = 'jpg';
                    break;
                case IMAGETYPE_PNG:
                    $ext = 'png';
                    break;
                default:
                    throw new Storage\Exception("Unsupported image type `$type`");
            }
            $options['extension'] = $ext;
        }

        $dir = $this->getDir($dirName);
        if (! $dir) {
            throw new Storage\Exception("Dir '$dirName' not defined");
        }

        $id = $this->generateLockWrite(
            $dirName,
            $options,
            $width,
            $height,
            function (string $fileName) use ($dir, $file) {
                $handle = fopen($file, 'r');
                $this->getS3Client()->putObject([
                    'Key'         => $fileName,
                    'Body'        => $handle,
                    'Bucket'      => $dir->getBucket(),
                    'ACL'         => 'public-read',
                    'ContentType' => mime_content_type($file),
                ]);
                fclose($handle);
            }
        );

        $exif = $this->extractEXIF($id);
        if ($exif) {
            $exif = json_encode($exif, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
            if ($exif === false) {
                throw new Storage\Exception("Failed to encode exif");
            }
        }

        $this->imageTable->update([
            'filesize' => filesize($file),
            'exif'     => $exif,
        ], [
            'id' => $id,
        ]);

        return $id;
    }

    /**
     * @throws Storage\Exception
     */
    public function flush(array $options): StorageInterface
    {
        $defaults = [
            'format' => null,
            'image'  => null,
        ];

        $options = array_replace($defaults, $options);

        $select = $this->formatedImageTable->getSql()->select();

        if ($options['format']) {
            $select->where([
                $this->formatedImageTableName . '.format' => (string) $options['format'],
            ]);
        }

        if ($options['image']) {
            $select->where([
                $this->formatedImageTableName . '.image_id' => (int) $options['image'],
            ]);
        }

        $rows = $this->formatedImageTable->selectWith($select);

        foreach ($rows as $row) {
            if ($row['formated_image_id']) {
                $this->removeImage((int) $row['formated_image_id']);
            }

            $this->formatedImageTable->delete([
                'image_id' => $row['image_id'],
                'format'   => $row['format'],
            ]);
        }

        return $this;
    }

    public function getDirCounter(string $dirName): int
    {
        $select = $this->dirTable->getSql()->select()
            ->columns(['count'])
            ->where(['dir' => $dirName]);

        $row = $this->dirTable->selectWith($select)->current();

        if (! $row) {
            return 0;
        }

        return (int) $row['count'];
    }

    private function incDirCounter(string $dirName): self
    {
        $row = $this->dirTable->select([
            'dir' => $dirName,
        ])->current();

        if ($row) {
            $this->dirTable->update([
                'count' => new Sql\Expression('count + 1'),
            ], [
                'dir' => $dirName,
            ]);
        } else {
            $this->dirTable->insert([
                'dir'   => $dirName,
                'count' => 1,
            ]);
        }

        return $this;
    }

    /**
     * @throws Storage\Exception
     */
    public function getImageEXIF(int $imageId): ?array
    {
        $imageRow = $this->getImageRow($imageId);

        if (! $imageRow || ! $imageRow['exif']) {
            return null;
        }

        $exif = json_decode($imageRow['exif'], true, 512, JSON_THROW_ON_ERROR);

        if ($exif === false) {
            return null;
        }

        return $exif;
    }

    /**
     * @throws Storage\Exception
     */
    public function extractEXIF(int $imageId): ?array
    {
        $imageRow = $this->getImageRow($imageId);

        if (! $imageRow) {
            return null;
        }

        $stream = $this->getImageBlobStream($imageId);

        if (! $stream) {
            return null;
        }

        if (! is_resource($stream)) {
            throw new Storage\Exception("Resource expected");
        }

        $exif = @exif_read_data($stream, '', true);

        return $exif ? $exif : null;
    }

    /**
     * @throws ImagickException
     * @throws Storage\Exception
     */
    public function getImageResolution(int $imageId): ?array
    {
        $imageRow = $this->getImageRow($imageId);

        if (! $imageRow) {
            return null;
        }

        $dir = $this->getDir($imageRow['dir']);
        if (! $dir) {
            throw new Storage\Exception("Dir '{$imageRow['dir']}' not defined");
        }

        $imagick = new Imagick();
        $object  = $this->getS3Client()->getObject([
            'Bucket' => $dir->getBucket(),
            'Key'    => $imageRow['filepath'],
        ]);

        $imagick->readImageBlob($object['Body']->getContents());

        $info = $imagick->identifyImage();

        $x = $info['resolution']['x'];
        $y = $info['resolution']['y'];

        if (! $x || ! $y) {
            return null;
        }

        switch ($info['units']) {
            case 'PixelsPerInch':
                break;
            case 'PixelsPerCentimeter':
                $x = round($x * 2.54);
                $y = round($y * 2.54);
                break;
            case 'Undefined':
            case 'undefined':
            case 'Unrecognized':
                return null;
            default:
                throw new Storage\Exception("Unexpected resolution unit `{$info['units']}`");
        }

        return [
            'x' => $x,
            'y' => $y,
        ];
    }

    /**
     * @throws Storage\Exception
     * @throws Exception
     */
    public function changeImageName(int $imageId, array $options = []): void
    {
        $imageRow = $this->getImageRow($imageId);
        if (! $imageRow) {
            throw new Storage\Exception("Image `$imageId` not found");
        }

        $dir = $this->getDir($imageRow['dir']);
        if (! $dir) {
            throw new Storage\Exception("Dir '{$imageRow['dir']}' not defined");
        }

        if (! isset($options['extension'])) {
            $options['extension'] = pathinfo($imageRow['filepath'], PATHINFO_EXTENSION);
        }

        $attemptIndex = 0;
        /**
         * @var Storage\Exception $insertAttemptException
         */
        $insertAttemptException = null;

        do {
            $destFileName = $this->createImagePath($imageRow['dir'], array_replace($options, [
                'index' => $this->indexByAttempt($attemptIndex),
            ]));

            $insertAttemptException = null;

            try {
                if ($destFileName === $imageRow['filepath']) {
                    throw new Storage\Exception("Trying to rename to self");
                }

                $this->imageTable->update([
                    'filepath' => $destFileName,
                ], [
                    'id' => $imageRow['id'],
                ]);
            } catch (Exception $e) {
                // duplicate or other error
                $insertAttemptException = $e;
            }

            if (! $insertAttemptException) {
                $s3 = $this->getS3Client();
                $s3->copyObject([
                    'Bucket'     => $dir->getBucket(),
                    'CopySource' => $dir->getBucket() . '/' . $imageRow['filepath'],
                    'Key'        => $destFileName,
                    'ACL'        => 'public-read',
                ]);
                $s3->deleteObject([
                    'Bucket' => $dir->getBucket(),
                    'Key'    => $imageRow['filepath'],
                ]);
            }

            $attemptIndex++;
        } while (($attemptIndex < self::INSERT_MAX_ATTEMPTS) && $insertAttemptException);

        if ($insertAttemptException) {
            throw $insertAttemptException;
        }
    }

    /**
     * @throws ImagickException
     * @throws Storage\Exception
     */
    public function flop(int $imageId): void
    {
        $imageRow = $this->getImageRow($imageId);

        if (! $imageRow) {
            throw new Storage\Exception("Failed to found path for `$imageId`");
        }

        $dir = $this->getDir($imageRow['dir']);
        if (! $dir) {
            throw new Storage\Exception("Dir '{$imageRow['dir']}' not defined");
        }

        $imagick = new Imagick();

        $object = $this->getS3Client()->getObject([
            'Bucket' => $dir->getBucket(),
            'Key'    => $imageRow['filepath'],
        ]);

        $imagick->readImageBlob($object['Body']->getContents());

        // format
        $imagick->flopImage();

        $this->getS3Client()->putObject([
            'Key'         => $imageRow['filepath'],
            'Body'        => $imagick->getImagesBlob(),
            'Bucket'      => $dir->getBucket(),
            'ACL'         => 'public-read',
            'ContentType' => $imagick->getImageMimeType(),
        ]);

        $imagick->clear();

        $this->flush([
            'image' => $imageId,
        ]);
    }

    /**
     * @throws ImagickException
     * @throws Storage\Exception
     */
    public function normalize(int $imageId): void
    {
        $imageRow = $this->getImageRow($imageId);

        if (! $imageRow) {
            throw new Storage\Exception("Failed to found path for `$imageId`");
        }

        $dir = $this->getDir($imageRow['dir']);
        if (! $dir) {
            throw new Storage\Exception("Dir '{$imageRow['dir']}' not defined");
        }

        $imagick = new Imagick();

        $object = $this->getS3Client()->getObject([
            'Bucket' => $dir->getBucket(),
            'Key'    => $imageRow['filepath'],
        ]);

        $imagick->readImageBlob($object['Body']->getContents());

        // format
        $imagick->normalizeImage();

        $this->getS3Client()->putObject([
            'Key'         => $imageRow['filepath'],
            'Body'        => $imagick->getImagesBlob(),
            'Bucket'      => $dir->getBucket(),
            'ACL'         => 'public-read',
            'ContentType' => $imagick->getImageMimeType(),
        ]);

        $imagick->clear();

        $this->flush([
            'image' => $imageId,
        ]);
    }

    public function hasFormat(string $format): bool
    {
        return (bool) $this->getFormat($format);
    }

    /**
     * @throws Storage\Exception
     */
    public function getImageCrop(int $imageId): ?array
    {
        $row = $this->getImageRow($imageId);

        if (! $row) {
            return null;
        }

        if ($row['crop_width'] <= 0 || $row['crop_height'] <= 0) {
            return null;
        }

        return [
            'left'   => (int) $row['crop_left'],
            'top'    => (int) $row['crop_top'],
            'width'  => (int) $row['crop_width'],
            'height' => (int) $row['crop_height'],
        ];
    }

    /**
     * @throws Storage\Exception
     */
    public function setImageCrop(int $imageId, ?array $crop): void
    {
        if (! $imageId) {
            throw new Storage\Exception("Invalid image id provided `$imageId`");
        }

        if (! is_array($crop)) {
            $crop = [];
        }

        if (! isset($crop['left'])) {
            $crop['left'] = 0;
        }

        if (! isset($crop['top'])) {
            $crop['top'] = 0;
        }

        if (! isset($crop['width'])) {
            $crop['width'] = 0;
        }

        if (! isset($crop['height'])) {
            $crop['height'] = 0;
        }

        $left   = (int) $crop['left'];
        $top    = (int) $crop['top'];
        $width  = (int) $crop['width'];
        $height = (int) $crop['height'];

        if ($left < 0 || $top < 0 || $width <= 0 || $height <= 0) {
            $left   = 0;
            $top    = 0;
            $width  = 0;
            $height = 0;
        }

        $this->imageTable->update([
            'crop_left'   => $left,
            'crop_top'    => $top,
            'crop_width'  => $width,
            'crop_height' => $height,
        ], [
            'id' => $imageId,
        ]);

        foreach ($this->formats as $formatName => $format) {
            if (! $format->getIgnoreCrop()) {
                $this->flush([
                    'format' => $formatName,
                    'image'  => $imageId,
                ]);
            }
        }
    }

    /**
     * @throws Storage\Exception
     */
    public function extractAllEXIF(string $dir): void
    {
        $select = $this->imageTable->getSql()->select()
            ->where([
                'dir' => $dir,
                'exif is null',
            ])
            ->limit(10000);

        $rows = $this->imageTable->selectWith($select);

        foreach ($rows as $row) {
            $exif = $this->extractEXIF($row['id']);
            if ($exif) {
                $exif = json_encode($exif, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
                if ($exif === false) {
                    throw new Storage\Exception("Failed to encode exif");
                }
            }

            $this->imageTable->update([
                'exif' => $exif,
            ], [
                'id' => $row['id'],
            ]);
        }
    }
}
