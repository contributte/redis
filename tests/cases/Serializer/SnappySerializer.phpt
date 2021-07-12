<?php declare(strict_types = 1);

/**
 * @phpExtension snappy
 */

namespace Tests\Cases\Redis\Serializer;

use Contributte\Redis\Serializer\SnappySerializer;
use Ninjify\Nunjuck\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

Toolkit::test(function (): void {
	$serializer = new SnappySerializer();
	$meta = [];

	Assert::equal("\x03\x08foo", $serializer->serialize('foo', $meta));
	Assert::equal("\x0d\x30{\"foo\":\"bar\"}", $serializer->serialize(['foo' => 'bar'], $meta));

	Assert::equal('foo', $serializer->unserialize("\x03\x08foo", $meta));
	Assert::equal(['foo' => 'bar'], $serializer->unserialize("\x0d\x30{\"foo\":\"bar\"}", $meta));
});
