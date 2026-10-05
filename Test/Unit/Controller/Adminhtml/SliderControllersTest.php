<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Test\Unit\Controller\Adminhtml;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\Component\MassAction\Filter as UiFilter;
use Panth\HeroSlider\Api\SliderRepositoryInterface;
use Panth\HeroSlider\Controller\Adminhtml\Slider\Delete;
use Panth\HeroSlider\Controller\Adminhtml\Slider\MassDelete;
use Panth\HeroSlider\Controller\Adminhtml\Slider\Save;
use Panth\HeroSlider\Model\ResourceModel\Slider\Collection;
use Panth\HeroSlider\Model\ResourceModel\Slider\CollectionFactory;
use Panth\HeroSlider\Model\Slider;
use Panth\HeroSlider\Model\SliderFactory;

class SliderControllersTest extends ControllerTestCase
{
    private function slider(array $data = []): Slider
    {
        $slider = (new \ReflectionClass(Slider::class))->newInstanceWithoutConstructor();
        $slider->setIdFieldName('slider_id');
        $slider->setData($data);
        return $slider;
    }

    private function save(
        ?array $post,
        array $params = [],
        ?SliderRepositoryInterface $repo = null,
        ?Slider $new = null,
        ?DataPersistorInterface $persistor = null
    ): Save {
        $factory = $this->createStub(SliderFactory::class);
        $factory->method('create')->willReturn($new ?? $this->slider());
        return new Save(
            $this->context($params, $post),
            $factory,
            $repo ?? $this->createStub(SliderRepositoryInterface::class),
            $persistor ?? $this->createStub(DataPersistorInterface::class)
        );
    }

    public function testNoPostRedirectsToGrid(): void
    {
        $this->save(null)->execute();
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    public function testIdentifierIsSanitisedAndLowercased(): void
    {
        $slider = $this->slider(['slider_id' => 3]);
        $repo = $this->createMock(SliderRepositoryInterface::class);
        $repo->expects($this->once())->method('save')->with($slider);

        $this->save([
            'identifier' => ' Home Page_Hero-2!<b> ',
            'name'       => 'Home',
            'is_active'  => '1',
            'store_ids'  => '1,2,,1',
        ], ['back' => 1], $repo, $slider)->execute();

        $this->assertSame('homepage_hero-2b', $slider->getData('identifier'));
        $this->assertSame('Home', $slider->getData('name'));
        $this->assertSame(1, $slider->getData('is_active'));
        $this->assertSame([1, 2], $slider->getData('store_ids'));
        $this->assertSame(['Slider saved.'], $this->success);
        $this->assertSame(['*/*/edit', ['slider_id' => 3]], $this->redirect);
    }

    public function testStoreIdsArrayKeepsAllStoreViewsZero(): void
    {
        $slider = $this->slider();
        $this->save(['identifier' => 'x', 'store_ids' => ['0', '', null, '0']], [], null, $slider)->execute();

        $this->assertSame([0], $slider->getData('store_ids'));
        $this->assertSame(0, $slider->getData('is_active'));
        $this->assertSame('', $slider->getData('name'));
        $this->assertSame(['*/*/', []], $this->redirect);
    }

    public function testEmptyIdentifierAfterSanitisingIsRejected(): void
    {
        $post = ['slider_id' => '5', 'identifier' => '!!! '];
        $existing = $this->slider(['slider_id' => 5]);
        $repo = $this->createMock(SliderRepositoryInterface::class);
        $repo->method('getById')->with(5)->willReturn($existing);
        $repo->expects($this->never())->method('save');
        $persistor = $this->createMock(DataPersistorInterface::class);
        $persistor->expects($this->once())->method('set')->with('panth_heroslider_slider', $post);

        $this->save($post, [], $repo, null, $persistor)->execute();

        $this->assertStringStartsWith('Identifier is required', $this->errors[0]);
        $this->assertSame(['*/*/edit', ['slider_id' => 5]], $this->redirect);
    }

    public function testUnexpectedSaveErrorIsReported(): void
    {
        $repo = $this->createStub(SliderRepositoryInterface::class);
        $repo->method('save')->willThrowException(new \RuntimeException('dup key'));

        $this->save(['identifier' => 'hero'], [], $repo)->execute();

        $this->assertSame(['Could not save the slider.'], $this->exceptions);
        $this->assertSame(['*/*/edit', ['slider_id' => 0]], $this->redirect);
    }

    public function testDeleteFlow(): void
    {
        $repo = $this->createMock(SliderRepositoryInterface::class);
        $repo->expects($this->once())->method('deleteById')->with(2)->willReturn(true);

        (new Delete($this->context(['slider_id' => '2']), $repo))->execute();
        $this->assertSame('*/*/', $this->redirect[0]);
        $this->assertCount(1, $this->success);

        (new Delete($this->context(), $this->createStub(SliderRepositoryInterface::class)))->execute();
        $this->assertCount(1, $this->errors);
        $this->assertSame('*/*/', $this->redirect[0]);
    }

    public function testDeleteFailureReturnsToEdit(): void
    {
        $repo = $this->createStub(SliderRepositoryInterface::class);
        $repo->method('deleteById')->willThrowException(new \RuntimeException('in use'));

        (new Delete($this->context(['slider_id' => '2']), $repo))->execute();

        $this->assertSame(['in use'], $this->errors);
        $this->assertSame(['*/*/edit', ['slider_id' => 2]], $this->redirect);
    }

    public function testMassDeleteCounts(): void
    {
        $ok = $this->slider(['slider_id' => 1]);
        $bad = $this->slider(['slider_id' => 2]);
        $collection = $this->createStub(Collection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$ok, $bad]));
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        $filter = $this->createStub(UiFilter::class);
        $filter->method('getCollection')->willReturn($collection);
        $repo = $this->createStub(SliderRepositoryInterface::class);
        $repo->method('delete')->willReturnCallback(static function ($s) use ($bad) {
            if ($s === $bad) {
                throw new \RuntimeException('x');
            }
            return true;
        });

        (new MassDelete($this->context(), $filter, $factory, $repo))->execute();

        $this->assertSame(['1 slider(s) deleted.'], $this->success);
        $this->assertSame(['1 slider(s) failed to delete.'], $this->errors);
    }
}
