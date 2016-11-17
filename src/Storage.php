<?php

namespace Autowp\Image;

use Imagick;
use ImagickException;
use Closure;

use Zend_Db_Adapter_Abstract;
use Zend_Db_Exception;
use Zend_Db_Expr;
use Zend_Db_Table_Abstract;
use Zend_Db_Table_Row;

use Autowp\Image\Sampler;
use Autowp\Image\Sampler\Format;
use Autowp\Image\Storage\DbTable\Image as ImageTable;
use Autowp\Image\Storage\DbTable\FormatedImage as FormatedImageTable;
use Autowp\Image\Storage\DbTable\Dir as DirTable;
use Autowp\Image\Storage\Dir;
use Autowp\Image\Storage\Exception;
use Autowp\Image\Storage\Image;
use Autowp\Image\Storage\Request;

/**
 * @author dima
 */
class Storage
{
    const EXTENSION_DEFAULT = 'jpg';

    const LOCK_MAX_ATTEMPTS = 10;

    const INSERT_MAX_ATTEMPTS = 10;

    /**
     * Zend_Db_Adapter_Abstract object.
     *
     * @var Zend_Db_Adapter_Abstract
     */
    private $db = null;

    /**
     * @var ImageTable
     */
    private $imageTable = null;

    /**
     * @var string
     */
    private $imageTableName = 'image';

    /**
     * @var FormatedImageTable
     */
    private $formatedImageTable = null;

    /**
     * @var string
     */
    private $formatedImageTableName = 'formated_image';

    /**
     * @var DirTable
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
     * @var string
     */
    private $useLocks = true;

    /**
     * @param array $options
     * @throws Exception
     */
    public function __construct(array $options = [])
    {
        $this->setOptions($options);
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
                $this->raise("Unexpected option '$key'");
            }

