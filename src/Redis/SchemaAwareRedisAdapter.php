<?php

declare(strict_types=1);

namespace Macpaw\RedisSchemaBundle\Redis;

use Macpaw\SchemaContextBundle\Service\BaggageSchemaResolver;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Contracts\Cache\CacheInterface;

class SchemaAwareRedisAdapter implements AdapterInterface, CacheInterface
{
    public function __construct(
        private readonly RedisAdapter $decorated,
        private readonly BaggageSchemaResolver $resolver,
    ) {
    }

    private function prefixKey(string $key): string
    {
        $schema = $this->resolver->getSchema();

        return $schema ? $schema . '.' . $key : $key;
    }

    public function clear(string $prefix = ''): bool
    {
        return $this->decorated->clear($prefix);
    }

    public function getItem(mixed $key): CacheItem
    {
        return $this->decorated->getItem($this->prefixKey($key));
    }

    public function getItems(array $keys = []): iterable
    {
        $prefixed = array_map(fn($k) => $this->prefixKey($k), $keys);
        return $this->decorated->getItems($prefixed);
    }

    public function hasItem(string $key): bool
    {
        return $this->decorated->hasItem($this->prefixKey($key));
    }

    public function deleteItem(string $key): bool
    {
        return $this->decorated->deleteItem($this->prefixKey($key));
    }

    public function deleteItems(array $keys): bool
    {
        $prefixed = array_map(fn($k) => $this->prefixKey($k), $keys);

        return $this->decorated->deleteItems($prefixed);
    }

    public function save(CacheItemInterface $item): bool
    {
        return $this->decorated->save($item);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->decorated->saveDeferred($item);
    }

    public function commit(): bool
    {
        return $this->decorated->commit();
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        $prefixedKey = $this->prefixKey($key);
        if (!$this->decorated instanceof CacheInterface) {
            throw new \LogicException(sprintf(
                '%s must implement Symfony\Contracts\Cache\CacheInterface to support get().',
                get_class($this->decorated)
            ));
        }

        return $this->decorated->get($prefixedKey, $callback, $beta, $metadata);
    }

    public function delete(string $key): bool
    {
        return $this->decorated->delete($this->prefixKey($key));
    }
}
