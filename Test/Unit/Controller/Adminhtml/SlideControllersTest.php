<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Test\Unit\Controller\Adminhtml;

use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\PageFactory;
use Magento\Ui\Component\MassAction\Filter as UiFilter;
use Panth\Core\Security\UploadExtensionPolicy;
use Panth\HeroSlider\Api\SlideRepositoryInterface;
use Panth\HeroSlider\Controller\Adminhtml\Slide\Delete;
use Panth\HeroSlider\Controller\Adminhtml\Slide\Edit;
use Panth\HeroSlider\Controller\Adminhtml\Slide\Image\Upload;
use Panth\HeroSlider\Controller\Adminhtml\Slide\MassDelete;
use Panth\HeroSlider\Controller\Adminhtml\Slide\MassStatus;
use Panth\HeroSlider\Controller\Adminhtml\Slide\Save;
use Panth\HeroSlider\Model\ImageUploader;
use Panth\HeroSlider\Model\ResourceModel\Slide\Collection;
use Panth\HeroSlider\Model\ResourceModel\Slide\CollectionFactory;
use Panth\HeroSlider\Model\Slide;
use Panth\HeroSlider\Model\SlideFactory;

class SlideControllersTest extends ControllerTestCase
{
    private function slide(array $data = []): Slide
    {
        $slide = (new \ReflectionClass(Slide::class))->newInstanceWithoutConstructor();
        $slide->setIdFieldName('entity_id');
        $slide->setData($data);
        return $slide;
    }

    private function uploader(array $tmpFiles = []): ImageUploader
    {
        $uploader = $this->createStub(ImageUploader::class);
        $uploader->method('sanitizeImageName')->willReturnCallback(static function (string $n) {
            $n = basename(str_replace('\\', '/', trim($n)));
            return in_array($n, ['', '.', '..'], true) ? '' : $n;
        });
        $uploader->method('isTmpFile')->willReturnCallback(static fn($n) => in_array($n, $tmpFiles, true));
        $uploader->method('moveFileFromTmp')->willReturnCallback(static fn($n) => 'moved_' . $n);
        return $uploader;
    }

    private function save(
        ?array $post,
        array $params = [],
        ?SlideRepositoryInterface $repo = null,
        ?Slide $newSlide = null,
        ?ImageUploader $uploader = null,
        ?DataPersistorInterface $persistor = null
    ): Save {
        $factory = $this->createStub(SlideFactory::class);
        $factory->method('create')->willReturn($newSlide ?? $this->slide());
        return new Save(
            $this->context($params, $post),
            $factory,
            $repo ?? $this->createStub(SlideRepositoryInterface::class),
            $persistor ?? $this->createStub(DataPersistorInterface::class),
            $uploader ?? $this->uploader()
        );
    }

