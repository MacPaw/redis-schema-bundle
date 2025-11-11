<?php

declare(strict_types=1);

namespace Macpaw\RedisSchemaBundle\Tests\Redis;

use Macpaw\RedisSchemaBundle\Redis\SchemaAwarePRedisClient;
use Macpaw\RedisSchemaBundle\Tests\Stub\TestRedisClient;
use Macpaw\SchemaContextBundle\Service\BaggageSchemaResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Predis\Command\CommandInterface;
use Predis\Command\CommandInterface as PredisCommandInterface;
use Predis\Command\FactoryInterface as PredisFactoryInterface;
use Predis\Command\Redis\PING;
use Predis\Configuration\OptionsInterface as PredisOptionsInterface;
use Predis\Connection\ConnectionInterface as PredisConnectionInterface;

class SchemaAwarePRedisClientTest extends TestCase
{
    /** @var BaggageSchemaResolver&MockObject */
    private BaggageSchemaResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = $this->createMock(BaggageSchemaResolver::class);
        $this->resolver->method('getEnvironmentSchema')->willReturn('public');
    }

    public function testPopPrefixesKeyWithSchema(): void
    {
        $this->resolver->method('getSchema')->willReturn('test');
        $client = new TestRedisClient();
        $client->popReturn = 'value';

        $adapter = new SchemaAwarePRedisClient($this->resolver, $client);

        self::assertSame('value', $adapter->pop('mykey'));
        self::assertSame([
            ['method' => 'pop', 'args' => ['test.mykey']],
        ], $client->calls);
    }

    public function testPushPrefixesKeyAndForwardsValues(): void
    {
        $this->resolver->method('getSchema')->willReturn('schem');
        $client = new TestRedisClient();
        $client->pushReturn = 3;

        $adapter = new SchemaAwarePRedisClient($this->resolver, $client);

        self::assertSame(3, $adapter->push('list', 'a', 2, true));
        self::assertSame([
            ['method' => 'push', 'args' => ['schem.list', 'a', 2, true]],
        ], $client->calls);
    }

    public function testCountAndRemovePrefixKeys(): void
    {
        $this->resolver->method('getSchema')->willReturn('ns');
        $client = new TestRedisClient();
        $client->countReturn = 5;
        $client->removeReturn = 1;

        $adapter = new SchemaAwarePRedisClient($this->resolver, $client);

        self::assertSame(5, $adapter->count('queue'));
        self::assertSame(1, $adapter->remove('to_remove'));
        self::assertSame([
            ['method' => 'count', 'args' => ['ns.queue']],
            ['method' => 'remove', 'args' => ['ns.to_remove']],
        ], $client->calls);
    }

    public function testDefaultSchemaSkipsPrefixing(): void
    {
        $this->resolver->method('getSchema')->willReturn('public');
        $client = new TestRedisClient();

        $adapter = new SchemaAwarePRedisClient($this->resolver, $client);

        self::assertNull($adapter->pop('plain'));
        self::assertSame([
            ['method' => 'pop', 'args' => ['plain']],
        ], $client->calls);
    }

    public function testDefaultSchemaReturnsKeyAsIs(): void
    {
        $this->resolver->method('getSchema')->willReturn('public');
        $client = new TestRedisClient();
        $client->pushReturn = 1;

        $adapter = new SchemaAwarePRedisClient($this->resolver, $client);

        self::assertSame(1, $adapter->push('name', 'x'));
        self::assertSame([
            ['method' => 'push', 'args' => ['name', 'x']],
        ], $client->calls);
    }

    public function testPassThroughClientMethods(): void
    {
        $client = new TestRedisClient();
        $factory = $this->createMock(PredisFactoryInterface::class);
        $options = $this->createMock(PredisOptionsInterface::class);
        $connection = $this->createMock(PredisConnectionInterface::class);
        $command = $this->createMock(PredisCommandInterface::class);

        $client->factory = $factory;
        $client->options = $options;
        $client->connection = $connection;
        $client->createCommandReturn = $command;
        $client->executeCommandReturn = 'PONG';

        $adapter = new SchemaAwarePRedisClient($this->resolver, $client);

        self::assertSame($factory, $adapter->getCommandFactory());
        self::assertSame($options, $adapter->getOptions());
        self::assertSame($connection, $adapter->getConnection());
        self::assertSame($command, $adapter->createCommand('ping', [1]));
        self::assertSame('PONG', $adapter->executeCommand($command));

        $adapter->connect();
        $adapter->disconnect();

        self::assertTrue($client->connected);
        self::assertTrue($client->disconnected);
    }

    public function testMagicCallPrefixesFirstArgumentWhenScalar(): void
    {
        $this->resolver->method('getSchema')->willReturn('s');
        $client = new TestRedisClient();
        $client->dynamicHandlers['hmget'] = function (array $args) {
            return ['value1', 'value2'];
        };

        $adapter = new SchemaAwarePRedisClient($this->resolver, $client, ['hmget']);

        $result = $adapter->hmget('hash', ['field1']);
        self::assertSame(['value1', 'value2'], $result);
        self::assertSame([
            ['method' => 'hmget', 'args' => ['s.hash', ['field1']]],
        ], $client->calls);
    }

    public function testMagicCallDoesNotPrefixWhenFirstArgumentNotScalar(): void
    {
        $this->resolver->method('getSchema')->willReturn('s');
        $client = new TestRedisClient();
        $client->dynamicHandlers['hmget'] = function (array $args) {
            return ['v'];
        };

        $adapter = new SchemaAwarePRedisClient($this->resolver, $client, ['hmget']);

        $firstArg = 'hash';
        $result = $adapter->hmget($firstArg, ['field']);
        self::assertSame(['v'], $result);
        self::assertSame([
            ['method' => 'hmget', 'args' => ['s.' . $firstArg, ['field']]],
        ], $client->calls);
    }

    public function testMagicCallWhenMethodNotInDecoratedListDoesNotPrefix(): void
    {
        $this->resolver->method('getSchema')->willReturn('prefix');
        $client = new TestRedisClient();
        $client->dynamicHandlers['custom'] = fn(array $args) => 'ok';

        // Not listing 'custom' in decoratedCallMethods, so it should not prefix the key
        $adapter = new SchemaAwarePRedisClient($this->resolver, $client, ['xhmget']);

        // @phpstan-ignore-next-line
        $result = $adapter->custom('key', 'arg');
        self::assertSame('ok', $result);
        self::assertSame([
            ['method' => 'custom', 'args' => ['key', 'arg']],
        ], $client->calls);
    }

    public function testMagicCallWhenMethodHasNoArguments(): void
    {
        $this->resolver->method('getSchema')->willReturn('pref');
        $client = new TestRedisClient();
        $client->dynamicHandlers['ping'] = fn(array $args) => 'PONG';

        $adapter = new SchemaAwarePRedisClient($this->resolver, $client, ['ping']);

        $result = $adapter->ping();
        self::assertSame('PONG', $result);
        self::assertSame([
            ['method' => 'ping', 'args' => []],
        ], $client->calls);
    }
}
