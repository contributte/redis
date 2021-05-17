<?php declare(strict_types = 1);

namespace Tests\Cases\Redis;

use Contributte\Redis\Tracy\RedisPanel;
use Nette\DI\Compiler;
use Nette\DI\Config\Loader;
use Nette\DI\Container;
use Nette\DI\ContainerBuilder;
use Nette\DI\Extensions\ExtensionsExtension;
use Nette\DI\PhpGenerator;
use Predis\Client;
use Predis\Connection\ConnectionException;
use Tester\Assert;
use Tester\FileMock;
use Tracy\Bar;
use Tracy\Bridges\Nette\TracyExtension;

require_once __DIR__ . '/../bootstrap.php';

function createContainer($source, $config = null, array $params = []): ?Container
{
	$class = 'Container' . md5((string) lcg_value());
	if ($source instanceof ContainerBuilder) {
		$source->complete();
		$code = (new PhpGenerator($source))->generate($class);

	} elseif ($source instanceof Compiler) {
		if (is_string($config)) {
			$loader = new Loader;
			$config = $loader->load(is_file($config) ? $config : FileMock::create($config, 'neon'));
		}
		$code = $source->addConfig((array) $config)
			->setClassName($class)
			->compile();
	} else {
		return null;
	}

	file_put_contents(__DIR__ . '/../tmp/code.php', "<?php\n\n$code");
	require __DIR__ . '/../tmp/code.php';
	return new $class($params);
}


test(function (): void {
	putenv('RD_DEBUG=1');
	putenv('RD_URI=tcp://foo.bar.example:6379s');


	$compiler = new Compiler;
	$compiler->addExtension('tracy', new TracyExtension());
	$compiler->addExtension('extensions', new ExtensionsExtension);
	$container = createContainer($compiler, '
services:
- \Nette\Caching\Storages\DevNullStorage
- \Nette\Http\Session
- \Nette\Http\Request
- \Nette\Http\Response
- \Nette\Http\UrlScript

extensions:
	redis: Contributte\Redis\DI\RedisExtension

tracy:
	showBar: true

redis:
	debug: ::getenv("RD_DEBUG")
	connection:
		default:
			uri: ::getenv("RD_URI")
			sessions: true
			storage: false
			options: []
');
	// Call container initiation to properly test whether RedisPanel is given to Tracy bar or not
	// Panel can be registered (as this cannot be easily prevented) but should not be registered in the bar.
	$container->initialize();
	Assert::notNull($container->getByType(RedisPanel::class));

	/** @var Bar|mixed|null $bar */
	$bar = $container->getByType(Bar::class);
	Assert::notNull($bar);
	Assert::type(Bar::class, $bar);
	$redisPanelInBar = $bar->getPanel(RedisPanel::class);
	Assert::null($redisPanelInBar);

	/** @var Client|mixed|null $client */
	$client = $container->getByName('redis.connection.default.client');
	$client2 = $container->getByType(Client::class);
	Assert::notNull($client);
	Assert::notNull($client2);
	Assert::type(Client::class, $client);
	Assert::type(Client::class, $client2);
	Assert::same($client, $client2);
	try {
		$client->connect();
		Assert::fail('Connect is expected to fail');
	} catch (ConnectionException $e) {
		Assert::equal('php_network_getaddresses: getaddrinfo failed: nodename nor servname provided, or not known [tcp://foo.bar.example:6379]', $e->getMessage());
	} finally {
		putenv('RD_DEBUG=');
		putenv('RD_URI=');
	}
});

test(function (): void {
	putenv('RD_DEBUG=0');
	putenv('RD_URI=tcp://foo.bar.example:6379s');


	$compiler = new Compiler;
	$compiler->addExtension('tracy', new TracyExtension());
	$compiler->addExtension('extensions', new ExtensionsExtension);
	$container = createContainer($compiler, '
services:
- \Nette\Caching\Storages\DevNullStorage
- \Nette\Http\Session
- \Nette\Http\Request
- \Nette\Http\Response
- \Nette\Http\UrlScript

extensions:
	redis: Contributte\Redis\DI\RedisExtension

tracy:
	showBar: true

redis:
	debug: ::getenv("RD_DEBUG")
	connection:
		default:
			uri: ::getenv("RD_URI")
			sessions: true
			storage: false
			options: []
');
	// Call container initiation to properly test whether RedisPanel is given to Tracy bar or not
	// Panel can be registered (as this cannot be easily prevented) but should not be registered in the bar.
	$container->initialize();
	Assert::notNull($container->getByType(RedisPanel::class));

	/** @var Bar|mixed|null $bar */
	$bar = $container->getByType(Bar::class);
	Assert::notNull($bar);
	Assert::type(Bar::class, $bar);
	$redisPanelInBar = $bar->getPanel(RedisPanel::class);
	Assert::notNull($redisPanelInBar);

	putenv('RD_DEBUG=');
	putenv('RD_URI=');
});