    public function testSaveWithoutPostRedirectsToGrid(): void
    {
        $repo = $this->createMock(SlideRepositoryInterface::class);
        $repo->expects($this->never())->method('save');

        $this->save(null, [], $repo)->execute();
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    public function testSaveRejectsUnsafeLinkAndPersistsForm(): void
    {
        $post = ['entity_id' => '4', 'link_url' => 'javascript:alert(1)'];
        $repo = $this->createMock(SlideRepositoryInterface::class);
        $repo->expects($this->never())->method('save');
        $persistor = $this->createMock(DataPersistorInterface::class);
        $persistor->expects($this->once())->method('set')->with('panth_heroslider_slide', $post);

        $this->save($post, [], $repo, null, null, $persistor)->execute();

        $this->assertSame(['The link URL must be a relative path or use http, https, mailto or tel.'], $this->errors);
        $this->assertSame(['*/*/edit', ['entity_id' => 4]], $this->redirect);
    }

    public function testSaveRejectsInvalidButtonColor(): void
    {
        $this->save(['button_text_color' => 'red;x:y'])->execute();

        $this->assertSame(['Button colors must be a CSS color such as #09090C.'], $this->errors);
        $this->assertSame(['*/*/edit', ['entity_id' => 0]], $this->redirect);
    }

    public function testSaveNewSlideMovesUploadedImageAndNormalisesFields(): void
    {
        $slide = $this->slide();
        $slide->setData('entity_id', 31);
        $repo = $this->createMock(SlideRepositoryInterface::class);
        $repo->expects($this->never())->method('getById');
        $repo->expects($this->once())->method('save')->with($slide);
        $persistor = $this->createMock(DataPersistorInterface::class);
        $persistor->expects($this->once())->method('clear')->with('panth_heroslider_slide');

        $this->save([
            'title'           => 'Summer',
            'link_url'        => '/summer',
            'image_desktop'   => [['name' => 'hero.jpg', 'tmp_name' => '/tmp/php1']],
            'image_mobile'    => [],
            'button_label'    => '',
            'button_bg_color' => '#000',
            'sort_order'      => '7',
        ], ['back' => '1'], $repo, $slide, $this->uploader(['hero.jpg']), $persistor)->execute();

        $this->assertSame('moved_hero.jpg', $slide->getData('image_desktop'));
        $this->assertNull($slide->getData('image_mobile'));
        $this->assertNull($slide->getData('button_label'));
        $this->assertSame(0, $slide->getData('is_active'));
        $this->assertSame(7, $slide->getData('sort_order'));
        $this->assertSame(['Slide saved.'], $this->success);
        $this->assertSame(['*/*/edit', ['entity_id' => 31]], $this->redirect);
    }

    public function testSaveNewSlideDropsEmptyEntityIdSoTheRowIsInserted(): void
    {
        $slide = $this->slide();
        $seenId = 'unset';
        $repo = $this->createMock(SlideRepositoryInterface::class);
        $repo->expects($this->never())->method('getById');
        $repo->expects($this->once())->method('save')->willReturnCallback(
            function (Slide $saved) use (&$seenId) {
                $seenId = $saved->getId();
                $saved->setData('entity_id', 32);
                return $saved;
            }
        );

        $this->save([
            'entity_id'     => '',
            'slider_id'     => '3',
            'title'         => 'New one',
            'image_desktop' => [['name' => 'hero.jpg', 'tmp_name' => '/tmp/php2']],
        ], ['back' => '1'], $repo, $slide, $this->uploader(['hero.jpg']))->execute();

        $this->assertNull($seenId);
        $this->assertFalse($slide->hasData('entity_id') && $slide->getData('entity_id') === '');
        $this->assertSame(['*/*/edit', ['entity_id' => 32]], $this->redirect);
    }

    public function testSaveExistingSlideKeepsAlreadyStoredImageAndSanitisesStrings(): void
    {
        $slide = $this->slide(['entity_id' => 4]);
        $repo = $this->createMock(SlideRepositoryInterface::class);
        $repo->expects($this->once())->method('getById')->with(4)->willReturn($slide);
        $repo->expects($this->once())->method('save');

        $this->save([
            'entity_id'     => '4',
            'is_active'     => '1',
            'image_desktop' => [['name' => 'existing.jpg']],
            'image_mobile'  => '../../evil/m.jpg',
        ], [], $repo)->execute();

        $this->assertSame('existing.jpg', $slide->getData('image_desktop'));
        $this->assertSame('m.jpg', $slide->getData('image_mobile'));
        $this->assertSame(1, $slide->getData('is_active'));
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    public function testSaveImageEntryWithoutNameIsCleared(): void
    {
        $slide = $this->slide();
        $this->save(['image_desktop' => [['url' => 'x']]], [], null, $slide)->execute();

        $this->assertNull($slide->getData('image_desktop'));
    }

    public function testSaveReportsUnexpectedErrors(): void
    {
        $repo = $this->createStub(SlideRepositoryInterface::class);
        $repo->method('save')->willThrowException(new \RuntimeException('db'));

        $this->save(['title' => 'x'], [], $repo)->execute();

        $this->assertSame(['Could not save the slide.'], $this->exceptions);
        $this->assertSame('*/*/edit', $this->redirect[0]);
    }

    public function testDeleteWithoutIdShowsError(): void
    {
        $repo = $this->createMock(SlideRepositoryInterface::class);
        $repo->expects($this->never())->method('deleteById');

        (new Delete($this->context(), $repo))->execute();
        $this->assertSame(["We can't find the slide to delete."], $this->errors);
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    public function testDeleteSuccessAndFailure(): void
    {
        $repo = $this->createMock(SlideRepositoryInterface::class);
        $repo->expects($this->once())->method('deleteById')->with(6)->willReturn(true);
        (new Delete($this->context(['entity_id' => '6']), $repo))->execute();
        $this->assertSame(['Slide deleted.'], $this->success);
        $this->assertSame(['*/*/', []], $this->redirect);

        $failing = $this->createStub(SlideRepositoryInterface::class);
        $failing->method('deleteById')->willThrowException(new LocalizedException(__('Locked')));
        (new Delete($this->context(['entity_id' => '6']), $failing))->execute();
        $this->assertSame(['Locked'], $this->errors);
        $this->assertSame(['*/*/edit', ['entity_id' => 6]], $this->redirect);
    }

    private function filterOver(array $items): array
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator($items));
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        $filter = $this->createStub(UiFilter::class);
        $filter->method('getCollection')->willReturn($collection);
        return [$filter, $factory];
    }

    public function testMassDeleteCountsSuccessesAndFailures(): void
    {
        $a = $this->slide(['entity_id' => 1]);
        $b = $this->slide(['entity_id' => 2]);
        $c = $this->slide(['entity_id' => 3]);
        [$filter, $factory] = $this->filterOver([$a, $b, $c]);
        $repo = $this->createStub(SlideRepositoryInterface::class);
        $repo->method('delete')->willReturnCallback(static function ($s) use ($b) {
            if ($s === $b) {
                throw new \RuntimeException('fk');
            }
            return true;
        });

        (new MassDelete($this->context(), $filter, $factory, $repo))->execute();

        $this->assertSame(['2 slide(s) deleted.'], $this->success);
        $this->assertSame(['1 slide(s) failed to delete.'], $this->errors);
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    public function testMassStatusRequiresStatusParam(): void
    {
        [$filter, $factory] = $this->filterOver([$this->slide()]);
        $repo = $this->createMock(SlideRepositoryInterface::class);
        $repo->expects($this->never())->method('save');

        (new MassStatus($this->context(), $filter, $factory, $repo))->execute();
        $this->assertSame(['Missing status parameter.'], $this->errors);
    }

    public function testMassStatusEnablesSelectedSlides(): void
    {
        $a = $this->slide(['entity_id' => 1, 'is_active' => 0]);
        $b = $this->slide(['entity_id' => 2, 'is_active' => 0]);
        [$filter, $factory] = $this->filterOver([$a, $b]);
        $repo = $this->createMock(SlideRepositoryInterface::class);
        $repo->expects($this->exactly(2))->method('save');

        (new MassStatus($this->context(['status' => '1']), $filter, $factory, $repo))->execute();

        $this->assertTrue($a->getIsActive());
        $this->assertTrue($b->getIsActive());
        $this->assertSame(['2 slide(s) enabled.'], $this->success);
    }

    public function testMassStatusDisableReportsFailures(): void
    {
        [$filter, $factory] = $this->filterOver([$this->slide(['is_active' => 1])]);
        $repo = $this->createStub(SlideRepositoryInterface::class);
        $repo->method('save')->willThrowException(new \RuntimeException('x'));

        (new MassStatus($this->context(['status' => '0']), $filter, $factory, $repo))->execute();

        $this->assertSame([], $this->success);
        $this->assertSame(['1 slide(s) failed to update.'], $this->errors);
    }

    private function upload(array $params, array $files, ImageUploader $uploader, ?UploadExtensionPolicy $policy = null): array
    {
        $captured = [];
        $json = $this->createStub(Json::class);
        $json->method('setData')->willReturnCallback(function ($d) use (&$captured, $json) {
            $captured = $d;
            return $json;
        });
        $jsonFactory = $this->createStub(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($json);

        (new Upload(
            $this->context($params, null, $files),
            $uploader,
            $jsonFactory,
            $policy ?? $this->createStub(UploadExtensionPolicy::class)
        ))->execute();
        return $captured;
    }

    public function testUploadRejectsUnknownField(): void
    {
        $uploader = $this->createMock(ImageUploader::class);
        $uploader->expects($this->never())->method('saveFileToTmpDir');

        $this->assertSame('Unknown image field.', $this->upload(['param' => 'logo'], [], $uploader)['error']);
    }

    public function testUploadRequiresFile(): void
    {
        $uploader = $this->createMock(ImageUploader::class);
        $uploader->expects($this->never())->method('saveFileToTmpDir');

        $this->assertSame('No image file was uploaded.', $this->upload([], [], $uploader)['error']);
    }

    public function testUploadReportsPolicyRejection(): void
    {
        $policy = $this->createStub(UploadExtensionPolicy::class);
        $policy->method('assertSafeExtension')->willThrowException(new LocalizedException(__('Blocked extension')));
        $uploader = $this->createMock(ImageUploader::class);
        $uploader->expects($this->never())->method('saveFileToTmpDir');

        $result = $this->upload([], ['image_desktop' => ['name' => 'x.php.jpg']], $uploader, $policy);
        $this->assertSame('Blocked extension', $result['error']);
    }

    public function testUploadReturnsUploaderResultForMobileField(): void
    {
        $uploader = $this->createMock(ImageUploader::class);
        $uploader->expects($this->once())->method('saveFileToTmpDir')->with('image_mobile')
            ->willReturn(['name' => 'm.jpg', 'url' => 'u']);

        $result = $this->upload(['param' => 'image_mobile'], ['image_mobile' => ['name' => 'm.jpg']], $uploader);
        $this->assertSame(['name' => 'm.jpg', 'url' => 'u'], $result);
    }

    public function testEditRedirectsWhenSlideMissing(): void
    {
        $repo = $this->createStub(SlideRepositoryInterface::class);
        $repo->method('getById')->willThrowException(new NoSuchEntityException(__('nope')));
        $pageFactory = $this->createMock(PageFactory::class);
        $pageFactory->expects($this->never())->method('create');

        (new Edit($this->context(['entity_id' => 9]), $pageFactory, $this->createStub(Registry::class), $repo, $this->createStub(DataPersistorInterface::class)))->execute();

        $this->assertSame(['This slide no longer exists.'], $this->errors);
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    public function testEditRegistersSlideAndTitlesPage(): void
    {
        $slide = $this->slide(['entity_id' => 9]);
        $repo = $this->createStub(SlideRepositoryInterface::class);
        $repo->method('getById')->willReturn($slide);
        $registry = $this->createMock(Registry::class);
        $registry->expects($this->once())->method('register')->with('panth_heroslider_slide', $slide);
        $page = $this->pageExpectingTitle('Edit Slide');
        $pageFactory = $this->createStub(PageFactory::class);
        $pageFactory->method('create')->willReturn($page);

        $result = (new Edit($this->context(['entity_id' => 9]), $pageFactory, $registry, $repo, $this->createStub(DataPersistorInterface::class)))->execute();
        $this->assertSame($page, $result);
    }

    public function testEditNewSlideUsesNewTitleWithoutRegistry(): void
    {
        $registry = $this->createMock(Registry::class);
        $registry->expects($this->never())->method('register');
        $pageFactory = $this->createStub(PageFactory::class);
        $pageFactory->method('create')->willReturn($this->pageExpectingTitle('New Slide'));

        (new Edit($this->context(), $pageFactory, $registry, $this->createStub(SlideRepositoryInterface::class), $this->createStub(DataPersistorInterface::class)))->execute();
    }

    private function pageExpectingTitle(string $title): Page
    {
        $titleObj = $this->createMock(Title::class);
        $titleObj->expects($this->once())->method('prepend')
            ->with($this->callback(static fn($t) => (string)$t === $title));
        $config = $this->createStub(PageConfig::class);
        $config->method('getTitle')->willReturn($titleObj);
        $page = $this->createStub(Page::class);
        $page->method('getConfig')->willReturn($config);
        return $page;
    }
}
