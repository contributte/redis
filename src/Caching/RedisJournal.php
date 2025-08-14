<?php declare(strict_types = 1);

namespace Contributte\Redis\Caching;

use Nette\Caching\Cache;
use Nette\Caching\Storages\Journal;
use Predis\ClientInterface;

/**
 * @see based on original https://github.com/Kdyby/Redis
 */
final class RedisJournal implements Journal
{

	public const NS_PREFIX = 'Contributte.Journal';

	public const KEY_PRIORITY = 'priority';
	public const SUFFIX_TAGS = 'tags';
	public const SUFFIX_KEYS = 'keys';

	/** @var ClientInterface $client */
	private $client;

	public function __construct(ClientInterface $client)
	{
		$this->client = $client;
	}

	/**
	 * Writes entry information into the journal.
	 *
	 * @param array{tags: string[], priority: int} $dependencies
	 */
	public function write(string $key, array $dependencies): void
	{
		$this->cleanEntry($key);

		$this->client->multi();

		$usedKeys = [];

		// add entry to each tag & tag to entry
		$tags = !isset($dependencies[Cache::Tags]) ? [] : (array) $dependencies[Cache::Tags];
		foreach (array_unique($tags) as $tag) {
			$usedKeys[] = $keySuffixKeys = $this->formatKey($tag, self::SUFFIX_KEYS);
			$usedKeys[] = $keySuffixTags = $this->formatKey($key, self::SUFFIX_TAGS);

			$this->client->sadd($keySuffixKeys, [$key]);
			$this->client->sadd($keySuffixTags, [$tag]);
		}

		if (isset($dependencies[Cache::Priority])) {
			$usedKeys[] = $keyPriority = $this->formatKey(self::KEY_PRIORITY);

			$this->client->zadd($keyPriority, [$key => (int) $dependencies[Cache::Priority]]);
		}

		if (isset($dependencies[Cache::Expire])) {
			foreach ($usedKeys as $usedKey) {
				$this->client->expire($usedKey, (int) $dependencies[Cache::Expire]);
			}
		}

		$this->client->exec();
	}

	/**
	 * Deletes all keys from associated tags and all priorities
	 *
	 * @param mixed[]|string $keys
	 */
	public function cleanEntry(array|string $keys): void
	{
		foreach (is_array($keys) ? $keys : [$keys] as $key) {
			$entries = $this->entryTags((string) $key);

			$this->client->multi();
			foreach ($entries as $tag) {
				$this->client->srem($this->formatKey((string) $tag, self::SUFFIX_KEYS), (string) $key);
			}

			// drop tags of entry and priority, in case there are some
			$this->client->del($this->formatKey((string) $key, self::SUFFIX_TAGS));
			$this->client->zrem($this->formatKey(self::KEY_PRIORITY), (string) $key);

			$this->client->exec();
		}
	}

	/**
	 * Cleans entries from journal.
	 *
	 * @param mixed[] $conditions
	 * @return mixed[] of removed items or NULL when performing a full cleanup
	 */
	public function clean(array $conditions): ?array
	{
		if (isset($conditions[Cache::All])) {
			$all = $this->client->keys(self::NS_PREFIX . ':*');

			$this->client->multi();
			call_user_func_array([$this->client, 'del'], $all);
			$this->client->exec();

			return null;
		}

		$entries = [];
		if (isset($conditions[Cache::Tags])) {
			foreach ((array) $conditions[Cache::Tags] as $tag) {
				$this->cleanEntry($found = $this->tagEntries((string) $tag));
				$entries[] = $found;
			}

			$entries = array_merge(...$entries);
		}

		if (isset($conditions[Cache::Priority])) {
			$this->cleanEntry($found = $this->priorityEntries($conditions[Cache::Priority]));
			$entries = array_merge($entries, $found);
		}

		return array_unique($entries);
	}

	/**
	 * @return mixed[]
	 */
	private function priorityEntries(int $priority): array
	{
		$result = $this->client->zrangebyscore($this->formatKey(self::KEY_PRIORITY), 0, $priority);

		return $result !== null ? (array) $result : [];
	}

	/**
	 * @return mixed[]
	 */
	private function entryTags(string $key): array
	{
		$result = $this->client->smembers($this->formatKey($key, self::SUFFIX_TAGS));

		return $result !== null ? (array) $result : [];
	}

	/**
	 * @return mixed[]
	 */
	private function tagEntries(string $tag): array
	{
		$result = $this->client->smembers($this->formatKey($tag, self::SUFFIX_KEYS));

		return $result !== null ? (array) $result : [];
	}

	private function formatKey(string $key, ?string $suffix = null): string
	{
		return self::NS_PREFIX . ':' . $key . ($suffix !== null ? ':' . $suffix : '');
	}

}
