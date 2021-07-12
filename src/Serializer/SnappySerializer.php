<?php declare(strict_types = 1);

namespace Contributte\Redis\Serializer;

final class SnappySerializer implements Serializer
{

	/**
	 * {@inheritDoc}
	 */
	public function serialize($data, array &$meta): string
	{
		return @snappy_compress($data);
	}

	/**
	 * {@inheritDoc}
	 */
	public function unserialize(string $data, array $meta)
	{
		return @snappy_uncompress($data);
	}

}
