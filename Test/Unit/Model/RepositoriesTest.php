<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Test\Unit\Model;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\HeroSlider\Model\ResourceModel\Slide as SlideResource;
use Panth\HeroSlider\Model\ResourceModel\Slider as SliderResource;
use Panth\HeroSlider\Model\ResourceModel\Slider\Collection as SliderCollection;
use Panth\HeroSlider\Model\ResourceModel\Slider\CollectionFactory as SliderCollectionFactory;
use Panth\HeroSlider\Model\Slide;
use Panth\HeroSlider\Model\SlideFactory;
use Panth\HeroSlider\Model\SlideRepository;
use Panth\HeroSlider\Model\Slider;
use Panth\HeroSlider\Model\SliderFactory;
use Panth\HeroSlider\Model\SliderRepository;
use PHPUnit\Framework\TestCase;

class RepositoriesTest extends TestCase
{
    private function slideModel(?int $id): Slide
    {
        $slide = $this->createStub(Slide::class);
        $slide->method('getId')->willReturn($id);
        return $slide;
    }

    private function slideRepo(SlideResource $resource, ?Slide $created = null): SlideRepository
    {
        $factory = $this->createStub(SlideFactory::class);
        $factory->method('create')->willReturn($created ?? $this->slideModel(null));
        return new SlideRepository($resource, $factory);
    }

    public function testSlideGetByIdReturnsLoadedSlide(): void
    {
        $slide = $this->slideModel(8);
        $resource = $this->createMock(SlideResource::class);
        $resource->expects($this->once())->method('load')->with($slide, 8);

        $this->assertSame($slide, $this->slideRepo($resource, $slide)->getById(8));
    }

    public function testSlideGetByIdThrowsWhenMissing(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Slide with id "8" does not exist.');

        $this->slideRepo($this->createStub(SlideResource::class))->getById(8);
    }

    public function testSlideSaveWrapsResourceErrors(): void
    {
        $resource = $this->createStub(SlideResource::class);
        $resource->method('save')->willThrowException(new \RuntimeException('duplicate'));

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Could not save the slide: duplicate');
        $this->slideRepo($resource)->save($this->slideModel(1));
    }

    public function testSlideSaveReturnsSameInstance(): void
    {
        $slide = $this->slideModel(1);
        $resource = $this->createMock(SlideResource::class);
        $resource->expects($this->once())->method('save')->with($slide);

        $this->assertSame($slide, $this->slideRepo($resource)->save($slide));
    }

    public function testSlideDeleteByIdLoadsThenDeletes(): void
    {
        $slide = $this->slideModel(3);
        $resource = $this->createMock(SlideResource::class);
        $resource->expects($this->once())->method('load')->with($slide, 3);
        $resource->expects($this->once())->method('delete')->with($slide);

        $this->assertTrue($this->slideRepo($resource, $slide)->deleteById(3));
    }

    public function testSlideDeleteWrapsErrors(): void
    {
        $resource = $this->createStub(SlideResource::class);
        $resource->method('delete')->willThrowException(new \RuntimeException('fk'));

        $this->expectException(CouldNotDeleteException::class);
        $this->expectExceptionMessage('Could not delete the slide: fk');
        $this->slideRepo($resource)->delete($this->slideModel(3));
    }

    private function sliderModel(?int $id): Slider
    {
        $slider = $this->createStub(Slider::class);
        $slider->method('getId')->willReturn($id);
        return $slider;
    }

    private function sliderRepo(
        SliderResource $resource,
        ?Slider $created = null,
        ?SliderCollectionFactory $collectionFactory = null
    ): SliderRepository {
        $factory = $this->createStub(SliderFactory::class);
        $factory->method('create')->willReturn($created ?? $this->sliderModel(null));
        return new SliderRepository(
            $resource,
            $factory,
            $collectionFactory ?? $this->createStub(SliderCollectionFactory::class)
        );
    }

    public function testSliderGetByIdThrowsWhenMissing(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Slider with id "2" does not exist.');

        $this->sliderRepo($this->createStub(SliderResource::class))->getById(2);
    }

    public function testSliderGetByIdReturnsLoaded(): void
    {
        $slider = $this->sliderModel(2);

        $this->assertSame($slider, $this->sliderRepo($this->createStub(SliderResource::class), $slider)->getById(2));
    }

    private function collectionFactoryReturning(Slider $first): SliderCollectionFactory
    {
        $collection = $this->createMock(SliderCollection::class);
        $collection->expects($this->once())->method('addFieldToFilter')
            ->with('identifier', 'homepage_hero')->willReturnSelf();
        $collection->expects($this->once())->method('setPageSize')->with(1)->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($first);
        $factory = $this->createStub(SliderCollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        return $factory;
    }

    public function testGetByIdentifierReturnsMatch(): void
    {
        $match = $this->sliderModel(5);
        $repo = $this->sliderRepo($this->createStub(SliderResource::class), null, $this->collectionFactoryReturning($match));

        $this->assertSame($match, $repo->getByIdentifier('homepage_hero'));
    }

    public function testGetByIdentifierReturnsNullWhenNotFound(): void
    {
        $repo = $this->sliderRepo(
            $this->createStub(SliderResource::class),
            null,
            $this->collectionFactoryReturning($this->sliderModel(null))
        );

        $this->assertNull($repo->getByIdentifier('homepage_hero'));
    }

    public function testSliderSaveAndDeleteWrapErrors(): void
    {
        $resource = $this->createStub(SliderResource::class);
        $resource->method('save')->willThrowException(new \RuntimeException('dup'));
        $resource->method('delete')->willThrowException(new \RuntimeException('fk'));
        $repo = $this->sliderRepo($resource);

        try {
            $repo->save($this->sliderModel(1));
            $this->fail('Expected CouldNotSaveException');
        } catch (CouldNotSaveException $e) {
            $this->assertSame('Could not save the slider: dup', $e->getMessage());
        }

        $this->expectException(CouldNotDeleteException::class);
        $repo->delete($this->sliderModel(1));
    }

    public function testSliderSaveExplainsDuplicateIdentifier(): void
    {
        $resource = $this->createStub(SliderResource::class);
        $resource->method('save')->willThrowException(new AlreadyExistsException(__('Unique constraint violation found')));
        $slider = $this->createStub(Slider::class);
        $slider->method('getIdentifier')->willReturn('sample_hero_main');

        try {
            $this->sliderRepo($resource)->save($slider);
            $this->fail('Expected CouldNotSaveException');
        } catch (CouldNotSaveException $e) {
            $this->assertSame(
                'A slider with the identifier "sample_hero_main" already exists. Use a different identifier.',
                $e->getMessage()
            );
            $this->assertInstanceOf(AlreadyExistsException::class, $e->getPrevious());
        }
    }

    public function testSliderDeleteByIdDeletesLoadedSlider(): void
    {
        $slider = $this->sliderModel(4);
        $resource = $this->createMock(SliderResource::class);
        $resource->expects($this->once())->method('delete')->with($slider);

        $this->assertTrue($this->sliderRepo($resource, $slider)->deleteById(4));
    }
}
