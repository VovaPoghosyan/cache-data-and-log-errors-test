<?php

namespace src\Decorator;

use DateTime;
use Exception;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use src\Integration\DataProvider;

class DecoratorManager extends DataProvider
{
    protected $cache;
    protected $logger;

    /**
     * @param string $host
     * @param string $user
     * @param string $password
     * @param CacheItemPoolInterface $cache
     */
    public function __construct($host, $user, $password, CacheItemPoolInterface $cache, LoggerInterface $logger)
    {
        parent::__construct($host, $user, $password);
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function getResponse(array $input)
    {
        $response = [];
        try {
            $cacheItems = $this->cache->getItems($input);
            foreach ($cacheItems as $key => $cacheItem) {
                if ($cacheItem->isHit()) {
                    $response[$key] = $cacheItem->get();
                    continue;
                }
                $result = parent::get($key);
                if(empty($result)) {
                    $this->logger->notice("Notice: The server hasn't value with that key!");
                } else {
                    $cacheItem
                        ->set($result)
                        ->expiresAt(
                            (new DateTime())->modify('+1 day')
                        );
                    $this->cache->save($cacheItem);
                    $response[$key] = $result;
                }
            }
        } catch (Exception $e) {
            $this->logger->critical("Error: $e->getMessage()");
        }

        return $response;
    }
}