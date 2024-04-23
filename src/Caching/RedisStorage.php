<?php declare(strict_types = 1);

namespace Contributte\Redis\Caching;

use Contributte\Redis\Serializer\DefaultSerializer;
use Contributte\Redis\Serializer\Serializer;
use Nette\Caching\Cache;
use Nette\Caching\IStorage as Storage;
use Nette\Caching\Storages\IJournal as Journal;
use Nette\InvalidStateException;
use Predis\ClientInterface;
use Predis\PredisException;
use Predis\Response\Status;

/**
 * @see based on original https://github.com/Kdyby/Redis
 */
final class RedisStorage implements Storage
{

	public const NS_PREFIX = 'Contributte.Storage';
	private const NS_SEPARATOR = "\x00";

	private const META_TIME = 'time'; // timestamp
	private const META_EXPIRE = 'expire'; // expiration timestamp
	private const META_DELTA = 'delta'; // relative (sliding) expiration
	private const META_ITEMS = 'di'; // array of dependent items (file => timestamp)
	private const META_CALLBACKS = 'callbacks'; // array of callbacks (function, args)
	private const KEY = 'key'; // additional cache structure

	/** @var ClientInterface $client */
	private $client;

	/** @var Journal|null $journal */
	private $journal;

	/** @var Serializer */
	private $serializer;

	/**
	 * @param ClientInterface $client
	 * @param Journal|null $journal
	 * @param Serializer|null $serializer
	 */
	public function __construct(ClientInterface $client, ?Journal $journal = null, ?Serializer $serializer = null)
	{
		$this->client = $client;
		$this->journal = $journal;
		$this->serializer = $serializer ?: new DefaultSerializer();
	}

	public function setSerializer(Serializer $serializer): void
	{
		$this->serializer = $serializer;
	}

	public function getClient(): ClientInterface
	{
		return $this->client;
	}

	/**
	 * Read from cache.
	 *
	 * @param string $key
	 * @return mixed|null
	 */
	public function read($key)
	{
		$stored = $this->doRead($key);
		if (!$stored || !$this->verify($stored[0])) {
			return null;
		}

		return $this->getUnserializedValue($stored);
	}

	/**
	 * Removes item from the cache.
	 *
	 * @param string $key
	 */
	public function remove($key): void
	{
		$this->client->del([$this->formatEntryKey($key)]);

		if ($this->journal instanceof RedisJournal) {
			$this->journal->cleanEntry($this->formatEntryKey($key));
		}
	}

	/**
	 * Read multiple entries from cache (using mget)
	 *
	 * @param mixed[] $keys
	 * @return mixed[]
	 */
	public function multiRead(array $keys): array
	{
		$values = [];
		foreach ($this->doMultiRead($keys) as $key => $stored) {
			$values[$key] = null;
			if ($stored !== null && $this->verify($stored[0])) {
				$values[$key] = $this->getUnserializedValue($stored);
			}
		}

		return $values;
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

		$data = $this->serializer->serialize($data, $meta);
		$store = json_encode($meta) . self::NS_SEPARATOR . $data;

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

	private function formatEntryKey(string $key): string
	{
		return self::NS_PREFIX . ':' . str_replace(self::NS_SEPARATOR, ':', $key);
	}


	/**
	 * Verifies dependencies.
	 *
	 * @param mixed[] $meta
	 * @return bool
	 */
	private function verify(array $meta): bool
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
	 * @param mixed[] $stored
	 * @return mixed
	 */
	private function getUnserializedValue(array $stored)
	{
		return $this->serializer->unserialize($stored[1], $stored[0]);
	}

	/**
	 * @param string $key
	 * @return mixed[]|null
	 */
	private function doRead(string $key): ?array
	{
		$stored = $this->client->get($this->formatEntryKey($key));
		if ($stored instanceof Status && $stored->getPayload() === 'QUEUED') {
			return null;
		}

		if (!$stored) {
			return null;
		}

		return self::processStoredValue($key, $stored);
	}

	/**
	 * @param mixed[] $keys
	 * @return mixed[]
	 */
	private function doMultiRead(array $keys): array
	{
		$formattedKeys = array_map([$this, 'formatEntryKey'], $keys);

		$result = [];
		foreach ($this->client->mget($formattedKeys) as $index => $stored) {
			$key = $keys[$index];
			$result[$key] = $stored ? self::processStoredValue($key, $stored) : null;
		}

		return $result;
	}

	/**
	 * @param string $key
	 * @param string $storedValue
	 * @return mixed[]
	 */
	private static function processStoredValue(string $key, string $storedValue): array
	{
		[$meta, $data] = explode(self::NS_SEPARATOR, $storedValue, 2) + [null, null];
		return [[self::KEY => $key] + json_decode((string) $meta, true), $data];
	}

}
