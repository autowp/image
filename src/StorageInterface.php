<?php

declare(strict_types=1);

namespace Autowp\Image;

use Imagick;

interface StorageInterface
{
    /**
     * @throws Storage\Exception
     */
    public function getImage(int $imageId): ?Storage\Image;

    public function getImages(array $imageIds): array;

    public function getImageBlob(int $imageId): ?string;

    /**
     * @throws Storage\Exception
     */
    public function getFormatedImageBlob(int $imageId, string $formatName): ?string;

    public function getFormatedImage(int $imageId, string $formatName): ?Storage\Image;

    public function getFormatedImages(array $imagesId, string $formatName): array;

    /**
     * @throws Storage\Exception
     */
    public function removeImage(int $imageId): self;

    public function addImageFromBlob(string $blob, string $dirName, array $options = []): int;

    public function addImageFromImagick(Imagick $imagick, string $dirName, array $options = []): int;

    /**
     * @throws Storage\Exception
     */
    public function addImageFromFile(string $file, string $dirName, array $options = []): int;

    public function flush(array $options): self;

    public function getImageEXIF(int $imageId): ?array;

    public function getImageResolution(int $imageId): ?array;

    public function changeImageName(int $imageId, array $options = []): void;

    public function flop(int $imageId): void;

    public function normalize(int $imageId): void;
}
