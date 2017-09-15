<?php

namespace Autowp\Image;

use Imagick;
use ImagickException;
use Closure;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

use Autowp\Image\Sampler;
use Autowp\Image\Sampler\Format;
use Autowp\Image\Storage\Dir;
use Autowp\Image\Storage\Exception;
use Autowp\Image\Storage\Image;

/**
 * @author dima
 */
class Storage implements StorageInterface
{
    const EXTENSION_DEFAULT = 'jpg';

    const INSERT_MAX_ATTEMPTS = 1000;

    /**
     * @var TableGateway
     */
    private $imageTable = null;

    /**
     * @var string
     */
    private $imageTableName = 'image';

    /**
     * @var TableGateway
     */
    private $formatedImageTable = null;

    /**
     * @var string
     */
    private $formatedImageTableName = 'formated_image';

    /**
     * @var TableGateway
     */
    private $dirTable = null;

    /**
     * @var string
     */
    private $dirTableName = 'image_dir';

    /**
     * @var array
     */
    private $dirs = [];

    /**
     * @var array
     */
    private $formats = [];

    /**
     * @var int
     */
    private $fileMode = 0600;

    /**
     * @var int
     */
    private $dirMode = 0700;

    /**
     * @var string
     */
    private $formatedImageDirName = null;

    /**
     * @var Sampler
     */
    private $imageSampler = null;

    /**
     * @var bool
     */
    private $forceHttps = false;

    /**
     * @param array $options
     * @throws Exception
     */
    public function __construct(
        array $options,
        TableGateway $imageTable,
        TableGateway $formatedImageTable,
        TableGateway $dirTable
    ) {
        $this->setOptions($options);

        $this->imageTable = $imageTable;
        $this->formatedImageTable = $formatedImageTable;
        $this->dirTable = $dirTable;
    }

    /**
     * @param array $options
     * @return Storage
     * @throws Exception
     */
    public function setOptions(array $options)
    {
        foreach ($options as $key => $value) {
            $method = 'set' . ucfirst($key);

            if (! method_exists($this, $method)) {
                throw new Exception("Unexpected option '$key'");
            }

            $this->$method($value);
        }

        return $this;
    }

    /**
     * @param bool $value
     * @return Storage
     */
    public function setForceHttps($value)
    {
        $this->forceHttps = (bool)$value;

        return $this;
    }

    /**
     * @param string $tableName
     * @return Storage
     */
    public function setImageTableName($tableName)
    {
        $this->imageTableName = $tableName;

        return $this;
    }

    /**
     * @param array|Sampler $options
     * @return Storage
     */
    public function setImageSampler($options)
    {
        if (is_array($options)) {
            $options = new Sampler($options);
        }

        if (! $options instanceof Sampler) {
            $message = "Unexpected imageSampler options. Array or object excepcted";
            throw new Exception($message);
        }

        $this->imageSampler = $options;

        return $this;
    }

    /**
     * @return Sampler
     */
    public function getImageSampler()
    {
        if (null === $this->imageSampler) {
            $this->setImageSampler([]);
        }
        return $this->imageSampler;
    }

    /**
     * @param string $tableName
     * @return Storage
     */
    public function setFormatedImageTableName($tableName)
    {
        $this->formatedImageTableName = $tableName;

        return $this;
    }

    /**
     * @param string $tableName
     * @return Storage
     */
    public function setDirTableName($tableName)
    {
        $this->dirTableName = $tableName;

        return $this;
    }

    /**
     * @param string|int $mode
     * @return Storage
     */
    public function setFileMode($mode)
    {
        $this->fileMode = is_string($mode) ? octdec($mode) : (int)$mode;

        return $this;
    }

    /**
     * @param string|int $mode
     * @return Storage
     */
    public function setDirMode($mode)
    {
        $this->dirMode = is_string($mode) ? octdec($mode) : (int)$mode;

        return $this;
    }

    /**
     * @param array $dirs
     * @return Storage
     */
    public function setDirs($dirs)
    {
        $this->dirs = [];

        foreach ($dirs as $dirName => $dir) {
            $this->addDir($dirName, $dir);
        }

        return $this;
    }

