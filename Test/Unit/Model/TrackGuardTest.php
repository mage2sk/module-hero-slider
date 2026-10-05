<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Test\Unit\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Session\SessionManager;
use Magento\Framework\Session\SessionManagerInterface;
use Panth\HeroSlider\Model\Config;
use Panth\HeroSlider\Model\TrackGuard;
use PHPUnit\Framework\TestCase;

class TrackGuardTest extends TestCase
{
    private function guard(
        int $limit,
        ?CacheInterface $cache = null,
        ?SessionManagerInterface $session = null
    ): TrackGuard {
        $config = $this->createStub(Config::class);
        $config->method('getTrackRateLimitPerMinute')->willReturn($limit);
        $remote = $this->createStub(RemoteAddress::class);
        $remote->method('getRemoteAddress')->willReturn('203.0.113.7');

        return new TrackGuard(
            $cache ?? $this->createStub(CacheInterface::class),
            $remote,
            $session ?? $this->createStub(SessionManagerInterface::class),
            $config
        );
    }

    /**
     * Session double backed by an array; records every setData call.
     */
    private function session(mixed $stored, array &$writes): SessionManager
    {
        $session = $this->getMockBuilder(SessionManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData', '__call'])
            ->getMock();
        $session->expects($this->once())->method('getData')->willReturnCallback(
            static fn($key = '') => $key === 'panth_hero_tracked' ? $stored : null
        );
        $session->method('__call')->willReturnCallback(
            static function (string $method, array $args) use (&$writes) {
                if ($method === 'setData') {
                    $writes[] = $args;
                }
                return null;
            }
        );
        return $session;
    }

    public function testRateLimitDisabledNeverTouchesCache(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('load');
        $cache->expects($this->never())->method('save');

        $this->assertFalse($this->guard(0, $cache)->isRateLimited());
    }

    public function testUnderLimitIncrementsCounterKeyedByHashedIp(): void
    {
        $prefix = 'panth_hero_track_rl_' . sha1('203.0.113.7') . '_';
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('load')
            ->with($this->callback(static fn(string $key) => str_starts_with($key, $prefix)))
            ->willReturn('4');
        $cache->expects($this->once())->method('save')->with(
            '5',
            $this->callback(static fn(string $key) => str_starts_with($key, $prefix)),
            [],
            120
        );

        $this->assertFalse($this->guard(5, $cache)->isRateLimited());
    }

    public function testFirstHitStartsCounterAtOne(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('load')->willReturn(false);
        $cache->expects($this->once())->method('save')->with('1');

        $this->assertFalse($this->guard(1, $cache)->isRateLimited());
    }

    public function testAtLimitIsRateLimitedWithoutSaving(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('load')->willReturn('5');
        $cache->expects($this->never())->method('save');

        $this->assertTrue($this->guard(5, $cache)->isRateLimited());
    }

    public function testFirstEventIsNotDuplicateAndIsRemembered(): void
    {
        $writes = [];
        $guard = $this->guard(0, null, $this->session(['3:view' => 1], $writes));

        $this->assertFalse($guard->isDuplicate(3, 'click'));
        $this->assertSame([['panth_hero_tracked', ['3:view' => 1, '3:click' => 1]]], $writes);
    }

    public function testRepeatEventIsDuplicate(): void
    {
        $writes = [];
        $guard = $this->guard(0, null, $this->session(['3:view' => 1], $writes));

        $this->assertTrue($guard->isDuplicate(3, 'view'));
        $this->assertSame([], $writes);
    }

    public function testCorruptSessionValueIsReset(): void
    {
        $writes = [];
        $guard = $this->guard(0, null, $this->session('garbage', $writes));

        $this->assertFalse($guard->isDuplicate(9, 'view'));
        $this->assertSame([['panth_hero_tracked', ['9:view' => 1]]], $writes);
    }
}
