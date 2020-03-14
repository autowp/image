<?php

declare(strict_types=1);

namespace Autowp\Image;

use ArrayObject;
use Aws\S3\S3Client;
use Closure;
use Exception;
use Imagick;
use ImagickException;
use Laminas\Db\Exception\ExceptionInterface;
use Laminas\Db\ResultSet\ResultSetInterface;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;

use function array_key_exists;
use function array_merge;
use function array_replace;
use function chmod;
use function copy;
use function count;
use function dirname;
use function exif_read_data;
use function fclose;
use function file_exists;
use function file_get_contents;
use function filesize;
use function fopen;
use function getimagesize;
use function htmlspecialchars;
use function image_type_to_extension;
use function implode;
use function iptcparse;
use function is_array;
use function is_dir;
use function is_resource;
use function is_string;
use function json_decode;
use function json_encode;
use function method_exists;
use function mime_content_type;
use function mkdir;
use function octdec;
use function pathinfo;
use function pow;
use function preg_replace;
use function random_int;
use function rename;
use function round;
use function rtrim;
use function sleep;
use function sprintf;
use function str_replace;
use function strlen;
use function strpos;
use function strtolower;
use function ucfirst;
use function umask;
use function unlink;

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

    /** @var TableGateway */
    private TableGateway $imageTable;

    /** @var string */
    private string $imageTableName = 'image';

    /** @var TableGateway */
    private TableGateway $formatedImageTable;

    /** @var string */
    private string $formatedImageTableName = 'formated_image';

    /** @var TableGateway */
    private TableGateway $dirTable;

    /** @var array */
    private array $dirs = [];

    /** @var array */
    private array $formats = [];

    /** @var int */
    private int $fileMode = 0600;

    /** @var int */
    private int $dirMode = 0700;

    /** @var string */
    private string $formatedImageDirName;

    /** @var Sampler */
    private ?Sampler $imageSampler;

    /** @var bool */
    private bool $forceHttps = false;

    /** @var Processor\ProcessorPluginManager */
    private Processor\ProcessorPluginManager $processors;

    /** @var S3Client */
    private ?S3Client $s3;

    /** @var array */
    private array $s3Options = [];

    /** @var bool */
    private bool $formatToS3 = false;

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
        $this->imageSampler = null;

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

    public function setFormatToS3(bool $value): self
    {
        $this->formatToS3 = $value;

        return $this;
    }

    public function setS3(array $options): self
    {
        $this->s3 = null;

        $this->s3Options = $options;

        return $this;
    }

    private function getS3Client(): S3Client
    {
        if (! $this->s3) {
            $this->s3 = new S3Client($this->s3Options);
        }

        return $this->s3;
    }

    public function setForceHttps(bool $value): self
    {
        $this->forceHttps = (bool) $value;

        return $this;
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
        if (null === $this->imageSampler) {
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
     * @param string|int $mode
     */
    public function setFileMode($mode): self
    {
        $this->fileMode = is_string($mode) ? octdec($mode) : (int) $mode;

        return $this;
    }

    /**
     * @param string|int $mode
     */
    public function setDirMode($mode): self
    {
        $this->dirMode = is_string($mode) ? octdec($mode) : (int) $mode;

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
        if ($imageRow['s3']) {
            $src = $this->getS3Client()->getObjectUrl($dir->getBucket(), $imageRow['filepath']);
        } else {
            $dirUrl = $dir->getUrl();

            $src = null;
            if ($dirUrl) {
                $path = str_replace('+', '%2B', $imageRow['filepath']);

                $src = $dirUrl . $path;
            }

            if ($this->forceHttps) {
                $src = preg_replace("/^http:/i", "https:", $src);
            }
        }

        return new Storage\Image([
            'id'       => $imageRow['id'],
            'width'    => $imageRow['width'],
            'height'   => $imageRow['height'],
            'src'      => $src,
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

        if ($imageRow['s3']) {
            $object = $this->getS3Client()->getObject([
                'Bucket' => $dir->getBucket(),
                'Key'    => $imageRow['filepath'],
            ]);

            return $object['Body']->getContents();
        }

        $filepath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow['filepath'];

        if (! file_exists($filepath)) {
            throw new Storage\Exception("File `$filepath` not found");
        }

        $result = file_get_contents($filepath);

        if ($result === false) {
            throw new Storage\Exception("Failed to read file `$filepath`");
        }

        return $result;
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
            $result = $this->imageTable->select([
                new Sql\Predicate\In('id', $imageIds),
            ]);
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
     * @throws Storage\Exception
     */
    public function getImageFilepath(int $imageId): ?string
    {
        $imageRow = $this->getImageRow($imageId);

        if (! $imageRow) {
            return null;
        }

        if ($imageRow['s3']) {
            throw new Storage\Exception("`getImageFilepath` not implemented for S3");
        }

        $dir = $this->getDir($imageRow['dir']);
        if (! $dir) {
            throw new Storage\Exception("Dir '{$imageRow['dir']}' not defined");
        }

        return $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow['filepath'];
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

        if ($imageRow['s3']) {
            $object = $this->getS3Client()->getObject([
                'Bucket' => $dir->getBucket(),
                'Key'    => $imageRow['filepath'],
            ]);

            return $object['Body']->detach();
        }

        $filepath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow['filepath'];

        if (! file_exists($filepath)) {
            throw new Storage\Exception("File `$filepath` not found");
        }

        $result = fopen($filepath, 'r');

        if ($result === false) {
            throw new Storage\Exception("Failed to read file `$filepath`");
        }

        return $result;
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
            if ($imageRow['s3']) {
                $object = $this->getS3Client()->getObject([
                    'Bucket' => $dir->getBucket(),
                    'Key'    => $imageRow['filepath'],
                ]);

                $imagick->readImageBlob($object['Body']->getContents());
            } else {
                $srcFilePath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow['filepath'];

                if (! file_exists($srcFilePath)) {
                    throw new Storage\Exception("File `$srcFilePath` not found");
                }

                $imagick->readImage($srcFilePath);
            }
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
        if (! $sampler) {
            throw new Storage\Exception("Image sampler not initialized");
        }

        try {
            $this->formatedImageTable->insert([
                'format'            => $formatName,
                'image_id'          => $imageId,
                'status'            => self::STATUS_PROCESSING,
                'formated_image_id' => null,
            ]);
        } catch (ExceptionInterface $e) {
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

                $done = $formatedImageRow['status'] !== self::STATUS_PROCESSING;

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
                    's3'        => $this->formatToS3,
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
            $destImageRow = null;
            foreach ($destImageRows as $row) {
                if ((int) $row['image_id'] === (int) $imageId) {
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
    public function getFormatedImagePath(int $imageId, string $formatName): ?string
    {
        $imageRow = $this->getFormatedImageRow($imageId, $formatName);

        if (! $imageRow) {
            return null;
        }

        if ($imageRow['s3']) {
            throw new Storage\Exception("`getFormatedImagePath` not implemented for S3");
        }

        $dir = $this->getDir($imageRow['dir']);
        if (! $dir) {
            throw new Storage\Exception("Dir '{$imageRow['dir']}' not defined");
        }

        $dirPath = $dir->getPath();

        $path = null;
        if ($dirPath) {
            $dirPath = rtrim($dirPath, '/\\') . DIRECTORY_SEPARATOR;
            $path    = $dirPath . $imageRow['filepath'];
        }

        return $path;
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

        if ($imageRow['s3']) {
            $this->getS3Client()->deleteObject([
                'Bucket' => $dir->getBucket(),
                'Key'    => $imageRow['filepath'],
            ]);
        } else {
            // remove file & row
            $filepath = implode(DIRECTORY_SEPARATOR, [
                rtrim($dir->getPath(), DIRECTORY_SEPARATOR),
                $imageRow['filepath'],
            ]);

            if (file_exists($filepath) && ! unlink($filepath)) {
                throw new Storage\Exception("Error unlink `$filepath`");
            }
        }

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

        $dirPath = $dir->getPath();

        $namingStrategy = $dir->getNamingStrategy();
        if (! $namingStrategy) {
            $message = "Naming strategy not initialized for `$dirName`";
            throw new Storage\Exception($message);
        }

        $options = array_merge([
            'count' => $this->getDirCounter($dirName),
        ], $options);

        if (! isset($options['extension'])) {
            $options['extension'] = self::EXTENSION_DEFAULT;
        }

        $destFileName = $namingStrategy->generate($options);
        $destFilePath = $dirPath . DIRECTORY_SEPARATOR . $destFileName;

        $destDir = dirname($destFilePath);
        if (! is_dir($destDir)) {
            $old = umask(0);
            if (! mkdir($destDir, $this->dirMode, true)) {
                throw new Storage\Exception("Cannot create dir '$destDir'");
            }
            umask($old);
        }

        return $destFileName;
    }

    /**
     * @throws Storage\Exception
     */
    private function chmodFile(string $path): void
    {
        if (! chmod($path, $this->fileMode)) {
            throw new Storage\Exception("Cannot chmod file '$path'");
        }
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
        $dir = $this->getDir($dirName);
        if (! $dir) {
            throw new Storage\Exception("Dir '$dirName' not defined");
        }

        $dirPath = $dir->getPath();

        $insertAttemptException = null;
        $imageId                = 0;
        $attemptIndex           = 0;
        do {
            $this->incDirCounter($dirName);

            $destFileName = $this->createImagePath($dirName, array_replace($options, [
                'index' => $this->indexByAttempt($attemptIndex),
            ]));
            $destFilePath = $dirPath . DIRECTORY_SEPARATOR . $destFileName;

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
                    's3'          => isset($options['s3']) && $options['s3'] ? 1 : 0,
                ]);

                $id = (int) $this->imageTable->getLastInsertValue();

                $callback($destFilePath, $destFileName);

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

        if (isset($options['s3']) && $options['s3']) {
            $blob = $imagick->getImagesBlob();
            $id   = $this->generateLockWrite(
                $dirName,
                $options,
                $width,
                $height,
                function ($filePath, $fileName) use ($dir, $blob, $imagick) {
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
        } else {
            $id = $this->generateLockWrite(
                $dirName,
                $options,
                $width,
                $height,
                function ($filePath) use ($imagick, &$filesize) {
                    if (! $imagick->writeImages($filePath, true)) {
                        throw new Storage\Exception("Imagick::writeImage error");
                    }

                    $this->chmodFile($filePath);

                    $filesize = filesize($filePath);
                }
            );
        }

        $exif = $this->extractEXIF($id);
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

        if (isset($options['s3']) && $options['s3']) {
            $id = $this->generateLockWrite(
                $dirName,
                $options,
                $width,
                $height,
                function ($filePath, $fileName) use ($dir, $file) {
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
        } else {
            $id = $this->generateLockWrite($dirName, $options, $width, $height, function ($filePath) use ($file) {
                if (! copy($file, $filePath)) {
                    throw new Storage\Exception("copy error '$file'");
                }

                $this->chmodFile($filePath);
            });
        }

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

        $options = array_merge($defaults, $options);

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
    public function getImageIPTC(int $imageId): ?string
    {
        $imageRow = $this->getImageRow($imageId);

        if (! $imageRow) {
            return null;
        }

        if ($imageRow['s3']) {
            return null;
        }

        $dir = $this->getDir($imageRow['dir']);
        if (! $dir) {
            throw new Storage\Exception("Dir '{$imageRow['dir']}' not defined");
        }

        $filepath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow['filepath'];

        if (! file_exists($filepath)) {
            throw new Storage\Exception("File `$filepath` not found");
        }

        $iptcStr = '';
        $info    = null;
        @getimagesize($filepath, $info);
        if (is_array($info) && array_key_exists('APP13', $info)) {
            $iptc = iptcparse($info['APP13']);
            if (is_array($iptc)) {
                foreach ($iptc as $key => $value) {
                    $iptcStr .= "<b>IPTC Key:</b> " . htmlspecialchars($key) . " <b>Contents:</b> ";
                    foreach ($value as $innerKey => $innerValue) {
                        $iptcStr .= htmlspecialchars($innerValue);
                        if (($innerKey + 1) !== count($value)) {
                            $iptcStr .= ", ";
                        }
                    }
                    $iptcStr .= '<br />';
                }
            } else {
                $iptcStr .= $iptc;
            }
        }

        return $iptcStr;
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
        if ($imageRow['s3']) {
            $object = $this->getS3Client()->getObject([
                'Bucket' => $dir->getBucket(),
                'Key'    => $imageRow['filepath'],
            ]);

            $imagick->readImageBlob($object['Body']->getContents());
        } else {
            $filepath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow['filepath'];

            if (! file_exists($filepath)) {
                throw new Storage\Exception("File `$filepath` not found");
            }
            $imagick->readImage($filepath);
        }

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
     */
    private static function detectExtension(string $filepath): string
    {
        $imageInfo = getimagesize($filepath);

        $imageType = $imageInfo[2];

        // подбираем имя для файла
        switch ($imageType) {
            case IMAGETYPE_JPEG:
            case IMAGETYPE_PNG:
                break;
            default:
                throw new Storage\Exception("Unsupported image type");
        }
        return image_type_to_extension($imageType, false);
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

        $dirPath = $dir->getPath();

        $oldFilePath = $dirPath . DIRECTORY_SEPARATOR . $imageRow['filepath'];

        if (! isset($options['extension'])) {
            if ($imageRow['s3']) {
                $options['extension'] = pathinfo($oldFilePath, PATHINFO_EXTENSION);
            } else {
                $options['extension'] = self::detectExtension($oldFilePath);
            }
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
            $destFilePath = $dirPath . DIRECTORY_SEPARATOR . $destFileName;

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
                if ($imageRow['s3']) {
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
                } else {
                    $success = rename($oldFilePath, $destFilePath);
                    if (! $success) {
                        throw new Storage\Exception("Failed to move file");
                    }

                    $this->chmodFile($destFilePath);
                }
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

        if ($imageRow['s3']) {
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
                'ContentType' => $imagick->getImageMimeType(),
            ]);
        } else {
            $filePath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow['filepath'];
            $imagick->readImage($filePath);

            // format
            $imagick->flopImage();

            $imagick->writeImages($filePath, true);
        }

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

        if ($imageRow['s3']) {
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
                'ContentType' => $imagick->getImageMimeType(),
            ]);
        } else {
            $filePath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow['filepath'];
            $imagick->readImage($filePath);

            // format
            $imagick->normalizeImage();

            $imagick->writeImages($filePath, true);
        }

        $imagick->clear();

        $this->flush([
            'image' => $imageId,
        ]);
    }

    /**
     * @throws Storage\Exception
     */
    public function moveDirToS3(string $dir): void
    {
        $select = $this->imageTable->getSql()->select()
            ->where([
                'dir' => $dir,
                'not s3',
            ])
            ->limit(10000);

        $rows = $this->imageTable->selectWith($select);

        foreach ($rows as $row) {
            $this->moveToS3($row['id']);
        }
    }

    /**
     * @throws Storage\Exception
     */
    public function moveToS3(int $imageID): void
    {
        $imageRow = $this->getImageRow($imageID);

        if (! $imageRow) {
            throw new Storage\Exception("Failed to found path for `$imageID`");
        }

        $dir = $this->getDir($imageRow['dir']);
        if (! $dir) {
            throw new Storage\Exception("Dir '{$imageRow['dir']}' not defined");
        }

        if ($imageRow['s3']) {
            return;
        }

        $filePath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow['filepath'];

        $mime = mime_content_type($filePath);
        if (! $mime) {
            throw new Storage\Exception("Failed to detect mime type of file `$filePath`");
        }

        $handle = fopen($filePath, 'r');

        if (! $handle) {
            throw new Storage\Exception("Failed to open file `$filePath`");
        }

        $this->getS3Client()->putObject([
            'Key'         => $imageRow['filepath'],
            'Body'        => $handle,
            'Bucket'      => $dir->getBucket(),
            'ContentType' => $mime,
            'ACL'         => 'public-read',
        ]);
        fclose($handle);

        $this->imageTable->update([
            's3' => 1,
        ], [
            'id' => $imageID,
        ]);

        unlink($filePath);
    }

    public function printBrokenFiles(): void
    {
        $select = $this->imageTable->getSql()->select()
            ->columns(['id', 'filepath', 'dir', 'date_add']);

        $rows = $this->imageTable->selectWith($select);

        foreach ($rows as $row) {
            $dir = $this->getDir($row['dir']);
            if (! $dir) {
                print $row['id'] . ' ' . $row['filepath'] . " - dir '{$row['dir']}' not defined\n";
            } else {
                $filepath = $dir->getPath() . DIRECTORY_SEPARATOR . $row['filepath'];

                if (! file_exists($filepath)) {
                    print $row['id'] . ' ' . $row['date_add'] . ' ' . $filepath . " - file not found\n";
                }
            }
        }
    }

    /**
     * @throws Storage\Exception
     */
    public function fixBrokenFiles(): void
    {
        $select = $this->imageTable->getSql()->select()
            ->columns(['id', 'filepath', 'dir']);

        $rows = $this->imageTable->selectWith($select);

        foreach ($rows as $row) {
            $dir = $this->getDir($row['dir']);
            if (! $dir) {
                print $row['id'] . ' ' . $row['filepath'] . " - dir '{$row['dir']}' not defined. Unable to fix\n";
            } else {
                $filepath = $dir->getPath() . DIRECTORY_SEPARATOR . $row['filepath'];

                if (! file_exists($filepath)) {
                    print $row['id'] . ' ' . $filepath . ' - file not found. ';

                    $fRows = $this->formatedImageTable->select([
                        'formated_image_id' => $row['id'],
                    ]);

                    if (count($fRows)) {
                        foreach ($fRows as $fRow) {
                            $this->flush([
                                'format' => $fRow['format'],
                                'image'  => $fRow['image_id'],
                            ]);
                        }

                        print "Flushed\n";
                    } else {
                        print "Unable to fix\n";
                    }
                }
            }
        }
    }

    /**
     * @throws Storage\Exception
     */
    public function deleteBrokenFiles(string $dirname): void
    {
        $dir = $this->getDir($dirname);
        if (! $dir) {
            throw new Storage\Exception("Dir '{$dirname}' not defined");
        }

        $select = $this->imageTable->getSql()->select()
            ->columns(['id', 'filepath'])
            ->where(['dir' => $dirname]);

        $rows = $this->imageTable->selectWith($select);

        foreach ($rows as $row) {
            $filepath = $dir->getPath() . DIRECTORY_SEPARATOR . $row['filepath'];

            if (! file_exists($filepath)) {
                print $row['id'] . ' ' . $row['filepath'] . " - file not found. ";

                $this->removeImage((int) $row['id']);

                print "Deleted\n";
            }
        }
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
    public function setImageCrop(int $imageId, array $crop): void
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
