<?php declare(strict_types = 1);

namespace Tests\Cases\Caching;

use Contributte\Redis\Caching\RedisJournal;
use Contributte\Redis\Caching\RedisStorage;
use Nette\Caching\Cache;
use Ninjify\Nunjuck\Toolkit;
use Predis\Client;
use Predis\Connection\ConnectionException;
use stdClass;
use Tester\Assert;
use Tester\Environment;

require_once __DIR__ . '/../../bootstrap.php';

try {
	$client = new Client();
	$client->ping();
	$journal = new RedisJournal($client);
	$storage = new RedisStorage($client, $journal);
	$cache = new Cache($storage);
} catch (ConnectionException $e) {
	Environment::skip('Redis not found: ' . $e->getMessage());
}

// Basic
Toolkit::test(function () use ($cache): void {
	$cache->save('foo', 'bar');
	Assert::same('bar', $cache->load('foo'));

	$cache->remove('foo');
	Assert::same(null, $cache->load('foo'));
});

// Complex
Toolkit::test(function () use ($cache): void {
	$data = [
		'foo1' => 'bar',
		'foo2' => new stdClass(),
		'foo3' => ['bar' => 'baz'],
	];

	$cache->save('complex', $data, [Cache::EXPIRE => 6000]);
	Assert::equal($data, $cache->load('complex'));

	$cache->remove('complex');
	Assert::equal(null, $cache->load('complex'));
});

// Clean
Toolkit::test(function () use ($cache): void {
	$cache->save('foo', 'bar', [Cache::TAGS => ['tag/tag', 'tag/foo']]);
	Assert::same('bar', $cache->load('foo'));

	$cache->clean([Cache::TAGS => ['tag/foo']]);
	Assert::equal(null, $cache->load('foo'));
});

// Clean (multiple)
Toolkit::test(function () use ($cache): void {
	$cache->save('foo1', 'bar1', [Cache::TAGS => ['tag']]);
	$cache->save('foo2', 'bar2', [Cache::TAGS => ['tag']]);
	Assert::same('bar1', $cache->load('foo1'));
	Assert::same('bar2', $cache->load('foo2'));

	$cache->clean([Cache::TAGS => ['tag']]);
	Assert::equal(null, $cache->load('foo1'));
	Assert::equal(null, $cache->load('foo2'));
});

// Clean (all)
Toolkit::test(function () use ($cache): void {
	$cache->save('foo1', 'bar1', [Cache::TAGS => ['tag']]);
	$cache->save('foo2', 'bar2', [Cache::TAGS => ['tag']]);
	$cache->save('foo3', 'bar3');
	Assert::same('bar1', $cache->load('foo1'));
	Assert::same('bar2', $cache->load('foo2'));
	Assert::same('bar3', $cache->load('foo3'));

	$cache->clean([Cache::ALL => true]);
	Assert::equal(null, $cache->load('foo1'));
	Assert::equal(null, $cache->load('foo2'));
	Assert::equal(null, $cache->load('foo3'));
});

// Override
Toolkit::test(function () use ($cache): void {
	$cache->save('foo', 'bar');
	Assert::same('bar', $cache->load('foo'));
	$cache->save('foo', 'bar2');
	Assert::same('bar2', $cache->load('foo'));
});

// Expiration
Toolkit::test(function () use ($cache): void {
	$cache->save('foo', 'bar', [Cache::EXPIRATION => 1]);
	Assert::same('bar', $cache->load('foo'));
	sleep(2);
	Assert::equal(null, $cache->load('foo'));
});

// Priority
Toolkit::test(function () use ($cache): void {
	$cache->save('foo1', 'bar1', [Cache::PRIORITY => 40]);
	$cache->save('foo2', 'bar2', [Cache::PRIORITY => 30]);
	$cache->save('foo3', 'bar3', [Cache::PRIORITY => 20]);
	$cache->save('foo4', 'bar4', [Cache::PRIORITY => 10]);
	Assert::same('bar1', $cache->load('foo1'));
	Assert::same('bar2', $cache->load('foo2'));
	Assert::same('bar3', $cache->load('foo3'));
	Assert::same('bar4', $cache->load('foo4'));

	$cache->clean([Cache::PRIORITY => 10]);
	Assert::same('bar1', $cache->load('foo1'));
	Assert::same('bar2', $cache->load('foo2'));
	Assert::same('bar3', $cache->load('foo3'));
	Assert::same(null, $cache->load('foo4'));

	$cache->clean([Cache::PRIORITY => 30]);
	Assert::same('bar1', $cache->load('foo1'));
	Assert::same(null, $cache->load('foo2'));
	Assert::same(null, $cache->load('foo3'));
	Assert::same(null, $cache->load('foo4'));

	$cache->clean([Cache::PRIORITY => 100]);
	Assert::same(null, $cache->load('foo1'));
	Assert::same(null, $cache->load('foo2'));
	Assert::same(null, $cache->load('foo3'));
	Assert::same(null, $cache->load('foo4'));
});

// Helper function for journal related tests
$generateJournalKey = static function (string $key, string $suffix, bool $addStoragePrefix): string {
	$prefix = $addStoragePrefix ? sprintf('%s:%s', RedisJournal::NS_PREFIX, RedisStorage::NS_PREFIX) : RedisJournal::NS_PREFIX;
	return sprintf('%s:%s:%s', $prefix, $key, $suffix);
};

// Tags cleaning
Toolkit::test(function () use ($storage, $client, $generateJournalKey): void {
	$storage->clean([Cache::ALL => true]);
	Assert::same(0, $client->exists($generateJournalKey('tag', RedisJournal::SUFFIX_KEYS, false)));

	$storage->write('foo', 'bar', [Cache::TAGS => ['tag']]);
	Assert::same(1, $client->exists($generateJournalKey('foo', RedisJournal::SUFFIX_TAGS, true)));
	Assert::same(1, $client->exists($generateJournalKey('tag', RedisJournal::SUFFIX_KEYS, false)));
	$storage->clean([Cache::TAGS => ['tag']]);
	Assert::same(0, $client->exists($generateJournalKey('foo', RedisJournal::SUFFIX_TAGS, true)));
	Assert::same(0, $client->exists($generateJournalKey('tag', RedisJournal::SUFFIX_KEYS, false)));

	$storage->write('foo', 'bar', [Cache::TAGS => ['tag']]);
	Assert::same(1, $client->exists($generateJournalKey('foo', RedisJournal::SUFFIX_TAGS, true)));
	Assert::same(1, $client->exists($generateJournalKey('tag', RedisJournal::SUFFIX_KEYS, false)));
	$storage->remove('foo');
	Assert::same(0, $client->exists($generateJournalKey('foo', RedisJournal::SUFFIX_TAGS, true)));
	Assert::same(0, $client->exists($generateJournalKey('tag', RedisJournal::SUFFIX_KEYS, false)));

	$storage->write('foo', 'bar', [Cache::TAGS => ['tag'], Cache::PRIORITY => 1]);
	Assert::same(1, $client->exists($generateJournalKey('foo', RedisJournal::SUFFIX_TAGS, true)));
	Assert::same(1, $client->exists($generateJournalKey('tag', RedisJournal::SUFFIX_KEYS, false)));
	$storage->clean([Cache::PRIORITY => 1]);
	Assert::same(0, $client->exists($generateJournalKey('foo', RedisJournal::SUFFIX_TAGS, true)));
	Assert::same(0, $client->exists($generateJournalKey('tag', RedisJournal::SUFFIX_KEYS, false)));
});