    /**
     * @param string $dirName
     * @param Dir|mixed $dir
     * @return Storage
     * @throws Exception
     */
    public function addDir($dirName, $dir)
    {
        if (isset($this->dirs[$dirName])) {
            throw new Exception("Dir '$dirName' alredy registered");
        }
        if (! $dir instanceof Dir) {
            $dir = new Dir($dir);
        }
        $this->dirs[$dirName] = $dir;

        return $this;
    }

    public function getDir(string $dirName): Dir
    {
        return isset($this->dirs[$dirName]) ? $this->dirs[$dirName] : null;
    }

    public function getDirs(): array
    {
        return $this->dirs;
    }

    /**
     * @param array $formats
     * @return Storage
     */
    public function setFormats($formats)
    {
        $this->formats = [];

        foreach ($formats as $formatName => $format) {
            $this->addFormat($formatName, $format);
        }

        return $this;
    }

    /**
     * @param string $formatName
     * @param Format|mixed $format
     * @return Storage
     * @throws Exception
     */
    public function addFormat($formatName, $format)
    {
        if (isset($this->formats[$formatName])) {
            throw new Exception("Format '$formatName' alredy registered");
        }
        if (! $format instanceof Format) {
            $format = new Format($format);
        }
        $this->formats[$formatName] = $format;

        return $this;
    }

    /**
     * @param string $dirName
     * @return Format
     */
    public function getFormat($formatName)
    {
        return isset($this->formats[$formatName]) ? $this->formats[$formatName] : null;
    }

    /**
     * @param string $dirName
     * @return Storage
     */
    public function setFormatedImageDirName($dirName)
    {
        $this->formatedImageDirName = $dirName;

        return $this;
    }

    /**
     * @param array|\ArrayObject $imageRow
     * @return Image
     * @throws Exception
     */
    private function buildImageResult($imageRow)
    {
        $dir = $this->getDir($imageRow['dir']);
        if (! $dir) {
            throw new Exception("Dir '{$imageRow['dir']}' not defined");
        }

        $dirUrl = $dir->getUrl();

        $src = null;
        if ($dirUrl) {
            $path = str_replace('+', '%2B', $imageRow['filepath']);

            $src = $dirUrl . $path;
        }

        if ($this->forceHttps) {
            $src = preg_replace("/^http:/i", "https:", $src);
        }

        return new Image([
            'id'       => $imageRow['id'],
            'width'    => $imageRow['width'],
            'height'   => $imageRow['height'],
            'src'      => $src,
            'filesize' => $imageRow['filesize'],
        ]);
    }

    /**
     * @param array|\ArrayObject $imageRow
     * @return string
     * @throws Exception
     */
    private function buildImageBlobResult($imageRow)
    {
        $dir = $this->getDir($imageRow['dir']);
        if (! $dir) {
            throw new Exception("Dir '{$imageRow['dir']}' not defined");
        }

        $filepath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow['filepath'];

        if (! file_exists($filepath)) {
            throw new Exception("File `$filepath` not found");
        }

        return file_get_contents($filepath);
    }

    /**
     * @param int $imageId
     * @return array|\ArrayObject
     * @throws Exception
     */
    private function getImageRow($imageId)
    {
        $id = (int)$imageId;
        if (strlen($id) != strlen($imageId)) {
            throw new Exception("Image id mus be int. `$imageId` given");
        }

        $imageRow = $this->imageTable->select([
            'id = ?' => $id
        ])->current();

        return $imageRow ? $imageRow : null;
    }

    /**
     * @param array $imageIds
     * @return array|\ArrayObject
     * @throws Exception
     */
    private function getImageRows(array $imageIds)
    {
        $result = [];
        if (count($imageIds)) {
            $result = $this->imageTable->select([
                new Sql\Predicate\In('id', $imageIds)
            ]);
        }

        return $result;
    }

    /**
     * @throws Exception
     * @return Storage\Image|null
     */
    public function getImage(int $imageId)
    {
        $imageRow = $this->getImageRow($imageId);

        return $imageRow ? $this->buildImageResult($imageRow) : null;
    }

