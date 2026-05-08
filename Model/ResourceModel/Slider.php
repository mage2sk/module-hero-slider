<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 *
 * Resource model for Slider entity. Persists store associations via the
 * `panth_hero_slider_slider_store` link table on save and rehydrates on
 * load — keeping the API for the model side clean (just `getStoreIds()`
 * / `setStoreIds()`).
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Slider extends AbstractDb
{
    private const STORE_TABLE = 'panth_hero_slider_slider_store';

    protected function _construct(): void
    {
        $this->_init('panth_hero_slider_slider', 'slider_id');
    }

    protected function _afterLoad(AbstractModel $object)
    {
        $id = (int)$object->getId();
        if ($id > 0) {
            $object->setData('store_ids', $this->lookupStoreIds($id));
        }
        return parent::_afterLoad($object);
    }

    protected function _afterSave(AbstractModel $object)
    {
        $id = (int)$object->getId();
        if ($id > 0) {
            // store_ids may not be present when callers do focused saves;
            // only rewrite the link table when the field was explicitly set.
            if ($object->hasData('store_ids')) {
                $newIds = array_map('intval', (array)$object->getData('store_ids'));
                $this->saveStoreLinks($id, $newIds);
            }
        }
        return parent::_afterSave($object);
    }

    /**
     * @return int[]
     */
    public function lookupStoreIds(int $sliderId): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::STORE_TABLE), 'store_id')
            ->where('slider_id = ?', $sliderId);
        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * @param int[] $storeIds
     */
    public function saveStoreLinks(int $sliderId, array $storeIds): void
    {
        $connection = $this->getConnection();
        $table = $this->getTable(self::STORE_TABLE);
        $connection->delete($table, ['slider_id = ?' => $sliderId]);
        // Empty list => no rows; treat that as "no stores" (slider hidden).
        if (!$storeIds) {
            return;
        }
        // Normalise: if 0 ("All Stores") is selected, persist ONLY the 0
        // row — any explicit store ids would be redundant.
        $ids = array_values(array_unique(array_map('intval', $storeIds)));
        if (in_array(0, $ids, true)) {
            $ids = [0];
        }
        $rows = [];
        foreach ($ids as $sid) {
            $rows[] = ['slider_id' => $sliderId, 'store_id' => $sid];
        }
        $connection->insertMultiple($table, $rows);
    }

    /**
     * Fetch the slider id matching `identifier` for the given store, or
     * the All-Stores slider if no store-specific record exists.
     */
    public function getIdByIdentifier(string $identifier, int $storeId): ?int
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(['s' => $this->getMainTable()], ['slider_id'])
            ->joinLeft(
                ['ss' => $this->getTable(self::STORE_TABLE)],
                's.slider_id = ss.slider_id',
                []
            )
            ->where('s.identifier = ?', $identifier)
            ->where('s.is_active = ?', 1)
            ->where('ss.store_id IS NULL OR ss.store_id IN (?)', [0, $storeId])
            ->limit(1);
        $id = $connection->fetchOne($select);
        return $id !== false && $id !== null ? (int)$id : null;
    }
}
