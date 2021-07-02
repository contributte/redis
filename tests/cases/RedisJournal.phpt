<?php declare(strict_types = 1);

namespace Tests\Cases\Redis;

use Contributte\Redis\Caching\RedisJournal;
use Contributte\Redis\Caching\RedisStorage;
use Nette\Caching\Cache;
use Predis\Client;
use stdClass;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

test(function (): void {
	$client = new Client();
	$journal = new RedisJournal($client);
	$storage = new RedisStorage($client, $journal);
	$cache = new Cache($storage);

	$testData = [
		'test' => 'val',
		'object' => new stdClass(),
		'object with data' => (object) ['row' => 'val'],
	];

	$cache->save('testkey1', $testData, [
		Cache::EXPIRE => 6000,
		Cache::TAGS => ['test/1', 'test'],
	]);
	$cache->save('testkey2', 'data', [
		Cache::EXPIRE => 6000,
		Cache::TAGS => ['test/2', 'test'],
	]);
	$cache->save('check', 'ok');

	Assert::equal($testData, $cache->load('testkey1'));
	Assert::equal('data', $cache->load('testkey2'));
	Assert::same('ok', $cache->load('check'));
	$cache->clean([Cache::TAGS => ['test/2']]);
	Assert::equal($testData, $cache->load('testkey1'));
	Assert::null($cache->load('testkey2'));
	Assert::same('ok', $cache->load('check'));
	$cache->save('testkey2', 'data', [
		Cache::EXPIRE => 6000,
		Cache::TAGS => ['test/2', 'test'],
	]);
	Assert::equal($testData, $cache->load('testkey1'));
	$cache->clean([Cache::TAGS => ['test']]);
	Assert::null($cache->load('testkey1'));
	Assert::null($cache->load('testkey2'));
	Assert::same('ok', $cache->load('check'));

	$cache->clean([Cache::ALL => true]);
	Assert::null($cache->load('testkey1'));
	Assert::null($cache->load('testkey2'));
	Assert::null($cache->load('check'));
});
