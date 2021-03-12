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
	redis: Contributte\Redis\DI\RedisExtension
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
				ttl: null # time after which is session invalidated
```

### Cache

Replaces Nette\Caching\IStorage in DIC with RedisStorage

```neon
redis:
	connection:
		default:
			storage: false
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
