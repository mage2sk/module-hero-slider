<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Test\Unit\Model;

use Panth\HeroSlider\Model\Slide;
use Panth\HeroSlider\Model\Slider;
use PHPUnit\Framework\TestCase;

class SlideAndSliderModelTest extends TestCase
{
    private function slide(array $data, ?array $orig = null): Slide
    {
        $slide = (new \ReflectionClass(Slide::class))->newInstanceWithoutConstructor();
        $slide->setIdFieldName('entity_id');
        $slide->setData($data);
        if ($orig !== null) {
            foreach ($orig as $k => $v) {
                $slide->setOrigData($k, $v);
            }
        }
        return $slide;
    }

    private function slider(array $data): Slider
    {
        $slider = (new \ReflectionClass(Slider::class))->newInstanceWithoutConstructor();
        $slider->setIdFieldName('slider_id');
        $slider->setData($data);
        return $slider;
    }

    public function testSlideIdentitiesIncludeCurrentAndPreviousSlider(): void
    {
        $slide = $this->slide(['entity_id' => 4, 'slider_id' => 2], ['slider_id' => 1]);

        $this->assertSame(
            ['panth_hero_slide_4', 'panth_hero_slider_2', 'panth_hero_slider_1'],
            $slide->getIdentities()
        );
    }

    public function testSlideIdentitiesAreDeduplicatedWhenSliderUnchanged(): void
    {
        $slide = $this->slide(['entity_id' => 4, 'slider_id' => '2'], ['slider_id' => 2]);

        $this->assertSame(['panth_hero_slide_4', 'panth_hero_slider_2'], $slide->getIdentities());
    }

    public function testSlideIdentitiesWithoutSlider(): void
    {
        $this->assertSame(['panth_hero_slide_4'], $this->slide(['entity_id' => 4])->getIdentities());
    }

    public function testSlideTypedAccessorsNormaliseValues(): void
    {
        $slide = $this->slide(['slider_id' => '', 'sort_order' => '5', 'is_active' => '1', 'title' => 12]);

        $this->assertNull($slide->getSliderId());
        $this->assertSame(5, $slide->getSortOrder());
        $this->assertTrue($slide->getIsActive());
        $this->assertSame('12', $slide->getTitle());
        $this->assertNull($slide->getLinkUrl());

        $slide->setSliderId(3)->setIsActive(false);
        $this->assertSame(3, $slide->getSliderId());
        $this->assertSame(0, $slide->getData('is_active'));
    }

    public function testSliderIdentities(): void
    {
        $this->assertSame(['panth_hero_slider'], $this->slider([])->getIdentities());
        $this->assertSame(
            ['panth_hero_slider', 'panth_hero_slider_6'],
            $this->slider(['slider_id' => 6])->getIdentities()
        );
    }

    public function testSliderStoreIdsAreIntegerUniqueAndReindexed(): void
    {
        $slider = $this->slider([]);
        $this->assertSame([], $slider->getStoreIds());

        $slider->setStoreIds(['1', 2, '1', '0']);
        $this->assertSame([1, 2, 0], $slider->getStoreIds());

        $slider->setData('store_ids', 'not-an-array');
        $this->assertSame([], $slider->getStoreIds());
    }

    public function testSliderIsActiveIsStoredAsInteger(): void
    {
        $slider = $this->slider([])->setIsActive(true);

        $this->assertSame(1, $slider->getData('is_active'));
        $this->assertTrue($slider->getIsActive());
    }
}
