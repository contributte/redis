# Contributte Redis

[Predis](https://github.com/nrk/predis) integration into [Nette/DI](https://github.com/nette/di)

## Content

- [Setup](#setup)
- [Configuration](#setup)

## Setup

```bash
composer require contributte/redis
```

```yaml
extensions:
    redis: Contributte\Redis\DI\RedisExtension
```

## Configuration

```yaml
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

```yaml
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

```yaml
redis:
    connection:
        default:
            storage: false
```
