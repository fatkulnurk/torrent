# fatkulnurk/torrent

**PHP torrent client SDK** — manage qBittorrent, Transmission, rTorrent, Deluge, rqbit, and aria2 through a single unified API. Add torrents, monitor downloads, pause/resume, and control your torrent server programmatically with PHP.

Perfect for automation scripts, web-based torrent managers, seedbox dashboards, and PHP applications that need BitTorrent client integration.

## Table of Contents

- [Features](#features)
- [Install](#install)
- [Quick Start](#quick-start)
  - [qBittorrent](#qbittorrent)
  - [Transmission](#transmission)
  - [rTorrent](#rtorrent)
  - [Deluge](#deluge)
  - [rqbit](#rqbit)
  - [aria2](#aria2)
- [Methods](#methods)
- [Driver Status](#driver-status)
- [Drivers](#drivers)
- [Testing](#testing)
  - [Unit Tests](#unit-tests)
  - [Integration Tests](#integration-tests)
- [Custom Driver](#custom-driver)
- [License](#license)

## Features

- **Multi-client support** — works with 6 different torrent clients out of the box
- **Single API** — same methods, same return types, regardless of the client
- **Composer-ready** — install in seconds with `composer require fatkulnurk/torrent`
- **PSR-4 autoloading** — clean namespace structure with strict typing
- **Full test coverage** — tested with PHPUnit 11, analysed with PHPStan 2

## Install

```bash
composer require fatkulnurk/torrent
```

## Quick Start

### qBittorrent

qBittorrent always requires authentication.

```php
use Fatkulnurk\Torrent\TorrentClientManager;

// With auth (required)
$client = TorrentClientManager::make('qbittorrent', 'http://192.168.1.10:8080', [
    'username' => 'admin',
    'password' => 'secret',
]);

$client->addTorrent('magnet:?xt=urn:btih:dd8255ecdc7ca55fb0bbf81323d87062db1f6d1c&dn=Big+Buck+Bunny');
$torrents = $client->getTorrents();
$client->pauseTorrent('hash123');
$client->removeTorrent('hash123', true);
```

### Transmission

Transmission supports both authenticated and anonymous mode depending on server configuration.

```php
// With auth
$client = TorrentClientManager::make('transmission', 'http://192.168.1.20:9091', [
    'username' => 'admin',
    'password' => 'secret',
]);

// Without auth (if server allows anonymous connections)
$client = TorrentClientManager::make('transmission', 'http://192.168.1.20:9091');

$client->addTorrent('magnet:?xt=urn:btih:dd8255ecdc7ca55fb0bbf81323d87062db1f6d1c&dn=Big+Buck+Bunny');
$torrents = $client->getTorrents();
```

### rTorrent

rTorrent has no authentication mechanism.

```php
// Without auth (default)
$client = TorrentClientManager::make('rtorrent', 'http://192.168.1.30:8080', [
    'rpc_endpoint' => 'RPC2',
]);

$client->addTorrent('magnet:?xt=urn:btih:dd8255ecdc7ca55fb0bbf81323d87062db1f6d1c&dn=Big+Buck+Bunny');
$torrents = $client->getTorrents();
```

> The `rpc_endpoint` option defaults to `RPC2`. Set it to `/` when connecting to rTorrent via an SCGI proxy that serves from root (e.g. port 8000 on the crazymax/rtorrent-rutorrent image).

### Deluge

Deluge always requires a password.

```php
// With auth (required)
$client = TorrentClientManager::make('deluge', 'http://192.168.1.40:8112', [
    'password' => 'deluge',
]);

$client->addTorrent('magnet:?xt=urn:btih:dd8255ecdc7ca55fb0bbf81323d87062db1f6d1c&dn=Big+Buck+Bunny');
$torrents = $client->getTorrents();
```

### rqbit

rqbit has no authentication by default. Supports pause, resume, remove (with or without files), and detailed torrent info via its REST API. `setDownloadPath` is not supported.

```php
// Without auth (default)
$client = TorrentClientManager::make('rqbit', 'http://127.0.0.1:3030');

$client->addTorrent('magnet:?xt=urn:btih:dd8255ecdc7ca55fb0bbf81323d87062db1f6d1c&dn=Big+Buck+Bunny');
$torrents = $client->getTorrents();
$client->pauseTorrent($torrents[0]->hash);
$client->resumeTorrent($torrents[0]->hash);
$client->removeTorrent($torrents[0]->hash, false); // keep files
$client->removeTorrent($torrents[0]->hash, true);  // delete files
```

### aria2

aria2 supports both secret-based auth and anonymous mode.

```php
// With auth
$client = TorrentClientManager::make('aria2', 'http://127.0.0.1:6800', [
    'secret' => 'your-secret-token',
]);

// Without auth (if server allows)
$client = TorrentClientManager::make('aria2', 'http://127.0.0.1:6800');

$client->addTorrent('magnet:?xt=urn:btih:dd8255ecdc7ca55fb0bbf81323d87062db1f6d1c&dn=Big+Buck+Bunny');
$torrents = $client->getTorrents();
```

## Methods

| Method | Description |
|--------|-------------|
| `addTorrent($source, $options)` | Add a torrent (magnet URI, HTTP URL, or base64-encoded .torrent) |
| `getTorrents($filters)` | List all torrents |
| `getTorrent($hash)` | Get a single torrent's details |
| `pauseTorrent($hash)` | Pause a torrent |
| `resumeTorrent($hash)` | Resume a torrent |
| `removeTorrent($hash, $deleteFiles?)` | Remove a torrent |
| `setDownloadPath($hash, $path)` | Change download directory |
| `getServerStatus()` | Get server status / version |

## Driver Status

| Method | qBittorrent | Transmission | rTorrent | Deluge | rqbit | aria2 |
|--------|:-----------:|:------------:|:--------:|:------:|:-----:|:-----:|
| `addTorrent` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getTorrents` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getTorrent` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `pauseTorrent` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `resumeTorrent` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `removeTorrent` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `setDownloadPath` | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| `getServerStatus` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

**Legend:**

| Icon | Meaning |
|------|---------|
| ✅ | Supported and tested |
| ❌ | Not supported by the provider (throws `RequestException`) |

## Drivers

| Driver | Class | Protocol | Auth | Default Port |
|--------|-------|----------|------|-------------|
| `qbittorrent` | `QbittorrentProvider` | REST (cookie) | username + password | 8080 |
| `transmission` | `TransmissionProvider` | JSON-RPC | username + password (optional) | 9091 |
| `rtorrent` | `RTorrentProvider` | XML-RPC | none | 8000 (RPC proxy) |
| `deluge` | `DelugeProvider` | JSON-RPC | password | 8112 |
| `rqbit` | `RqbitProvider` | REST | none | 3030 |
| `aria2` | `Aria2Provider` | JSON-RPC | secret token (optional) | 6800 |

## Testing

### Unit Tests

Run all unit tests (no external services required):

```bash
make test-unit
# or
php vendor/bin/phpunit tests/Data tests/Exceptions tests/Providers tests/TorrentClientManagerTest.php
```

### Integration Tests

Integration tests connect to real torrent client instances via Docker. Start all services and run:

```bash
make setup
make up
make test-integration
```

The `test-integration` target automatically:
1. Starts all containers via Docker Compose
2. Extracts the temporary qBittorrent 5.x password from container logs
3. Passes it as the `QBITTORRENT_PASSWORD` environment variable
4. Runs integration tests against all 6 services

To run integration tests manually:

```bash
# Start containers
make setup && make up

# Get qBittorrent temp password
docker logs torrent-qbittorrent 2>&1 | grep -oP 'temporary password is provided for this session: \K\S+'

# Run integration tests
QB_PASS=<extracted-password> INTEGRATION=true QBITTORRENT_PASSWORD=$QB_PASS php vendor/bin/phpunit tests/integration
```

Available Docker services:

| Service | Image | Version | URL | Auth |
|---------|-------|---------|-----|------|
| qBittorrent | `lscr.io/linuxserver/qbittorrent` | 5.2.0 | http://localhost:8080 | username: `admin`, password: auto-generated (see logs) |
| Transmission | `lscr.io/linuxserver/transmission` | 4.1.1 | http://localhost:9091 | username: `admin`, password: `admin` |
| rTorrent | custom `docker/rtorrent/Dockerfile` | 0.16.7 (rTorrent) | http://localhost:8000 (RPC) | none |
| | (based on `crazymax/rtorrent-rutorrent:5.2.10-0.16.7`) | | http://localhost:8081 (Web UI) | none |
| Deluge | `lscr.io/linuxserver/deluge` | 2.1.1 | http://localhost:8112 | password: `deluge` |
| rqbit | `ikatson/rqbit` | 9.0.0-beta.1 | http://localhost:3030 | none |
| aria2 | custom `docker/aria2/Dockerfile` | 1.37.0 | http://localhost:6800 | secret: `secret123` |

> **Note:** qBittorrent 5.x uses per-session temporary passwords. The password changes on every container restart. Use `make test-integration` to auto-extract it, or run `make qb-password` to view the current password.

## Custom Driver

```php
use Fatkulnurk\Torrent\TorrentClientManager;
use Fatkulnurk\Torrent\Providers\AbstractProvider;

class MyProvider extends AbstractProvider
{
    protected function initialize(): void {}

    public function addTorrent(string $source, array $options = []): bool { /* ... */ }
    // implement other methods...
}

TorrentClientManager::register('custom', MyProvider::class);
$client = TorrentClientManager::make('custom', 'http://...');
```

## License

MIT
