<?php

namespace Integration\DataManager;

use DateTime;
use Exception;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use src\Integration\DataProvider;

class CachedDataProvider implements DataProviderInterface
{
    /**
     * @var DataProviderInterface
     */
    private $dataProvider;

    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    /**
     * @var string
     */
    private $timeInterval;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param DataProviderInterface $dataProvider
     * @param CacheItemPoolInterface $cache
     * @param string $timeInterval
     */
    public function __construct(DataProviderInterface $dataProvider, CacheItemPoolInterface $cache, $timeInterval)
    {
        $this->dataProvider = $dataProvider;
        $this->cache = $cache;
        $this->timeInterval = $timeInterval;
        $this->logger = new NullLogger();
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function get(array $request)
    {
        $result = [];
        try {
            $cacheKey = $this->getCacheKey($request);
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                $result =  $cacheItem->get();
            } else {
                $result = $this->dataProvider->get($request);

                $cacheItem
                    ->set($result)
                    ->expiresAt(
                        (new DateTime())->modify($this->timeInterval)
                    );
            }
        } catch (Exception $e) {
            $this->logger->critical(
                'Cached data provider error',
                [
                    'exception' => $e
                ]
            );
        }

        return $result;
    }

    /**
     * @param array $request
     *
     * @return string
     */
    private function getCacheKey(array $request)
    {
        return md5(json_encode($request));
    }
}