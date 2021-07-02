<?php declare(strict_types = 1);

namespace Contributte\Redis\Serializer;

interface Serializer
{

	/**
	 * @param mixed $data
	 * @param mixed[] $meta
	 * @return string
	 */
	public function serialize($data, array &$meta): string;

	/**
	 * @param string $data
	 * @param mixed[] $meta
	 * @return mixed
	 */
	public function unserialize(string $data, array $meta);

}
