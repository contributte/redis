<?php declare(strict_types = 1);

namespace Contributte\Redis\Caching;

use Contributte\Redis\Exception\LogicalException;
use Nette\Caching\Cache;
use Nette\Caching\IStorage as Storage;
use Nette\Caching\Storages\IJournal as Journal;
use Nette\InvalidStateException;
use Predis\Client;
use Predis\PredisException;
use function array_map;
use function explode;
use function is_string;
use function json_decode;
use function json_encode;
use function microtime;
use function serialize;
use function str_replace;
use function time;
use function unserialize;

final class RedisStorage implements Storage
{

	private const NS_NETTE = 'Nette.Storage';

	private const META_TIME = 'time'; // timestamp
	private const META_SERIALIZED = 'serialized'; // is content serialized?
	private const META_EXPIRE = 'expire'; // expiration timestamp
	private const META_DELTA = 'delta'; // relative (sliding) expiration
	private const META_ITEMS = 'di'; // array of dependent items (file => timestamp)
	private const META_CALLBACKS = 'callbacks'; // array of callbacks (function, args)

	private const SERIALIZE_PHP = 1;
	private const SERIALIZE_IGBINARY = 2;

	/**
	 * additional cache structure
	 */
	private const KEY = 'key';

	/** @var bool $igbinary */
	private static $igbinary;

	/** @var Client<mixed> $client */
	private $client;

	/** @var Journal|null $journal */
	private $journal;

	/**
	 * @param Client<mixed> $client
	 * @param Journal|null $journal
	 */
	public function __construct(Client $client, ?Journal $journal = null)
	{
		$this->client = $client;
		$this->journal = $journal;
		self::$igbinary = extension_loaded('igbinary');
	}

	/**
	 * @return Client<mixed>
	 */
	public function getClient(): Client
	{
		return $this->client;
	}

	/**
	 * Read from cache.
	 *
	 * @param string $key
	 * @return mixed|null
	 * @throws PredisException
	 */
	public function read($key)
	{
		$stored = $this->doRead($key);
		if (!$stored || !$this->verify($stored[0])) {
			return null;
		}

		return self::getUnserializedValue($stored);
	}

	/**
	 * @param string $key
	 * @return mixed[]|null
	 * @throws PredisException
	 */
	private function doRead(string $key): ?array
	{
		$stored = $this->client->get($this->formatEntryKey($key));
		if (!$stored) {
			return null;
		}

		return self::processStoredValue($key, $stored);
	}

	/**
	 * @param string $key
	 * @return string
	 */
	protected function formatEntryKey(string $key): string
	{
		return self::NS_NETTE . ':' . str_replace(Cache::NAMESPACE_SEPARATOR, ':', $key);
	}

	/**
	 * @param string $key
	 * @param string $storedValue
	 * @return mixed[]
	 */
	private static function processStoredValue(string $key, string $storedValue): array
	{
		[$meta, $data] = explode(Cache::NAMESPACE_SEPARATOR, $storedValue, 2) + [null, null];
		return [[self::KEY => $key] + json_decode((string) $meta, true), $data];
	}

	/**
	 * Verifies dependencies.
	 *
	 * @param mixed[] $meta
	 * @return bool
	 * @throws PredisException
	 */
	protected function verify(array $meta): bool
	{
		do {
			if (!empty($meta[self::META_DELTA])) {
				$this->client->expire($this->formatEntryKey($meta[self::KEY]), $meta[self::META_DELTA]);

			} elseif (!empty($meta[self::META_EXPIRE]) && $meta[self::META_EXPIRE] < time()) {
				break;
			}

			if (!empty($meta[self::META_CALLBACKS]) && !Cache::checkCallbacks($meta[self::META_CALLBACKS])) {
				break;
			}

			if (!empty($meta[self::META_ITEMS])) {
				foreach ($meta[self::META_ITEMS] as $itemKey => $time) {
					$m = $this->readMeta($itemKey);
					$metaTime = $m[self::META_TIME] ?? null;
					if ($metaTime !== $time || ($m && !$this->verify($m))) {
						break 2;
					}
				}
			}

			return true;
		} while (false);

		$this->remove($meta[self::KEY]); // meta[handle] & meta[file] was added by readMetaAndLock()
		return false;
	}

	/**
	 * @param string $key
	 * @return mixed[]|null
	 * @throws PredisException
	 */
	protected function readMeta(string $key): ?array
	{
		$stored = $this->doRead($key);

		if (!$stored) {
			return null;
		}

		return $stored[0];
	}

	/**
	 * Removes item from the cache.
	 *
	 * @param string $key
	 */
	public function remove($key): void
	{
		$this->client->del([$this->formatEntryKey($key)]);
	}

	/**
	 * @param mixed[] $stored
	 * @return mixed
	 */
	private static function getUnserializedValue(array $stored)
	{
		return self::unserializeData($stored[1], $stored[0]);
	}

