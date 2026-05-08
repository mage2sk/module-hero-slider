<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const XPATH_ENABLED              = 'panth_heroslider/general/enabled';
    public const XPATH_AUTOPLAY             = 'panth_heroslider/general/autoplay';
    public const XPATH_INTERVAL             = 'panth_heroslider/general/interval';
    public const XPATH_PER_PAGE             = 'panth_heroslider/general/per_page';
    public const XPATH_SHOW_ARROWS          = 'panth_heroslider/general/show_arrows';
    public const XPATH_MOBILE_BREAKPOINT    = 'panth_heroslider/general/mobile_breakpoint';
    public const XPATH_AUTO_INJECT_HOMEPAGE = 'panth_heroslider/general/auto_inject_homepage';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool)$this->scopeConfig->getValue(self::XPATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function isAutoplay(): bool
    {
        return (bool)$this->scopeConfig->getValue(self::XPATH_AUTOPLAY, ScopeInterface::SCOPE_STORE);
    }

    public function getInterval(): int
    {
        $v = (int)$this->scopeConfig->getValue(self::XPATH_INTERVAL, ScopeInterface::SCOPE_STORE);
        return $v > 0 ? $v : 22000;
    }

    public function getPerPage(): int
    {
        $v = (int)$this->scopeConfig->getValue(self::XPATH_PER_PAGE, ScopeInterface::SCOPE_STORE);
        return $v > 0 ? $v : 3;
    }

    public function showArrows(): bool
    {
        return (bool)$this->scopeConfig->getValue(self::XPATH_SHOW_ARROWS, ScopeInterface::SCOPE_STORE);
    }

    public function getMobileBreakpoint(): int
    {
        $v = (int)$this->scopeConfig->getValue(self::XPATH_MOBILE_BREAKPOINT, ScopeInterface::SCOPE_STORE);
        return $v > 0 ? $v : 1025;
    }

    public function isAutoInjectHomepage(): bool
    {
        return (bool)$this->scopeConfig->getValue(self::XPATH_AUTO_INJECT_HOMEPAGE, ScopeInterface::SCOPE_STORE);
    }
}
