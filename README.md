# fatkulnurk/torrent

[![Latest release](https://img.shields.io/github/v/release/fatkulnurk/torrent)](https://github.com/fatkulnurk/torrent/releases)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**Unified PHP SDK** for torrent clients — one API for **[qBittorrent](https://www.qbittorrent.org/)**, **[Transmission](https://transmissionbt.com/)**, **[rTorrent](https://github.com/rakshasa/rtorrent)**, **[Deluge](https://deluge-torrent.org/)**, **[rqbit](https://github.com/ikatson/rqbit)**, **[aria2](https://aria2.github.io/)**, and **[Porla](https://porla.org)**.

Add magnets or `.torrent` files, list progress, pause/resume, remove torrents, and read server version from PHP automation, seedbox tools, or web dashboards.

```bash
composer require fatkulnurk/torrent
```

Requires **PHP 8.3+** and [Guzzle](https://docs.guzzlephp.org/) 7.

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
  - [Porla](#porla)
- [Methods](#methods)
- [Torrent data](#torrent-data)
- [Driver status](#driver-status)
- [Drivers](#drivers)
- [Testing](#testing)
- [Custom driver](#custom-driver)
- [AI / LLM Agents](#ai--llm-agents)
- [License](#license)

## Features

- **Seven clients, one interface** — switch drivers without rewriting app code
- **Normalized `Torrent` DTO** — hash, name, size, progress, path, and status mapped from each client’s API
- **Flexible add sources** — magnet URI, HTTP(S) URL (where supported), or base64-encoded `.torrent`
- **Strict typing** — PHP 8.3+, readonly data objects, PSR-4 autoload
- **Tested** — PHPUnit 12 unit suite + optional Docker integration against real clients; PHPStan analysed

## Install

```bash
composer require fatkulnurk/torrent
# pin a minor: composer require fatkulnurk/torrent:^1.1
```

## Quick Start

All drivers are created the same way:

```php
use Fatkulnurk\Torrent\TorrentClientManager;

$client = TorrentClientManager::make('qbittorrent', 'http://192.168.1.10:8080', [
    'username' => 'admin',
    'password' => 'secret',
]);
```

### [qBittorrent](https://www.qbittorrent.org/)

Auth is **required**. This package targets the **qBittorrent 5.x** WebUI API (pause/resume use `/torrents/stop` and `/torrents/start`).

```php
$client = TorrentClientManager::make('qbittorrent', 'http://192.168.1.10:8080', [
    'username' => 'admin',
    'password' => 'secret',
]);

$client->addTorrent('magnet:?xt=urn:btih:dd8255ecdc7ca55fb0bbf81323d87062db1f6d1c&dn=Big+Buck+Bunny');
$torrents = $client->getTorrents();

foreach ($torrents as $torrent) {
    echo "{$torrent->name}: " . round($torrent->percentDone * 100) . "%\n";
}

$client->pauseTorrent($torrents[0]->hash);
$client->removeTorrent($torrents[0]->hash, true);
```

### [Transmission](https://transmissionbt.com/)

Auth is optional (depends on server config). Uses legacy Transmission RPC (`torrent-add`, kebab-case fields) compatible with 4.x.

```php
// With auth
$client = TorrentClientManager::make('transmission', 'http://192.168.1.20:9091', [
    'username' => 'admin',
    'password' => 'secret',
]);

// Without auth
$client = TorrentClientManager::make('transmission', 'http://192.168.1.20:9091');

$client->addTorrent('magnet:?xt=urn:btih:dd8255ecdc7ca55fb0bbf81323d87062db1f6d1c&dn=Big+Buck+Bunny');
$torrents = $client->getTorrents();
```

### [rTorrent](https://github.com/rakshasa/rtorrent)

No authentication. XML-RPC over HTTP (SCGI proxy).

```php
$client = TorrentClientManager::make('rtorrent', 'http://192.168.1.30:8000', [
    'rpc_endpoint' => '/', // or 'RPC2' depending on your proxy
]);

$client->addTorrent('magnet:?xt=urn:btih:dd8255ecdc7ca55fb0bbf81323d87062db1f6d1c&dn=Big+Buck+Bunny');
$torrents = $client->getTorrents();
```

> `rpc_endpoint` defaults to `RPC2`. Use `/` when the SCGI gateway serves XML-RPC at the root (e.g. port 8000 on the crazymax/rtorrent-rutorrent image used in this repo’s Docker stack).

### [Deluge](https://deluge-torrent.org/)

Web UI JSON-RPC; **password required**.

```php
$client = TorrentClientManager::make('deluge', 'http://192.168.1.40:8112', [
    'password' => 'deluge',
]);

$client->addTorrent('magnet:?xt=urn:btih:dd8255ecdc7ca55fb0bbf81323d87062db1f6d1c&dn=Big+Buck+Bunny');
$torrents = $client->getTorrents();
```

### [rqbit](https://github.com/ikatson/rqbit)

No auth by default. REST API. `setDownloadPath` is **not** supported (throws `RequestException`).

```php
$client = TorrentClientManager::make('rqbit', 'http://127.0.0.1:3030');

$client->addTorrent('magnet:?xt=urn:btih:dd8255ecdc7ca55fb0bbf81323d87062db1f6d1c&dn=Big+Buck+Bunny');
$torrents = $client->getTorrents();
$client->pauseTorrent($torrents[0]->hash);
$client->resumeTorrent($torrents[0]->hash);
$client->removeTorrent($torrents[0]->hash, false); // forget, keep files
$client->removeTorrent($torrents[0]->hash, true);  // delete files
```

### [aria2](https://aria2.github.io/)

Optional RPC secret token. Magnets and HTTP(S) URLs use `addUri`; base64 `.torrent` uses `addTorrent`.

```php
// With secret
$client = TorrentClientManager::make('aria2', 'http://127.0.0.1:6800', [
    'secret' => 'your-secret-token',
]);

// Without secret
$client = TorrentClientManager::make('aria2', 'http://127.0.0.1:6800');

$client->addTorrent('magnet:?xt=urn:btih:dd8255ecdc7ca55fb0bbf81323d87062db1f6d1c&dn=Big+Buck+Bunny');
$torrents = $client->getTorrents();
```

### [Porla](https://porla.org)

[Porla](https://porla.org) uses JSON-RPC at `/api/v1/jsonrpc` with optional JWT bearer auth. Prefer setting a default `save_path` (required by `torrents.add` unless a preset supplies one). Production should use a JWT from `porla auth:token`; Docker integration in this repo disables auth for local testing only.

```php
// With JWT (recommended)
$client = TorrentClientManager::make('porla', 'http://127.0.0.1:1337', [
    'token' => 'your-jwt-token',
    'save_path' => '/dl',
]);

// Auth disabled (dev / docker only)
$client = TorrentClientManager::make('porla', 'http://127.0.0.1:1337', [
    'save_path' => '/dl',
]);

$client->addTorrent('magnet:?xt=urn:btih:dd8255ecdc7ca55fb0bbf81323d87062db1f6d1c&dn=Big+Buck+Bunny');
$torrents = $client->getTorrents();
$client->pauseTorrent($torrents[0]->hash);
$client->setDownloadPath($torrents[0]->hash, '/dl/other');
```

## Methods

| Method | Description |
|--------|-------------|
| `addTorrent(string $source, array $options = [])` | Add magnet, URL (where supported), or base64-encoded `.torrent` |
| `getTorrents(array $filters = [])` | List torrents as `Torrent[]` |
| `getTorrent(string $hash)` | Single torrent details |
| `pauseTorrent(string $hash)` | Pause / stop |
| `resumeTorrent(string $hash)` | Resume / start |
| `removeTorrent(string $hash, bool $deleteFiles = false)` | Remove torrent; `$deleteFiles` is best-effort (rTorrent/aria2 may not wipe data via RPC) |
| `setDownloadPath(string $hash, string $path)` | Change download directory (not supported on rqbit) |
| `getServerStatus()` | `ServerStatus` with version info when the client exposes it |

## Torrent data

Every list/get call returns a readonly `Fatkulnurk\Torrent\Data\Torrent`:

| Property | Type | Meaning |
|----------|------|---------|
| `hash` | `string` | Info-hash or client id (aria2 uses GID) |
| `name` | `string` | Display name |
| `status` | `int` | Coarse, provider-normalized state (see below) |
| `totalSize` | `int` | Total size in bytes |
| `leftUntilDone` | `int` | Bytes remaining |
| `downloadDir` | `string` | Download / save path |
| `percentDone` | `float` | Progress `0.0`–`1.0` |

`status` is **not** a global enum. Rough guide:

| Value | Typical meaning |
|------:|-----------------|
| `0` | Paused / stopped / unknown |
| `1` | Downloading / queued / initializing |
| `2` | Seeding / uploading / active |
| `3` | Complete (aria2) |
| `4` | Error |
| `5` | Removed (aria2) |

Native status codes still differ per client; use this field as a coarse UI signal.

## Driver status

| Method | [qBittorrent](https://www.qbittorrent.org/) | [Transmission](https://transmissionbt.com/) | [rTorrent](https://github.com/rakshasa/rtorrent) | [Deluge](https://deluge-torrent.org/) | [rqbit](https://github.com/ikatson/rqbit) | [aria2](https://aria2.github.io/) | [Porla](https://porla.org) |
|--------|:-----------:|:------------:|:--------:|:------:|:-----:|:-----:|:-----:|
| `addTorrent` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getTorrents` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getTorrent` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `pauseTorrent` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `resumeTorrent` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `removeTorrent` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `setDownloadPath` | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ |
| `getServerStatus` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

| Icon | Meaning |
|------|---------|
| ✅ | Supported |
| ❌ | Not available on that client (throws `RequestException`) |

## Drivers

| Driver | Class | Protocol | Auth | Typical port | Client Website |
|--------|-------|----------|------|--------------|----------------|
| `qbittorrent` | `QbittorrentProvider` | REST (cookie session) | username + password | 8080 | [qbittorrent.org](https://www.qbittorrent.org/) |
| `transmission` | `TransmissionProvider` | JSON-RPC | username + password (optional) | 9091 | [transmissionbt.com](https://transmissionbt.com/) |
| `rtorrent` | `RTorrentProvider` | XML-RPC | none | 8000 (RPC proxy) | [github.com/rakshasa/rtorrent](https://github.com/rakshasa/rtorrent) |
| `deluge` | `DelugeProvider` | JSON-RPC (Web UI) | password | 8112 | [deluge-torrent.org](https://deluge-torrent.org/) |
| `rqbit` | `RqbitProvider` | REST | none by default | 3030 | [github.com/ikatson/rqbit](https://github.com/ikatson/rqbit) |
| `aria2` | `Aria2Provider` | JSON-RPC | secret token (optional) | 6800 | [aria2.github.io](https://aria2.github.io/) |
| `porla` | `PorlaProvider` | JSON-RPC (`/api/v1/jsonrpc`) | JWT bearer (optional if auth disabled) | 1337 | [porla.org](https://porla.org) |

## Testing

### Unit tests

No external services:

```bash
make test-unit
# or
php vendor/bin/phpunit tests/Data tests/Exceptions tests/Providers tests/TorrentClientManagerTest.php
```

### Integration tests

Against real clients via Docker Compose:

```bash
make setup
make up
make test-integration
```

`make test-integration` starts containers, reads the temporary qBittorrent 5.x password from logs, and runs the suite against all seven services.

Manual run:

```bash
make setup && make up
docker logs torrent-qbittorrent 2>&1 | grep -oP 'temporary password is provided for this session: \K\S+'
QB_PASS=<password> INTEGRATION=true QBITTORRENT_PASSWORD=$QB_PASS php vendor/bin/phpunit tests/integration
```

Docker services used by this repo:

| Service | Image / build | Version | URL | Auth | Client Website |
|---------|---------------|---------|-----|------|----------------|
| [qBittorrent](https://www.qbittorrent.org/) | `lscr.io/linuxserver/qbittorrent` | 5.2.0 | http://localhost:8080 | `admin` + session temp password | [qbittorrent.org](https://www.qbittorrent.org/) |
| [Transmission](https://transmissionbt.com/) | `lscr.io/linuxserver/transmission` | 4.1.1 | http://localhost:9091 | `admin` / `admin` | [transmissionbt.com](https://transmissionbt.com/) |
| [rTorrent](https://github.com/rakshasa/rtorrent) | `docker/rtorrent` (crazymax-based) | 0.16.7 | http://localhost:8000 (RPC), :8081 (UI) | none | [rakshasa/rtorrent](https://github.com/rakshasa/rtorrent) |
| [Deluge](https://deluge-torrent.org/) | `lscr.io/linuxserver/deluge` | 2.1.1 | http://localhost:8112 | password `deluge` | [deluge-torrent.org](https://deluge-torrent.org/) |
| [rqbit](https://github.com/ikatson/rqbit) | `ikatson/rqbit` | 9.0.0-beta.1 | http://localhost:3030 | none | [ikatson/rqbit](https://github.com/ikatson/rqbit) |
| [aria2](https://aria2.github.io/) | `docker/aria2` | 1.37.0 | http://localhost:6800 | secret `secret123` | [aria2.github.io](https://aria2.github.io/) |
| [Porla](https://porla.org) | `ghcr.io/porla/porla` | 0.41.0 | http://localhost:1337 | auth disabled in compose (`PORLA_HTTP_AUTH_DISABLED_YES_REALLY`); use JWT in production | [porla.org](https://porla.org) |

> qBittorrent 5.x temporary passwords change on container restart. Use `make test-integration` or `make qb-password`.

## Custom driver

```php
use Fatkulnurk\Torrent\TorrentClientManager;
use Fatkulnurk\Torrent\Providers\AbstractProvider;
use Fatkulnurk\Torrent\Data\Torrent;
use Fatkulnurk\Torrent\Data\ServerStatus;

class MyProvider extends AbstractProvider
{
    protected function initialize(): void {}

    public function addTorrent(string $source, array $options = []): bool { /* ... */ }
    public function getTorrents(array $filters = []): array { /* ... */ }
    public function getTorrent(string $hash): Torrent { /* ... */ }
    public function pauseTorrent(string $hash): bool { /* ... */ }
    public function resumeTorrent(string $hash): bool { /* ... */ }
    public function removeTorrent(string $hash, bool $deleteFiles = false): bool { /* ... */ }
    public function setDownloadPath(string $hash, string $path): bool { /* ... */ }
    public function getServerStatus(): ServerStatus { /* ... */ }
}

TorrentClientManager::register('custom', MyProvider::class);
$client = TorrentClientManager::make('custom', 'http://...');
```

## AI / LLM Agents

If you are using an AI coding assistant, autonomous agent, or LLM (e.g. Claude Code, Cursor, OpenCode, GitHub Copilot) to contribute to or integrate this repository, refer to the full agent operational guide in [**AGENTS.md**](AGENTS.md).

### Quick Agent Reference

- **Language & Runtime**: PHP 8.3+ (`declare(strict_types=1);` mandatory across all files).
- **Core Abstraction**: All drivers implement `Fatkulnurk\Torrent\Contracts\TorrentClientInterface`.
- **Factory**: Instantiate clients exclusively via `Fatkulnurk\Torrent\TorrentClientManager::make($driver, $baseUrl, $config)`.
- **Immutable DTOs**: Torrent records and server info are represented by `Fatkulnurk\Torrent\Data\Torrent` and `Fatkulnurk\Torrent\Data\ServerStatus` (readonly).
- **Verification Commands**:
  - Run unit tests: `php vendor/bin/phpunit tests/Data tests/Exceptions tests/Providers tests/TorrentClientManagerTest.php` (or `make test-unit`).
  - Run static analysis: `php vendor/bin/phpstan analyse`.
  - Check code style: `php vendor/bin/php-cs-fixer fix --dry-run --diff src/`.

## License

MIT — see [LICENSE](LICENSE).
