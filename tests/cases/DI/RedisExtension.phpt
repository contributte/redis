<?php declare(strict_types = 1);

namespace Tests\Cases\DI;

use Contributte\Redis\Caching\RedisJournal;
use Contributte\Redis\Caching\RedisStorage;
use Contributte\Redis\DI\RedisExtension;
use Nette\Bridges\CacheDI\CacheExtension;
use Nette\Caching\IStorage;
use Nette\DI\Compiler;
use Ninjify\Nunjuck\Toolkit;
use Predis\Client;
use Tester\Assert;
use Tests\Toolkit\Container;
use Tests\Toolkit\Helpers;
use Tests\Toolkit\Liberator;
use Tests\Toolkit\Tests;

require_once __DIR__ . '/../../bootstrap.php';

// Connection
Toolkit::test(function (): void {
	$container = Container::of()
		->withDefaults()
		->build();

	Assert::type(Client::class, $container->getService('redis.connection.default.client'));
	Assert::falsey($container->hasService('redis.connection.default.journal'));
	Assert::falsey($container->hasService('redis.connection.default.storage'));
});

// Client + Storage + Journal
Toolkit::test(function (): void {
	$container = Container::of()
		->withCompiler(function (Compiler $compiler): void {
			$compiler->addExtension('redis', new RedisExtension());
			$compiler->addExtension('caching', new CacheExtension(Tests::TEMP_PATH));
			$compiler->addConfig(Helpers::neon('
				redis:
					connection:
						default:
							uri: tcp://127.0.0.1:6379
							storage: true
			'));
		})
		->build();

	Assert::type(Client::class, $container->getService('redis.connection.default.client'));
	Assert::type(RedisJournal::class, $container->getService('redis.connection.default.journal'));
	Assert::type(RedisStorage::class, $container->getService('redis.connection.default.storage'));
});

// Client + Storage + Journal
Toolkit::test(function (): void {
	$container = Container::of()
		->withCompiler(function (Compiler $compiler): void {
			$compiler->addExtension('redis', new RedisExtension());
			$compiler->addExtension('caching', new CacheExtension(Tests::TEMP_PATH));
			$compiler->addConfig(Helpers::neon('
				redis:
					connection:
						default:
							uri: tcp://127.0.0.1:6379
							storage: true
			'));
		})
		->build();

	Assert::type(Client::class, $container->getService('redis.connection.default.client'));
	Assert::type(RedisJournal::class, $container->getService('redis.connection.default.journal'));
	Assert::type(RedisStorage::class, $container->getService('redis.connection.default.storage'));
});

// Multiple connections
Toolkit::test(function (): void {
	$container = Container::of()
		->withCompiler(function (Compiler $compiler): void {
			$compiler->addExtension('redis', new RedisExtension());
			$compiler->addExtension('caching', new CacheExtension(Tests::TEMP_PATH));
			$compiler->addConfig(Helpers::neon('
				redis:
					connection:
						default:
							uri: tcp://127.0.0.1:1111
						second:
							uri: tcp://127.0.0.2:2222
			'));
		})
		->build();

	Assert::type(Client::class, $container->getService('redis.connection.default.client'));
	Assert::falsey($container->hasService('redis.connection.default.journal'));
	Assert::falsey($container->hasService('redis.connection.default.storage'));

	Assert::type(Client::class, $container->getService('redis.connection.second.client'));
	Assert::falsey($container->hasService('redis.connection.second.journal'));
	Assert::falsey($container->hasService('redis.connection.second.storage'));
});

// Client + Storage + Journal
Toolkit::test(function (): void {
	$container = Container::of()
		->withCompiler(function (Compiler $compiler): void {
			$compiler->addExtension('redis', new RedisExtension());
			$compiler->addExtension('caching', new CacheExtension(Tests::TEMP_PATH));
			$compiler->addConfig(Helpers::neon('
				redis:
					connection:
						default:
							uri: tcp://127.0.0.1:1111
							storage: true
						second:
							uri: tcp://127.0.0.2:2222
							storage: true
			'));
		})
		->build();

	Assert::noError(function () use ($container) {
		$container->getByType(IStorage::class);
	});

	Assert::type(Client::class, $container->getService('redis.connection.default.client'));
	Assert::type(RedisJournal::class, $container->getService('redis.connection.default.journal'));
	Assert::type(RedisStorage::class, $container->getService('redis.connection.default.storage'));

	Assert::type(Client::class, $container->getService('redis.connection.second.client'));
	Assert::type(RedisJournal::class, $container->getService('redis.connection.second.journal'));
	Assert::type(RedisStorage::class, $container->getService('redis.connection.second.storage'));

	/** @var RedisStorage $storage1 */
	$storage1 = $container->getService('redis.connection.default.storage');
	/** @var RedisStorage $storage2 */
	$storage2 = $container->getService('redis.connection.second.storage');

	Assert::notSame(Liberator::of($storage1)->client, Liberator::of($storage2)->client);
	Assert::notSame(Liberator::of($storage1)->journal, Liberator::of($storage2)->journal);
});

// Dynamic parameters
Toolkit::test(function (): void {
	$container = Container::of()
		->withCompiler(function (Compiler $compiler): void {
			$compiler->addExtension('redis', new RedisExtension());
			$compiler->addConfig(Helpers::neon('
				redis:
					connection:
						default:
							uri: %env.REDIS_URI%
			'));
		})
		->withDynamicParameters([
			'env' => [
				'REDIS_URI' => 'tcp://1.2.3.4:1234',
			],
		])
		->build();

	/** @var Client $client */
	$client = $container->getService('redis.connection.default.client');

	$parameters = Liberator::of($client->getConnection())->parameters->toArray();

	Assert::equal('1.2.3.4', $parameters['host']);
	Assert::equal(1234, $parameters['port']);
});
