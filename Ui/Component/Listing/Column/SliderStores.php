<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 *
 * Renders the list of store views each slider is assigned to as a
 * comma-separated label. Stats are batched in a single SQL query across
 * all sliders on the current page — no N+1.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Ui\Component\Listing\Column;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\Listing\Columns\Column;

class SliderStores extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly ResourceConnection $resource,
        private readonly StoreManagerInterface $storeManager,
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
        $sliderIds = array_filter(array_map(static fn ($r) => (int)($r['slider_id'] ?? 0), $items));
        $linksBySlider = $this->fetchLinks($sliderIds);
        $field = (string)$this->getData('name');

        foreach ($items as &$row) {
            $id = (int)($row['slider_id'] ?? 0);
            $storeIds = $linksBySlider[$id] ?? [];
            $row[$field] = $this->labelFor($storeIds);
        }
        return $dataSource;
    }

    /**
     * @param int[] $sliderIds
     * @return array<int, int[]>
     */
    private function fetchLinks(array $sliderIds): array
    {
        if (!$sliderIds) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                $this->resource->getTableName('panth_hero_slider_slider_store'),
                ['slider_id', 'store_id']
            )
            ->where('slider_id IN (?)', $sliderIds);
        $rows = $connection->fetchAll($select);
        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r['slider_id']][] = (int)$r['store_id'];
        }
        return $out;
    }

    /**
     * @param int[] $storeIds
     */
    private function labelFor(array $storeIds): string
    {
        if (!$storeIds) {
            return (string)__('— hidden —');
        }
        if (in_array(0, $storeIds, true)) {
            return (string)__('All Store Views');
        }
        $names = [];
        foreach ($storeIds as $sid) {
            try {
                $store = $this->storeManager->getStore($sid);
                $names[] = $store->getName();
            } catch (\Throwable) {
                $names[] = '#' . $sid;
            }
        }
        return implode(', ', $names);
    }
}
