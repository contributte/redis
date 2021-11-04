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
