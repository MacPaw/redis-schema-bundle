# Redis Schema Bundle

The **Redis Schema Bundle** provides automatic **Redis key namespacing** based on the current schema context, allowing **data isolation** in shared environments (e.g., staging, preview, multi-tenant).

This bundle builds on top of [Schema Context Bundle](https://github.com/macpaw/schema-context-bundle) to propagate the schema context across the app and decorate your Redis cache.

## Features

- Transparent key prefixing for Redis
- Compatible with `Symfony\Component\Cache\Adapter\RedisAdapter`
- Works with Symfony `CacheInterface` and `AdapterInterface`
- Supports schema-based multitenancy

---

## Installation

```bash
composer require macpaw/redis-schema-bundle
```

If you are not using Symfony Flex, register the bundle manually:

```php
// config/bundles.php
return [
    Macpaw\SchemaContextBundle\SchemaContextBundle::class => ['all' => true],
    Macpaw\RedisSchemaBundle\RedisSchemaBundle::class => ['all' => true],
];
```
## Configuration
Make sure to register the base Redis adapter and decorate it with the schema-aware implementation:

```yaml
# config/services.yaml

services:
    redis_cache_adapter:
        class: Symfony\Component\Cache\Adapter\RedisAdapter
        arguments:
            - '@SymfonyBundles\RedisBundle\Redis\ClientInterface'
            - 'cache_storage'
            - '%redis_default_cache_ttl%'

    Macpaw\RedisSchemaBundle\Redis\SchemaAwareRedisAdapter:
        decorates: Symfony\Component\Cache\Adapter\RedisAdapter
        arguments:
            - '@.inner'
            - '@Macpaw\SchemaContextBundle\Service\BaggageSchemaResolver'
```

Decorate predis client with symfony-bundles/redis-bundle:

```yaml
# config/services.yaml

services:
    Macpaw\RedisSchemaBundle\Redis\SchemaAwarePRedisClient:
        decorates: SymfonyBundles\RedisBundle\Redis\ClientInterface
        arguments:
            - '@.inner'
            - '@Macpaw\SchemaContextBundle\Service\BaggageSchemaResolver'
```

## Usage
Anywhere you use Symfony cache services (injected via CacheInterface or AdapterInterface), your keys will automatically be prefixed based on the current schema.
For example:

```php
public function __construct(private CacheInterface $cache) {}

public function save(): void
{
    $this->cache->get('user.123', function () {
        return 'value';
    });
}
```
If the schema is client_a, this will store key: client_a.user.123.

## Testing
To run tests:
```bash
vendor/bin/phpunit
```

## Contributing
Feel free to open issues and submit pull requests.

## License
This bundle is released under the MIT license.
