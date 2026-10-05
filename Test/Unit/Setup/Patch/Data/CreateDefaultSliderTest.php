<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Test\Unit\Setup\Patch\Data;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Panth\HeroSlider\Setup\Patch\Data\CreateDefaultSlider;
use PHPUnit\Framework\TestCase;

class CreateDefaultSliderTest extends TestCase
{
    private function patch(AdapterInterface $connection): CreateDefaultSlider
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection->method('select')->willReturn($select);
        $setup = $this->createStub(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnArgument(0);
        return new CreateDefaultSlider($setup);
    }

    public function testCreatesSliderStoreLinkAndAdoptsOrphanSlidesOnFreshInstall(): void
    {
        $connection = $this->createMock(Mysql::class);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls(false, false);
        $connection->expects($this->once())->method('insert')->with('panth_hero_slider_slider', [
            'identifier' => 'homepage_hero',
            'name'       => 'Home Page Hero',
            'is_active'  => 1,
        ]);
        $connection->expects($this->once())->method('lastInsertId')->willReturn('11');
        $connection->expects($this->once())->method('insertOnDuplicate')
            ->with('panth_hero_slider_slider_store', ['slider_id' => 11, 'store_id' => 0]);
        $connection->expects($this->once())->method('update')
            ->with('panth_hero_slider_slide', ['slider_id' => 11], ['slider_id IS NULL']);

        $this->patch($connection)->apply();
    }

    public function testIsIdempotentWhenSliderAndStoreLinkExist(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls('4', '0');
        $connection->expects($this->never())->method('insert');
        $connection->expects($this->never())->method('insertOnDuplicate');
        $connection->expects($this->once())->method('update')
            ->with('panth_hero_slider_slide', ['slider_id' => 4], ['slider_id IS NULL']);

        $this->patch($connection)->apply();
    }

    public function testNoDependenciesOrAliases(): void
    {
        $this->assertSame([], CreateDefaultSlider::getDependencies());
        $this->assertSame([], $this->patch($this->createStub(AdapterInterface::class))->getAliases());
    }
}
