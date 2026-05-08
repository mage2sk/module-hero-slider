<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 *
 * Daily prune of `panth_hero_slider_stat` rows older than the configured
 * retention window (default 365 days). Keeps the table compact long-term
 * without burdening admin SQL.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Cron;

use Panth\HeroSlider\Model\Config as HeroConfig;
use Panth\HeroSlider\Model\StatTracker;
use Psr\Log\LoggerInterface;

class PruneStats
{
    public function __construct(
        private readonly HeroConfig $heroConfig,
        private readonly StatTracker $tracker,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->heroConfig->isAnalyticsEnabled()) {
            return;
        }
        $days = $this->heroConfig->getAnalyticsRetentionDays();
        $deleted = $this->tracker->pruneOlderThan($days);
        if ($deleted > 0) {
            $this->logger->info(
                sprintf('[Panth_HeroSlider] pruned %d stat rows older than %d days', $deleted, $days)
            );
        }
    }
}
