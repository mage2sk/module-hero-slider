<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\HeroSlider\Model\StatTracker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class StatTrackerTest extends TestCase
{
    private AdapterInterface $connection;
    private Select $select;
    private array $whereCalls = [];

    protected function setUp(): void
    {
        $this->whereCalls = [];
        $this->select = $this->createStub(Select::class);
        $this->select->method('from')->willReturnSelf();
        $this->select->method('group')->willReturnSelf();
        $this->select->method('where')->willReturnCallback(function ($cond, $value = null) {
            $args = [$cond, $value];
            $this->whereCalls[] = $args;
            return $this->select;
        });
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('quoteIdentifier')->willReturnCallback(static fn($t) => '`' . $t . '`');
    }

    private function tracker(?LoggerInterface $logger = null, int $storeId = 1): StatTracker
    {
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getTableName')->willReturnCallback(static fn($t) => 'pfx_' . $t);
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new StatTracker($resource, $storeManager, $logger ?? $this->createStub(LoggerInterface::class));
    }

    public static function invalidTrackInput(): array
    {
        return [
            'zero slide'     => [0, 'view', 'desktop'],
            'negative slide' => [-1, 'view', 'desktop'],
            'bad event'      => [1, 'hover', 'desktop'],
            'event case'     => [1, 'VIEW', 'desktop'],
            'bad device'     => [1, 'click', 'watch'],
        ];
    }

    #[DataProvider('invalidTrackInput')]
    public function testTrackRejectsInvalidInputWithoutQuerying(int $slideId, string $event, string $device): void
    {
        $this->connection->expects($this->never())->method('query');

        $this->assertFalse($this->tracker()->track($slideId, $event, $device));
    }

    public function testTrackUpsertsCounterUsingCurrentStore(): void
    {
        $this->connection->expects($this->once())->method('query')->with(
            $this->callback(static fn(string $sql) =>
                str_contains($sql, 'INSERT INTO `pfx_panth_hero_slider_stat`')
                && str_contains($sql, 'ON DUPLICATE KEY UPDATE event_count = event_count + 1')),
            [5, 7, 'click', 'mobile']
        );

        $this->assertTrue($this->tracker(null, 7)->track(5, 'click', 'mobile'));
    }

    public function testTrackUsesExplicitStore(): void
    {
        $this->connection->expects($this->once())->method('query')
            ->with($this->anything(), [5, 3, 'view', 'tablet']);

        $this->assertTrue($this->tracker(null, 7)->track(5, 'view', 'tablet', 3));
    }

    public function testTrackLogsAndReturnsFalseOnDatabaseError(): void
    {
        $this->connection->expects($this->once())->method('query')->willThrowException(new \RuntimeException('deadlock'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('deadlock'));

        $this->assertFalse($this->tracker($logger)->track(5, 'view', 'desktop'));
    }

    public function testGetTotalsReturnsEmptyArrayForNoIds(): void
    {
        $this->connection->expects($this->never())->method('fetchAll');

        $this->assertSame([], $this->tracker()->getTotals([]));
    }

    public function testGetTotalsAggregatesRowsAndZeroFillsMissingSlides(): void
    {
        $this->connection->expects($this->once())->method('fetchAll')->willReturn([
            ['slide_id' => '1', 'event_type' => 'view', 'total' => '40'],
            ['slide_id' => '1', 'event_type' => 'click', 'total' => '4'],
            ['slide_id' => '2', 'event_type' => 'other', 'total' => '9'],
        ]);

        $totals = $this->tracker()->getTotals(['1', 2, 3]);

        $this->assertSame([
            1 => ['views' => 40, 'clicks' => 4],
            2 => ['views' => 0, 'clicks' => 0],
            3 => ['views' => 0, 'clicks' => 0],
        ], $totals);
    }

    public function testGetTotalsAddsStoreFilterOnlyWhenGiven(): void
    {
        $this->connection->expects($this->exactly(2))->method('fetchAll')->willReturn([]);

        $this->tracker()->getTotals([1], 7);
        $this->assertCount(2, $this->whereCalls);
        $this->assertSame(['slide_id IN (?)', [1]], $this->whereCalls[0]);
        $this->assertSame('event_date >= ?', $this->whereCalls[1][0]);
        $this->assertSame(gmdate('Y-m-d', strtotime('-7 days')), $this->whereCalls[1][1]);

        $this->whereCalls = [];
        $this->tracker()->getTotals([1], 30, 4);
        $this->assertContains(['store_id = ?', 4], $this->whereCalls);
    }

    public function testGetTotalsSwallowsErrorsAndKeepsZeroes(): void
    {
        $this->connection->expects($this->once())->method('fetchAll')->willThrowException(new \RuntimeException('gone'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $this->assertSame([9 => ['views' => 0, 'clicks' => 0]], $this->tracker($logger)->getTotals([9]));
    }

    public function testDeviceBreakdownForInvalidSlideIsZeroFilled(): void
    {
        $this->connection->expects($this->never())->method('fetchAll');

        $result = $this->tracker()->getDeviceBreakdown(0);

        $this->assertSame(['desktop', 'tablet', 'mobile'], array_keys($result));
        foreach ($result as $bucket) {
            $this->assertSame(['views' => 0, 'clicks' => 0], $bucket);
        }
    }

    public function testDeviceBreakdownMapsRowsAndIgnoresUnknownDevices(): void
    {
        $this->connection->expects($this->once())->method('fetchAll')->willReturn([
            ['device_type' => 'desktop', 'event_type' => 'view', 'total' => '10'],
            ['device_type' => 'mobile', 'event_type' => 'click', 'total' => '3'],
            ['device_type' => 'tv', 'event_type' => 'view', 'total' => '99'],
        ]);

        $result = $this->tracker()->getDeviceBreakdown(5, 30, 2);

        $this->assertSame(['views' => 10, 'clicks' => 0], $result['desktop']);
        $this->assertSame(['views' => 0, 'clicks' => 3], $result['mobile']);
        $this->assertArrayNotHasKey('tv', $result);
        $this->assertContains(['store_id = ?', 2], $this->whereCalls);
    }

    public function testTimelineBuildsOneEntryPerDayEndingToday(): void
    {
        $this->connection->expects($this->never())->method('fetchAll');

        $result = $this->tracker()->getTimeline(0, 3);

        $this->assertCount(3, $result);
        $this->assertSame(gmdate('Y-m-d', strtotime('-2 days')), $result[0]['date']);
        $this->assertSame(gmdate('Y-m-d'), $result[2]['date']);
        $this->assertSame(0, $result[1]['views']);
    }

    public function testTimelineFillsKnownDaysAndSkipsOutOfRangeRows(): void
    {
        $today = gmdate('Y-m-d');
        $this->connection->expects($this->once())->method('fetchAll')->willReturn([
            ['event_date' => $today, 'event_type' => 'view', 'total' => '8'],
            ['event_date' => $today, 'event_type' => 'click', 'total' => '2'],
            ['event_date' => '1999-01-01', 'event_type' => 'view', 'total' => '50'],
        ]);

        $result = $this->tracker()->getTimeline(5, 2);

        $this->assertCount(2, $result);
        $this->assertSame(['date' => $today, 'views' => 8, 'clicks' => 2], $result[1]);
        $this->assertSame(0, $result[0]['views']);
    }

    public function testPruneDeletesRowsOlderThanCutoff(): void
    {
        $this->connection->expects($this->once())->method('delete')->with(
            'pfx_panth_hero_slider_stat',
            ['event_date < ?' => gmdate('Y-m-d', strtotime('-30 days'))]
        )->willReturn(17);

        $this->assertSame(17, $this->tracker()->pruneOlderThan(30));
    }

    public function testPruneReturnsZeroOnError(): void
    {
        $this->connection->expects($this->once())->method('delete')->willThrowException(new \RuntimeException('locked'));

        $this->assertSame(0, $this->tracker()->pruneOlderThan(30));
    }
}