    /**
     * @param array $imageIds
     * @return Image
     * @throws Exception
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
     * @param int $imageId
     * @return string
     * @throws Exception
     */
    public function getImageFilepath($imageId)
    {
        $imageRow = $this->getImageRow($imageId);

        if (! $imageRow) {
            return null;
        }

        $dir = $this->getDir($imageRow['dir']);
        if (! $dir) {
            throw new Exception("Dir '{$imageRow['dir']}' not defined");
        }

        return $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow['filepath'];
    }

    /**
     * @param int $imageId
     * @return string|null
     * @throws Exception
     */
    public function getImageBlob(int $imageId)
    {
        $imageRow = $this->getImageRow($imageId);

        return $imageRow ? $this->buildImageBlobResult($imageRow) : null;
    }

    private function getFormatedImageRows(array $requests, $formatName)
    {
        $imagesId = [];
        foreach ($requests as &$request) {
            $request = $this->castRequest($request);

            $imageId = $request->getImageId();
            if (! $imageId) {
                throw new Exception("ImageId not provided");
            }

            $imagesId[] = $imageId;
        }
        unset($request);

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
                    'f.format = ?' => (string)$formatName
                ]);
            foreach ($this->imageTable->selectWith($select) as $row) {
                $destImageRows[] = $row;
            }
        }

        $result = [];

        foreach ($requests as $key => $request) {
            $imageId = $request->getImageId();

            $destImageRow = null;
            foreach ($destImageRows as $row) {
                if ($row['image_id'] == $imageId) {
                    $destImageRow = $row;
                    break;
                }
            }

            if (! $destImageRow) {
                // find source image
                $imageRow = $this->imageTable->select([
                    'id = ?' => $imageId
                ])->current();
                if ($imageRow) {
                    $dir = $this->getDir($imageRow['dir']);
                    if (! $dir) {
                        throw new Exception("Dir '{$imageRow['dir']}' not defined");
                    }

                    $srcFilePath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow['filepath'];

                    if (! file_exists($srcFilePath)) {
                        throw new Exception("File `$srcFilePath` not found");
                    }

                    $imagick = new Imagick();
                    try {
                        $imagick->readImage($srcFilePath);
                    } catch (ImagickException $e) {
                        throw new Exception('Imagick: ' . $e->getMessage());
                        //continue;
                    }

                    // format
                    $format = $this->getFormat($formatName);
                    if (! $format) {
                        throw new Exception("Format `$formatName` not found");
                    }
                    $cFormat = clone $format;

                    $crop = $request->getCrop();
                    if ($crop) {
                        $cFormat->setCrop($crop);
                    }

                    $sampler = $this->getImageSampler();
                    if (! $sampler) {
                        throw new Exception("Image sampler not initialized");
                    }
                    $sampler->convertImagick($imagick, $cFormat);

                    // store result
                    $newPath = implode(DIRECTORY_SEPARATOR, [
                        $imageRow['dir'],
                        $formatName,
                        $imageRow['filepath']
                    ]);
                    $pi = pathinfo($newPath);
                    $formatExt = $cFormat->getFormatExtension();
                    $extension = $formatExt ? $formatExt : $pi['extension'];
                    $formatedImageId = $this->addImageFromImagick(
                        $imagick,
                        $this->formatedImageDirName,
                        [
                            'extension' => $extension,
                            'pattern'   => $pi['dirname'] . DIRECTORY_SEPARATOR . $pi['filename']
                        ]
                    );

                    $imagick->clear();

                    $formatedImageRow = $this->formatedImageTable->select([
                        'format = ?'   => (string)$formatName,
                        'image_id = ?' => $imageId,
                    ])->current();
                    if (! $formatedImageRow) {
                        $this->formatedImageTable->insert([
                            'format'            => (string)$formatName,
                            'image_id'          => $imageId,
                            'formated_image_id' => $formatedImageId
                        ]);
                    } else {
                        $this->formatedImageTable->update([
                            'formated_image_id' => $formatedImageId
                        ], [
                            'format = ?'   => (string)$formatName,
                            'image_id = ?' => $imageId,
                        ]);
                    }

                    // result
                    $destImageRow = $this->imageTable->select([
                        'id = ?' => $formatedImageId
                    ])->current();
                }
            }

            $result[$key] = $destImageRow;
        }

        return $result;
    }

    /**
     * @param Storage\Request $request
     * @param string $formatName
     * @return array|\ArrayObject|null
     */
    private function getFormatedImageRow(Storage\Request $request, $formatName)
    {
        $result = $this->getFormatedImageRows([$request], $formatName);

        if (! isset($result[0])) {
            throw new Exception("getFormatedImageRows fails");
        }

        return $result[0];
    }

    /**
     * @param int|Storage\Request $imageId
     * @return string
     * @throws Exception
     */
    public function getFormatedImageBlob($request, string $formatName)
    {
        if (! $request instanceof Storage\Request) {
            $request = new Storage\Request([
                'imageId' => $request
            ]);
        }

        $row = $this->getFormatedImageRow($request, $formatName);

        return $row === null ? null : $this->buildImageBlobResult($row);
    }

    private function castRequest($request): Storage\Request
    {
        if (is_array($request)) {
            $request = new Storage\Request($request);
        } elseif (! $request instanceof Storage\Request) {
            $request = new Storage\Request([
                'imageId' => $request
            ]);
        }

        return $request;
    }

    /**
     * @param int|Storage\Request $request
     * @param string $format
     * @return Image|null
     */
    public function getFormatedImage($request, string $formatName)
    {
        $row = $this->getFormatedImageRow($this->castRequest($request), $formatName);
        return $row === null ? null : $this->buildImageResult($row);
    }

    /**
     * @param int|Storage\Request $request
     * @param string $format
     * @return string|null
     */
    public function getFormatedImagePath($request, $formatName)
    {
        $request = $this->castRequest($request);

        $imageRow = $this->getFormatedImageRow($request, $formatName);

        if (! $imageRow) {
            return null;
        }

        $dir = $this->getDir($imageRow['dir']);
        if (! $dir) {
            throw new Exception("Dir '{$imageRow['dir']}' not defined");
        }

        $dirPath = $dir->getPath();

        $path = null;
        if ($dirPath) {
            $dirPath = rtrim($dirPath, '/\\') . DIRECTORY_SEPARATOR;
            $path = $dirPath . $imageRow['filepath'];
        }

        return $path;
    }

    /**
     * @param array $images
     * @param string $format
     * @return array
     */
    public function getFormatedImages(array $requests, string $formatName)
    {
        $result = [];
        foreach ($this->getFormatedImageRows($requests, $formatName) as $key => $row) {
            $result[$key] = $row === null ? null : $this->buildImageResult($row);
        }

        return $result;
    }

    /**
     * @param int $imageId
     * @return Image
     * @throws Exception
     */
    public function removeImage(int $imageId)
    {
        $imageRow = $this->imageTable->select([
            'id = ?' => $imageId
        ])->current();

        if (! $imageRow) {
            throw new Exception("Image '$imageId' not found");
        }

        $this->flush([
            'image' => $imageRow['id']
        ]);

        // to save remove formated image
        $this->formatedImageTable->delete([
            'formated_image_id = ?' => $imageRow['id']
        ]);

        // remove file & row
        $dir = $this->getDir($imageRow['dir']);
        if (! $dir) {
            throw new Exception("Dir '{$imageRow['dir']}' not defined");
        }

        $filepath = implode(DIRECTORY_SEPARATOR, [
            rtrim($dir->getPath(), DIRECTORY_SEPARATOR),
            $imageRow['filepath']
        ]);

        // important to delete row first
        $this->imageTable->delete([
            'id = ?' => $imageRow['id']
        ]);

        if (file_exists($filepath)) {
            if (! unlink($filepath)) {
                throw new Exception("Error unlink `$filepath`");
            }
        }

        return $this;
    }

    /**
     * @param string $dirName
     * @param string $ext
     * @param array $options
     * @return string
     * @throws Exception
     */
    private function createImagePath($dirName, array $options = [])
    {
        $dir = $this->getDir($dirName);
        if (! $dir) {
            throw new Exception("Dir '$dirName' not defined");
        }

        $dirPath = $dir->getPath();

        $namingStrategy = $dir->getNamingStrategy();
        if (! $namingStrategy) {
            $message = "Naming strategy not initialized for `$dirName`";
            throw new Exception($message);
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
                throw new Exception("Cannot create dir '$destDir'");
            }
            umask($old);
        }

        return $destFileName;
    }

    /**
     * @param string $path
     * @throws Exception
     */
    private function chmodFile($path)
    {
        if (! chmod($path, $this->fileMode)) {
            throw new Exception("Cannot chmod file '$path'");
        }
    }

    /**
     * @throws Exception
     */
    public function addImageFromBlob(string $blob, string $dirName, array $options = []): int
    {
        $imagick = new Imagick();
        $imagick->readImageBlob($blob);
        $imageId = $this->addImageFromImagick($imagick, $dirName, $options);
        $imagick->clear();

        return $imageId;
    }

    private function indexByAttempt($attempt)
    {
        $from = pow(10, $attempt - 1);
        $to = pow(10, $attempt) - 1;

        return rand($from, $to);
    }

    private function generateLockWrite(string $dirName, array $options, $width, $height, Closure $callback): int
    {
        $dir = $this->getDir($dirName);
        if (! $dir) {
            throw new Exception("Dir '$dirName' not defined");
        }

        $dirPath = $dir->getPath();

        $insertAttemptException = null;
        $imageId = null;
        $attemptIndex = 0;
        do {
            $this->incDirCounter($dirName);

            $destFileName = $this->createImagePath($dirName, array_replace($options, [
                'index' => $this->indexByAttempt($attemptIndex)
            ]));
            $destFilePath = $dirPath . DIRECTORY_SEPARATOR . $destFileName;

            $insertAttemptException = null;

            try {
                // store to db
                $this->imageTable->insert([
                    'width'    => $width,
                    'height'   => $height,
                    'dir'      => $dirName,
                    'filesize' => 0,
                    'filepath' => $destFileName,
                    'date_add' => new Sql\Expression('now()')
                ]);

                $id = $this->imageTable->getLastInsertValue();

                $callback($destFilePath);

                $this->chmodFile($destFilePath);

                $this->imageTable->update([
                    'filesize' => filesize($destFilePath)
                ], [
                    'id' => $id
                ]);

                $imageId = $id;
            } catch (\Exception $e) {
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
     * @throws Exception
     */
    public function addImageFromImagick(Imagick $imagick, string $dirName, array $options = []): int
    {
        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();

        if (! $width || ! $height) {
            throw new Exception("Failed to get image size ($width x $height)");
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
                throw new Exception("Unsupported image type `$format`");
        }

        return $this->generateLockWrite($dirName, $options, $width, $height, function ($filePath) use ($imagick) {
            if (! $imagick->writeImage($filePath)) {
                throw new Exception("Imagick::writeImage error");
            }
        });
    }

    /**
     * @throws Exception
     */
    public function addImageFromFile(string $file, string $dirName, array $options = []): int
    {
        $imageInfo = getimagesize($file);

        $width = (int)$imageInfo[0];
        $height = (int)$imageInfo[1];
        $type = $imageInfo[2];

        if (! $width || ! $height) {
            throw new Exception("Failed to get image size of '$file' ($width x $height)");
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
                    throw new Exception("Unsupported image type `$type`");
            }
            $options['extension'] = $ext;
        }

        return $this->generateLockWrite($dirName, $options, $width, $height, function ($filePath) use ($file) {
            if (! copy($file, $filePath)) {
                throw new Exception("copy error '$file'");
            }
        });
    }

    /**
     * @param array $options
     * @return Storage
     */
    public function flush(array $options)
    {
        $defaults = [
            'format' => null,
            'image'  => null,
        ];

        $options = array_merge($defaults, $options);

        $select = $this->formatedImageTable->getSql()->select();

        if ($options['format']) {
            $select->where([
                $this->formatedImageTableName . '.format = ?' => (string)$options['format']
            ]);
        }

        if ($options['image']) {
            $select->where([
                $this->formatedImageTableName . '.image_id = ?' => (int)$options['image']
            ]);
        }

        $rows = $this->formatedImageTable->selectWith($select);

        foreach ($rows as $row) {
            $this->removeImage($row['formated_image_id']);

            $this->formatedImageTable->delete([
                'image_id' => $row['image_id'],
                'format'   => $row['format']
            ]);
        }

        return $this;
    }

    /**
     * @param string $dirName
     * @return int
     */
    public function getDirCounter($dirName)
    {
        $select = $this->dirTable->getSql()->select()
            ->columns(['count'])
            ->where(['dir = ?' => $dirName]);

        $row = $this->dirTable->selectWith($select)->current();

        if (! $row) {
            return 0;
        }

        return (int)$row['count'];
    }

    /**
     * @param string $dirName
     * @return Storage
     */
    private function incDirCounter($dirName)
    {
        $row = $this->dirTable->select([
            'dir = ?' => $dirName
        ])->current();

        if ($row) {
            $this->dirTable->update([
                'count' => new Sql\Expression('count + 1')
            ], [
                'dir' => $dirName
            ]);
        } else {
            $this->dirTable->insert([
                'dir'   => $dirName,
                'count' => 1
            ]);
        }

        return $this;
    }

    /**
     * @param int $imageId
     * @return boolean|string
     */
    public function getImageIPTC(int $imageId)
    {
        $imageRow = $this->getImageRow($imageId);

        if (! $imageRow) {
            return false;
        }

        $dir = $this->getDir($imageRow['dir']);
        if (! $dir) {
            throw new Exception("Dir '{$imageRow['dir']}' not defined");
        }

        $filepath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow['filepath'];

        if (! file_exists($filepath)) {
            throw new Exception("File `$filepath` not found");
        }

        $iptcStr = '';
        getimagesize($filepath, $info);
        if (is_array($info) && array_key_exists('APP13', $info)) {
            $iptc = iptcparse($info['APP13']);
            if (is_array($iptc)) {
                foreach ($iptc as $key => $value) {
                    $iptcStr .= "<b>IPTC Key:</b> ".htmlspecialchars($key)." <b>Contents:</b> ";
                    foreach ($value as $innerkey => $innervalue) {
                        $iptcStr .= htmlspecialchars($innervalue);
                        if (($innerkey + 1) != count($value)) {
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
     * @param int $imageId
     * @return boolean|array
     */
    public function getImageEXIF(int $imageId)
    {
        $imageRow = $this->getImageRow($imageId);

        if (! $imageRow) {
            return false;
        }

        $dir = $this->getDir($imageRow['dir']);
        if (! $dir) {
            throw new Exception("Dir '{$imageRow['dir']}' not defined");
        }

        $filepath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow['filepath'];

        if (! file_exists($filepath)) {
            throw new Exception("File `$filepath` not found");
        }

        return @exif_read_data($filepath, null, true);
    }

    /**
     * @param int $imageId
     * @return boolean|array
     */
    public function getImageResolution(int $imageId)
    {
        $imageRow = $this->getImageRow($imageId);

        if (! $imageRow) {
            return false;
        }

        $dir = $this->getDir($imageRow['dir']);
        if (! $dir) {
            throw new Exception("Dir '{$imageRow['dir']}' not defined");
        }

        $filepath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow['filepath'];

        if (! file_exists($filepath)) {
            throw new Exception("File `$filepath` not found");
        }

        $imagick = new Imagick();
        $imagick->readImage($filepath);

        try {
            $info = $imagick->identifyImage();
        } catch (ImagickException $e) {
            return false;
        }

        $x = $info['resolution']['x'];
        $y = $info['resolution']['x'];

        if (! $x || ! $y) {
            return false;
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
                throw new Exception("Unexpected resolution unit `{$info['units']}`");
        }

        return [
            'x' => $x,
            'y' => $y
        ];
    }

    private static function detectExtenstion($filepath)
    {
        $imageInfo = getimagesize($filepath);

        $imageType = $imageInfo[2];

        // подбираем имя для файла
        switch ($imageType) {
            case IMAGETYPE_JPEG:
            case IMAGETYPE_PNG:
                break;
            default:
                throw new Exception("Unsupported image type");
        }
        return image_type_to_extension($imageType, false);
    }

    /**
     * @throws Exception
     */
    public function changeImageName(int $imageId, array $options = [])
    {
        $imageRow = $this->getImageRow($imageId);
        if (! $imageRow) {
            throw new Exception("Image `$imageId` not found");
        }

        $dir = $this->getDir($imageRow['dir']);
        if (! $dir) {
            throw new Exception("Dir '{$imageRow['dir']}' not defined");
        }

        $dirPath = $dir->getPath();

        $oldFilePath = $dirPath . DIRECTORY_SEPARATOR . $imageRow['filepath'];

        if (! isset($options['extension'])) {
            $options['extension'] = self::detectExtenstion($oldFilePath);
        }

        $attemptIndex = 0;
        $insertAttemptException = null;

        do {
            $destFileName = $this->createImagePath($imageRow['dir'], array_replace($options, [
                'index' => $this->indexByAttempt($attemptIndex)
            ]));
            $destFilePath = $dirPath . DIRECTORY_SEPARATOR . $destFileName;

            $insertAttemptException = null;

            try {
                if ($destFileName == $imageRow['filepath']) {
                    throw new Exception("Trying to rename to self");
                }

                $this->imageTable->update([
                    'filepath' => $destFileName
                ], [
                    'id' => $imageRow['id']
                ]);
            } catch (\Exception $e) {
                // duplicate or other error
                $insertAttemptException = $e;
            }

            if (! $insertAttemptException) {
                $success = rename($oldFilePath, $destFilePath);
                if (! $success) {
                    throw new Exception("Failed to move file");
                }

                $this->chmodFile($destFilePath);
            }

            $attemptIndex++;
        } while (($attemptIndex < self::INSERT_MAX_ATTEMPTS) && $insertAttemptException);

        if ($insertAttemptException) {
            throw $insertAttemptException;
        }
    }

    public function flop(int $imageId)
    {
        $filePath = $this->getImageFilepath($imageId);
        if (! $filePath) {
            throw new Exception("Failed to found path for `$imageId`");
        }

        $imagick = new Imagick();
        $imagick->readImage($filePath);

        // format
        $imagick->flopImage();

        $imagick->writeImage($filePath);

        $imagick->clear();

        $this->flush([
            'image' => $imageId
        ]);
    }

    public function normalize(int $imageId)
    {
        $filePath = $this->getImageFilepath($imageId);
        if (! $filePath) {
            throw new Exception("Failed to found path for `$imageId`");
        }

        $imagick = new Imagick();
        $imagick->readImage($filePath);

        // format
        $imagick->normalizeImage();

        $imagick->writeImage($filePath);

        $imagick->clear();

        $this->flush([
            'image' => $imageId
        ]);
    }

    public function printBrokenFiles()
    {
        $select = $this->imageTable->getSql()->select()
            ->columns(['id', 'filepath', 'dir']);

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

    public function fixBrokenFiles()
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
                        'formated_image_id = ?' => $row['id']
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

    public function deleteBrokenFiles(string $dirname)
    {
        $dir = $this->getDir($dirname);
        if (! $dir) {
            throw new Exception("Dir '{$dirname}' not defined");
        }

        $select = $this->imageTable->getSql()->select()
            ->columns(['id', 'filepath'])
            ->where(['dir = ?' => $dirname]);

        $rows = $this->imageTable->selectWith($select);

        foreach ($rows as $row) {
            $filepath = $dir->getPath() . DIRECTORY_SEPARATOR . $row['filepath'];

            if (! file_exists($filepath)) {
                print $row['id'] . ' ' . $row['filepath'] . " - file not found. ";

                $this->removeImage($row['id']);

                print "Deleted\n";
            }
        }
    }

    public function hasFormat(string $format): bool
    {
        return (bool) $this->getFormat($format);
    }
}
