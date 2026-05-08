<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Block;

use Magento\Framework\Math\Random;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Panth\HeroSlider\Api\Data\SlideInterface;
use Panth\HeroSlider\Model\Config as HeroConfig;
use Panth\HeroSlider\Model\ResourceModel\Slide\CollectionFactory;

class Slider extends Template
{
    private ?array $slidesCache = null;
    private ?string $sliderIdCache = null;

    public function __construct(
        Context $context,
        private readonly HeroConfig $heroConfig,
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Random $mathRandom,
        array $data = []
    ) {
        parent::__construct($context, $data);
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
        $coll = $this->collectionFactory->create()
            ->addActiveFilter()
            ->addSortOrder();
        $this->slidesCache = array_values($coll->getItems());
        return $this->slidesCache;
    }

    public function getSliderId(): string
    {
        if ($this->sliderIdCache === null) {
            $this->sliderIdCache = 'panth-hero-slider-' . $this->mathRandom->getRandomString(16, Random::CHARS_LOWERS . Random::CHARS_DIGITS);
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
}
