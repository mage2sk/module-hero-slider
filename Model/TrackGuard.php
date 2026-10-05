<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Session\SessionManagerInterface;
use Panth\HeroSlider\Model\Config as HeroConfig;

class TrackGuard
{
    private const SESSION_KEY = 'panth_hero_tracked';
    private const CACHE_PREFIX = 'panth_hero_track_rl_';
    private const WINDOW_SECONDS = 60;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly RemoteAddress $remoteAddress,
        private readonly SessionManagerInterface $session,
        private readonly HeroConfig $heroConfig
    ) {
    }

    public function isRateLimited(): bool
    {
        $limit = $this->heroConfig->getTrackRateLimitPerMinute();
        if ($limit <= 0) {
            return false;
        }
        $ip = (string)$this->remoteAddress->getRemoteAddress();
        $window = intdiv(time(), self::WINDOW_SECONDS);
        $key = self::CACHE_PREFIX . sha1($ip) . '_' . $window;
        $count = (int)$this->cache->load($key);
        if ($count >= $limit) {
            return true;
        }
        $this->cache->save((string)($count + 1), $key, [], self::WINDOW_SECONDS * 2);
        return false;
    }

    public function isDuplicate(int $slideId, string $eventType): bool
    {
        $tracked = $this->session->getData(self::SESSION_KEY);
        if (!is_array($tracked)) {
            $tracked = [];
        }
        $key = $slideId . ':' . $eventType;
        if (isset($tracked[$key])) {
            return true;
        }
        $tracked[$key] = 1;
        $this->session->setData(self::SESSION_KEY, $tracked);
        return false;
    }
}
