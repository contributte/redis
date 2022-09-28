<?php declare(strict_types = 1);

/**
 * @phpExtension igbinary
 */

namespace Tests\Cases\Serializer;

use Contributte\Redis\Serializer\IgbinarySerializer;
use Ninjify\Nunjuck\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

Toolkit::test(function (): void {
	$serializer = new IgbinarySerializer();
	$meta = [];

	Assert::equal("\x00\x00\x00\x02\x11\x03foo", $serializer->serialize('foo', $meta));
	Assert::equal("\x00\x00\x00\x02\x14\x01\x11\x03foo\x11\x03bar", $serializer->serialize(['foo' => 'bar'], $meta));

	Assert::equal('foo', $serializer->unserialize("\x00\x00\x00\x02\x11\x03foo", $meta));
	Assert::equal(['foo' => 'bar'], $serializer->unserialize("\x00\x00\x00\x02\x14\x01\x11\x03foo\x11\x03bar", $meta));
});
