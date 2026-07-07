<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Block\Adminhtml\Slide\Edit;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Panth\HeroSlider\Model\StatTracker;

class AnalyticsHtml extends Template
{
    protected $_template = 'Panth_HeroSlider::slide/analytics.phtml';

    public function __construct(
        Context $context,
        private readonly StatTracker $tracker,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getSlideId(): int
    {
        return (int)$this->getRequest()->getParam('entity_id');
    }

    public function isNewSlide(): bool
    {
        return $this->getSlideId() === 0;
    }

    public function getTotals(int $days = 30): array
    {
        $id = $this->getSlideId();
        if (!$id) {
            return ['views' => 0, 'clicks' => 0];
        }
        $totals = $this->tracker->getTotals([$id], $days);
        return $totals[$id] ?? ['views' => 0, 'clicks' => 0];
    }

    public function getDeviceBreakdown(int $days = 30): array
    {
        return $this->tracker->getDeviceBreakdown($this->getSlideId(), $days);
    }

    public function getTimeline(int $days = 30): array
    {
        return $this->tracker->getTimeline($this->getSlideId(), $days);
    }

    public function ctr(int $views, int $clicks): string
    {
        return $views > 0 ? sprintf('%.2f%%', $clicks * 100 / $views) : '—';
    }

    public function deviceLabel(string $device): string
    {
        return ucfirst($device);
    }
}
