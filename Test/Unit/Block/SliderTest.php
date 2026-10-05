<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Test\Unit\Block;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\HeroSlider\Api\SliderRepositoryInterface;
use Panth\HeroSlider\Block\Slider;
use Panth\HeroSlider\Model\Config;
use Panth\HeroSlider\Model\ResourceModel\Slide\Collection;
use Panth\HeroSlider\Model\ResourceModel\Slide\CollectionFactory;
use Panth\HeroSlider\Model\ResourceModel\Slider as SliderResource;
use Panth\HeroSlider\Model\Slide;
use PHPUnit\Framework\TestCase;

class SliderTest extends TestCase
{
    private Config $config;
    private SliderResource $sliderResource;
    private CollectionFactory $collectionFactory;
    private UrlInterface $urlBuilder;
    private AssetRepository $assetRepo;

    protected function setUp(): void
    {
        $this->config = $this->createStub(Config::class);
        $this->sliderResource = $this->createStub(SliderResource::class);
        $this->collectionFactory = $this->createStub(CollectionFactory::class);
        $this->urlBuilder = $this->createStub(UrlInterface::class);
        $this->assetRepo = $this->createStub(AssetRepository::class);
    }

    private function block(array $data = []): Slider
    {
        $context = $this->createStub(Context::class);
        $context->method('getUrlBuilder')->willReturn($this->urlBuilder);
        $context->method('getAssetRepository')->willReturn($this->assetRepo);
        $context->method('getRequest')->willReturn($this->createStub(HttpRequest::class));

        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getBaseUrl')->willReturn('https://shop.test/media/');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new Slider(
            $context,
            $this->config,
            $this->collectionFactory,
            $storeManager,
            $this->sliderResource,
            $this->createStub(SliderRepositoryInterface::class),
            $data
        );
    }

    private function slide(array $data): Slide
    {
        $slide = (new \ReflectionClass(Slide::class))->newInstanceWithoutConstructor();
        $slide->setIdFieldName('entity_id');
        $slide->setData($data);
        return $slide;
    }

    private function withSlides(array $slides, int $expectedSliderId): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('addSliderFilter')->with($expectedSliderId)->willReturnSelf();
        $collection->expects($this->once())->method('addActiveFilter')->willReturnSelf();
        $collection->expects($this->once())->method('addSortOrder')->willReturnSelf();
        $collection->method('getItems')->willReturn($slides);
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->collectionFactory->expects($this->once())->method('create')->willReturn($collection);
    }

    public function testExplicitSliderIdIsUsedWithoutValidation(): void
    {
        $this->sliderResource = $this->createMock(SliderResource::class);
        $this->sliderResource->expects($this->never())->method('isActiveForStore');
        $this->sliderResource->expects($this->never())->method('getIdByIdentifier');

        $this->assertSame(7, $this->block(['slider_id' => '7'])->getResolvedSliderId());
    }

    public function testExplicitSliderIdRejectedWhenValidatedAndInactive(): void
    {
        $this->config->method('isLayoutSliderIdValidated')->willReturn(true);
        $this->sliderResource = $this->createMock(SliderResource::class);
        $this->sliderResource->expects($this->once())->method('isActiveForStore')->with(7, 1)->willReturn(false);

        $block = $this->block(['slider_id' => 7]);
        $this->assertSame(0, $block->getResolvedSliderId());
        $this->assertSame([], $block->getSlides());
    }

    public function testExplicitSliderIdAcceptedWhenValidatedAndActive(): void
    {
        $this->config->method('isLayoutSliderIdValidated')->willReturn(true);
        $this->sliderResource->method('isActiveForStore')->willReturn(true);

        $this->assertSame(7, $this->block(['slider_id' => 7])->getResolvedSliderId());
    }

    public function testFallsBackToHomepageIdentifierAndCachesResult(): void
    {
        $this->sliderResource = $this->createMock(SliderResource::class);
        $this->sliderResource->expects($this->once())->method('getIdByIdentifier')
            ->with('homepage_hero', 1)->willReturn(3);

        $block = $this->block();
        $this->assertSame(3, $block->getResolvedSliderId());
        $this->assertSame(3, $block->getResolvedSliderId());
    }

    public function testCustomIdentifierThatDoesNotResolveGivesZero(): void
    {
        $this->sliderResource = $this->createMock(SliderResource::class);
        $this->sliderResource->expects($this->once())->method('getIdByIdentifier')
            ->with('sale_banner', 1)->willReturn(null);

        $this->assertSame(0, $this->block(['slider_identifier' => 'sale_banner'])->getResolvedSliderId());
    }

    public function testSlidesAreLoadedOnceForResolvedSlider(): void
    {
        $slides = [10 => $this->slide(['entity_id' => 10]), 11 => $this->slide(['entity_id' => 11])];
        $this->withSlides($slides, 4);

        $block = $this->block(['slider_id' => 4]);
        $this->assertSame(array_values($slides), $block->getSlides());
        $this->assertCount(2, $block->getSlides());
    }

    public function testIsEnabledRequiresConfigAndSlides(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->assertFalse($this->block()->isEnabled());

        $this->withSlides([$this->slide(['entity_id' => 1])], 4);
        $this->assertTrue($this->block(['slider_id' => 4])->isEnabled());
    }

    public function testIsDisabledWhenConfigOff(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->collectionFactory->expects($this->never())->method('create');

        $this->assertFalse($this->block(['slider_id' => 4])->isEnabled());
    }

    public function testDomIdIsStableAndDependsOnSlides(): void
    {
        $this->withSlides([$this->slide(['entity_id' => 1]), $this->slide(['entity_id' => 2])], 4);
        $block = $this->block(['slider_id' => 4]);

        $expected = 'panth-hero-' . substr(sha1('panth-hero:1,2'), 0, 12);
        $this->assertSame($expected, $block->getSliderId());
        $this->assertMatchesRegularExpression('/^panth-hero-[0-9a-f]{12}$/', $block->getSliderId());
    }

    public function testImageUrls(): void
    {
        $block = $this->block();
        $both = $this->slide(['image_desktop' => '/d.jpg', 'image_mobile' => 'm.jpg']);
        $desktopOnly = $this->slide(['image_desktop' => 'd.jpg']);

        $this->assertSame('', $block->getSlideImageUrl(null));
        $this->assertSame('', $block->getSlideImageUrl(''));
        $this->assertSame('https://shop.test/media', $block->getMediaBaseUrl());
        $this->assertSame('https://shop.test/media/panth/heroslider/slide/d.jpg', $block->getDesktopSrc($both));
        $this->assertSame('https://shop.test/media/panth/heroslider/slide/m.jpg', $block->getMobileSrc($both));
        $this->assertSame('https://shop.test/media/panth/heroslider/slide/d.jpg', $block->getMobileSrc($desktopOnly));
    }

    public function testButtonDefaultsAndColorSanitising(): void
    {
        $block = $this->block();
        $empty = $this->slide(['button_label' => '  ']);
        $custom = $this->slide([
            'button_label' => ' Buy ',
            'button_bg_color' => '#ff0000',
            'button_text_color' => 'white',
        ]);
        $hostile = $this->slide([
            'button_bg_color' => 'red;background:url(x)',
            'button_text_color' => '"><script>',
        ]);

        $this->assertSame('SHOP NOW', $block->getButtonLabel($empty));
        $this->assertSame('#09090C', $block->getButtonBg($empty));
        $this->assertSame('#FFFFFF', $block->getButtonColor($empty));
        $this->assertSame('Buy', $block->getButtonLabel($custom));
        $this->assertSame('#ff0000', $block->getButtonBg($custom));
        $this->assertSame('white', $block->getButtonColor($custom));
        $this->assertSame('#09090C', $block->getButtonBg($hostile));
        $this->assertSame('#FFFFFF', $block->getButtonColor($hostile));
    }

    public function testAltAndLinkFallbacks(): void
    {
        $block = $this->block();

        $this->assertSame('Alt', $block->getAlt($this->slide(['image_alt' => 'Alt', 'title' => 'T'])));
        $this->assertSame('T', $block->getAlt($this->slide(['title' => 'T'])));
        $this->assertSame('', $block->getAlt($this->slide([])));
        $this->assertSame('#', $block->getLinkUrl($this->slide([])));
        $this->assertSame('#', $block->getLinkUrl($this->slide(['link_url' => 'javascript:alert(1)'])));
        $this->assertSame('/sale.html', $block->getLinkUrl($this->slide(['link_url' => ' /sale.html '])));
    }

    public function testIdentitiesCoverSliderAndSlides(): void
    {
        $this->withSlides([$this->slide(['entity_id' => 1]), $this->slide(['entity_id' => 2])], 4);

        $this->assertSame(
            ['panth_hero_slider', 'panth_hero_slider_4', 'panth_hero_slide_1', 'panth_hero_slide_2'],
            $this->block(['slider_id' => 4])->getIdentities()
        );
    }

    public function testIdentitiesWithoutSlider(): void
    {
        $this->assertSame(['panth_hero_slider'], $this->block()->getIdentities());
    }

    public function testSplideConfigReflectsSettings(): void
    {
        $this->config->method('getPerPage')->willReturn(3);
        $this->config->method('isAutoplay')->willReturn(true);
        $this->config->method('getInterval')->willReturn(5000);
        $this->config->method('showArrows')->willReturn(true);
        $this->config->method('getMobileBreakpoint')->willReturn(768);

        $block = $this->block();
        $config = $block->getSplideConfig();

        $this->assertSame('loop', $config['type']);
        $this->assertSame(3, $config['perPage']);
        $this->assertTrue($config['autoplay']);
        $this->assertSame(5000, $config['interval']);
        $this->assertTrue($config['arrows']);
        $this->assertSame(['perPage' => 1, 'pagination' => true, 'autoplay' => true, 'arrows' => false], $config['breakpoints'][768]);
        $this->assertStringContainsString('"breakpoints":{"768":{"perPage":1', $block->getSplideConfigJson());
    }

    public function testMultiSlideConfigEnablesKeyboardAndPauseOnInteraction(): void
    {
        $this->config->method('getPerPage')->willReturn(3);
        $this->config->method('isAutoplay')->willReturn(true);
        $this->config->method('getMobileBreakpoint')->willReturn(1025);
        $this->withSlides([$this->slide(['entity_id' => 1]), $this->slide(['entity_id' => 2])], 4);

        $block = $this->block(['slider_id' => 4]);
        $config = $block->getSplideConfig();

        $this->assertFalse($block->isSingleSlide());
        $this->assertTrue($block->hasAutoplayToggle());
        $this->assertSame('loop', $config['type']);
        $this->assertSame('focused', $config['keyboard']);
        $this->assertTrue($config['pauseOnHover']);
        $this->assertTrue($config['pauseOnFocus']);
        $this->assertTrue($config['breakpoints'][1025]['pagination']);
    }

    public function testNoAutoplayToggleWhenAutoplayIsOff(): void
    {
        $this->config->method('isAutoplay')->willReturn(false);
        $this->withSlides([$this->slide(['entity_id' => 1]), $this->slide(['entity_id' => 2])], 4);

        $block = $this->block(['slider_id' => 4]);
        $this->assertCount(2, $block->getSlides());
        $this->assertFalse($block->hasAutoplayToggle());
    }

    public function testSingleSlideIsStaticWithoutControls(): void
    {
        $this->config->method('getPerPage')->willReturn(3);
        $this->config->method('isAutoplay')->willReturn(true);
        $this->config->method('showArrows')->willReturn(true);
        $this->config->method('getMobileBreakpoint')->willReturn(1025);
        $this->withSlides([$this->slide(['entity_id' => 7])], 4);

        $block = $this->block(['slider_id' => 4]);
        $config = $block->getSplideConfig();

        $this->assertTrue($block->isSingleSlide());
        $this->assertFalse($block->hasAutoplayToggle());
        $this->assertSame('slide', $config['type']);
        $this->assertFalse($config['autoplay']);
        $this->assertFalse($config['arrows']);
        $this->assertFalse($config['pagination']);
        $this->assertFalse($config['drag']);
        $this->assertFalse($config['trimSpace']);
        $this->assertSame(
            ['perPage' => 1, 'pagination' => false, 'autoplay' => false, 'arrows' => false],
            $config['breakpoints'][1025]
        );
    }

    public function testJsonLdListsSlidesAndEscapesMarkup(): void
    {
        $this->withSlides([
            $this->slide(['entity_id' => 1, 'image_alt' => '</script><b>', 'image_desktop' => 'a.jpg', 'link_url' => '/a', 'button_label' => 'Go']),
            $this->slide(['entity_id' => 2, 'image_desktop' => 'b.jpg', 'link_url' => 'javascript:x']),
        ], 4);
        $block = $this->block(['slider_id' => 4]);

        $ld = $block->getJsonLd();
        $this->assertSame('ItemList', $ld['@type']);
        $this->assertCount(2, $ld['itemListElement']);
        $this->assertSame(1, $ld['itemListElement'][0]['position']);
        $this->assertSame('/a', $ld['itemListElement'][0]['item']['url']);
        $this->assertSame('Go', $ld['itemListElement'][0]['item']['description']);
        $this->assertSame('Slide 2', $ld['itemListElement'][1]['item']['name']);
        $this->assertSame('#', $ld['itemListElement'][1]['item']['url']);
        $this->assertSame('https://shop.test/media/panth/heroslider/slide/b.jpg', $ld['itemListElement'][1]['item']['thumbnailUrl']);

        $json = $block->getJsonLdJson();
        $this->assertStringNotContainsString('</script>', $json);
        $this->assertStringContainsString("\u003C/script\u003E", $json);
    }

    public function testTrackingEndpointAndAnalyticsFlag(): void
    {
        $this->config->method('isAnalyticsEnabled')->willReturn(true);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->urlBuilder->expects($this->once())->method('getUrl')
            ->with('panth_heroslider/track/event')->willReturn('https://shop.test/panth_heroslider/track/event/');

        $block = $this->block();
        $this->assertTrue($block->isAnalyticsEnabled());
        $this->assertSame('https://shop.test/panth_heroslider/track/event/', $block->getTrackEndpointUrl());
    }

    public function testSplideAssetUrlsComeFromModuleViewFiles(): void
    {
        $this->assetRepo = $this->createMock(AssetRepository::class);
        $this->assetRepo->expects($this->exactly(2))->method('getUrlWithParams')
            ->willReturnCallback(static fn(string $fileId) => 'https://shop.test/static/' . $fileId);

        $block = $this->block();
        $this->assertSame('https://shop.test/static/Panth_HeroSlider::splide/splide.min.js', $block->getSplideJsUrl());
        $this->assertSame('https://shop.test/static/Panth_HeroSlider::splide/splide-core.min.css', $block->getSplideCssUrl());
    }
}
