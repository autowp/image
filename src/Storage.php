<?php

namespace Autowp\Image;

use Imagick;
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
    private $_db = null;

    /**
     * @var ImageTable
     */
    private $_imageTable = null;

    /**
     * @var string
     */
    private $_imageTableName = 'image';

    /**
     * @var FormatedImageTable
     */
    private $_formatedImageTable = null;

    /**
     * @var string
     */
    private $_formatedImageTableName = 'formated_image';

    /**
     * @var DirTable
     */
    private $_dirTable = null;

    /**
     * @var string
     */
    private $_dirTableName = 'image_dir';

    /**
     * @var array
     */
    private $_dirs = array();

    /**
     * @var array
     */
    private $_formats = array();

    /**
     * @var int
     */
    private $_fileMode = 0600;

    /**
     * @var int
     */
    private $_dirMode = 0700;

    /**
     * @var string
     */
    private $_formatedImageDirName = null;

    /**
     * @var Sampler
     */
    private $_imageSampler = null;

    /**
     * @var boll
     */
    private $_forceHttps = false;

    /**
     * @param array $options
     * @throws Exception
     */
    public function __construct(array $options = array())
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

            if (method_exists($this, $method)) {
                $this->$method($value);
            } else {
                $this->_raise("Unexpected option '$key'");
            }
        }

        return $this;
    }

    /**
     * @param bool $value
     * @return Storage
     */
    public function setForceHttps($value)
    {
        $this->_forceHttps = (bool)$value;

        return $this;
    }

    /**
     * @param string $tableName
     * @return Storage
     */
    public function setImageTableName($tableName)
    {
        $this->_imageTableName = $tableName;

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
            return $this->_raise($message);
        }

        $this->_imageSampler = $imageSampler;

        return $this;
    }

    /**
     * @return Sampler
     */
    public function getImageSampler()
    {
        if (null === $this->_imageSampler) {
            $this->setImageSampler(array());
        }
        return $this->_imageSampler;
    }

    /**
     * @param string $tableName
     * @return Storage
     */
    public function setFormatedImageTableName($tableName)
    {
        $this->_formatedImageTableName = $tableName;

        return $this;
    }

    /**
     * @param string $tableName
     * @return Storage
     */
    public function setDirTableName($tableName)
    {
        $this->_dirTableName = $tableName;

        return $this;
    }

    /**
     * @param Zend_Db_Adapter_Abstract $dbAdapter
     * @return Storage
     */
    public function setDbAdapter(Zend_Db_Adapter_Abstract $dbAdapter)
    {
        $this->_db = $dbAdapter;

        return $this;
    }

    /**
     * @param string|int $mode
     * @return Storage
     */
    public function setFileMode($mode)
    {
        $this->_fileMode = is_string($mode) ? octdec($mode) : (int)$mode;

        return $this;
    }

    /**
     * @param string|int $mode
     * @return Storage
     */
    public function setDirMode($mode)
    {
        $this->_dirMode = is_string($mode) ? octdec($mode) : (int)$mode;

        return $this;
    }

    /**
     * @param array $dirs
     * @return Storage
     */
    public function setDirs($dirs)
    {
        $this->_dirs = array();

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
        if (isset($this->_dirs[$dirName])) {
            $this->_raise("Dir '$dirName' alredy registered");
        }
        if (!$dir instanceof Dir) {
            $dir = new Dir($dir);
        }
        $this->_dirs[$dirName] = $dir;

        return $this;
    }

    /**
     * @param string $dirName
     * @return Dir
     */
    public function getDir($dirName)
    {
        return isset($this->_dirs[$dirName]) ? $this->_dirs[$dirName] : null;
    }

    /**
     * @param array $formats
     * @return Storage
     */
    public function setFormats($formats)
    {
        $this->_formats = array();

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
        if (isset($this->_formats[$formatName])) {
            $this->_raise("Format '$formatName' alredy registered");
        }
        if (!$format instanceof Format) {
            $format = new Format($format);
        }
        $this->_formats[$formatName] = $format;

        return $this;
    }

    /**
     * @param string $dirName
     * @return Format
     */
    public function getFormat($formatName)
    {
        return isset($this->_formats[$formatName]) ? $this->_formats[$formatName] : null;
    }

    /**
     * @param string $dirName
     * @return Storage
     */
    public function setFormatedImageDirName($dirName)
    {
        $this->_formatedImageDirName = $dirName;

        return $this;
    }

    /**
     * @param string $message
     * @throws Exception
     */
    private function _raise($message)
    {
        throw new Exception($message);
    }

    /**
     * @return ImageTable
     */
    private function _getImageTable()
    {
        if (null === $this->_imageTable) {
            $this->_imageTable = new ImageTable(array(
                Zend_Db_Table_Abstract::ADAPTER => $this->_db,
                Zend_Db_Table_Abstract::NAME    => $this->_imageTableName,
            ));
        }

        return $this->_imageTable;
    }

    /**
     * @return FormatedImageTable
     */
    private function _getFormatedImageTable()
    {
        if (null === $this->_formatedImageTable) {
            $this->_formatedImageTable = new FormatedImageTable(array(
                Zend_Db_Table_Abstract::ADAPTER => $this->_db,
                Zend_Db_Table_Abstract::NAME    => $this->_formatedImageTableName,
            ));
        }

        return $this->_formatedImageTable;
    }

    /**
     * @return DirTable
     */
    private function _getDirTable()
    {
        if (null === $this->_dirTable) {
            $this->_dirTable = new DirTable(array(
                Zend_Db_Table_Abstract::ADAPTER => $this->_db,
                Zend_Db_Table_Abstract::NAME    => $this->_dirTableName,
            ));
        }

        return $this->_dirTable;
    }

    /**
     * @param Zend_Db_Table_Row $imageRow
     * @return Image
     * @throws Exception
     */
    private function _buildImageResult(Zend_Db_Table_Row $imageRow)
    {
        $dir = $this->getDir($imageRow->dir);
        if (!$dir) {
            $this->_raise("Dir '{$imageRow->dir}' not defined");
        }

        $dirUrl = $dir->getUrl();

        $src = null;
        if ($dirUrl) {

            $path = str_replace('+', '%2B', $imageRow->filepath);

            $src = $dirUrl . $path;
        }

        if ($this->_forceHttps) {
            $src = preg_replace("/^http:/i", "https:", $src);
        }

        return new Image(array(
            'width'    => $imageRow->width,
            'height'   => $imageRow->height,
            'src'      => $src,
            'filesize' => $imageRow->filesize,
        ));
    }

    /**
     * @param Zend_Db_Table_Row $imageRow
     * @return string
     * @throws Exception
     */
    private function _buildImageBlobResult(Zend_Db_Table_Row $imageRow)
    {
        $dir = $this->getDir($imageRow->dir);
        if (!$dir) {
            $this->_raise("Dir '{$imageRow->dir}' not defined");
        }

        $filepath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow->filepath;

        if (!file_exists($filepath)) {
            return $this->_raise("File `$filepath` not found");
        }

        return file_get_contents($filepath);
    }

    /**
     * @param int $imageId
     * @return Zend_Db_Table_Row
     * @throws Exception
     */
    private function _getImageRow($imageId)
    {
        $id = (int)$imageId;
        if (strlen($id) != strlen($imageId)) {
            return $this->_raise("Image id mus be int. `$imageId` given");
        }

        $imageRow = $this->_getImageTable()->fetchRow(array(
            'id = ?' => $id
        ));

        return $imageRow ? $imageRow : null;
    }

    /**
     * @param array $imageIds
     * @return Zend_Db_Table_Row
     * @throws Exception
     */
    private function _getImageRows(array $imageIds)
    {
        $result = array();
        if (count($imageIds)) {
            $result = $this->_getImageTable()->fetchAll(array(
                'id in (?)' => $imageIds
            ));
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
        $imageRow = $this->_getImageRow($imageId);

        return $imageRow ? $this->_buildImageResult($imageRow) : null;
    }

    /**
     * @param array $imageIds
     * @return Image
     * @throws Exception
     */
    public function getImages(array $imageIds)
    {
        $result = array();
        foreach ($this->_getImageRows($imageIds) as $imageRow) {
            $result[$imageRow['id']] = $this->_buildImageResult($imageRow);
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
        $imageRow = $this->_getImageRow($imageId);

        return $imageRow ? $this->_buildImageBlobResult($imageRow) : null;
    }

    private function _getFormatedImageRows(array $requests, $formatName)
    {
        $imageTable = $this->_getImageTable();

        $imagesId = array();
        foreach ($requests as $request) {
            if (!$request instanceof Request) {
                return $this->_raise('$requests is not array of Autowp\Image\Storage\Request');
            }
            $imageId = $request->getImageId();
            if (!$imageId) {
                $this->_raise("ImageId not provided");
            }

            $imagesId[] = $imageId;
        }

        if (count($imagesId)) {
            $destImageRows = $imageTable->fetchAll(
                $imageTable->select(true)
                    ->setIntegrityCheck(false) // to fetch image_id
                    ->join(
                        array('f' => $this->_formatedImageTableName),
                        $this->_imageTableName . '.id = f.formated_image_id',
                        'image_id'
                    )
                    ->where('f.image_id in (?)', $imagesId)
                    ->where('f.format = ?', (string)$formatName)
            );
        } else {
            $destImageRows = array();
        }

        $result = array();

        foreach ($requests as $key => $request) {

            $imageId = $request->getImageId();

            $destImageRow = null;
            foreach ($destImageRows as $row) {
                if ($row->image_id == $imageId) {
                    $destImageRow = $row;
                    break;
                }
            }

            if (!$destImageRow) {

                // find source image
                $imageRow = $this->_getImageTable()->fetchRow(array(
                    'id = ?' => $imageId
                ));
                if (!$imageRow) {
                    $this->_raise("Image `$imageId` not found");
                }

                $dir = $this->getDir($imageRow->dir);
                if (!$dir) {
                    $this->_raise("Dir '{$imageRow->dir}' not defined");
                }

                $srcFilePath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow->filepath;

                $imagick = new Imagick();
                try {
                    $imagick->readImage($srcFilePath);
                } catch (ImagickException $e) {
                    $this->_raise('Imagick: ' . $e->getMessage());
                }

                // format
                $format = $this->getFormat($formatName);
                if (!$format) {
                    $this->_raise("Format `$formatName` not found");
                }
                $cFormat = clone $format;

                $crop = $request->getCrop();
                if ($crop) {
                    $cFormat->setCrop($crop);
                }

                $sampler = $this->getImageSampler();
                if (!$sampler) {
                    return $this->_raise("Image sampler not initialized");
                }
                $sampler->convertImagick($imagick, $cFormat);

                // store result
                $newPath = implode(DIRECTORY_SEPARATOR, array(
                    $imageRow->dir,
                    $formatName,
                    $imageRow->filepath
                ));
                $pi = pathinfo($newPath);
                $formatExt = $cFormat->getFormatExtension();
                $extension = $formatExt ? $formatExt : $pi['extension'];
                $formatedImageId = $this->addImageFromImagick(
                    $imagick, $this->_formatedImageDirName,
                    array(
                        'extension' => $extension,
                        'pattern'   => $pi['dirname'] . DIRECTORY_SEPARATOR . $pi['filename']
                    )
                );

                $imagick->clear();

                $formatedImageTable = $this->_getFormatedImageTable();
                $formatedImageRow = $formatedImageTable->fetchRow(array(
                    'format = ?'   => (string)$formatName,
                    'image_id = ?' => $imageId,
                ));
                if (!$formatedImageRow) {
                    $formatedImageRow = $formatedImageTable->createRow(array(
                        'format'            => (string)$formatName,
                        'image_id'          => $imageId,
                        'formated_image_id' => $formatedImageId
                    ));
                } else {
                    $formatedImageRow->formated_image_id = $formatedImageId;
                }
                $formatedImageRow->save();

                // result
                $destImageRow = $this->_getImageTable()->fetchRow(array(
                    'id = ?' => $formatedImageId
                ));
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
    private function _getFormatedImageRow(Request $request, $formatName)
    {
        $result = $this->_getFormatedImageRows(array($request), $formatName);

        if (!isset($result[0])) {
            $this->_raise("_getFormatedImageRows fails");
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
        if (!$request instanceof Request) {
            $request = new Request(array(
                'imageId' => $request
            ));
        }

        return $this->_buildImageBlobResult(
            $this->_getFormatedImageRow($request, $formatName)
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
        } elseif (!$request instanceof Request) {
            $request = new Request(array(
                'imageId' => $request
            ));
        }

        return $this->_buildImageResult(
            $this->_getFormatedImageRow($request, $formatName)
        );
    }

    /**
     * @param array $images
     * @param string $format
     * @return array
     */
    public function getFormatedImages(array $requests, $formatName)
    {
        $result = array();
        foreach ($this->_getFormatedImageRows($requests, $formatName) as $key => $row) {
            $result[$key] = $this->_buildImageResult($row);
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
        $imageTable = $this->_getImageTable();

        $imageRow = $imageTable->fetchRow(array(
            'id = ?' => (int)$imageId
        ));

        if (!$imageRow) {
            $this->_raise("Image '$imageId' not found");
        }

        $this->flush(array(
            'image' => $imageRow->id
        ));

        // to save remove formated image
        $this->_getFormatedImageTable()->delete(array(
            'formated_image_id = ?' => $imageRow->id
        ));

        // remove file & row
        $dir = $this->getDir($imageRow->dir);
        if (!$dir) {
            $this->_raise("Dir '{$imageRow->dir}' not defined");
        }

        $filepath = implode(DIRECTORY_SEPARATOR, array(
            rtrim($dir->getPath(), DIRECTORY_SEPARATOR),
            $imageRow->filepath
        ));

        // important to delete row first
        $imageRow->delete();

        if (file_exists($filepath)) {
            if (!unlink($filepath)) {
                return $this->_raise("Error unlink `$filepath`");
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
    private function _createImagePath($dirName, array $options = array())
    {
        $dir = $this->getDir($dirName);
        if (!$dir) {
            $this->_raise("Dir '$dirName' not defined");
        }

        $dirPath = $dir->getPath();

        $namingStrategy = $dir->getNamingStrategy();
        if (!$namingStrategy) {
            $message = "Naming strategy not initialized for `$dirName`";
            $this->_raise($message);
        }


        $options = array_merge(array(
            'count' => $this->getDirCounter($dirName),
        ), $options);

        if (!isset($options['extension'])) {
            $options['extension'] = self::EXTENSION_DEFAULT;
        }

        $destFileName = $namingStrategy->generate($options);
        $destFilePath = $dirPath . DIRECTORY_SEPARATOR . $destFileName;

        $destDir = dirname($destFilePath);
        if (!is_dir($destDir)) {
            $old = umask(0);
            if (!mkdir($destDir, $this->_dirMode, true)) {
                $this->_raise("Cannot create dir '$destDir'");
            }
            umask($old);
        }

        return $destFileName;
    }

    /**
     * @param string $path
     * @throws Exception
     */
    private function _chmodFile($path)
    {
        if (!chmod($path, $this->_fileMode)) {
            $this->_raise("Cannot chmod file '$path'");
        }
    }

    /**
     * @param string $blob
     * @param string $dirName
     * @param array $options
     * @throws Exception
     */
    public function addImageFromBlob($blob, $dirName, array $options = array())
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
    private function _lockFile($dirName, array $options, Closure $callback)
    {
        $dir = $this->getDir($dirName);
        if (!$dir) {
            $this->_raise("Dir '$dirName' not defined");
        }

        $dirPath = $dir->getPath();

        $lockAttemptsLeft = self::LOCK_MAX_ATTEMPTS;
        $fileSuccess = false;
        do {
            $lockAttemptsLeft--;

            $destFileName = $this->_createImagePath($dirName, $options);
            $destFilePath = $dirPath . DIRECTORY_SEPARATOR . $destFileName;

            $fp = fopen($destFilePath, 'c+');
            if (!$fp) {
                $this->_raise("Cannot open file '$destFilePath'");
            }

            if (!flock($fp, LOCK_EX | LOCK_NB)) {
                // already locked, try next file
                return $this->_raise("already locked, try next file");
                fclose($fp);
                continue;
            }

            if (false !== fgetc($fp)) {
                // not empty, try next file
                return $this->_raise("not empty, try next file $destFilePath");
                flock($fp, LOCK_UN);
                fclose($fp);
                continue;
            }

            $callback($fp);

            flock($fp, LOCK_UN);
            fclose($fp);

            $fileSuccess = true;

        } while (($lockAttemptsLeft > 0) && !$fileSuccess);

        if (!$fileSuccess) {
            return $this->_raise("Cannot save to `$destFilePath` after few attempts");
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
    private function _generateLockWrite($dirName, array $options, $width, $height, Closure $callback)
    {
        $dir = $this->getDir($dirName);
        if (!$dir) {
            $this->_raise("Dir '$dirName' not defined");
        }

        $dirPath = $dir->getPath();

        $insertAttemptsLeft = self::INSERT_MAX_ATTEMPTS;
        $insertAttemptException = null;
        do {

            $destFileName = $this->_lockFile($dirName, $options, $callback);

            $filePath = $dirPath . DIRECTORY_SEPARATOR . $destFileName;

            $this->_chmodFile($filePath);

            // store to db
            $imageRow = $this->_getImageTable()->createRow(array(
                'width'    => $width,
                'height'   => $height,
                'dir'      => $dirName,
                'filesize' => filesize($filePath),
                'filepath' => $destFileName,
                'date_add' => new Zend_Db_Expr('now()')
            ));
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

        $this->incDirCounter($dirName);

        return $imageRow->id;
    }

    /**
     * @param Imagick $imagick
     * @param string $dirName
     * @param array $options
     * @throws Exception
     */
    public function addImageFromImagick(Imagick $imagick, $dirName, array $options = array())
    {
        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();

        if (!$width || !$height) {
            $this->_raise("Failed to get image size ($width x $height)");
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
                $this->_raise("Unsupported image type `$format`");
        }

        return $this->_generateLockWrite($dirName, $options, $width, $height, function($fp) use ($imagick) {
            if (!$imagick->writeImageFile($fp)) {
                $this->_raise("Imagick::writeImageFile error");
            }
        });
    }

    /**
     * @param string $file
     * @param string $dirName
     * @param array $options
     * @throws Exception
     */
    public function addImageFromFile($file, $dirName, array $options = array())
    {
        list($width, $height, $type) = getimagesize($file);
        $width = (int)$width;
        $height = (int)$height;

        if (!$width || !$height) {
            $this->_raise("Failed to get image size of '$file' ($width x $height)");
        }

        if (!isset($options['extension'])) {
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
                    $this->_raise("Unsupported image type `$type`");
            }
            $options['extension'] = $ext;
        }

        return $this->_generateLockWrite($dirName, $options, $width, $height, function($fp) use ($file) {
            /**
             * @todo buffered read-write
             */
            if (!fwrite($fp, file_get_contents($file))) {
                $this->_raise("fwrite error '$file'");
            }
        });
    }

    /**
     * @param array $options
     * @return Storage
     */
    public function flush(array $options)
    {
        $defaults = array(
            'format' => null,
            'image'  => null,
        );

        $options = array_merge($defaults, $options);

        $select = $this->_getFormatedImageTable()->select(true);

        if ($options['format']) {
            $select->where($this->_formatedImageTableName . '.format = ?', (string)$options['format']);
        }

        if ($options['image']) {
            $select->where($this->_formatedImageTableName . '.image_id = ?', (int)$options['image']);
        }

        $rows = $this->_getFormatedImageTable()->fetchAll($select);

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
        $dirTable = $this->_getDirTable();
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
        $dirTable = $this->_getDirTable();

        $row = $dirTable->fetchRow(array(
            'dir = ?' => $dirName
        ));

        if ($row) {
            $row->count = new Zend_Db_Expr('count + 1');
        } else {
            $row = $dirTable->createRow(array(
                'dir'   => $dirName,
                'count' => 1
            ));
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
        $imageRow = $this->_getImageRow($imageId);

        if (!$imageRow) {
            return false;
        }

        $dir = $this->getDir($imageRow->dir);
        if (!$dir) {
            return $this->_raise("Dir '{$imageRow->dir}' not defined");
        }

        $filepath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow->filepath;

        if (!file_exists($filepath)) {
            return $this->_raise("File `$filepath` not found");
        }

        $iptcStr = '';
        getimagesize($filepath, $info);
        if (is_array($info) && array_key_exists('APP13', $info)) {
            $IPTC = iptcparse($info['APP13']);
            if (is_array($IPTC)) {
                foreach ($IPTC as $key => $value) {
                    $iptcStr .= "<b>IPTC Key:</b> ".htmlspecialchars($key)." <b>Contents:</b> ";
                    foreach ($value as $innerkey => $innervalue) {
                        if ( ($innerkey+1) != count($value) )
                            $iptcStr .= htmlspecialchars($innervalue) . ", ";
                        else
                            $iptcStr .= htmlspecialchars($innervalue);
                    }
                    $iptcStr .= '<br />';
                }
            } else {
                $iptcStr .= $IPTC;
            }
        }

        return $iptcStr;
    }

    /**
     * @param int $imageId
     * @return boolean|string
     */
    public function getImageEXIF($imageId)
    {
        $imageRow = $this->_getImageRow($imageId);

        if (!$imageRow) {
            return false;
        }

        $dir = $this->getDir($imageRow->dir);
        if (!$dir) {
            return $this->_raise("Dir '{$imageRow->dir}' not defined");
        }

        $filepath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow->filepath;

        if (!file_exists($filepath)) {
            return $this->_raise("File `$filepath` not found");
        }

        $exifStr = '';
        try {
            $NotSections = array('FILE', 'COMPUTED');
            $exif = @exif_read_data($filepath, 0, true);
            if ($exif !== false) {
                foreach ($exif as $key => $section) {
                    if (array_search($key, $NotSections) !== false)
                        continue;

                    $exifStr .= '<p>['.htmlspecialchars($key).']';
                    foreach ($section as $name => $val) {
                        $exifStr .= "<br />".htmlspecialchars($name).": ";
                        if (is_array($val))
                            $exifStr .= htmlspecialchars(implode(', ', $val));
                        else
                            $exifStr .= htmlspecialchars($val);
                    }

                    $exifStr .= '</p>';
                }
            }
        } catch (Exception $e) {
            $exifStr .= 'Ошибка при чтении EXIF: '.$e->getMessage();
        }

        return $exifStr;
    }

    public static function detectExtenstion($filepath)
    {
        list ($width, $height, $imageType) = getimagesize($filepath);

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
    public function changeImageName($imageId, array $options = array())
    {
        $imageRow = $this->_getImageRow($imageId);
        if (!$imageRow) {
            return $this->_raise("Image `$imageId` not found");
        }

        $dir = $this->getDir($imageRow->dir);
        if (!$dir) {
            return $this->_raise("Dir '{$imageRow->dir}' not defined");
        }

        $dirPath = $dir->getPath();

        $oldFilePath = $dirPath . DIRECTORY_SEPARATOR . $imageRow->filepath;

        if (!isset($options['extension'])) {
            $options['extension'] = self::detectExtenstion($oldFilePath);
        }

        $insertAttemptsLeft = self::INSERT_MAX_ATTEMPTS;
        $insertAttemptException = null;
        do {

            $destFileName = $this->_lockFile($imageRow->dir, $options, function($fp) use($imageRow) {
                fwrite($fp, $this->_buildImageBlobResult($imageRow));
            });

            $filePath = $dirPath . DIRECTORY_SEPARATOR . $destFileName;

            $this->_chmodFile($filePath);

            // store to db
            $imageRow->setFromArray(array(
                'filepath' => $destFileName
            ));
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
        if (!$dir) {
            $this->_raise("Dir '$dirName' not defined");
        }

        $dirPath = $dir->getPath();

        $filePath = $dirPath . DIRECTORY_SEPARATOR . $file;
        if (!$filePath) {
            throw new Exception("File `$filePath` not found");
        }

        list($width, $height, $type, $attr) = getimagesize($filePath);

        // store to db
        $imageRow = $this->_getImageTable()->createRow(array(
            'width'    => $width,
            'height'   => $height,
            'dir'      => $dirName,
            'filesize' => filesize($filePath),
            'filepath' => $file,
            'date_add' => new Zend_Db_Expr('now()')
        ));
        $imageRow->save();

        return $imageRow->id;
    }

    public function flop($imageId)
    {
        $imageRow = $this->_getImageRow($imageId);
        if (!$imageRow) {
            return $this->_raise("Image `$imageId` not found");
        }

        $dir = $this->getDir($imageRow->dir);
        if (!$dir) {
            $this->_raise("Dir '{$imageRow->dir}' not defined");
        }

        $filePath = $dir->getPath() . DIRECTORY_SEPARATOR . $imageRow->filepath;

        $imagick = new Imagick();
        $imagick->readImage($filePath);

        // format
        $imagick->flopImage();

        $imagick->writeImage($filePath);

        $imagick->clear();

        $this->flush(array(
            'image' => $imageId
        ));
    }
}