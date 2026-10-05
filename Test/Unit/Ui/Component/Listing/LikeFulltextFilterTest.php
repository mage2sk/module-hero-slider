<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Test\Unit\Ui\Component\Listing;

use Magento\Framework\Api\Filter;
use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Panth\HeroSlider\Ui\Component\Listing\LikeFulltextFilter;
use PHPUnit\Framework\TestCase;

class LikeFulltextFilterTest extends TestCase
{
    private function filter(mixed $value): Filter
    {
        $filter = $this->createStub(Filter::class);
        $filter->method('getValue')->willReturn($value);
        return $filter;
    }

    private function collection(?string &$where): AbstractDb
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('quoteIdentifier')->willReturnCallback(static fn($c) => '`' . $c . '`');
        $connection->method('quoteInto')->willReturnCallback(
            static fn($text, $value) => str_replace('?', "'" . $value . "'", $text)
        );
        $select = $this->createStub(Select::class);
        $select->method('where')->willReturnCallback(function ($cond) use (&$where, $select) {
            $where = $cond;
            return $select;
        });
        $collection = $this->createStub(AbstractDb::class);
        $collection->method('getConnection')->willReturn($connection);
        $collection->method('getSelect')->willReturn($select);
        return $collection;
    }

    public function testBuildsOrLikeAcrossConfiguredColumnsWithEscaping(): void
    {
        $where = null;
        (new LikeFulltextFilter(['title', 123, 'link_url']))->apply($this->collection($where), $this->filter(' 50%_off '));

        $this->assertSame("`title` LIKE '%50\\%\\_off%' OR `link_url` LIKE '%50\\%\\_off%'", $where);
    }

    public function testSearchTermIsTruncatedTo200Characters(): void
    {
        $where = null;
        (new LikeFulltextFilter(['title']))->apply($this->collection($where), $this->filter(str_repeat('a', 300)));

        $this->assertSame("`title` LIKE '%" . str_repeat('a', 200) . "%'", $where);
    }

    public function testNoOpForBlankOrNonScalarValues(): void
    {
        foreach (['   ', ['x'], null] as $value) {
            $where = null;
            (new LikeFulltextFilter(['title']))->apply($this->collection($where), $this->filter($value));
            $this->assertNull($where);
        }
    }

    public function testNoOpWithoutColumns(): void
    {
        $where = null;
        (new LikeFulltextFilter([]))->apply($this->collection($where), $this->filter('x'));
        $this->assertNull($where);
    }

    public function testNonDbCollectionIsLeftUntouched(): void
    {
        $plain = $this->createMock(Collection::class);
        $plain->expects($this->never())->method('addFilter');
        $plain->expects($this->never())->method('addFieldToFilter');

        (new LikeFulltextFilter(['title']))->apply($plain, $this->filter('x'));
    }
}
