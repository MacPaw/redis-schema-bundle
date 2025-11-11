<?php

declare(strict_types=1);

namespace Macpaw\RedisSchemaBundle\Tests\Stub;

use Predis\Client;
use Predis\Command\CommandInterface;
use Predis\Command\FactoryInterface;
use Predis\Configuration\OptionsInterface;
use Predis\Connection\ConnectionInterface;
use SymfonyBundles\RedisBundle\Redis\ClientInterface;

class TestRedisClient extends Client implements ClientInterface
{
    // @phpstan-ignore-next-line
    public array $calls = [];

    public ?FactoryInterface $factory = null;
    public ?OptionsInterface $options = null;
    public ?ConnectionInterface $connection = null;

    public ?CommandInterface $createCommandReturn = null;
    public mixed $executeCommandReturn = null;

    public ?string $popReturn = null;
    public int $pushReturn = 0;
    public int $countReturn = 0;
    public int $removeReturn = 0;

    public bool $connected = false;
    public bool $disconnected = false;

    // @phpstan-ignore-next-line
    public array $dynamicHandlers = [];

    public function pop(string $key): ?string
    {
        $this->calls[] = ['method' => 'pop', 'args' => [$key]];
        return $this->popReturn;
    }

    public function push(string $key, ...$values): int
    {
        $this->calls[] = ['method' => 'push', 'args' => array_merge([$key], $values)];
        return $this->pushReturn;
    }

    public function count(string $key): int
    {
        $this->calls[] = ['method' => 'count', 'args' => [$key]];
        return $this->countReturn;
    }

    public function remove(string $key): int
    {
        $this->calls[] = ['method' => 'remove', 'args' => [$key]];
        return $this->removeReturn;
    }

    public function getCommandFactory(): FactoryInterface
    {
        return $this->factory ?? throw new \RuntimeException('factory not set');
    }

    public function getOptions(): OptionsInterface
    {
        return $this->options ?? throw new \RuntimeException('options not set');
    }

    public function connect(): void
    {
        $this->connected = true;
        $this->calls[] = ['method' => 'connect', 'args' => []];
    }

    public function disconnect(): void
    {
        $this->disconnected = true;
        $this->calls[] = ['method' => 'disconnect', 'args' => []];
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection ?? throw new \RuntimeException('connection not set');
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    public function createCommand($method, $arguments = []): CommandInterface
    {
        $this->calls[] = ['method' => 'createCommand', 'args' => [$method, $arguments]];
        return $this->createCommandReturn ?? throw new \RuntimeException('createCommandReturn not set');
    }

    public function executeCommand(CommandInterface $command): mixed
    {
        $this->calls[] = ['method' => 'executeCommand', 'args' => [$command]];

        return $this->executeCommandReturn;
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    public function __call($name, $arguments)
    {
        $this->calls[] = ['method' => $name, 'args' => $arguments];
        if (array_key_exists($name, $this->dynamicHandlers)) {
            return ($this->dynamicHandlers[$name])($arguments);
        }
        throw new \BadMethodCallException("No handler for dynamic method {$name}");
    }
}
