<?php

declare(strict_types=1);

namespace Macpaw\RedisSchemaBundle\Redis;

use Macpaw\SchemaContextBundle\Service\BaggageSchemaResolver;
use Predis\Command\CommandInterface;
use Predis\Command\FactoryInterface;
use Predis\Configuration\OptionsInterface;
use Predis\Connection\ConnectionInterface;
use SymfonyBundles\RedisBundle\Redis\ClientInterface;

class SchemaAwarePRedisClient implements ClientInterface
{
    public function __construct(
        private BaggageSchemaResolver $resolver,
        private ClientInterface $decorated,
        /**
         * Method names that should be prefixed with the schema which not present in interfaces
         * like a hmget, hset, etc.
         *
         * @var array<array-key, string>
         */
        private readonly array $decoratedCallMethods = [],
    ) {
    }

    public function pop(string $key): ?string
    {
        return $this->decorated->pop($this->prefixKey($key));
    }

    public function push(string $key, ...$values): int
    {
        return $this->decorated->push($this->prefixKey($key), ...$values);
    }

    public function count(string $key): int
    {
        return $this->decorated->count($this->prefixKey($key));
    }

    public function remove(string $key): int
    {
        return $this->decorated->remove($this->prefixKey($key));
    }

    public function getCommandFactory(): FactoryInterface
    {
        return $this->decorated->getCommandFactory();
    }

    public function getOptions(): OptionsInterface
    {
        return $this->decorated->getOptions();
    }

    public function connect(): void
    {
        $this->decorated->connect();
    }

    public function disconnect(): void
    {
        $this->decorated->disconnect();
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->decorated->getConnection();
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    public function createCommand($method, $arguments = []): CommandInterface
    {
        return $this->decorated->createCommand($method, $arguments);
    }

    public function executeCommand(CommandInterface $command): mixed
    {
        return $this->decorated->executeCommand($command);
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    public function __call($method, $arguments): mixed
    {
        if (in_array($method, $this->decoratedCallMethods, true)) {
            // Automatically prefix the first argument if it's a string
            if (array_key_exists(0, $arguments) && is_scalar($arguments[0])) {
                $arguments[0] = $this->prefixKey((string) $arguments[0]);
            }
        }

        return $this->decorated->$method(...$arguments);
    }

    private function prefixKey(string $key): string
    {
        $schema = $this->resolver->getSchema();

        return $schema === $this->resolver->getEnvironmentSchema() ? $key : $schema . '.' . $key;
    }
}
