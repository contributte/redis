<?php declare(strict_types = 1);

namespace Tests\Contributte\Redis;

use Contributte\Redis\Caching\RedisStorage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Predis\Client;

final class RedisStorageTest extends TestCase
{

	/** @var RedisStorage */
	private $redis;

	/** @var string[] */
	private $data = [];

	public function setUp(): void
	{
		parent::setUp();

		/** @var MockObject $clientMock */
		$clientMock = $this->createPartialMock(Client::class, ['set', 'get', 'del']);
		$clientMock->method('set')->willReturnCallback(function (string $key, string $value): void {
			$this->data[$key] = $value;
		});
		$clientMock->method('get')->willReturnCallback(function (string $key): ?string {
			return $this->data[$key] ?? null;
		});
		$clientMock->method('del')->willReturnCallback(function (array $keys): int {
			$deleted = 0;
			foreach ($keys as $key) {
				if (array_key_exists($key, $this->data)) {
					unset($this->data[$key]);
					$deleted++;
				}
			}

			return $deleted;
		});

		/** @var Client $client */
		$client = $clientMock;
		$this->redis = new RedisStorage($client);
	}

	public function testCrud(): void
	{
		$this->redis->write('foo', 'bar', []);
		$this->assertEquals('bar', $this->redis->read('foo'));
		$this->redis->write('foo', 'bat', []);
		$this->assertEquals('bat', $this->redis->read('foo'));
		$this->redis->remove('foo');
		$this->assertEquals(null, $this->redis->read('foo'));
	}

}
