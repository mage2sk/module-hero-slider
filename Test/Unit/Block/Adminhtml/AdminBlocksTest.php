<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Test\Unit\Block\Adminhtml;

use Magento\Backend\Block\Widget\Context as WidgetContext;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Backend\Block\Template\Context as TemplateContext;
use Panth\HeroSlider\Block\Adminhtml\Slide\Edit\AnalyticsHtml;
use Panth\HeroSlider\Block\Adminhtml\Slide\Edit\DeleteButton as SlideDeleteButton;
use Panth\HeroSlider\Block\Adminhtml\Slide\Edit\SaveAndContinueButton as SlideSaveAndContinueButton;
use Panth\HeroSlider\Block\Adminhtml\Slide\Edit\SaveButton as SlideSaveButton;
use Panth\HeroSlider\Block\Adminhtml\Slider\Edit\DeleteButton as SliderDeleteButton;
use Panth\HeroSlider\Block\Adminhtml\Slider\Edit\SaveAndContinueButton as SliderSaveAndContinueButton;
use Panth\HeroSlider\Model\StatTracker;
use PHPUnit\Framework\TestCase;

class AdminBlocksTest extends TestCase
{
    protected function tearDown(): void
    {
        (new \ReflectionProperty(ObjectManager::class, '_instance'))->setValue(null, null);
    }

    private function request(array $params): HttpRequest
    {
        $request = $this->createStub(HttpRequest::class);
        $request->method('getParam')->willReturnCallback(static fn($k, $d = null) => $params[$k] ?? $d);
        return $request;
    }

    private function widgetContext(array $params, ?UrlInterface $url = null): WidgetContext
    {
        $escaper = $this->createStub(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(static fn($v) => htmlspecialchars((string)$v));
        $escaper->method('escapeJs')->willReturnCallback(static fn($v) => addslashes((string)$v));
        $context = $this->createStub(WidgetContext::class);
        $context->method('getRequest')->willReturn($this->request($params));
        $context->method('getEscaper')->willReturn($escaper);
        $context->method('getUrlBuilder')->willReturn($url ?? $this->createStub(UrlInterface::class));
        return $context;
    }

    public function testSlideDeleteButtonHiddenForNewSlide(): void
    {
        $this->assertSame([], (new SlideDeleteButton($this->widgetContext([])))->getButtonData());
        $this->assertSame([], (new SlideDeleteButton($this->widgetContext(['entity_id' => '-3'])))->getButtonData());
    }

    public function testSlideDeleteButtonPostsToDeleteUrl(): void
    {
        $url = $this->createMock(UrlInterface::class);
        $url->expects($this->once())->method('getUrl')->with('*/*/delete', ['entity_id' => 12])
            ->willReturn('https://admin.test/slide/delete/12');

        $data = (new SlideDeleteButton($this->widgetContext(['entity_id' => '12'], $url)))->getButtonData();

        $this->assertSame('delete', $data['class']);
        $this->assertSame(20, $data['sort_order']);
        $this->assertStringStartsWith("deleteConfirm('Are you sure you want to delete this slide?'", $data['on_click']);
        $this->assertStringContainsString("'https://admin.test/slide/delete/12'", $data['on_click']);
    }

    public function testSliderDeleteButtonUsesSliderIdParam(): void
    {
        $url = $this->createMock(UrlInterface::class);
        $url->expects($this->once())->method('getUrl')->with('*/*/delete', ['slider_id' => 5])->willReturn('u');

        $button = new SliderDeleteButton($this->widgetContext(['slider_id' => 5], $url));
        $this->assertSame(5, $button->getSliderId());
        $this->assertStringContainsString('lose their group reference', $button->getButtonData()['on_click']);
        $this->assertSame([], (new SliderDeleteButton($this->widgetContext(['entity_id' => 5])))->getButtonData());
    }

    public function testSaveButtonsTriggerFormEvents(): void
    {
        $save = (new SlideSaveButton($this->widgetContext([])))->getButtonData();
        $this->assertSame('save', $save['data_attribute']['mage-init']['button']['event']);
        $this->assertSame(90, $save['sort_order']);

        foreach ([new SlideSaveAndContinueButton($this->widgetContext([])), new SliderSaveAndContinueButton($this->widgetContext([]))] as $b) {
            $this->assertSame('saveAndContinueEdit', $b->getButtonData()['data_attribute']['mage-init']['button']['event']);
        }
    }

    private function analytics(array $params, StatTracker $tracker): AnalyticsHtml
    {
        // The backend Template constructor pulls optional helpers from the static object manager.
        ObjectManager::setInstance($this->createStub(ObjectManagerInterface::class));
        $context = $this->createStub(TemplateContext::class);
        $context->method('getRequest')->willReturn($this->request($params));
        return new AnalyticsHtml($context, $tracker);
    }

    public function testAnalyticsForNewSlideReturnsZeroesWithoutQuerying(): void
    {
        $tracker = $this->createMock(StatTracker::class);
        $tracker->expects($this->never())->method('getTotals');

        $block = $this->analytics([], $tracker);
        $this->assertTrue($block->isNewSlide());
        $this->assertSame(['views' => 0, 'clicks' => 0], $block->getTotals());
    }

    public function testAnalyticsDelegatesToTrackerForExistingSlide(): void
    {
        $tracker = $this->createMock(StatTracker::class);
        $tracker->expects($this->exactly(2))->method('getTotals')->with([9], $this->anything())
            ->willReturnOnConsecutiveCalls([9 => ['views' => 50, 'clicks' => 5]], []);
        $tracker->expects($this->once())->method('getDeviceBreakdown')->with(9, 7)->willReturn(['desktop' => []]);
        $tracker->expects($this->once())->method('getTimeline')->with(9, 14)->willReturn([['date' => 'x']]);

        $block = $this->analytics(['entity_id' => '9'], $tracker);
        $this->assertFalse($block->isNewSlide());
        $this->assertSame(['views' => 50, 'clicks' => 5], $block->getTotals());
        $this->assertSame(['views' => 0, 'clicks' => 0], $block->getTotals(7));
        $this->assertSame(['desktop' => []], $block->getDeviceBreakdown(7));
        $this->assertSame([['date' => 'x']], $block->getTimeline(14));
    }

    public function testCtrAndDeviceLabelFormatting(): void
    {
        $block = $this->analytics([], $this->createStub(StatTracker::class));

        $this->assertSame('-', $block->ctr(0, 5));
        $this->assertSame('12.50%', $block->ctr(8, 1));
        $this->assertSame('0.00%', $block->ctr(3, 0));
        $this->assertSame('Mobile', $block->deviceLabel('mobile'));
    }
}
