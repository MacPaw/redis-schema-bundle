<?php

declare(strict_types=1);

namespace Macpaw\RedisSchemaBundle\Tests\Redis;

use Macpaw\RedisSchemaBundle\Redis\SchemaAwareRedisAdapter;
use Macpaw\SchemaContextBundle\Service\SchemaResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\CacheInterface;
use Psr\Cache\CacheItemInterface;

class SchemaAwareRedisAdapterTest extends TestCase
{
    private RedisAdapter&CacheInterface&MockObject $decorated;
    private SchemaResolver $resolver;
    private SchemaAwareRedisAdapter $adapter;

    protected function setUp(): void
    {
        $this->decorated = $this->createMock(RedisAdapter::class);
        $this->resolver = $this->createMock(SchemaResolver::class);
        $this->resolver->method('getSchema')->willReturn('test_schema');

        $this->adapter = new SchemaAwareRedisAdapter($this->decorated, $this->resolver);
    }

    public function testGetItemPrefixesKey(): void
    {
        $key = 'foo';
        $expected = 'test_schema.foo';
        $item = new CacheItem();

        $this->decorated->expects(self::once())
            ->method('getItem')
            ->with($expected)
            ->willReturn($item);

        $result = $this->adapter->getItem($key);

        self::assertSame($item, $result);
    }

    public function testHasItemPrefixesKey(): void
    {
        $this->decorated->expects(self::once())
            ->method('hasItem')
            ->with('test_schema.bar')
            ->willReturn(true);

        self::assertTrue($this->adapter->hasItem('bar'));
    }

    public function testDeleteItemPrefixesKey(): void
    {
        $this->decorated->expects(self::once())
            ->method('deleteItem')
            ->with('test_schema.baz')
            ->willReturn(true);

        self::assertTrue($this->adapter->deleteItem('baz'));
    }

    public function testDeleteItemsPrefixesKeys(): void
    {
        $keys = ['one', 'two'];
        $expected = ['test_schema.one', 'test_schema.two'];

        $this->decorated->expects(self::once())
            ->method('deleteItems')
            ->with($expected)
            ->willReturn(true);

        self::assertTrue($this->adapter->deleteItems($keys));
    }

    public function testGetItemsPrefixesKeys(): void
    {
        $keys = ['a', 'b'];
        $expected = ['test_schema.a', 'test_schema.b'];

        $items = ['a' => new CacheItem(), 'b' => new CacheItem()];
        $this->decorated->expects(self::once())
            ->method('getItems')
            ->with($expected)
            ->willReturn($items);

        $result = $this->adapter->getItems($keys);
        self::assertSame($items, $result);
    }

    public function testGetCallsUnderlyingAdapter(): void
    {
        $this->decorated = $this->createMock(RedisAdapter::class);
        $this->decorated->method('get')
            ->with('test_schema.some_key', self::isType('callable'))
            ->willReturn('cached_value');

        $adapter = new SchemaAwareRedisAdapter($this->decorated, $this->resolver);

        $result = $adapter->get('some_key', fn() => 'value');
        self::assertSame('cached_value', $result);
    }

    public function testDeleteCallsUnderlyingAdapter(): void
    {
        $this->decorated->expects(self::once())
            ->method('delete')
            ->with('test_schema.delete_me')
            ->willReturn(true);

        self::assertTrue($this->adapter->delete('delete_me'));
    }

    public function testClearPassesPrefix(): void
    {
        $this->decorated->expects(self::once())
            ->method('clear')
            ->with('prefix_')
            ->willReturn(true);

        self::assertTrue($this->adapter->clear('prefix_'));
    }

    public function testSaveAndCommitArePassedThrough(): void
    {
        $item = $this->createMock(CacheItemInterface::class);

        $this->decorated->expects(self::once())
            ->method('save')
            ->with($item)
            ->willReturn(true);

        $this->decorated->expects(self::once())
            ->method('commit')
            ->willReturn(true);

        self::assertTrue($this->adapter->save($item));
        self::assertTrue($this->adapter->commit());
    }
}
