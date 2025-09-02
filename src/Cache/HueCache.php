<?php

namespace OguzhanTogay\HueClient\Cache;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class HueCache
{
    private CacheItemPoolInterface $cache;
    private LoggerInterface $logger;
    private array $defaultTtl = [
        'lights' => 10,
        'groups' => 30,
        'scenes' => 60,
        'schedules' => 60,
        'sensors' => 5,
        'config' => 300
    ];

    public function __construct(
        string $cacheType = 'filesystem',
        array $config = [],
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->cache = $this->createCacheAdapter($cacheType, $config);
    }

    public function get(string $resource, string $key, callable $dataProvider = null): mixed
    {
        $cacheKey = $this->buildCacheKey($resource, $key);
        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            $this->logger->debug("Cache hit", ['key' => $cacheKey]);
            return $item->get();
        }

        if ($dataProvider === null) {
            return null;
        }

        $this->logger->debug("Cache miss, fetching data", ['key' => $cacheKey]);

        $data = $dataProvider();
        $ttl = $this->defaultTtl[$resource] ?? 60;

        $item->set($data);
        $item->expiresAfter($ttl);
        $this->cache->save($item);

        $this->logger->debug("Data cached", ['key' => $cacheKey, 'ttl' => $ttl]);

        return $data;
    }

    public function set(string $resource, string $key, mixed $data, ?int $ttl = null): bool
    {
        $cacheKey = $this->buildCacheKey($resource, $key);
        $item = $this->cache->getItem($cacheKey);

        $item->set($data);

        if ($ttl !== null) {
            $item->expiresAfter($ttl);
        } else {
            $item->expiresAfter($this->defaultTtl[$resource] ?? 60);
        }

        $success = $this->cache->save($item);

        if ($success) {
            $this->logger->debug("Data cached successfully", ['key' => $cacheKey]);
        } else {
            $this->logger->error("Failed to cache data", ['key' => $cacheKey]);
        }

        return $success;
    }

    public function delete(string $resource, string $key): bool
    {
        $cacheKey = $this->buildCacheKey($resource, $key);
        $success = $this->cache->deleteItem($cacheKey);

        if ($success) {
            $this->logger->debug("Cache item deleted", ['key' => $cacheKey]);
        }

        return $success;
    }

    public function invalidate(string $resource): bool
    {
        $pattern = $this->buildCacheKey($resource, '*');

        // For filesystem cache, we need to clear by pattern
        if ($this->cache instanceof FilesystemAdapter) {
            return $this->cache->clear();
        }

        // For Redis, we can use pattern matching
        if ($this->cache instanceof RedisAdapter) {
            $redis = $this->cache->getRedis();
            $keys = $redis->keys($pattern);

            if (!empty($keys)) {
                return $redis->del($keys) > 0;
            }
        }

        return true;
    }

    public function clear(): bool
    {
        $success = $this->cache->clear();

        if ($success) {
            $this->logger->info("Cache cleared successfully");
        } else {
            $this->logger->error("Failed to clear cache");
        }

        return $success;
    }

    public function getStats(): array
    {
        $stats = [
            'adapter' => get_class($this->cache),
            'default_ttl' => $this->defaultTtl
        ];

        // Add adapter-specific stats if available
        if (method_exists($this->cache, 'getStats')) {
            $stats['adapter_stats'] = $this->cache->getStats();
        }

        return $stats;
    }

    private function createCacheAdapter(string $type, array $config): CacheItemPoolInterface
    {
        switch ($type) {
            case 'redis':
                $redisUrl = $config['redis_url'] ?? 'redis://localhost:6379';
                return new RedisAdapter(
                    RedisAdapter::createConnection($redisUrl),
                    'hue_client',
                    $config['default_ttl'] ?? 0
                );

            case 'filesystem':
            default:
                return new FilesystemAdapter(
                    'hue_client',
                    $config['default_ttl'] ?? 0,
                    $config['cache_dir'] ?? sys_get_temp_dir() . '/hue_cache'
                );
        }
    }

    private function buildCacheKey(string $resource, string $key): string
    {
        return sprintf('hue_%s_%s', $resource, md5($key));
    }
}