            $this->$method($value);
        }

        return $this;
    }

    /**
     * @param bool $value
     * @return Storage
     */
    public function setUseLocks($value)
    {
        $this->useLocks = (bool)$value;

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
        if ($options instanceof Sampler) {
            $imageSampler = $options;
        } elseif (is_array($options)) {
            $imageSampler = new Sampler($options);
        } else {
            $message = "Unexpected imageSampler options. Array or object excepcted";
            return $this->raise($message);
        }

        $this->imageSampler = $imageSampler;

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
     * @param Zend_Db_Adapter_Abstract $dbAdapter
     * @return Storage
     */
    public function setDbAdapter(Zend_Db_Adapter_Abstract $dbAdapter)
    {
        $this->db = $dbAdapter;

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
            $this->raise("Dir '$dirName' alredy registered");
        }
        if (! $dir instanceof Dir) {
            $dir = new Dir($dir);
        }
        $this->dirs[$dirName] = $dir;

        return $this;
    }

    /**
     * @param string $dirName
     * @return Dir
     */
    public function getDir($dirName)
    {
        return isset($this->dirs[$dirName]) ? $this->dirs[$dirName] : null;
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
            $this->raise("Format '$formatName' alredy registered");
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
     * @param string $message
     * @throws Exception
     */
    private function raise($message)
    {
        throw new Exception($message);
    }

    /**
     * @return ImageTable
     */
    private function getImageTable()
    {
        if (null === $this->imageTable) {
            $this->imageTable = new ImageTable([
                Zend_Db_Table_Abstract::ADAPTER => $this->db,
                Zend_Db_Table_Abstract::NAME    => $this->imageTableName,
            ]);
        }

        return $this->imageTable;
    }

    /**
     * @return FormatedImageTable
     */
    private function getFormatedImageTable()
    {
        if (null === $this->formatedImageTable) {
            $this->formatedImageTable = new FormatedImageTable([
                Zend_Db_Table_Abstract::ADAPTER => $this->db,
                Zend_Db_Table_Abstract::NAME    => $this->formatedImageTableName,
            ]);
        }

        return $this->formatedImageTable;
    }

    /**
     * @return DirTable
     */
    private function getDirTable()
    {
        if (null === $this->dirTable) {
            $this->dirTable = new DirTable([
                Zend_Db_Table_Abstract::ADAPTER => $this->db,
                Zend_Db_Table_Abstract::NAME    => $this->dirTableName,
            ]);
        }

        return $this->dirTable;
    }

    /**
     * @param Zend_Db_Table_Row $imageRow
     * @return Image
     * @throws Exception
     */
    private function buildImageResult(Zend_Db_Table_Row $imageRow)
    {
        $dir = $this->getDir($imageRow->dir);
        if (! $dir) {
            $this->raise("Dir '{$imageRow->dir}' not defined");
        }

        $dirUrl = $dir->getUrl();

        $src = null;
        if ($dirUrl) {
            $path = str_replace('+', '%2B', $imageRow->filepath);

            $src = $dirUrl . $path;
        }

        if ($this->forceHttps) {
            $src = preg_replace("/^http:/i", "https:", $src);
        }

        return new Image([
            'width'    => $imageRow->width,
            'height'   => $imageRow->height,
            'src'      => $src,
            'filesize' => $imageRow->filesize,
        ]);
    }

    /**
     * @param Zend_Db_Table_Row $imageRow
     * @return string
     * @throws Exception
     */
    private function buildImageBlobResult(Zend_Db_Table_Row $imageRow)
    {
        $dir = $this->getDir($imageRow->dir);
        if (! $dir) {
            $this->raise("Dir '{$imageRow->dir}' not defined");
        }

        $filepath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow->filepath;

        if (! file_exists($filepath)) {
            return $this->raise("File `$filepath` not found");
        }

        return file_get_contents($filepath);
    }

    /**
     * @param int $imageId
     * @return Zend_Db_Table_Row
     * @throws Exception
     */
    private function getImageRow($imageId)
    {
        $id = (int)$imageId;
        if (strlen($id) != strlen($imageId)) {
            return $this->raise("Image id mus be int. `$imageId` given");
        }

        $imageRow = $this->getImageTable()->fetchRow([
            'id = ?' => $id
        ]);

        return $imageRow ? $imageRow : null;
    }

    /**
     * @param array $imageIds
     * @return Zend_Db_Table_Row
     * @throws Exception
     */
    private function getImageRows(array $imageIds)
    {
        $result = [];
        if (count($imageIds)) {
            $result = $this->getImageTable()->fetchAll([
                'id in (?)' => $imageIds
            ]);
        }

        return $result;
    }

    /**
     * @param int $imageId
     * @return Image
     * @throws Exception
     */
    public function getImage($imageId)
    {
        $imageRow = $this->getImageRow($imageId);

        return $imageRow ? $this->buildImageResult($imageRow) : null;
    }

    /**
     * @param array $imageIds
     * @return Image
     * @throws Exception
     */
    public function getImages(array $imageIds)
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
    public function getImageBlob($imageId)
    {
        $imageRow = $this->getImageRow($imageId);

        return $imageRow ? $this->buildImageBlobResult($imageRow) : null;
    }

    private function getFormatedImageRows(array $requests, $formatName)
    {
        $imageTable = $this->getImageTable();

        $imagesId = [];
        foreach ($requests as $request) {
            if (! $request instanceof Request) {
                return $this->raise('$requests is not array of Autowp\Image\Storage\Request');
            }
            $imageId = $request->getImageId();
            if (! $imageId) {
                $this->raise("ImageId not provided");
            }

            $imagesId[] = $imageId;
        }

        if (count($imagesId)) {
            $destImageRows = $imageTable->fetchAll(
                $imageTable->select(true)
                    ->setIntegrityCheck(false) // to fetch image_id
                    ->join(
                        ['f' => $this->formatedImageTableName],
                        $this->imageTableName . '.id = f.formated_image_id',
                        'image_id'
                    )
                    ->where('f.image_id in (?)', $imagesId)
                    ->where('f.format = ?', (string)$formatName)
            );
        } else {
            $destImageRows = [];
        }

        $result = [];

        foreach ($requests as $key => $request) {
            $imageId = $request->getImageId();

            $destImageRow = null;
            foreach ($destImageRows as $row) {
                if ($row->image_id == $imageId) {
                    $destImageRow = $row;
                    break;
                }
            }

            if (! $destImageRow) {
                // find source image
                $imageRow = $this->getImageTable()->fetchRow([
                    'id = ?' => $imageId
                ]);
                if (! $imageRow) {
                    $this->raise("Image `$imageId` not found");
                }

                $dir = $this->getDir($imageRow->dir);
                if (! $dir) {
                    $this->raise("Dir '{$imageRow->dir}' not defined");
                }

                $srcFilePath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow->filepath;

                $imagick = new Imagick();
                try {
                    $imagick->readImage($srcFilePath);
                } catch (ImagickException $e) {
                    //$this->raise('Imagick: ' . $e->getMessage());
                    continue;
                }

                // format
                $format = $this->getFormat($formatName);
                if (! $format) {
                    $this->raise("Format `$formatName` not found");
                }
                $cFormat = clone $format;

                $crop = $request->getCrop();
                if ($crop) {
                    $cFormat->setCrop($crop);
                }

                $sampler = $this->getImageSampler();
                if (! $sampler) {
                    return $this->raise("Image sampler not initialized");
                }
                $sampler->convertImagick($imagick, $cFormat);

                // store result
                $newPath = implode(DIRECTORY_SEPARATOR, [
                    $imageRow->dir,
                    $formatName,
                    $imageRow->filepath
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

                $formatedImageTable = $this->getFormatedImageTable();
                $formatedImageRow = $formatedImageTable->fetchRow([
                    'format = ?'   => (string)$formatName,
                    'image_id = ?' => $imageId,
                ]);
                if (! $formatedImageRow) {
                    $formatedImageRow = $formatedImageTable->createRow([
                        'format'            => (string)$formatName,
                        'image_id'          => $imageId,
                        'formated_image_id' => $formatedImageId
                    ]);
                } else {
                    $formatedImageRow->formated_image_id = $formatedImageId;
                }
                $formatedImageRow->save();

                // result
                $destImageRow = $this->getImageTable()->fetchRow([
                    'id = ?' => $formatedImageId
                ]);
            }

            $result[$key] = $destImageRow;
        }

        return $result;
    }

    /**
     * @param Request $request
     * @param string $formatName
     * @return Zend_Db_Table_Row
     */
    private function getFormatedImageRow(Request $request, $formatName)
    {
        $result = $this->getFormatedImageRows([$request], $formatName);

        if (! isset($result[0])) {
            $this->raise("getFormatedImageRows fails");
        }

        return $result[0];
    }

    /**
     * @param int|Request $imageId
     * @return string
     * @throws Exception
     */
    public function getFormatedImageBlob($request, $formatName)
    {
        if (! $request instanceof Request) {
            $request = new Request([
                'imageId' => $request
            ]);
        }

        return $this->buildImageBlobResult(
            $this->getFormatedImageRow($request, $formatName)
        );
    }

    /**
     * @param int|Request $request
     * @param string $format
     * @return Image
     */
    public function getFormatedImage($request, $formatName)
    {
        if (is_array($request)) {
            $request = new Request($request);
        } elseif (! $request instanceof Request) {
            $request = new Request([
                'imageId' => $request
            ]);
        }

        return $this->buildImageResult(
            $this->getFormatedImageRow($request, $formatName)
        );
    }

    /**
     * @param array $images
     * @param string $format
     * @return array
     */
    public function getFormatedImages(array $requests, $formatName)
    {
        $result = [];
        foreach ($this->getFormatedImageRows($requests, $formatName) as $key => $row) {
            $result[$key] = $this->buildImageResult($row);
        }

        return $result;
    }

    /**
     * @param int $imageId
     * @return Image
     * @throws Exception
     */
    public function removeImage($imageId)
    {
        $imageTable = $this->getImageTable();

        $imageRow = $imageTable->fetchRow([
            'id = ?' => (int)$imageId
        ]);

        if (! $imageRow) {
            $this->raise("Image '$imageId' not found");
        }

        $this->flush([
            'image' => $imageRow->id
        ]);

        // to save remove formated image
        $this->getFormatedImageTable()->delete([
            'formated_image_id = ?' => $imageRow->id
        ]);

        // remove file & row
        $dir = $this->getDir($imageRow->dir);
        if (! $dir) {
            $this->raise("Dir '{$imageRow->dir}' not defined");
        }

        $filepath = implode(DIRECTORY_SEPARATOR, [
            rtrim($dir->getPath(), DIRECTORY_SEPARATOR),
            $imageRow->filepath
        ]);

        // important to delete row first
        $imageRow->delete();

        if (file_exists($filepath)) {
            if (! unlink($filepath)) {
                return $this->raise("Error unlink `$filepath`");
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
            $this->raise("Dir '$dirName' not defined");
        }

        $dirPath = $dir->getPath();

        $namingStrategy = $dir->getNamingStrategy();
        if (! $namingStrategy) {
            $message = "Naming strategy not initialized for `$dirName`";
            $this->raise($message);
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
                $this->raise("Cannot create dir '$destDir'");
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
            $this->raise("Cannot chmod file '$path'");
        }
    }

    /**
     * @param string $blob
     * @param string $dirName
     * @param array $options
     * @throws Exception
     */
    public function addImageFromBlob($blob, $dirName, array $options = [])
    {
        $imagick = new Imagick();
        $imagick->readImageBlob($blob);
        $imageId = $this->addImageFromImagick($imagick, $dirName, $options);
        $imagick->clear();

        return $imageId;
    }

    /**
     * @param string $dirName
     * @param array $options
     * @param Closure $callback
     * @return string
     */
    private function lockFile($dirName, array $options, Closure $callback)
    {
        $dir = $this->getDir($dirName);
        if (! $dir) {
            $this->raise("Dir '$dirName' not defined");
        }

        $dirPath = $dir->getPath();

        $lockAttemptsLeft = self::LOCK_MAX_ATTEMPTS;
        $fileSuccess = false;
        do {
            $lockAttemptsLeft--;

            $destFileName = $this->createImagePath($dirName, $options);
            $destFilePath = $dirPath . DIRECTORY_SEPARATOR . $destFileName;

            $fp = fopen($destFilePath, 'c+');
            if (! $fp) {
                $this->raise("Cannot open file '$destFilePath'");
            }

            if ($this->useLocks) {
                if (! flock($fp, LOCK_EX | LOCK_NB)) {
                    // already locked, try next file
                    return $this->raise("already locked, try next file");
                    fclose($fp);
                    continue;
                }
            }

            if (false !== fgetc($fp)) {
                // not empty, try next file
                return $this->raise("not empty, try next file $destFilePath");
                if ($this->useLocks) {
                    flock($fp, LOCK_UN);
                }
                fclose($fp);
                continue;
            }

            $callback($fp);

            if ($this->useLocks) {
                flock($fp, LOCK_UN);
            }
            fclose($fp);

            $fileSuccess = true;
        } while (($lockAttemptsLeft > 0) && ! $fileSuccess);

        if (! $fileSuccess) {
            return $this->raise("Cannot save to `$destFilePath` after few attempts");
        }

        return $destFileName;
    }

    /**
     * @param string $dirName
     * @param array $options
     * @param int $width
     * @param int $height
     * @param Closure $callback
     * @return int
     */
    private function generateLockWrite($dirName, array $options, $width, $height, Closure $callback)
    {
        $dir = $this->getDir($dirName);
        if (! $dir) {
            $this->raise("Dir '$dirName' not defined");
        }

        $dirPath = $dir->getPath();

        $this->incDirCounter($dirName);

        $insertAttemptsLeft = self::INSERT_MAX_ATTEMPTS;
        $insertAttemptException = null;
        do {
            $destFileName = $this->lockFile($dirName, $options, $callback);

            $filePath = $dirPath . DIRECTORY_SEPARATOR . $destFileName;

            $this->chmodFile($filePath);

            // store to db
            $imageRow = $this->getImageTable()->createRow([
                'width'    => $width,
                'height'   => $height,
                'dir'      => $dirName,
                'filesize' => filesize($filePath),
                'filepath' => $destFileName,
                'date_add' => new Zend_Db_Expr('now()')
            ]);
            try {
                $imageRow->save();
                $insertAttemptException = null;
            } catch (Zend_Db_Exception $e) {
                // duplicate or other error
                $insertAttemptException = $e;
            }
        } while (($insertAttemptsLeft > 0) && $insertAttemptException);

        if ($insertAttemptException) {
            throw $insertAttemptException;
        }

        return $imageRow->id;
    }

    /**
     * @param Imagick $imagick
     * @param string $dirName
     * @param array $options
     * @throws Exception
     */
    public function addImageFromImagick(Imagick $imagick, $dirName, array $options = [])
    {
        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();

        if (! $width || ! $height) {
            $this->raise("Failed to get image size ($width x $height)");
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
                $this->raise("Unsupported image type `$format`");
        }

        return $this->generateLockWrite($dirName, $options, $width, $height, function ($fp) use ($imagick) {
            if (! $imagick->writeImageFile($fp)) {
                $this->raise("Imagick::writeImageFile error");
            }
        });
    }

    /**
     * @param string $file
     * @param string $dirName
     * @param array $options
     * @throws Exception
     */
    public function addImageFromFile($file, $dirName, array $options = [])
    {
        $imageInfo = getimagesize($file);

        $width = (int)$imageInfo[0];
        $height = (int)$imageInfo[1];
        $type = $imageInfo[2];

        if (! $width || ! $height) {
            $this->raise("Failed to get image size of '$file' ($width x $height)");
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
                    $this->raise("Unsupported image type `$type`");
            }
            $options['extension'] = $ext;
        }

        return $this->generateLockWrite($dirName, $options, $width, $height, function ($fp) use ($file) {
            /**
             * @todo buffered read-write
             */
            if (! fwrite($fp, file_get_contents($file))) {
                $this->raise("fwrite error '$file'");
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

        $select = $this->getFormatedImageTable()->select(true);

        if ($options['format']) {
            $select->where($this->formatedImageTableName . '.format = ?', (string)$options['format']);
        }

        if ($options['image']) {
            $select->where($this->formatedImageTableName . '.image_id = ?', (int)$options['image']);
        }

        $rows = $this->getFormatedImageTable()->fetchAll($select);

        foreach ($rows as $row) {
            $this->removeImage($row->formated_image_id);
            $row->delete();
        }

        return $this;
    }

    /**
     * @param string $dirName
     * @return int
     */
    public function getDirCounter($dirName)
    {
        $dirTable = $this->getDirTable();
        $adapter = $dirTable->getAdapter();
        return (int)$adapter->fetchOne(
            $adapter->select()
                ->from($dirTable->info('name'), 'count')
                ->where('dir = ?', $dirName)
        );
    }

    /**
     * @param string $dirName
     * @return Storage
     */
    public function incDirCounter($dirName)
    {
        $dirTable = $this->getDirTable();

        $row = $dirTable->fetchRow([
            'dir = ?' => $dirName
        ]);

        if ($row) {
            $row->count = new Zend_Db_Expr('count + 1');
        } else {
            $row = $dirTable->createRow([
                'dir'   => $dirName,
                'count' => 1
            ]);
        }

        $row->save();

        return $this;
    }

    /**
     * @param int $imageId
     * @return boolean|string
     */
    public function getImageIPTC($imageId)
    {
        $imageRow = $this->getImageRow($imageId);

        if (! $imageRow) {
            return false;
        }

        $dir = $this->getDir($imageRow->dir);
        if (! $dir) {
            return $this->raise("Dir '{$imageRow->dir}' not defined");
        }

        $filepath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow->filepath;

        if (! file_exists($filepath)) {
            return $this->raise("File `$filepath` not found");
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
    public function getImageEXIF($imageId)
    {
        $imageRow = $this->getImageRow($imageId);

        if (! $imageRow) {
            return false;
        }

        $dir = $this->getDir($imageRow->dir);
        if (! $dir) {
            return $this->raise("Dir '{$imageRow->dir}' not defined");
        }

        $filepath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow->filepath;

        if (! file_exists($filepath)) {
            return $this->raise("File `$filepath` not found");
        }

        return exif_read_data($filepath, null, true);
    }

    public static function detectExtenstion($filepath)
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
     * @param int $imageId
     * @param array $options
     * @throws Exception
     */
    public function changeImageName($imageId, array $options = [])
    {
        $imageRow = $this->getImageRow($imageId);
        if (! $imageRow) {
            return $this->raise("Image `$imageId` not found");
        }

        $dir = $this->getDir($imageRow->dir);
        if (! $dir) {
            return $this->raise("Dir '{$imageRow->dir}' not defined");
        }

        $dirPath = $dir->getPath();

        $oldFilePath = $dirPath . DIRECTORY_SEPARATOR . $imageRow->filepath;

        if (! isset($options['extension'])) {
            $options['extension'] = self::detectExtenstion($oldFilePath);
        }

        $insertAttemptsLeft = self::INSERT_MAX_ATTEMPTS;
        $insertAttemptException = null;
        do {
            $destFileName = $this->lockFile($imageRow->dir, $options, function ($fp) use ($imageRow) {
                fwrite($fp, $this->buildImageBlobResult($imageRow));
            });

            $filePath = $dirPath . DIRECTORY_SEPARATOR . $destFileName;

            $this->chmodFile($filePath);

            // store to db
            $imageRow->setFromArray([
                'filepath' => $destFileName
            ]);
            try {
                $imageRow->save();
                $insertAttemptException = null;
            } catch (Zend_Db_Exception $e) {
                // duplicate or other error
                $insertAttemptException = $e;
            }
        } while (($insertAttemptsLeft > 0) && $insertAttemptException);

        if ($insertAttemptException) {
            throw $insertAttemptException;
        }

        // remove old file
        if (file_exists($oldFilePath)) {
            unlink($oldFilePath);
        }
    }

    /**
     * @param string $file
     * @param string $dirName
     * @throws Exception
     * @return int
     */
    public function registerImageFile($file, $dirName)
    {
        $dir = $this->getDir($dirName);
        if (! $dir) {
            $this->raise("Dir '$dirName' not defined");
        }

        $dirPath = $dir->getPath();

        $filePath = $dirPath . DIRECTORY_SEPARATOR . $file;
        if (! $filePath) {
            throw new Exception("File `$filePath` not found");
        }

        $imageInfo = getimagesize($filePath);

        // store to db
        $imageRow = $this->getImageTable()->createRow([
            'width'    => $imageInfo[0],
            'height'   => $imageInfo[1],
            'dir'      => $dirName,
            'filesize' => filesize($filePath),
            'filepath' => $file,
            'date_add' => new Zend_Db_Expr('now()')
        ]);
        $imageRow->save();

        return $imageRow->id;
    }

    public function flop($imageId)
    {
        $imageRow = $this->getImageRow($imageId);
        if (! $imageRow) {
            return $this->raise("Image `$imageId` not found");
        }

        $dir = $this->getDir($imageRow->dir);
        if (! $dir) {
            $this->raise("Dir '{$imageRow->dir}' not defined");
        }

        $filePath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow->filepath;

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

    public function normalize($imageId)
    {
        $imageRow = $this->getImageRow($imageId);
        if (! $imageRow) {
            return $this->raise("Image `$imageId` not found");
        }

        $dir = $this->getDir($imageRow->dir);
        if (! $dir) {
            $this->raise("Dir '{$imageRow->dir}' not defined");
        }

        $filePath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow->filepath;

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
        $imageTable = $this->getImageTable();

        $db = $imageTable->getAdapter();

        $rows = $db->fetchAll(
            $db->select()
                ->from($imageTable->info('name'), ['id', 'filepath', 'dir'])
        );

        foreach ($rows as $row) {
            $dir = $this->getDir($row['dir']);
            if (! $dir) {
                print $row['id'] . ' ' . $row['filepath'] . " - dir '{$row['dir']}' not defined\n";
            } else {
                $filepath = $dir->getPath() . '/' . $row['filepath'];

                if (! file_exists($filepath)) {
                    print $row['id'] . ' ' . $row['date_add'] . ' ' . $filepath . " - file not found\n";
                }
            }
        }
    }

    public function fixBrokenFiles()
    {
        $imageTable = $this->getImageTable();
        $formatedImageTable = $this->getFormatedImageTable();

        $db = $imageTable->getAdapter();

        $rows = $db->fetchAll(
            $db->select()
                ->from($imageTable->info('name'), ['id', 'filepath', 'dir'])
        );

        foreach ($rows as $row) {
            $dir = $this->getDir($row['dir']);
            if (! $dir) {
                print $row['id'] . ' ' . $row['filepath'] . " - dir '{$row['dir']}' not defined. Unable to fix\n";
            } else {
                $filepath = $dir->getPath() . '/' . $row['filepath'];

                if (! file_exists($filepath)) {
                    print $row['id'] . ' ' . $filepath . ' - file not found. ';

                    $fRows = $formatedImageTable->fetchAll([
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

    public function deleteBrokenFiles($dirname)
    {
        $dir = $this->getDir($dirname);
        if (! $dir) {
            $this->raise("Dir '{$dirname}' not defined");
        }

        $imageTable = $this->getImageTable();

        $db = $imageTable->getAdapter();

        $rows = $db->fetchAll(
            $db->select()
                ->from($imageTable->info('name'), ['id', 'filepath'])
                ->where('dir = ?', $dirname)
        );

        foreach ($rows as $row) {
            $filepath = $dir->getPath() . '/' . $row['filepath'];

            if (! file_exists($filepath)) {
                print $row['id'] . ' ' . $row['filepath'] . " - file not found. ";

                $this->removeImage($row['id']);

                print "Deleted\n";
            }
        }
    }
}
