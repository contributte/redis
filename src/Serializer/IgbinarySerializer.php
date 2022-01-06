<?php declare(strict_types = 1);

namespace Contributte\Redis\Serializer;

final class IgbinarySerializer implements Serializer
{

	/**
	 * {@inheritDoc}
	 */
	public function serialize($data, array &$meta): string
	{
		return (string) @igbinary_serialize($data);
	}

	/**
	 * {@inheritDoc}
	 */
	public function unserialize(string $data, array $meta)
	{
		return @igbinary_unserialize($data);
	}

}
