<?php declare(strict_types = 1);

namespace Tests\Toolkit;

use ReflectionClass;

final class Liberator
{

	/** @var object */
	private $object;

	/** @var ReflectionClass */
	private $class;

	/**
	 * @param object $object
	 */
	public function __construct($object, string $class)
	{
		$this->object = $object;
		$this->class = new ReflectionClass($class);
	}

	/**
	 * @param object $object
	 */
	public static function of($object): self
	{
		return new static($object, get_class($object));
	}

	/**
	 * @param object $object
	 */
	public static function ofClass($object, string $class): self
	{
		return new static($object, $class);
	}

	public function __isset(string $name): bool
	{
		if (!$this->class->hasProperty($name)) {
			return false;
		}

		$property = $this->class->getProperty($name);
		$property->setAccessible(true);

		return /* $property->isInitialized($this->object) &&*/ $property->getValue($this->object) !== null;
	}

	public function __get(string $name)
	{
		$property = $this->class->getProperty($name);
		$property->setAccessible(true);

		return $property->getValue($this->object);
	}

	/**
	 * @param mixed $value
	 */
	public function __set(string $name, $value): void
	{
		$property = $this->class->getProperty($name);
		$property->setAccessible(true);
		$property->setValue($this->object, $value);
	}

	/**
	 * @param mixed[] $args
	 */
	public function __call(string $name, array $args = [])
	{
		$method = $this->class->getMethod($name);
		$method->setAccessible(true);

		return $method->invokeArgs($this->object, $args);
	}

}
