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
    /**
     * @var array<array-key, string>
     */
    protected readonly array $decoratedCallMethods;

    /**
     * @param array<array-key, string> $decoratedCallMethods Method names that should be prefixed with
     * the schema which not present in interfaces like a hmget, hset, etc.
     */
    public function __construct(
        private BaggageSchemaResolver $resolver,
        private ClientInterface $decorated,
        array $decoratedCallMethods = [],
    ) {
        $this->decoratedCallMethods = count($decoratedCallMethods) === 0 ? $this->getDefaultDecoratedMethods()
            : $decoratedCallMethods;
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
            // Automatically prefix the first argument if it's a string or an array of strings
            if (array_key_exists(0, $arguments)) {
                if (is_string($arguments[0])) {
                    $arguments[0] = $this->prefixKey($arguments[0]);
                }

                if (is_array($arguments[0])) {
                    $arguments[0] = array_map(
                        fn(mixed $value): mixed => is_string($value) ? $this->prefixKey($value) : $value,
                        $arguments[0]
                    );
                }
            }
        }

        return $this->decorated->$method(...$arguments);
    }

    private function prefixKey(string $key): string
    {
        $schema = $this->resolver->getSchema();

        return $schema === $this->resolver->getEnvironmentSchema() ? $key : $schema . '.' . $key;
    }

    /**
     * @return array<array-key, string>
     */
    private function getDefaultDecoratedMethods(): array
    {
        return [
            // Basic key ops
            'copy', 'dump', 'exists', 'expire', 'expireat', 'expiretime', 'move', 'rename', 'renamenx',
            'sort', 'sort_ro', 'ttl', 'pttl', 'type', 'append', 'del', 'watch', 'unwatch', 'persist', 'touch',
            'pexpire', 'pexpireat',

            // Bloom filter (BF*)
            'bfadd', 'bfexists', 'bfinfo', 'bfinsert', 'bfloadchunk', 'bfmadd', 'bfmexists',
            'bfreserve', 'bfscandump',

            // Bit operations
            'bitcount', 'bitfield', 'bitfield_ro', 'bitpos',

            // Cuckoo filter (CF*)
            'cfadd', 'cfaddnx', 'cfcount', 'cfdel', 'cfexists', 'cfloadchunk', 'cfmexists',
            'cfinfo', 'cfinsert', 'cfinsertnx', 'cfreserve', 'cfscandump',

            // Count-Min Sketch (CMS*)
            'cmsincrby', 'cmsinfo', 'cmsinitbydim', 'cmsinitbyprob', 'cmsmerge', 'cmsquery',

            // String counters / String ops
            'decr', 'decrby', 'get', 'getbit', 'getex', 'getrange', 'getdel', 'getset', 'incr', 'incrby', 'incrbyfloat',
            'mget', 'psetex', 'set', 'setbit', 'setex', 'setnx', 'setrange', 'strlen',

            // Hashes (H*)
            'hdel', 'hexists', 'hexpire', 'hexpireat', 'hexpiretime', 'hpersist', 'hpexpire',
            'hpexpireat', 'hpexpiretime', 'hget', 'hgetex', 'hgetall', 'hgetdel', 'hincrby',
            'hincrbyfloat', 'hkeys', 'hlen', 'hmget', 'hmset', 'hrandfield', 'hscan', 'hset',
            'hsetex', 'hsetnx', 'httl', 'hpttl', 'hvals', 'hstrlen',

            // JSON (ReJSON)
            'jsonarrappend', 'jsonarrindex', 'jsonarrinsert', 'jsonarrlen', 'jsonarrpop',
            'jsonclear', 'jsonarrtrim', 'jsondel', 'jsonforget', 'jsonget', 'jsonnumincrby',
            'jsonmerge', 'jsonmget', 'jsonobjkeys', 'jsonobjlen', 'jsonresp', 'jsonset', 'jsonstrappend',
            'jsonstrlen', 'jsontoggle', 'jsontype',

            // Lists (L*)
            'blmove', 'blpop', 'brpop', 'brpoplpush', 'lcs', 'lindex', 'linsert', 'llen', 'lmove', 'lmpop',
            'lpop', 'lpush', 'lpushx',
            'lrange', 'lrem', 'lset', 'ltrim', 'rpop', 'rpoplpush', 'rpush', 'rpushx',

            // Sets (S*)
            'sadd', 'scard', 'sdiff', 'sdiffstore', 'sinter', 'sintercard', 'sinterstore',
            'sismember', 'smembers', 'smismember',
            'smove', 'spop', 'srandmember', 'srem', 'sscan', 'sunion', 'sunionstore',

            // T-Digest (TDIGEST*)
            'tdigestadd', 'tdigestbyrank', 'tdigestbyrevrank', 'tdigestcdf', 'tdigestcreate',
            'tdigestinfo', 'tdigestmax', 'tdigestmerge', 'tdigestquantile', 'tdigestmin', 'tdigestrank',
            'tdigestreset', 'tdigestrevrank', 'tdigesttrimmed_mean',

            // TOP-K (TOPK*)
            'topkadd', 'topkincrby', 'topkinfo', 'topklist', 'topkquery', 'topkreserve',

            // TimeSeries (TS*)
            'tsadd', 'tsalter', 'tscreate', 'tscreaterule', 'tsdecrby', 'tsdel', 'tsdeleterule',
            'tsget', 'tsincrby', 'tsinfo', 'tsrange', 'tsrevrange',

            // Streams (X*)
            'xadd', 'xdel', 'xlen', 'xrevrange', 'xrange', 'xtrim',

            // Sorted sets (Z*)
            'zadd', 'zcard', 'zcount', 'zdiff', 'zdiffstore', 'zincrby', 'zinter', 'zintercard', 'zinterstore', 'zmpop',
            'zmscore', 'zpopmin', 'zpopmax', 'bzpopmin', 'bzpopmax', 'bzmpop', 'zrandmember', 'zrange',
            'zrangebyscore', 'zrangestore', 'zrank', 'zrem', 'zremrangebyrank', 'zremrangebyscore',
            'zrevrange', 'zrevrangebyscore', 'zrevrank', 'zscore', 'zscan', 'zrangebylex',
            'zrevrangebylex', 'zremrangebylex', 'zlexcount',

            // HyperLogLog
            'pfadd', 'pfmerge', 'pfcount',

            // Key TTL precise
            'pexpiretime',

            // Geo
            'geoadd', 'geohash', 'geopos', 'geodist', 'georadius', 'georadiusbymember', 'geosearch', 'geosearchstore',
        ];
    }
}
