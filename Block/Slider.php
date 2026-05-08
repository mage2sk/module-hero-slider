<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Block;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Panth\HeroSlider\Api\Data\SlideInterface;
use Panth\HeroSlider\Api\SliderRepositoryInterface;
use Panth\HeroSlider\Model\Config as HeroConfig;
use Panth\HeroSlider\Model\ResourceModel\Slide\CollectionFactory;
use Panth\HeroSlider\Model\ResourceModel\Slider as SliderResource;

class Slider extends Template
{
    private ?array $slidesCache = null;
    private ?string $sliderIdCache = null;
    private ?int $resolvedSliderIdCache = null;

    public function __construct(
        Context $context,
        private readonly HeroConfig $heroConfig,
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly SliderResource $sliderResource,
        private readonly SliderRepositoryInterface $sliderRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Resolve which slider to render. Source precedence:
     *   1. Explicit `slider_id` block argument
     *   2. `slider_identifier` block argument (widget / layout XML)
     *   3. Falls back to `homepage_hero` for the default-injected block
     *
     * Always filtered by current store via `Slider::getIdByIdentifier()`
     * so a slider not assigned to the active store renders nothing.
     */
    public function getResolvedSliderId(): int
    {
        if ($this->resolvedSliderIdCache !== null) {
            return $this->resolvedSliderIdCache;
        }
        $explicitId = (int)$this->getData('slider_id');
        if ($explicitId > 0) {
            return $this->resolvedSliderIdCache = $explicitId;
        }
        $identifier = (string)($this->getData('slider_identifier') ?: 'homepage_hero');
        $storeId = (int)$this->storeManager->getStore()->getId();
        $resolved = $this->sliderResource->getIdByIdentifier($identifier, $storeId);
        return $this->resolvedSliderIdCache = (int)($resolved ?: 0);
    }

    public function isEnabled(): bool
    {
        return $this->heroConfig->isEnabled() && count($this->getSlides()) > 0;
    }

    /** @return list<SlideInterface> */
    public function getSlides(): array
    {
        if ($this->slidesCache !== null) {
            return $this->slidesCache;
        }
        $sliderId = $this->getResolvedSliderId();
        if ($sliderId <= 0) {
            return $this->slidesCache = [];
        }
        $coll = $this->collectionFactory->create()
            ->addSliderFilter($sliderId)
            ->addActiveFilter()
            ->addSortOrder();
        $this->slidesCache = array_values($coll->getItems());
        return $this->slidesCache;
    }

    /**
     * Deterministic ID derived from the block name + active slide IDs.
     * Stable across FPC cache hits AND across multiple instances on the
     * same page (each instance gets a distinct hash because block names
     * differ).
     */
    public function getSliderId(): string
    {
        if ($this->sliderIdCache === null) {
            $ids = array_map(static fn ($s) => (int)$s->getId(), $this->getSlides());
            $key = ($this->getNameInLayout() ?: 'panth-hero') . ':' . implode(',', $ids);
            $this->sliderIdCache = 'panth-hero-' . substr(sha1($key), 0, 12);
        }
        return $this->sliderIdCache;
    }

    public function getMediaBaseUrl(): string
    {
        return rtrim($this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/');
    }

    public function getSlideImageUrl(?string $name): string
    {
        if (!$name) {
            return '';
        }
        return $this->getMediaBaseUrl() . '/panth/heroslider/slide/' . ltrim($name, '/');
    }

    public function getDesktopSrc(SlideInterface $slide): string
    {
        return $this->getSlideImageUrl($slide->getImageDesktop());
    }

    public function getMobileSrc(SlideInterface $slide): string
    {
        return $this->getSlideImageUrl($slide->getImageMobile() ?: $slide->getImageDesktop());
    }

    public function getButtonLabel(SlideInterface $slide): string
    {
        $label = trim((string)$slide->getButtonLabel());
        return $label !== '' ? $label : 'SHOP NOW';
    }

    public function getButtonBg(SlideInterface $slide): string
    {
        $color = trim((string)$slide->getButtonBgColor());
        return $color !== '' ? $color : '#09090C';
    }

    public function getButtonColor(SlideInterface $slide): string
    {
        $color = trim((string)$slide->getButtonTextColor());
        return $color !== '' ? $color : '#FFFFFF';
    }

    public function getAlt(SlideInterface $slide): string
    {
        return (string)($slide->getImageAlt() ?: $slide->getTitle() ?: '');
    }

    public function getLinkUrl(SlideInterface $slide): string
    {
        return (string)($slide->getLinkUrl() ?: '#');
    }

    /**
     * Splide config for the slider — returned as JSON for inline init.
     *
     * @return array<string,mixed>
     */
    public function getSplideConfig(): array
    {
        return [
            'type'       => 'loop',
            'perPage'    => $this->heroConfig->getPerPage(),
            'focus'      => 'center',
            'autoplay'   => $this->heroConfig->isAutoplay(),
            'interval'   => $this->heroConfig->getInterval(),
            'pagination' => false,
            'arrows'     => $this->heroConfig->showArrows(),
            'breakpoints' => [
                // At <= mobile-breakpoint: single full-width slide.
                // We deliberately omit `focus: 'center'` here — combined with
                // perPage:1 it nudges the active slide off-centre on mobile
                // and leaks a sliver of the next slide on the trailing edge.
                $this->heroConfig->getMobileBreakpoint() => [
                    'perPage'    => 1,
                    'pagination' => true,
                    'autoplay'   => $this->heroConfig->isAutoplay(),
                    'arrows'     => false,
                ],
            ],
        ];
    }

    public function getSplideConfigJson(): string
    {
        return (string)json_encode($this->getSplideConfig(), JSON_UNESCAPED_SLASHES);
    }

    public function getSplideJsUrl(): string
    {
        return 'https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/js/splide.min.js';
    }

    public function getSplideCssUrl(): string
    {
        return 'https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/css/splide-core.min.css';
    }

    public function isAnalyticsEnabled(): bool
    {
        return $this->heroConfig->isAnalyticsEnabled();
    }

    public function getTrackEndpointUrl(): string
    {
        return $this->_urlBuilder->getUrl('panth_heroslider/track/event');
    }

    /**
     * SEO: Schema.org ItemList of ImageObjects describing the slider.
     * Inlined as JSON-LD so search engines can crawl the campaign images
     * without executing the carousel script.
     *
     * @return array<string, mixed>
     */
    public function getJsonLd(): array
    {
        $items = [];
        $position = 1;
        foreach ($this->getSlides() as $slide) {
            $name = $slide->getImageAlt() ?: $slide->getTitle() ?: ('Slide ' . $position);
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'item'     => [
                    '@type'       => 'ImageObject',
                    'name'        => (string)$name,
                    'contentUrl'  => $this->getDesktopSrc($slide),
                    'thumbnailUrl'=> $this->getMobileSrc($slide),
                    'url'         => $this->getLinkUrl($slide),
                    'description' => (string)($slide->getButtonLabel() ?: ''),
                ],
            ];
        }
        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'itemListElement' => $items,
        ];
    }

    public function getJsonLdJson(): string
    {
        return (string)json_encode(
            $this->getJsonLd(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}
