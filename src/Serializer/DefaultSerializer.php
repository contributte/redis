<?php declare(strict_types = 1);

namespace Contributte\Redis\Serializer;

use Contributte\Redis\Exception\LogicalException;

final class DefaultSerializer implements Serializer
{

	private const SERIALIZE_IGBINARY = 2;
	private const SERIALIZE_PHP = 1;

	private const META_SERIALIZED = 'serialized';

	/** @var bool */
	private $igbinary;

	public function __construct()
	{
		$this->igbinary = extension_loaded('igbinary');
	}

	/**
	 * @param mixed $data
	 * @param mixed[] $meta
	 * @return string
	 */
	public function serialize($data, array &$meta): string
	{
		if ($this->igbinary) {
			$meta[self::META_SERIALIZED] = self::SERIALIZE_IGBINARY;
			return @igbinary_serialize($data);
		}

		if (!is_string($data)) {
			$meta[self::META_SERIALIZED] = self::SERIALIZE_PHP;
			return @serialize($data);
		}

		return $data;
	}

	/**
	 * @param string $data
	 * @param mixed[] $meta
	 * @return mixed
	 */
	public function unserialize(string $data, array $meta)
	{
		switch ($meta[self::META_SERIALIZED] ?? 0) {
			case self::SERIALIZE_IGBINARY:
				if ($this->igbinary) {
					return @igbinary_unserialize($data);
				}

				throw new LogicalException('Incompatible serialization method, igbinary is missing but required.');

			case self::SERIALIZE_PHP:
				return @unserialize($data);

			default:
				return $data;
		}
	}

}
