<?php declare(strict_types = 1);

namespace Contributte\Redis\Caching;

use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Predis\Client;
use Throwable;

final class RedisStorage implements IStorage
{

	/** @var Client */
	private $client;

	public function __construct(Client $client)
	{
		$this->client = $client;
	}

	public function getClient(): Client
	{
		return $this->client;
	}

	/**
	 * @param mixed   $data
	 * @param mixed[] $dependencies
	 */
	public function write(string $key, $data, array $dependencies): void
	{
		$this->client->set($key, $data);

		if (isset($dependencies[Cache::EXPIRATION])) {
			$expiration = (int) $dependencies[Cache::EXPIRATION];

			if ($dependencies[Cache::SLIDING] !== true) {
				$this->client->expireat($key, time() + $expiration);
			} else {
				$this->client->expire($key, $expiration);
			}
		}
	}

	/**
	 * @return mixed
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 */
	public function read(string $key)
	{
		$val = $this->client->get($key);

		try {
			return $val;
		} catch (Throwable $e) {
			return null;
		}
	}

	public function lock(string $key): void
	{
		// locking not implemented
	}

	public function remove(string $key): void
	{
		$this->client->del([$key]);
	}

	/**
	 * @param mixed[] $conditions
	 */
	public function clean(array $conditions): void
	{
		$this->client->flushall();
	}

}
