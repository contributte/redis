<?php declare(strict_types = 1);

namespace Tests\Cases\Redis;

use Contributte\Redis\DI\RedisExtension;
use Nette\DI\Config\Loader;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Processor;
use Tester\Assert;
use Tester\FileMock;

require_once __DIR__ . '/../bootstrap.php';

test(function (): void {
	putenv('RD_DEBUG=true');
	putenv('RD_URI=tcp://foo.bar.example:6379');

	$extension = new RedisExtension();
	$loader = new Loader;
	$config = $loader->load(FileMock::create('
	debug: ::getenv("RD_DEBUG")
	connection:
		default:
			uri: ::getenv("RD_URI")
			sessions: true
			storage: false
			options: []
	', 'neon'));

	$processor = new Processor();
	$schema = $extension->getConfigSchema();
	$normalized = $processor->process($schema, $config);

	Assert::type(Statement::class, $normalized->debug);
	Assert::type(Statement::class, $normalized->connection['default']->uri);
	Assert::equal([], $normalized->connection['default']->options);

	putenv('RD_DEBUG=');
	putenv('RD_URI=');
});
