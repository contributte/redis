<?php declare(strict_types = 1);

namespace Tests\Cases\Redis;

use Contributte\Redis\DI\RedisExtension;
use Nette\DI\Config\Loader;
use Nette\Schema\Processor;
use Tester\Assert;
use Tester\FileMock;

require_once __DIR__ . '/../bootstrap.php';

test(function (): void {
	$extension = new RedisExtension();
	$loader = new Loader;
	$config = $loader->load(FileMock::create('
	debug: false
	connection:
		default:
			uri: "tcp://foo.bar.example:6379"
			sessions: false
			storage: true
			options: ["parameters": ["database": 1]]
	', 'neon'));

	$processor = new Processor();
	$schema = $extension->getConfigSchema();
	$normalized = $processor->process($schema, $config);

	Assert::equal(false, $normalized->debug);
	Assert::equal('tcp://foo.bar.example:6379', $normalized->connection['default']->uri);
	Assert::equal(false, $normalized->connection['default']->sessions);
	Assert::equal(true, $normalized->connection['default']->storage);
	Assert::equal(['parameters' => ['database' => 1]], $normalized->connection['default']->options);
});
