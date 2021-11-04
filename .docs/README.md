# Contributte Redis

[Predis](https://github.com/nrk/predis) integration into [Nette/DI](https://github.com/nette/di)

## Content

- [Setup](#setup)
- [Configuration](#setup)

## Setup

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
