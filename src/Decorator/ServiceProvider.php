<?php

namespace src\Decorator;

use DateTime;
use Throwable;
use DefaultLogger;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use src\Integration\Abstraction\AbstractDataProvider;

class ServiceProvider extends AbstractDataProvider
{
    public $cache;
    public $logger;

    /**
     * @param string $host
     * @param string $user
     * @param string $password
     * @param CacheItemPoolInterface $cache
     */
    public function __construct($host, $user, $password, CacheItemPoolInterface $cache)
    {
        parent::__construct($host, $user, $password);
        $this->cache = $cache;
        $this->logger = new DefaultLogger();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param array $input
     * @return array
     * {@inheritdoc}
     */
    public function getResponse(array $input): array
    {
        try {
            $this->checkCacheKey($input);

            $cacheKey = $this->getCacheKey($input);
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }

            $result = parent::get($input);

            $cacheItem->set($result)->expiresAt((new DateTime())->modify('+1 day'));

            return $result;

        } catch (Throwable $exception) {
            $this->logger->critical($exception->getMessage(), $exception);
        }

        return [];
    }

    /**
     * @param array $input
     * @return false|string
     * @throws \JsonException
     */
    public function getCacheKey(array $input)
    {
        $this->checkCacheKey($input);
        return json_encode($input, JSON_THROW_ON_ERROR);
    }

    private function checkCacheKey(array $input): void
    {
        if (empty($input)) {
            throw new \InvalidArgumentException('Empty argument value passed');
        }
    }
}
