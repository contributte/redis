<?php declare(strict_types = 1);

namespace Contributte\Redis\DI;

use Contributte\Redis\Caching\RedisJournal;
use Contributte\Redis\Caching\RedisStorage;
use Contributte\Redis\Exception\Logic\InvalidStateException;
use Contributte\Redis\Tracy\RedisPanel;
use Nette\Caching\IStorage;
use Nette\DI\CompilerExtension;
use Nette\Http\Session;
use Nette\PhpGenerator\ClassType;
use Nette\Utils\Validators;
use Predis\Client;
use Predis\ClientInterface;
use Predis\Session\Handler;
use RuntimeException;

final class RedisExtension24 extends CompilerExtension
{

	/** @var mixed[] */
	private $defaults = [
		'debug' => false,
		'serializer' => null,
		'connection' => [],
		'clientFactory' => Client::class,
	];

	/** @var mixed[] */
	private $connectionDefaults = [
		'uri' => 'tcp://127.0.0.1:6379',
		'options' => [],
		'storage' => false,
		'sessions' => false,
	];

	/** @var mixed[] */
	private $sessionDefaults = [
		'ttl' => null,
	];

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->validateConfig($this->defaults);

		if (!isset($config['connection']['default'])) {
			throw new InvalidStateException(sprintf('%s.connection.default is required.', $this->name));
		}

		$connections = [];

		foreach ($config['connection'] as $name => $connection) {
			$autowired = $name === 'default';
			$connection = $this->validateConfig($this->connectionDefaults, $connection, $this->prefix('connection.' . $name));

			$client = $builder->addDefinition($this->prefix('connection.' . $name . '.client'))
				->setType(ClientInterface::class)
				->setFactory($config['clientFactory'], [$connection['uri'], $connection['options']])
				->setAutowired($autowired);

			$connections[] = [
				'name' => $name,
				'client' => $client,
				'uri' => $connection['uri'],
				'options' => $connection['options'],
			];
		}

		if ($config['debug'] === true) {
			$builder->addDefinition($this->prefix('panel'))
				->setFactory(RedisPanel::class, [$connections]);
		}
	}

	public function beforeCompile(): void
	{
		$this->beforeCompileStorage();
		$this->beforeCompileSession();
	}

	public function beforeCompileStorage(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->validateConfig($this->defaults);
		$storages = 0;

		foreach ($config['connection'] as $name => $connection) {
			$autowired = $name === 'default';
			$connection = $this->validateConfig($this->connectionDefaults, $connection, $this->prefix('connection.' . $name));

			// Skip if replacing storage is disabled
			if ($connection['storage'] === false) {
				continue;
			}

			// Validate needed services
			if ($builder->getByType(IStorage::class) === null) {
				throw new RuntimeException(sprintf('Please install nette/caching package. %s is required', IStorage::class));
			}

			if ($storages === 0) {
				$builder->getDefinitionByType(IStorage::class)
					->setAutowired(false);
			}

			$builder->addDefinition($this->prefix('connection.' . $name . '.journal'))
				->setFactory(RedisJournal::class)
				->setAutowired(false);

			$builder->addDefinition($this->prefix('connection.' . $name . '.storage'))
				->setFactory(RedisStorage::class)
				->setArguments([
					'client' => $builder->getDefinition($this->prefix('connection.' . $name . '.client')),
					'journal' => $builder->getDefinition($this->prefix('connection.' . $name . '.journal')),
					'serializer' => $config['serializer'],
				])
				->setAutowired($autowired);

			$storages++;
		}
	}

	public function beforeCompileSession(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->validateConfig($this->defaults);

		$sessionHandlingConnection = null;

		foreach ($config['connection'] as $name => $connection) {
			$connection = $this->validateConfig($this->connectionDefaults, $connection, $this->prefix('connection.' . $name));

			// Skip if replacing session is disabled
			if ($connection['sessions'] === false) {
				continue;
			}

			if ($sessionHandlingConnection === null) {
				$sessionHandlingConnection = $name;
			} else {
				throw new InvalidStateException(sprintf(
					'Connections "%s" and "%s" both try to register session handler. Only one of them could have session handler enabled.',
					$sessionHandlingConnection,
					$name
				));
			}

			// Validate given config
			Validators::assert($connection['sessions'], 'bool|array');

			// Validate needed services
			if ($builder->getByType(Session::class) === null) {
				throw new RuntimeException(sprintf('Please install nette/http package. %s is required', Session::class));
			}

			// Validate session config
			if ($connection['sessions'] === true) {
				$sessionConfig = $this->sessionDefaults;
			} else {
				$sessionConfig = $this->validateConfig($this->sessionDefaults, $connection['sessions'], $this->prefix('connection.' . $name . 'sessions'));
			}

			$sessionHandler = $builder->addDefinition($this->prefix('connection.' . $name . 'sessionHandler'))
				->setType(Handler::class)
				->setArguments([$this->prefix('@connection.' . $name . '.client'), ['gc_maxlifetime' => $sessionConfig['ttl']]]);

			$builder->getDefinitionByType(Session::class)
				->addSetup('setHandler', [$sessionHandler]);
		}
	}

	public function afterCompile(ClassType $class): void
	{
		$config = $this->validateConfig($this->defaults);

		if ($config['debug'] === true) {
			$initialize = $class->getMethod('initialize');
			$initialize->addBody('$this->getService(?)->addPanel($this->getService(?));', ['tracy.bar', $this->prefix('panel')]);
		}
	}

}
