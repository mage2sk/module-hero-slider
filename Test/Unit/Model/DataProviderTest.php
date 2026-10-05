<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Test\Unit\Model;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\HeroSlider\Model\ResourceModel\Slide\Collection as SlideCollection;
use Panth\HeroSlider\Model\ResourceModel\Slide\CollectionFactory as SlideCollectionFactory;
use Panth\HeroSlider\Model\ResourceModel\Slider as SliderResource;
use Panth\HeroSlider\Model\ResourceModel\Slider\Collection as SliderCollection;
use Panth\HeroSlider\Model\ResourceModel\Slider\CollectionFactory as SliderCollectionFactory;
use Panth\HeroSlider\Model\Slide\DataProvider as SlideDataProvider;
use Panth\HeroSlider\Model\Slider\DataProvider as SliderDataProvider;
use PHPUnit\Framework\TestCase;

class DataProviderTest extends TestCase
{
    private function item(array $data): DataObject
    {
        return new DataObject($data);
    }

    private function slideProvider(array $items, mixed $persisted, ?DataPersistorInterface $persistor = null, bool $fileExists = false): SlideDataProvider
    {
        $collection = $this->createStub(SlideCollection::class);
        $collection->method('getItems')->willReturn($items);
        $collection->method('getNewEmptyItem')->willReturnCallback(fn() => new DataObject());
        $factory = $this->createStub(SlideCollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        if ($persistor === null) {
            $persistor = $this->createStub(DataPersistorInterface::class);
            $persistor->method('get')->willReturn($persisted);
        }
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.test/media/');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $media = $this->createStub(ReadInterface::class);
        $media->method('isFile')->willReturn($fileExists);
        $media->method('stat')->willReturn(['size' => 2048]);
        $media->method('getAbsolutePath')->willReturn(__FILE__);
        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('getDirectoryRead')->willReturn($media);

        return new SlideDataProvider('ds', 'entity_id', 'entity_id', $factory, $persistor, $storeManager, $filesystem);
    }

    public function testSlideDataExpandsImagesForUploaderComponent(): void
    {
        $provider = $this->slideProvider(
            [5 => $this->item(['id' => 5, 'title' => 'A', 'image_desktop' => '/d.jpg', 'image_mobile' => ''])],
            null
        );

        $data = $provider->getData();

        $this->assertSame([[
            'name' => '/d.jpg',
            'url'  => 'https://shop.test/media/panth/heroslider/slide/d.jpg',
            'size' => 0,
        ]], $data[5]['image_desktop']);
        $this->assertNull($data[5]['image_mobile']);
        $this->assertSame('A', $data[5]['title']);
    }

    public function testSlideDataIncludesFileSizeWhenImageExists(): void
    {
        $provider = $this->slideProvider([1 => $this->item(['id' => 1, 'image_desktop' => 'd.jpg'])], null, null, true);

        $image = $provider->getData()[1]['image_desktop'][0];

        $this->assertSame(2048, $image['size']);
        $this->assertArrayHasKey('type', $image);
    }

    public function testSlidePersistedFormDataIsRestoredOnceAndCleared(): void
    {
        $persistor = $this->createMock(DataPersistorInterface::class);
        $persistor->expects($this->once())->method('get')->with('panth_heroslider_slide')
            ->willReturn(['title' => 'Unsaved']);
        $persistor->expects($this->once())->method('clear')->with('panth_heroslider_slide');

        $provider = $this->slideProvider([], null, $persistor);
        $first = $provider->getData();

        $this->assertSame('Unsaved', $first['']['title']);
        $this->assertSame($first, $provider->getData());
    }

    private function sliderProvider(array $items, DataPersistorInterface $persistor, SliderResource $resource): SliderDataProvider
    {
        $collection = $this->createStub(SliderCollection::class);
        $collection->method('getItems')->willReturn($items);
        $collection->method('getNewEmptyItem')->willReturnCallback(fn() => new DataObject());
        $factory = $this->createStub(SliderCollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return new SliderDataProvider('ds', 'slider_id', 'slider_id', $factory, $persistor, $resource);
    }

    public function testPersistedUploaderArraysAfterFailedSaveAreKept(): void
    {
        $uploaded = ['name' => 'new.png', 'url' => 'https://shop.test/media/tmp/new.png', 'size' => 77];
        $persisted = [
            'title' => 'Unsaved',
            'link_url' => 'javascript:void(0)',
            'image_desktop' => [$uploaded, 'junk', ['url' => 'no-name']],
            'image_mobile' => [],
        ];

        $data = $this->slideProvider([], $persisted)->getData();

        $this->assertSame([$uploaded], $data['']['image_desktop']);
        $this->assertNull($data['']['image_mobile']);
        $this->assertSame('javascript:void(0)', $data['']['link_url']);
    }

    public function testImageFieldAcceptsJsonAndSingleArrayShapes(): void
    {
        $provider = $this->slideProvider([
            3 => $this->item([
                'id' => 3,
                'image_desktop' => '[{"name":"a.jpg"}]',
                'image_mobile' => ['name' => 'm.jpg', 'url' => 'https://shop.test/x/m.jpg'],
            ]),
            4 => $this->item(['id' => 4, 'image_desktop' => '{broken', 'image_mobile' => ['b.jpg', 7, ['name' => ['x']]]]),
        ], null);

        $data = $provider->getData();

        $this->assertSame(
            [['name' => 'a.jpg', 'url' => 'https://shop.test/media/panth/heroslider/slide/a.jpg']],
            $data[3]['image_desktop']
        );
        $this->assertSame([['name' => 'm.jpg', 'url' => 'https://shop.test/x/m.jpg']], $data[3]['image_mobile']);
        $this->assertNull($data[4]['image_desktop']);
        $this->assertNull($data[4]['image_mobile']);
    }

    public function testSliderDataAddsStoreIdsAsStrings(): void
    {
        $resource = $this->createMock(SliderResource::class);
        $resource->expects($this->once())->method('lookupStoreIds')->with(3)->willReturn([0, 2]);
        $persistor = $this->createStub(DataPersistorInterface::class);

        $data = $this->sliderProvider([3 => $this->item(['id' => 3, 'name' => 'Hero'])], $persistor, $resource)->getData();

        $this->assertSame(['id' => 3, 'name' => 'Hero', 'store_ids' => ['0', '2']], $data[3]);
    }

    public function testSliderPersistedDataOverridesUnderItsId(): void
    {
        $persistor = $this->createMock(DataPersistorInterface::class);
        $persistor->expects($this->once())->method('get')->willReturn(['id' => 9, 'name' => 'Draft']);
        $persistor->expects($this->once())->method('clear')->with('panth_heroslider_slider');

        $data = $this->sliderProvider([], $persistor, $this->createStub(SliderResource::class))->getData();

        $this->assertSame(['id' => 9, 'name' => 'Draft'], $data[9]);
    }
}
