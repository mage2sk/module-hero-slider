<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class StatTracker
{
    public const EVENT_VIEW  = 'view';
    public const EVENT_CLICK = 'click';

    public const DEVICE_DESKTOP = 'desktop';
    public const DEVICE_TABLET  = 'tablet';
    public const DEVICE_MOBILE  = 'mobile';

    public const VALID_EVENTS  = [self::EVENT_VIEW, self::EVENT_CLICK];
    public const VALID_DEVICES = [self::DEVICE_DESKTOP, self::DEVICE_TABLET, self::DEVICE_MOBILE];

    private const TABLE = 'panth_hero_slider_stat';

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function track(int $slideId, string $eventType, string $deviceType, ?int $storeId = null): bool
    {
        if ($slideId <= 0) {
            return false;
        }
        if (!in_array($eventType, self::VALID_EVENTS, true)) {
            return false;
        }
        if (!in_array($deviceType, self::VALID_DEVICES, true)) {
            return false;
        }

        try {
            if ($storeId === null) {
                $storeId = (int)$this->storeManager->getStore()->getId();
            }
            $connection = $this->resource->getConnection();
            $connection->query(
                'INSERT INTO ' . $connection->quoteIdentifier($this->resource->getTableName(self::TABLE))
                . ' (slide_id, event_date, store_id, event_type, device_type, event_count)'
                . ' VALUES (?, UTC_DATE(), ?, ?, ?, 1)'
                . ' ON DUPLICATE KEY UPDATE event_count = event_count + 1',
                [$slideId, $storeId, $eventType, $deviceType]
            );
            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_HeroSlider StatTracker] ' . $e->getMessage());
            return false;
        }
    }

    public function getTotals(array $slideIds, int $daysBack = 30, ?int $storeId = null): array
    {
        $result = [];
        foreach ($slideIds as $id) {
            $result[(int)$id] = ['views' => 0, 'clicks' => 0];
        }
        if (!$slideIds) {
            return $result;
        }

        try {
            $connection = $this->resource->getConnection();
            $select = $connection->select()
                ->from(
                    $this->resource->getTableName(self::TABLE),
                    [
                        'slide_id',
                        'event_type',
                        'total' => new \Zend_Db_Expr('SUM(event_count)'),
                    ]
                )
                ->where('slide_id IN (?)', $slideIds)
                ->where('event_date >= ?', gmdate('Y-m-d', strtotime("-{$daysBack} days")))
                ->group(['slide_id', 'event_type']);
            if ($storeId !== null) {
                $select->where('store_id = ?', $storeId);
            }
            $rows = $connection->fetchAll($select);
            foreach ($rows as $row) {
                $sid  = (int)$row['slide_id'];
                $type = (string)$row['event_type'];
                $cnt  = (int)$row['total'];
                if ($type === self::EVENT_VIEW) {
                    $result[$sid]['views'] = $cnt;
                } elseif ($type === self::EVENT_CLICK) {
                    $result[$sid]['clicks'] = $cnt;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_HeroSlider StatTracker getTotals] ' . $e->getMessage());
        }
        return $result;
    }

    public function getDeviceBreakdown(int $slideId, int $daysBack = 30, ?int $storeId = null): array
    {
        $result = [
            self::DEVICE_DESKTOP => ['views' => 0, 'clicks' => 0],
            self::DEVICE_TABLET  => ['views' => 0, 'clicks' => 0],
            self::DEVICE_MOBILE  => ['views' => 0, 'clicks' => 0],
        ];
        if ($slideId <= 0) {
            return $result;
        }

        try {
            $connection = $this->resource->getConnection();
            $select = $connection->select()
                ->from(
                    $this->resource->getTableName(self::TABLE),
                    [
                        'device_type',
                        'event_type',
                        'total' => new \Zend_Db_Expr('SUM(event_count)'),
                    ]
                )
                ->where('slide_id = ?', $slideId)
                ->where('event_date >= ?', gmdate('Y-m-d', strtotime("-{$daysBack} days")))
                ->group(['device_type', 'event_type']);
            if ($storeId !== null) {
                $select->where('store_id = ?', $storeId);
            }
            foreach ($connection->fetchAll($select) as $row) {
                $device = (string)$row['device_type'];
                $type   = (string)$row['event_type'];
                if (!isset($result[$device])) {
                    continue;
                }
                if ($type === self::EVENT_VIEW) {
                    $result[$device]['views'] = (int)$row['total'];
                } elseif ($type === self::EVENT_CLICK) {
                    $result[$device]['clicks'] = (int)$row['total'];
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_HeroSlider StatTracker breakdown] ' . $e->getMessage());
        }
        return $result;
    }

    public function getTimeline(int $slideId, int $daysBack = 30, ?int $storeId = null): array
    {
        $timeline = [];

        for ($i = $daysBack - 1; $i >= 0; $i--) {
            $d = gmdate('Y-m-d', strtotime("-{$i} days"));
            $timeline[$d] = ['date' => $d, 'views' => 0, 'clicks' => 0];
        }
        if ($slideId <= 0) {
            return array_values($timeline);
        }

        try {
            $connection = $this->resource->getConnection();
            $select = $connection->select()
                ->from(
                    $this->resource->getTableName(self::TABLE),
                    [
                        'event_date',
                        'event_type',
                        'total' => new \Zend_Db_Expr('SUM(event_count)'),
                    ]
                )
                ->where('slide_id = ?', $slideId)
                ->where('event_date >= ?', gmdate('Y-m-d', strtotime("-{$daysBack} days")))
                ->group(['event_date', 'event_type']);
            if ($storeId !== null) {
                $select->where('store_id = ?', $storeId);
            }
            foreach ($connection->fetchAll($select) as $row) {
                $d = (string)$row['event_date'];
                if (!isset($timeline[$d])) {
                    continue;
                }
                if ($row['event_type'] === self::EVENT_VIEW) {
                    $timeline[$d]['views'] = (int)$row['total'];
                } elseif ($row['event_type'] === self::EVENT_CLICK) {
                    $timeline[$d]['clicks'] = (int)$row['total'];
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_HeroSlider StatTracker timeline] ' . $e->getMessage());
        }
        return array_values($timeline);
    }

    public function pruneOlderThan(int $daysToKeep): int
    {
        try {
            $connection = $this->resource->getConnection();
            return (int)$connection->delete(
                $this->resource->getTableName(self::TABLE),
                ['event_date < ?' => gmdate('Y-m-d', strtotime("-{$daysToKeep} days"))]
            );
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth_HeroSlider StatTracker prune] ' . $e->getMessage());
            return 0;
        }
    }
}
