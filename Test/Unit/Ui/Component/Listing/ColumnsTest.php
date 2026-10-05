<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Test\Unit\Ui\Component\Listing;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\HeroSlider\Model\StatTracker;
use Panth\HeroSlider\Ui\Component\Listing\Column\ImagePreview;
use Panth\HeroSlider\Ui\Component\Listing\Column\SlideActions;
use Panth\HeroSlider\Ui\Component\Listing\Column\SliderActions;
use Panth\HeroSlider\Ui\Component\Listing\Column\SliderStores;
use Panth\HeroSlider\Ui\Component\Listing\Column\StatColumn;
use PHPUnit\Framework\TestCase;

class ColumnsTest extends TestCase
{
    private function ctx(): array
    {
        return [$this->createStub(ContextInterface::class), $this->createStub(UiComponentFactory::class)];
    }

    private function url(): UrlInterface
    {
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn($route, $params = []) => $route . '?' . http_build_query($params)
        );
        return $url;
    }

    public function testSlideActionsBuildEditAndDeleteLinks(): void
    {
        [$c, $f] = $this->ctx();
        $column = new SlideActions($c, $f, $this->url(), [], ['name' => 'actions']);

        $out = $column->prepareDataSource(['data' => ['items' => [['entity_id' => '3'], ['title' => 'no id']]]]);

        $row = $out['data']['items'][0]['actions'];
        $this->assertSame('panth_heroslider/slide/edit?entity_id=3', $row['edit']['href']);
        $this->assertSame('panth_heroslider/slide/delete?entity_id=3', $row['delete']['href']);
        $this->assertTrue($row['delete']['post']);
        $this->assertSame('Delete slide #3', (string)$row['delete']['confirm']['title']);
        $this->assertArrayNotHasKey('actions', $out['data']['items'][1]);
    }

    public function testSliderActionsBuildLinksAndIgnoreMissingItems(): void
    {
        [$c, $f] = $this->ctx();
        $column = new SliderActions($c, $f, $this->url(), [], ['name' => 'actions']);

        $this->assertSame(['data' => []], $column->prepareDataSource(['data' => []]));
        $out = $column->prepareDataSource(['data' => ['items' => [['slider_id' => 8]]]]);
        $this->assertSame('panth_heroslider/slider/edit?slider_id=8', $out['data']['items'][0]['actions']['edit']['href']);
        $this->assertSame('panth_heroslider/slider/delete?slider_id=8', $out['data']['items'][0]['actions']['delete']['href']);
    }

    public function testImagePreviewAddsThumbnailFields(): void
    {
        [$c, $f] = $this->ctx();
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.test/media/');
        $sm = $this->createStub(StoreManagerInterface::class);
        $sm->method('getStore')->willReturn($store);
        $column = new ImagePreview($c, $f, $sm, [], ['name' => 'image_desktop']);

        $out = $column->prepareDataSource(['data' => ['items' => [
            ['image_desktop' => '/a.jpg', 'title' => 'Alpha'],
            ['image_desktop' => null],
        ]]]);

        $first = $out['data']['items'][0];
        $this->assertSame('https://shop.test/media/panth/heroslider/slide/a.jpg', $first['image_desktop_src']);
        $this->assertSame($first['image_desktop_src'], $first['image_desktop_orig_src']);
        $this->assertSame('Alpha', $first['image_desktop_alt']);
        $this->assertSame('', $out['data']['items'][1]['image_desktop_src']);
        $this->assertSame(['data' => ['items' => []]], $column->prepareDataSource(['data' => ['items' => []]]));
    }

    private function statColumn(string $metric, StatTracker $tracker): StatColumn
    {
        [$c, $f] = $this->ctx();
        return new StatColumn($c, $f, $tracker, [], ['name' => 'stat', 'config' => ['metric' => $metric]]);
    }

    public function testStatColumnMetrics(): void
    {
        $rows = ['data' => ['items' => [['entity_id' => 1], ['entity_id' => 2], ['entity_id' => 3]]]];
        $totals = [1 => ['views' => 200, 'clicks' => 5], 2 => ['views' => 0, 'clicks' => 0]];

        $tracker = $this->createMock(StatTracker::class);
        $tracker->expects($this->once())->method('getTotals')->with([1, 2, 3], 30)->willReturn($totals);
        $views = $this->statColumn('views', $tracker)->prepareDataSource($rows)['data']['items'];
        $this->assertSame([200, 0, 0], array_column($views, 'stat'));

        $tracker = $this->createStub(StatTracker::class);
        $tracker->method('getTotals')->willReturn($totals);
        $this->assertSame([5, 0, 0], array_column($this->statColumn('clicks', $tracker)->prepareDataSource($rows)['data']['items'], 'stat'));
        $this->assertSame(['2.50%', '-', '-'], array_column($this->statColumn('ctr', $tracker)->prepareDataSource($rows)['data']['items'], 'stat'));
    }

    public function testStatColumnCachesTotalsAcrossCalls(): void
    {
        $tracker = $this->createMock(StatTracker::class);
        $tracker->expects($this->once())->method('getTotals')->willReturn([]);
        $column = $this->statColumn('views', $tracker);

        $column->prepareDataSource(['data' => ['items' => [['entity_id' => 1]]]]);
        $out = $column->prepareDataSource(['data' => ['items' => [['entity_id' => 1]]]]);
        $this->assertSame(0, $out['data']['items'][0]['stat']);
    }

    public function testSliderStoresLabels(): void
    {
        [$c, $f] = $this->ctx();
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->expects($this->once())->method('fetchAll')->willReturn([
            ['slider_id' => '1', 'store_id' => '0'],
            ['slider_id' => '1', 'store_id' => '2'],
            ['slider_id' => '2', 'store_id' => '1'],
            ['slider_id' => '2', 'store_id' => '99'],
        ]);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $default = $this->createStub(Store::class);
        $default->method('getName')->willReturn('Default Store View');
        $sm = $this->createStub(StoreManagerInterface::class);
        $sm->method('getStore')->willReturnCallback(static function ($id) use ($default) {
            if ($id === 1) {
                return $default;
            }
            throw new \RuntimeException('no store');
        });
        $column = new SliderStores($c, $f, $resource, $sm, [], ['name' => 'stores']);

        $out = $column->prepareDataSource(['data' => ['items' => [
            ['slider_id' => 1], ['slider_id' => 2], ['slider_id' => 3],
        ]]]);

        $this->assertSame(
            ['All Store Views', 'Default Store View, #99', '- hidden -'],
            array_column($out['data']['items'], 'stores')
        );
    }
}
