<?php declare(strict_types = 1);

namespace Contributte\Redis\Caching;

use Exception;
use Nette\Caching\Cache;
use Nette\Caching\Storages\Journal;
use Predis\Client;
use function array_merge;
use function array_unique;
use function call_user_func_array;
use function is_array;

final class RedisJournal implements Journal
{

	private const NS_NETTE = 'Nette.Journal';

	private const PRIORITY = 'priority';
	private const TAGS = 'tags';
	private const KEYS = 'keys';

	/** @var Client */
	protected $client;

	/**
	 * @param Client<mixed> $client
	 */
	public function __construct(Client $client)
	{
		$this->client = $client;
	}

	/**
	 * Writes entry information into the journal.
	 *
	 * @param string $key
	 * @param mixed[] $dependencies
	 * @return void
	 * @throws Exception
	 */
	public function write(string $key, array $dependencies): void
	{
		$this->cleanEntry($key);

		$this->client->multi();

		// add entry to each tag & tag to entry
		$tags = empty($dependencies[Cache::TAGS]) ? [] : (array) $dependencies[Cache::TAGS];
		foreach (array_unique($tags) as $tag) {
			$this->client->sadd($this->formatKey($tag, self::KEYS), [$key]);
			$this->client->sadd($this->formatKey($key, self::TAGS), [$tag]);
		}

		if (isset($dependencies[Cache::PRIORITY])) {
			$this->client->zadd($this->formatKey(self::PRIORITY), $dependencies[Cache::PRIORITY]);
		}

		$this->client->exec();
	}

	/**
	 * Deletes all keys from associated tags and all priorities
	 *
	 * @param mixed[]|string $keys
	 * @throws Exception
	 */
	private function cleanEntry($keys): void
	{
		foreach (is_array($keys) ? $keys : [$keys] as $key) {
			$entries = $this->entryTags($key);

			$this->client->multi();
			foreach ($entries as $tag) {
				$this->client->srem($this->formatKey($tag, self::KEYS), $key);
			}

			// drop tags of entry and priority, in case there are some
			$this->client->del([$this->formatKey($key, self::TAGS), $this->formatKey($key, self::PRIORITY)]);
			$this->client->zrem($this->formatKey(self::PRIORITY), $key);

			$this->client->exec();
		}
	}

	/**
	 * Cleans entries from journal.
	 *
	 * @param mixed[] $conditions
	 * @return mixed[] of removed items or NULL when performing a full cleanup
	 * @throws Exception
	 */
	public function clean(array $conditions): ?array
	{
		if (!empty($conditions[Cache::ALL])) {
			$all = $this->client->keys(self::NS_NETTE . ':*');

			$this->client->multi();
			call_user_func_array([$this->client, 'del'], $all);
			$this->client->exec();
			return null;
		}

		$entries = [];
		if (!empty($conditions[Cache::TAGS])) {
			foreach ((array) $conditions[Cache::TAGS] as $tag) {
				$this->cleanEntry($found = $this->tagEntries($tag));
				$entries[] = $found;
			}

			$entries = array_merge(...$entries);
		}

		if (isset($conditions[Cache::PRIORITY])) {
			$this->cleanEntry($found = $this->priorityEntries($conditions[Cache::PRIORITY]));
			$entries = array_merge($entries, $found);
		}

		return array_unique($entries);
	}

	/**
	 * @param int $priority
	 * @return mixed[]
	 */
	private function priorityEntries(int $priority): array
	{
		return $this->client->zrangebyscore($this->formatKey(self::PRIORITY), 0, $priority) ?: [];
	}

	/**
	 * @param string $key
	 * @return mixed[]
	 */
	private function entryTags(string $key): array
	{
		return $this->client->smembers($this->formatKey($key, self::TAGS)) ? : [];
	}

	/**
	 * @param string $tag
	 * @return mixed[]
	 */
	private function tagEntries(string $tag): array
	{
		return $this->client->smembers($this->formatKey($tag, self::KEYS)) ? : [];
	}

	protected function formatKey(string $key, ?string $suffix = null): string
	{
		return self::NS_NETTE . ':' . $key . ($suffix ? ':' . $suffix : null);
	}

}
