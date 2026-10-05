<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Test\Unit\Model;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\Filesystem\Directory\TargetDirectory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\File\Uploader;
use Magento\Framework\File\UploaderFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\MediaStorage\Helper\File\Storage\Database;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\HeroSlider\Model\ImageUploader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ImageUploaderTest extends TestCase
{
    /** @var string[] absolute paths reported as already existing */
    private array $existing = [];

    protected function setUp(): void
    {
        $this->existing = [];
        $read = $this->createStub(ReadInterface::class);
        $read->method('isExist')->willReturnCallback(fn($path) => in_array($path, $this->existing, true));
        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('getDirectoryRead')->willReturn($read);
        $remoteRead = $this->createStub(ReadInterface::class);
        $remoteRead->method('isExist')->willReturn(false);
        $target = $this->createStub(TargetDirectory::class);
        $target->method('getDirectoryRead')->willReturn($remoteRead);
        $objectManager = $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturnMap([
            [Filesystem::class, $filesystem],
            [TargetDirectory::class, $target],
        ]);
        // Uploader::getNewFileName() resolves its collaborators statically.
        ObjectManager::setInstance($objectManager);
    }

    protected function tearDown(): void
    {
        $prop = new \ReflectionProperty(ObjectManager::class, '_instance');
        $prop->setValue(null, null);
    }

    private function uploader(
        ?WriteInterface $media = null,
        ?Database $db = null,
        ?UploaderFactory $uploaderFactory = null,
        ?LoggerInterface $logger = null
    ): ImageUploader {
        if ($media === null) {
            $media = $this->createStub(WriteInterface::class);
            $media->method('getAbsolutePath')->willReturnCallback(fn($p = null) => '/media/' . $p);
        }
        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturn($media);
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.test/media/');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new ImageUploader(
            $filesystem,
            $db ?? $this->createStub(Database::class),
            $uploaderFactory ?? $this->createStub(UploaderFactory::class),
            $storeManager,
            $logger ?? $this->createStub(LoggerInterface::class),
            'panth/heroslider/tmp/slide',
            'panth/heroslider/slide',
            ['jpg', 'png']
        );
    }

    public function testAccessorsAndFilePathJoining(): void
    {
        $u = $this->uploader();

        $this->assertSame('panth/heroslider/tmp/slide', $u->getBaseTmpPath());
        $this->assertSame('panth/heroslider/slide', $u->getBasePath());
        $this->assertSame(['jpg', 'png'], $u->getAllowedExtensions());
        $this->assertSame('a/b/c.jpg', $u->getFilePath('a/b/', '/c.jpg'));
    }

    public static function names(): array
    {
        return [
            'plain'             => ['hero.jpg', 'hero.jpg'],
            'trimmed'           => ['  hero.jpg ', 'hero.jpg'],
            'traversal'         => ['../../etc/passwd', 'passwd'],
            'windows traversal' => ['..\\..\\shell.php', 'shell.php'],
            'empty'             => ['', ''],
            'dot'               => ['.', ''],
            'dotdot'            => ['..', ''],
            'null byte'         => ["a\0.jpg", ''],
        ];
    }

    #[DataProvider('names')]
    public function testSanitizeImageName(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->uploader()->sanitizeImageName($input));
    }

    public function testIsTmpFileChecksSanitisedPathInTmpDir(): void
    {
        $media = $this->createMock(WriteInterface::class);
        $media->expects($this->once())->method('isFile')
            ->with('panth/heroslider/tmp/slide/hero.jpg')->willReturn(true);

        $u = $this->uploader($media);
        $this->assertTrue($u->isTmpFile('../hero.jpg'));
        $this->assertFalse($u->isTmpFile('..'));
    }

    public function testMoveFileFromTmpRejectsInvalidName(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The image file name is not valid.');

        $this->uploader()->moveFileFromTmp('..');
    }

    public function testMoveFileFromTmpCopiesAndRenames(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->once())->method('copyFile')
            ->with('panth/heroslider/tmp/slide/hero.jpg', 'panth/heroslider/slide/hero.jpg');
        $media = $this->createMock(WriteInterface::class);
        $media->method('getAbsolutePath')->willReturnCallback(fn($p = null) => '/media/' . $p);
        $media->expects($this->once())->method('renameFile')
            ->with('panth/heroslider/tmp/slide/hero.jpg', 'panth/heroslider/slide/hero.jpg');

        $this->assertSame('hero.jpg', $this->uploader($media, $db)->moveFileFromTmp('hero.jpg'));
    }

    public function testMoveFileFromTmpPicksNewNameWhenTargetExists(): void
    {
        $this->existing = ['/media/panth/heroslider/slide/hero.jpg', '/media/panth/heroslider/slide/hero_1.jpg'];

        $this->assertSame('hero_2.jpg', $this->uploader()->moveFileFromTmp('hero.jpg'));
    }

    public function testMoveFileFromTmpWrapsStorageErrors(): void
    {
        $media = $this->createStub(WriteInterface::class);
        $media->method('getAbsolutePath')->willReturnCallback(fn($p = null) => '/media/' . $p);
        $media->method('renameFile')->willThrowException(new \RuntimeException('disk full'));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Something went wrong while saving the image: disk full');
        $this->uploader($media)->moveFileFromTmp('hero.jpg');
    }

    private function fileUploader(bool $mimeOk, array|false $saveResult): Uploader
    {
        $uploader = $this->createMock(Uploader::class);
        $uploader->expects($this->once())->method('setAllowedExtensions')->with(['jpg', 'png']);
        $uploader->expects($this->once())->method('setAllowRenameFiles')->with(true);
        $uploader->method('checkMimeType')->with(ImageUploader::ALLOWED_MIME_TYPES)->willReturn($mimeOk);
        $uploader->expects($mimeOk ? $this->once() : $this->never())->method('save')->willReturn($saveResult);
        return $uploader;
    }

    private function factoryFor(Uploader $uploader): UploaderFactory
    {
        $factory = $this->createMock(UploaderFactory::class);
        $factory->expects($this->once())->method('create')->with(['fileId' => 'image_desktop'])->willReturn($uploader);
        return $factory;
    }

    public function testSaveFileToTmpDirRejectsBadMimeType(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('File validation failed.');

        $this->uploader(null, null, $this->factoryFor($this->fileUploader(false, false)))
            ->saveFileToTmpDir('image_desktop');
    }

    public function testSaveFileToTmpDirFailsWhenSaveReturnsNothing(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('File can not be saved to the destination folder.');

        $this->uploader(null, null, $this->factoryFor($this->fileUploader(true, false)))
            ->saveFileToTmpDir('image_desktop');
    }

    public function testSaveFileToTmpDirNormalisesResultAndRegistersFile(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->once())->method('saveFile')->with('panth/heroslider/tmp/slide/hero.jpg');
        $factory = $this->factoryFor($this->fileUploader(true, [
            'file'     => 'hero.jpg',
            'tmp_name' => 'C:\\tmp\\php1',
            'path'     => 'D:\\media\\tmp',
        ]));

        $result = $this->uploader(null, $db, $factory)->saveFileToTmpDir('image_desktop');

        $this->assertSame('C:/tmp/php1', $result['tmp_name']);
        $this->assertSame('D:/media/tmp', $result['path']);
        $this->assertSame('https://shop.test/media/panth/heroslider/tmp/slide/hero.jpg', $result['url']);
        $this->assertSame('hero.jpg', $result['name']);
    }

    public function testSaveFileToTmpDirLogsAndWrapsDatabaseErrors(): void
    {
        $db = $this->createStub(Database::class);
        $db->method('saveFile')->willThrowException(new \RuntimeException('db down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('critical');
        $factory = $this->factoryFor($this->fileUploader(true, ['file' => 'a.jpg', 'tmp_name' => 't', 'path' => 'p']));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Something went wrong while saving the file(s).');
        $this->uploader(null, $db, $factory, $logger)->saveFileToTmpDir('image_desktop');
    }
}