	/**
	 * @param string $data
	 * @param mixed[] $meta
	 * @return mixed
	 */
	private static function unserializeData(string $data, array $meta)
	{
		switch ($meta[self::META_SERIALIZED] ?? 0) {
			case self::SERIALIZE_IGBINARY:
				if (self::$igbinary) {
					return @igbinary_unserialize($data);
				}

				throw new LogicalException('Incompatible serialization method, igbinary is missing');

			case self::SERIALIZE_PHP:
				return @unserialize($data);

			default:
				return $data;
		}
	}

	/**
	 * Read multiple entries from cache (using mget)
	 *
	 * @param mixed[] $keys
	 * @return mixed[]
	 * @throws PredisException
	 */
	public function multiRead(array $keys): array
	{
		$values = [];
		foreach ($this->doMultiRead($keys) as $key => $stored) {
			$values[$key] = null;
			if ($stored !== null && $this->verify($stored[0])) {
				$values[$key] = self::getUnserializedValue($stored);
			}
		}

		return $values;
	}

	/**
	 * @param mixed[] $keys
	 * @return mixed[]
	 * @throws PredisException
	 */
	private function doMultiRead(array $keys): array
	{
		$formattedKeys = array_map([$this, 'formatEntryKey'], $keys);

		$result = [];
		foreach ($this->client->mget([$formattedKeys]) as $index => $stored) {
			$key = $keys[$index];
			$result[$key] = $stored !== false ? self::processStoredValue($key, $stored) : null;
		}

		return $result;
	}

	/**
	 * @param string $key
	 */
	public function lock($key): void
	{
		// unsupported now
	}

	/**
	 * Writes item into the cache.
	 *
	 * @param string $key
	 * @param mixed $data
	 * @param mixed[] $dependencies
	 * @throws InvalidStateException
	 * @throws PredisException
	 */
	public function write($key, $data, array $dependencies): void
	{
		$meta = [
			self::META_TIME => microtime(),
		];

		if (isset($dependencies[Cache::EXPIRATION])) {
			if (empty($dependencies[Cache::SLIDING])) {
				$meta[self::META_EXPIRE] = $dependencies[Cache::EXPIRATION] + time(); // absolute time

			} else {
				$meta[self::META_DELTA] = (int) $dependencies[Cache::EXPIRATION]; // sliding time
			}
		}

		if (isset($dependencies[Cache::ITEMS])) {
			foreach ((array) $dependencies[Cache::ITEMS] as $itemName) {
				$m = $this->readMeta($itemName);
				$meta[self::META_ITEMS][$itemName] = $m[self::META_TIME] ?? null; // may be null
				unset($m);
			}
		}

		if (isset($dependencies[Cache::CALLBACKS])) {
			$meta[self::META_CALLBACKS] = $dependencies[Cache::CALLBACKS];
		}

		$cacheKey = $this->formatEntryKey($key);

		if (isset($dependencies[Cache::TAGS]) || isset($dependencies[Cache::PRIORITY])) {
			if ($this->journal === null) {
				throw new InvalidStateException('CacheJournal has not been provided.');
			}

			$this->journal->write($cacheKey, $dependencies);
		}

		$data = self::serializeData($data, $meta);
		$store = json_encode($meta) . Cache::NAMESPACE_SEPARATOR . $data;

		try {
			if (isset($dependencies[Cache::EXPIRATION])) {
				$this->client->setex($cacheKey, $dependencies[Cache::EXPIRATION], $store);

			} else {
				$this->client->set($cacheKey, $store);
			}

			$this->unlock($key);

		} catch (PredisException $e) {
			$this->remove($key);
			throw new InvalidStateException($e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @param mixed $data
	 * @param mixed[] $meta
	 * @return string
	 */
	private static function serializeData($data, array &$meta): string
	{
		if (self::$igbinary) {
			$meta[self::META_SERIALIZED] = self::SERIALIZE_IGBINARY;
			return @igbinary_serialize($data);
		}

		if (!is_string($data)) {
			$meta[self::META_SERIALIZED] = self::SERIALIZE_PHP;
			return @serialize($data);
		}

		return $data;
	}

	/**
	 * @param string $key
	 * @internal
	 */
	public function unlock(string $key): void
	{
		// unsupported
	}

	/**
	 * Removes items from the cache by conditions & garbage collector.
	 *
	 * @param mixed[] $conditions
	 * @throws PredisException
	 */
	public function clean(array $conditions): void
	{
		// cleaning using file iterator
		if (!empty($conditions[Cache::ALL])) {
			$this->client->flushdb();
			return;
		}

		// cleaning using journal
		if ($this->journal) {
			$keys = $this->journal->clean($conditions);
			if ($keys) {
				$this->client->del($keys);
			}
		}
	}

}
