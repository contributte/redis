<?php declare(strict_types=1);

namespace Nette\Caching;

if (!interface_exists('Nette\Caching\Storage')) {
	interface Storage extends IStorage{};
}
