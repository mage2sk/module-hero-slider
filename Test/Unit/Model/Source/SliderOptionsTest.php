<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Test\Unit\Model\Source;

use Panth\HeroSlider\Model\ResourceModel\Slider\Collection;
use Panth\HeroSlider\Model\ResourceModel\Slider\CollectionFactory;
use Panth\HeroSlider\Model\Slider;
use Panth\HeroSlider\Model\Source\SliderOptions;
use PHPUnit\Framework\TestCase;

class SliderOptionsTest extends TestCase
{
    private function slider(string $id, string $name, string $identifier): Slider
    {
        $slider = $this->createStub(Slider::class);
        $slider->method('getId')->willReturn($id);
        $slider->method('getName')->willReturn($name);
        $slider->method('getIdentifier')->willReturn($identifier);
        return $slider;
    }

    private function options(array $sliders): array
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator($sliders));
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        return (new SliderOptions($factory))->toOptionArray();
    }

    public function testPlaceholderOnlyWhenNoSliders(): void
    {
        $options = $this->options([]);

        $this->assertCount(1, $options);
        $this->assertSame('', $options[0]['value']);
        $this->assertSame('-- Select Slider --', (string)$options[0]['label']);
    }

    public function testSlidersAreListedWithIntegerValueAndIdentifierInLabel(): void
    {
        $options = $this->options([
            $this->slider('3', 'Home Page Hero', 'homepage_hero'),
            $this->slider('8', 'Sale', 'sale'),
        ]);

        $this->assertCount(3, $options);
        $this->assertSame(['value' => 3, 'label' => 'Home Page Hero (homepage_hero)'], $options[1]);
        $this->assertSame(['value' => 8, 'label' => 'Sale (sale)'], $options[2]);
    }
}
