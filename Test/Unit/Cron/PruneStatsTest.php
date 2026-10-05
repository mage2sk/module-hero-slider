<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Test\Unit\Cron;

use Panth\HeroSlider\Cron\PruneStats;
use Panth\HeroSlider\Model\Config;
use Panth\HeroSlider\Model\StatTracker;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PruneStatsTest extends TestCase
{
    public function testSkipsWhenAnalyticsDisabled(): void
    {
        $config = $this->createStub(Config::class);
        $config->method('isAnalyticsEnabled')->willReturn(false);
        $tracker = $this->createMock(StatTracker::class);
        $tracker->expects($this->never())->method('pruneOlderThan');

        (new PruneStats($config, $tracker, $this->createStub(LoggerInterface::class)))->execute();
    }

    public function testPrunesWithRetentionAndLogsDeletedRows(): void
    {
        $config = $this->createStub(Config::class);
        $config->method('isAnalyticsEnabled')->willReturn(true);
        $config->method('getAnalyticsRetentionDays')->willReturn(90);
        $tracker = $this->createMock(StatTracker::class);
        $tracker->expects($this->once())->method('pruneOlderThan')->with(90)->willReturn(12);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')
            ->with('[Panth_HeroSlider] pruned 12 stat rows older than 90 days');

        (new PruneStats($config, $tracker, $logger))->execute();
    }

    public function testDoesNotLogWhenNothingDeleted(): void
    {
        $config = $this->createStub(Config::class);
        $config->method('isAnalyticsEnabled')->willReturn(true);
        $config->method('getAnalyticsRetentionDays')->willReturn(365);
        $tracker = $this->createMock(StatTracker::class);
        $tracker->expects($this->once())->method('pruneOlderThan')->with(365)->willReturn(0);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');

        (new PruneStats($config, $tracker, $logger))->execute();
    }
}
