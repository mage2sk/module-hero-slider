<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Panth\HeroSlider\Model\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private function config(array $values, array $flags = []): Config
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn(string $path) => $values[$path] ?? null
        );
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn(string $path) => (bool)($flags[$path] ?? false)
        );
        return new Config($scopeConfig);
    }

    public function testNumericSettingsFallBackToDefaultsWhenUnsetOrInvalid(): void
    {
        $invalid = [
            Config::XPATH_INTERVAL            => '0',
            Config::XPATH_PER_PAGE            => '-2',
            Config::XPATH_MOBILE_BREAKPOINT   => 'abc',
            Config::XPATH_ANALYTICS_RETENTION => '',
        ];
        foreach ([[], $invalid] as $values) {
            $config = $this->config($values);
            $this->assertSame(22000, $config->getInterval());
            $this->assertSame(3, $config->getPerPage());
            $this->assertSame(1025, $config->getMobileBreakpoint());
            $this->assertSame(365, $config->getAnalyticsRetentionDays());
        }
    }

    public function testNumericSettingsUseConfiguredPositiveValues(): void
    {
        $config = $this->config([
            Config::XPATH_INTERVAL            => '5000',
            Config::XPATH_PER_PAGE            => '1',
            Config::XPATH_MOBILE_BREAKPOINT   => '768',
            Config::XPATH_ANALYTICS_RETENTION => '30',
        ]);

        $this->assertSame(5000, $config->getInterval());
        $this->assertSame(1, $config->getPerPage());
        $this->assertSame(768, $config->getMobileBreakpoint());
        $this->assertSame(30, $config->getAnalyticsRetentionDays());
    }

    public function testBooleanFlags(): void
    {
        $on = $this->config([
            Config::XPATH_ENABLED              => '1',
            Config::XPATH_AUTOPLAY             => '1',
            Config::XPATH_SHOW_ARROWS          => '1',
            Config::XPATH_AUTO_INJECT_HOMEPAGE => '1',
            Config::XPATH_ANALYTICS_ENABLED    => '1',
        ], [Config::XPATH_VALIDATE_LAYOUT_ID => true]);
        $this->assertTrue($on->isEnabled());
        $this->assertTrue($on->isAutoplay());
        $this->assertTrue($on->showArrows());
        $this->assertTrue($on->isAutoInjectHomepage());
        $this->assertTrue($on->isAnalyticsEnabled());
        $this->assertTrue($on->isLayoutSliderIdValidated());

        $off = $this->config([Config::XPATH_ENABLED => '0']);
        $this->assertFalse($off->isEnabled());
        $this->assertFalse($off->isAutoplay());
        $this->assertFalse($off->showArrows());
        $this->assertFalse($off->isAutoInjectHomepage());
        $this->assertFalse($off->isAnalyticsEnabled());
        $this->assertFalse($off->isLayoutSliderIdValidated());
    }

    public function testTrackRateLimitDefaultsTo60WhenUnset(): void
    {
        $this->assertSame(60, $this->config([])->getTrackRateLimitPerMinute());
        $this->assertSame(60, $this->config([Config::XPATH_TRACK_RATE_LIMIT => ''])->getTrackRateLimitPerMinute());
    }

    public function testTrackRateLimitZeroDisablesAndNegativeIsClamped(): void
    {
        $this->assertSame(0, $this->config([Config::XPATH_TRACK_RATE_LIMIT => '0'])->getTrackRateLimitPerMinute());
        $this->assertSame(0, $this->config([Config::XPATH_TRACK_RATE_LIMIT => '-5'])->getTrackRateLimitPerMinute());
        $this->assertSame(120, $this->config([Config::XPATH_TRACK_RATE_LIMIT => '120'])->getTrackRateLimitPerMinute());
    }
}
