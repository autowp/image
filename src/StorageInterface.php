<?php

namespace Autowp\Image;

use Imagick;

interface StorageInterface
{
    /**
     * @throws Exception
     * @return Storage\Image|null
     */
    public function getImage(int $imageId);

    public function getImages(array $imageIds): array;

    /**
     * @return string|null
     */
    public function getImageBlob(int $imageId);

    /**
     * @param int $imageId
     * @return string
     * @throws Exception
     */
    public function getFormatedImageBlob(int $imageId, string $formatName);

    /**
     * @param int $imageId
     * @param string $format
     * @return Image
     */
    public function getFormatedImage(int $imageId, string $formatName);

    /**
     * @param array $imagesId
     * @param string $format
     * @return array
     */
    public function getFormatedImages(array $imagesId, string $formatName);

    /**
     * @param int $imageId
     * @return Image
     * @throws Exception
     */
    public function removeImage(int $imageId);

    /**
     * @throws Exception
     */
    public function addImageFromBlob(string $blob, string $dirName, array $options = []): int;

    /**
     * @throws Exception
     */
    public function addImageFromImagick(Imagick $imagick, string $dirName, array $options = []): int;

    /**
     * @throws Exception
     */
    public function addImageFromFile(string $file, string $dirName, array $options = []): int;

    public function flush(array $options);

    public function getImageIPTC(int $imageId);

    public function getImageEXIF(int $imageId);

    public function getImageResolution(int $imageId);

    /**
     * @throws Exception
     */
    public function changeImageName(int $imageId, array $options = []);

    public function flop(int $imageId);

    public function normalize(int $imageId);
}
