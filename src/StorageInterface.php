<?php

namespace Autowp\Image;

use Imagick;

interface StorageInterface
{
    /**
     * @param int $imageId
     * @throws Storage\Exception
     * @return Storage\Image|null
     */
    public function getImage(int $imageId): ?Storage\Image;

    /**
     * @param array $imageIds
     * @return array
     */
    public function getImages(array $imageIds): array;

    /**
     * @param $imageId
     * @return string|null
     */
    public function getImageBlob(int $imageId): ?string;

    /**
     * @param int $imageId
     * @param string $formatName
     * @return string
     * @throws Storage\Exception
     */
    public function getFormatedImageBlob(int $imageId, string $formatName): ?string;

    /**
     * @param int $imageId
     * @param string $formatName
     * @return Storage\Image|null
     */
    public function getFormatedImage(int $imageId, string $formatName): ?Storage\Image;

    /**
     * @param array $imagesId
     * @param string $formatName
     * @return array
     */
    public function getFormatedImages(array $imagesId, string $formatName): array;

    /**
     * @param int $imageId
     * @return StorageInterface
     * @throws Storage\Exception
     */
    public function removeImage(int $imageId): StorageInterface;

    /**
     * @param string $blob
     * @param string $dirName
     * @param array $options
     * @return int
     */
    public function addImageFromBlob(string $blob, string $dirName, array $options = []): int;

    /**
     * @param Imagick $imagick
     * @param string $dirName
     * @param array $options
     * @return int
     */
    public function addImageFromImagick(Imagick $imagick, string $dirName, array $options = []): int;

    /**
     * @param string $file
     * @param string $dirName
     * @param array $options
     * @return int
     * @throws Storage\Exception
     */
    public function addImageFromFile(string $file, string $dirName, array $options = []): int;

    /**
     * @param array $options
     * @return StorageInterface
     */
    public function flush(array $options): StorageInterface;

    /**
     * @param int $imageId
     * @return string|null
     */
    public function getImageIPTC(int $imageId): ?string;

    /**
     * @param int $imageId
     * @return array|null
     */
    public function getImageEXIF(int $imageId): ?array;

    /**
     * @param int $imageId
     * @return array|null
     */
    public function getImageResolution(int $imageId): ?array;

    /**
     * @param int $imageId
     * @param array $options
     */
    public function changeImageName(int $imageId, array $options = []): void;

    /**
     * @param int $imageId
     */
    public function flop(int $imageId): void;

    /**
     * @param int $imageId
     */
    public function normalize(int $imageId): void;
}
