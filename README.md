![](https://heatbadger.now.sh/github/readme/contributte/redis/)

<p align=center>
    <a href="https://github.com/contributte/redis/actions"><img src="https://badgen.net/github/checks/contributte/redis"></a>
    <a href="https://coveralls.io/r/contributte/redis"><img src="https://badgen.net/coveralls/c/github/contributte/redis"></a>
    <a href="https://packagist.org/packages/contributte/redis"><img src="https://badgen.net/packagist/dm/contributte/redis"></a>
    <a href="https://packagist.org/packages/contributte/redis"><img src="https://badgen.net/packagist/v/contributte/redis"></a>
</p>
<p align=center>
    <a href="https://packagist.org/packages/contributte/redis"><img src="https://badgen.net/packagist/php/contributte/redis"></a>
    <a href="https://github.com/contributte/redis"><img src="https://badgen.net/github/license/contributte/redis"></a>
    <a href="https://bit.ly/ctteg"><img src="https://badgen.net/badge/support/gitter/cyan"></a>
    <a href="https://bit.ly/cttfo"><img src="https://badgen.net/badge/support/forum/yellow"></a>
    <a href="https://contributte.org/partners.html"><img src="https://badgen.net/badge/sponsor/donations/F96854"></a>
</p>

<p align=center>
    Website 🚀 <a href="https://contributte.org">contributte.org</a> | Contact 👨🏻‍💻 <a href="https://f3l1x.io">f3l1x.io</a> | Twitter 🐦 <a href="https://twitter.com/contributte">@contributte</a>
</p>

Redis client integration into Nette framework.

## Versions

| State       | Version | Branch   | Nette | PHP     |
|-------------|---------|----------|-------|---------|
| dev         | `^0.7`  | `master` | 3.0+  | `>=8.2` |
| stable      | `^0.6`  | `master` | 3.0+  | `>=7.2` |

## Contents

- [Installation](#installation)
- [Configuration](#configuration)

## Installation

```bash
composer require contributte/redis
```

```neon
extensions:
	redis: Contributte\Redis\DI\RedisExtension # For Nette 3+
	redis: Contributte\Redis\DI\RedisExtension24 # For Nette 2.4
```

## Configuration

```neon
redis:
	# Setup Tracy panel
	debug: %debugMode%

	# Default client factory
	clientFactory: Predis\Client

	connection:
		default:
			uri: tcp://127.0.0.1:6379

			# Options passed directly to Predis\Client
			# https://github.com/nrk/predis#client-configuration
			options: []
```

### Sessions

Setup Nette\Http\Session to store session with Redis

```neon
redis:
	connection:
		default:
			sessions: true

			## you can also configure session
			sessions:
				ttl: 3600 # time in seconds after which is session invalidated
```

### Cache

Replaces Nette\Caching\IStorage in DIC with RedisStorage

```neon
redis:
	connection:
		default:
			storage: true
```

### Custom serializer

To use custom serializer for storage you need to implement `Contributte/Redis/Serializer/Serializer` interface.

After that, register serializer as a service and place it under `redis.serializer` key.

```neon
redis:
	serializer: @customSerializer
	connection:
		default:
			storage: true

services:
	customSerializer: App\Serializers\YourSerializer
```

This package provides several serializers:

- DefaultSerializer
- IgbinarySerializer
- SnappySerializer

### ENV variables

In the need of passing down ENV variables to the configuration you can use `nette/configurator` and [**dynamic parameters**](https://doc.nette.org/en/3.1/bootstrap#toc-dynamic-parameters).

```php
$configurator->addDynamicParameters([
	'env' => getenv(),
]);
```

```neon
redis:
	connection:
		default:
			uri: %env.REDIS_HOST%
```

### Sessions and cache

When using sessions and cache make sure you use **2 different databases**. One for cache and one for sessions. In case you will use only 1 database for both **you will loose sessions when clearing cache.**
This would be preferred config:

```neon
connection:
	default:
		uri: tcp://127.0.0.1:6379
		sessions: false
		storage: true
		options: ['parameters': ['database': 0]]
	session:
		uri: tcp://127.0.0.1:6379
		sessions: true
		storage: false
		options: ['parameters': ['database': 1]]
```

## Development

See [how to contribute](https://contributte.org/contributing.html) to this package.

This package is currently maintaining by these authors.

<a href="https://github.com/f3l1x">
  <img width="80" height="80" src="https://avatars2.githubusercontent.com/u/538058?v=3&s=80">
</a>

-----

Consider to [support](https://contributte.org/partners.html) **contributte** development team.
Also thank you for using this package.
