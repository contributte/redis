<?php declare(strict_types = 1);

namespace Tests\Toolkit;

use Contributte\Redis\DI\RedisExtension;
use Nette\DI\Compiler;
use Nette\DI\Container as NetteContainer;
use Nette\DI\ContainerLoader;

final class Container
{

	/** @var string */
	private $key;

	/** @var callable[] */
	private $onCompile = [];

	/** @var mixed[] */
	private $parameters = [];

	public function __construct(string $key)
	{
		$this->key = $key;
	}

	public static function of(?string $key = null): Container
	{
		return new static($key ?? uniqid(random_bytes(16)));
	}

	public function withDefaults(): Container
	{
		$this->withDefaultExtensions();
		$this->withDefaultParameters();

		return $this;
	}

	public function withDefaultExtensions(): Container
	{
		$this->onCompile[] = function (Compiler $compiler): void {
			$compiler->addExtension('redis', new RedisExtension());
		};

		return $this;
	}

	public function withDefaultParameters(): Container
	{
		$this->onCompile[] = function (Compiler $compiler): void {
			$compiler->addConfig([
				'parameters' => [
					'tempDir' => Tests::TEMP_PATH,
					'appDir' => Tests::APP_PATH,
				],
			]);
			$compiler->addConfig(Helpers::neon('
				redis:
					connection:
						default:
							uri: tcp://127.0.0.1:6379
			'));
		};

		return $this;
	}

	public function withDynamicParameters(array $parameters): Container
	{
		$this->parameters = $parameters;

		return $this;
	}

	public function withCompiler(callable $cb): Container
	{
		$this->onCompile[] = function (Compiler $compiler) use ($cb): void {
			$cb($compiler);
		};

		return $this;
	}

	public function build(): NetteContainer
	{
		$loader = new ContainerLoader(Tests::TEMP_PATH, true);
		$class = $loader->load(function (Compiler $compiler): void {
			foreach ($this->onCompile as $cb) {
				$cb($compiler);
			}

			$compiler->setDynamicParameterNames(array_keys($this->parameters));
		}, $this->key);

		return new $class($this->parameters);
	}

}
