<?php declare(strict_types = 1);

namespace Contributte\Redis\Serializer;

final class SnappySerializer implements Serializer
{

	/**
	 * {@inheritDoc}
	 */
	public function serialize($data, array &$meta): string
	{
		return @snappy_compress(json_encode($data)); // @phpstan-ignore-line
	}

	/**
	 * {@inheritDoc}
	 */
	public function unserialize(string $data, array $meta)
	{
		return json_decode(@snappy_uncompress($data)); // @phpstan-ignore-line
	}

}
