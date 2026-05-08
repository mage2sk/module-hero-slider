<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Model\ResourceModel\Slide;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(
            \Panth\HeroSlider\Model\Slide::class,
            \Panth\HeroSlider\Model\ResourceModel\Slide::class
        );
    }

    public function addActiveFilter(): self
    {
        $this->addFieldToFilter('is_active', 1);
        return $this;
    }

    public function addSortOrder(): self
    {
        $this->setOrder('sort_order', self::SORT_ORDER_ASC);
        $this->setOrder('entity_id', self::SORT_ORDER_ASC);
        return $this;
    }
}
