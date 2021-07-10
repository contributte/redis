<?php declare(strict_types = 1);

namespace Tests\Cases\Redis\Serializer;

use Contributte\Redis\Serializer\DefaultSerializer;
use Ninjify\Nunjuck\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

Toolkit::test(function (): void {
	$serializer = new DefaultSerializer();
	$meta = [];

	Assert::equal('s:3:"foo";', $serializer->serialize('foo', $meta));
	Assert::equal('a:1:{s:3:"foo";s:3:"bar";}', $serializer->serialize(['foo' => 'bar'], $meta));

	Assert::equal('foo', $serializer->unserialize('s:3:"foo";', $meta));
	Assert::equal(['foo' => 'bar'], $serializer->unserialize('a:1:{s:3:"foo";s:3:"bar";}', $meta));
});
