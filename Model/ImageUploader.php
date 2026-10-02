<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\Uploader;
use Magento\Framework\File\UploaderFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\UrlInterface;
use Magento\MediaStorage\Helper\File\Storage\Database;
use Psr\Log\LoggerInterface;

class ImageUploader
{
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    private WriteInterface $mediaDirectory;

    public function __construct(
        Filesystem $filesystem,
        private readonly Database $coreFileStorageDatabase,
        private readonly UploaderFactory $uploaderFactory,
        private readonly \Magento\Store\Model\StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
        private readonly string $baseTmpPath,
        private readonly string $basePath,
        private readonly array $allowedExtensions
    ) {
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
    }

    public function getBaseTmpPath(): string
    {
        return $this->baseTmpPath;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function getAllowedExtensions(): array
    {
        return $this->allowedExtensions;
    }

    public function getFilePath(string $path, string $imageName): string
    {
        return rtrim($path, '/') . '/' . ltrim($imageName, '/');
    }

    public function sanitizeImageName(string $imageName): string
    {
        $name = basename(str_replace('\\', '/', trim($imageName)));
        if ($name === '' || $name === '.' || $name === '..' || strpos($name, "\0") !== false) {
            return '';
        }
        return $name;
    }

    public function isTmpFile(string $imageName): bool
    {
        $name = $this->sanitizeImageName($imageName);
        return $name !== '' && $this->mediaDirectory->isFile($this->getFilePath($this->getBaseTmpPath(), $name));
    }

    public function moveFileFromTmp(string $imageName): string
    {
        $imageName = $this->sanitizeImageName($imageName);
        if ($imageName === '') {
            throw new LocalizedException(__('The image file name is not valid.'));
        }

        $baseTmpPath = $this->getBaseTmpPath();
        $basePath = $this->getBasePath();

        $baseTmpImagePath = $this->getFilePath($baseTmpPath, $imageName);
        $imageName = Uploader::getNewFileName(
            $this->mediaDirectory->getAbsolutePath($this->getFilePath($basePath, $imageName))
        );
        $baseImagePath = $this->getFilePath($basePath, $imageName);

        try {
            $this->coreFileStorageDatabase->copyFile(
                $baseTmpImagePath,
                $baseImagePath
            );
            $this->mediaDirectory->renameFile(
                $baseTmpImagePath,
                $baseImagePath
            );
        } catch (\Throwable $e) {
            throw new LocalizedException(__('Something went wrong while saving the image: %1', $e->getMessage()), $e);
        }

        return $imageName;
    }

    public function saveFileToTmpDir(string $fileId): array
    {
        $baseTmpPath = $this->getBaseTmpPath();

        $uploader = $this->uploaderFactory->create(['fileId' => $fileId]);
        $uploader->setAllowedExtensions($this->getAllowedExtensions());
        $uploader->setAllowRenameFiles(true);
        if (!$uploader->checkMimeType(self::ALLOWED_MIME_TYPES)) {
            throw new LocalizedException(__('File validation failed. Upload a JPG, PNG, GIF or WebP image.'));
        }

        $result = $uploader->save($this->mediaDirectory->getAbsolutePath($baseTmpPath));
        if (!$result) {
            throw new LocalizedException(__('File can not be saved to the destination folder.'));
        }

        $result['tmp_name'] = str_replace('\\', '/', $result['tmp_name']);
        $result['path'] = str_replace('\\', '/', $result['path']);
        $result['url'] = $this->storeManager
            ->getStore()
            ->getBaseUrl(UrlInterface::URL_TYPE_MEDIA)
            . $this->getFilePath($baseTmpPath, $result['file']);
        $result['name'] = $result['file'];

        if (isset($result['file'])) {
            try {
                $relativePath = rtrim($baseTmpPath, '/') . '/' . ltrim($result['file'], '/');
                $this->coreFileStorageDatabase->saveFile($relativePath);
            } catch (\Throwable $e) {
                $this->logger->critical($e);
                throw new LocalizedException(__('Something went wrong while saving the file(s).'), $e);
            }
        }

        return $result;
    }
}
