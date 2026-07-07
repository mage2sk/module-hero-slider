<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Panth\HeroSlider\Model\StatTracker;

class StatColumn extends Column
{
    private ?array $statsCache = null;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly StatTracker $tracker,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (empty($dataSource['data']['items'])) {
            return $dataSource;
        }
        $items = &$dataSource['data']['items'];
        $ids = array_filter(array_map(static fn ($r) => (int)($r['entity_id'] ?? 0), $items));
        $stats = $this->fetchStats($ids);

        $field = (string)$this->getData('name');

        $metric = (string)$this->getData('config/metric') ?: 'views';

        foreach ($items as &$row) {
            $id = (int)($row['entity_id'] ?? 0);
            $bucket = $stats[$id] ?? ['views' => 0, 'clicks' => 0];
            $views  = (int)$bucket['views'];
            $clicks = (int)$bucket['clicks'];
            switch ($metric) {
                case 'clicks':
                    $row[$field] = $clicks;
                    break;
                case 'ctr':
                    $row[$field] = $views > 0
                        ? sprintf('%.2f%%', $clicks * 100 / $views)
                        : '—';
                    break;
                case 'views':
                default:
                    $row[$field] = $views;
            }
        }
        return $dataSource;
    }

    private function fetchStats(array $ids): array
    {
        if ($this->statsCache !== null) {
            return $this->statsCache;
        }
        $this->statsCache = $this->tracker->getTotals($ids, 30);
        return $this->statsCache;
    }
}
