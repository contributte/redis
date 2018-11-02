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
	 * @param string  $key
	 * @param mixed   $data
	 * @param mixed[] $dependencies
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 */
	public function write($key, $data, array $dependencies): void
	{
		$this->client->set($key, json_encode($data));

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
	 * @param string $key
	 * @return mixed
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 */
	public function read($key)
	{
		$val = $this->client->get($key);

		try {
			return json_decode($val);
		} catch (Throwable $e) {
			return null;
		}
	}

	/**
	 * @param string $key
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 */
	public function lock($key): void
	{
		// locking not implemented
	}

	/**
	 * @param string $key
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 */
	public function remove($key): void
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
