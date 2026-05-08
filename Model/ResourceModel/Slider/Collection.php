<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Model\ResourceModel\Slider;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'slider_id';

    protected function _construct(): void
    {
        $this->_init(
            \Panth\HeroSlider\Model\Slider::class,
            \Panth\HeroSlider\Model\ResourceModel\Slider::class
        );
    }

    public function addActiveFilter(): self
    {
        $this->addFieldToFilter('is_active', 1);
        return $this;
    }

    public function addStoreFilter(int $storeId): self
    {
        $this->getSelect()->joinLeft(
            ['ss' => $this->getTable('panth_hero_slider_slider_store')],
            'main_table.slider_id = ss.slider_id',
            []
        )->where(
            'ss.store_id IS NULL OR ss.store_id IN (?)',
            [0, $storeId]
        )->group('main_table.slider_id');
        return $this;
    }
}
