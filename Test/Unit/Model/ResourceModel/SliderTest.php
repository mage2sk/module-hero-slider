<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Test\Unit\Model\ResourceModel;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Panth\HeroSlider\Model\ResourceModel\Slider;
use PHPUnit\Framework\TestCase;

class SliderTest extends TestCase
{
    private function resource(AdapterInterface $connection): Slider
    {
        $resource = $this->getMockBuilder(Slider::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getTable', 'getMainTable'])
            ->getMock();
        $resource->expects($this->atLeastOnce())->method('getConnection')->willReturn($connection);
        $resource->method('getTable')->willReturnCallback(static fn($t) => 'pfx_' . $t);
        $resource->method('getMainTable')->willReturn('pfx_panth_hero_slider_slider');
        return $resource;
    }

    private function select(array &$where): Select
    {
        $select = $this->createStub(Select::class);
        foreach (['from', 'joinLeft', 'limit'] as $m) {
            $select->method($m)->willReturnSelf();
        }
        $select->method('where')->willReturnCallback(function ($cond, $value = null) use (&$where, $select) {
            $args = [$cond, $value];
            $where[] = $args;
            return $select;
        });
        return $select;
    }

    public function testSaveStoreLinksReplacesRowsAndDeduplicates(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('delete')
            ->with('pfx_panth_hero_slider_slider_store', ['slider_id = ?' => 4]);
        $connection->expects($this->once())->method('insertMultiple')->with(
            'pfx_panth_hero_slider_slider_store',
            [['slider_id' => 4, 'store_id' => 1], ['slider_id' => 4, 'store_id' => 2]]
        );

        $this->resource($connection)->saveStoreLinks(4, ['1', 2, 1]);
    }

    public function testSaveStoreLinksCollapsesToAllStoreViewsWhenZeroPresent(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('delete');
        $connection->expects($this->once())->method('insertMultiple')
            ->with('pfx_panth_hero_slider_slider_store', [['slider_id' => 4, 'store_id' => 0]]);

        $this->resource($connection)->saveStoreLinks(4, [2, '0', 3]);
    }

    public function testSaveStoreLinksWithEmptyListOnlyDeletes(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('delete');
        $connection->expects($this->never())->method('insertMultiple');

        $this->resource($connection)->saveStoreLinks(4, []);
    }

    public function testLookupStoreIdsCastsToInt(): void
    {
        $where = [];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($this->select($where));
        $connection->expects($this->once())->method('fetchCol')->willReturn(['0', '3']);

        $this->assertSame([0, 3], $this->resource($connection)->lookupStoreIds(9));
        $this->assertSame([['slider_id = ?', 9]], $where);
    }

    public function testIsActiveForStore(): void
    {
        $where = [];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($this->select($where));
        $connection->expects($this->exactly(2))->method('fetchOne')->willReturnOnConsecutiveCalls('5', false);

        $resource = $this->resource($connection);
        $this->assertTrue($resource->isActiveForStore(5, 2));
        $this->assertFalse($resource->isActiveForStore(5, 2));
        $this->assertContains(['ss.store_id IS NULL OR ss.store_id IN (?)', [0, 2]], $where);
        $this->assertContains(['s.is_active = ?', 1], $where);
    }

    public function testGetIdByIdentifier(): void
    {
        $where = [];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($this->select($where));
        $connection->expects($this->exactly(3))->method('fetchOne')->willReturnOnConsecutiveCalls('7', false, null);

        $resource = $this->resource($connection);
        $this->assertSame(7, $resource->getIdByIdentifier('homepage_hero', 1));
        $this->assertNull($resource->getIdByIdentifier('missing', 1));
        $this->assertNull($resource->getIdByIdentifier('missing', 1));
        $this->assertContains(['s.identifier = ?', 'homepage_hero'], $where);
    }
}
