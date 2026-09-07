# AI Agent Guidelines (`AGENTS.md`)

This document serves as an operational reference for autonomous AI agents and coding assistants working in the `fatkulnurk/torrent` repository.

---

## 1. Project Overview & Architecture

`fatkulnurk/torrent` is a unified, strongly-typed PHP 8.3+ SDK providing a single abstraction layer over multiple BitTorrent client APIs:
- **qBittorrent** (REST API, WebUI v2)
- **Transmission** (JSON-RPC)
- **rTorrent** (XML-RPC over SCGI gateway)
- **Deluge** (JSON-RPC via Web UI)
- **rqbit** (REST API)
- **aria2** (JSON-RPC)
- **Porla** (JSON-RPC at `/api/v1/jsonrpc`)

### Core Architecture Components

```
src/
├── Contracts/
│   └── TorrentClientInterface.php  # Public contract all drivers implement
├── Data/
│   ├── ServerStatus.php            # Readonly DTO for server version/status
│   └── Torrent.php                 # Readonly DTO for normalized torrent state
├── Exceptions/
│   ├── AuthenticationException.php # Thrown on invalid credentials / 401 / 403
│   ├── RequestException.php        # Thrown on network / HTTP / client API failure
│   ├── TorrentClientException.php  # Base package exception
│   └── UnsupportedDriverException.php # Thrown when driver key is unknown
├── Providers/
│   ├── AbstractProvider.php        # Base HTTP provider wrapping Guzzle client
│   ├── Aria2Provider.php
│   ├── DelugeProvider.php
│   ├── PorlaProvider.php
│   ├── QbittorrentProvider.php
│   ├── RqbitProvider.php
│   ├── RTorrentProvider.php
│   └── TransmissionProvider.php
└── TorrentClientManager.php        # Factory & driver registration manager
```

---

## 2. Technical Stack & Requirements

- **PHP**: `^8.3` (runs on 8.3, 8.4, and 8.5)
- **HTTP Client**: `guzzlehttp/guzzle: ^7.8`
- **Testing**: `phpunit/phpunit: ^12.0`
- **Static Analysis**: `phpstan/phpstan: ^2.0` (level 5, configured in `phpstan.neon`)
- **Code Style**: `friendsofphp/php-cs-fixer: ^3.64` (config in `.php-cs-fixer.dist.php`)

---

## 3. Development Conventions & Rules

When modifying or generating code in this repository:

1. **Strict Typing**:
   - Always declare `declare(strict_types=1);` at the top of every PHP file.
2. **DTO Immutability**:
   - `Torrent` and `ServerStatus` are `readonly` classes.
   - Any normalization or mapping must occur before/within their static factory methods (e.g. `Torrent::fromArray()`).
3. **Contract Stability**:
   - Do not alter method signatures on `TorrentClientInterface` unless explicitly requested, as this breaks backward compatibility for external consumers.
4. **Error Handling**:
   - Catch client/network errors and re-throw standard library exceptions:
     - `AuthenticationException` for auth failures.
     - `RequestException` for bad requests, HTTP 4xx/5xx, RPC fault responses, or unsupported operations (e.g., `setDownloadPath` in rqbit).
5. **No Blind Assumptions**:
   - Always inspect neighboring provider implementations in `src/Providers/` before adding new logic or adjusting payload structures.

---

## 4. Driver Specifics & Gotchas

Keep these client idiosyncrasies in mind when inspecting or adjusting providers:

| Client | Protocol & Auth | Crucial Behavior Details |
|---|---|---|
| **qBittorrent** | REST / Cookie Session | Targets WebUI 5.x. Endpoints for pause/resume are `/torrents/stop` and `/torrents/start`. Multiple hashes are pipe-delimited strings (`hash1\|hash2`), not array query parameters. |
| **Transmission** | JSON-RPC | Requires handling `X-Transmission-Session-Id` header (HTTP 409 handshake). `setDownloadPath` uses `torrent-set-location` with `move: true`. |
| **rTorrent** | XML-RPC over HTTP | Connects through SCGI gateway. Modern commands (`d.hash=`, `d.directory.set`, etc.) are used. `removeTorrent` data deletion via RPC is best-effort. `load.raw_start` requires binary payload. |
| **Deluge** | JSON-RPC (Web UI) | Web UI password authentication (`auth.login`), followed by `core.*` method calls. Torrent file dumps are base64 strings. |
| **rqbit** | REST API | Does not support `setDownloadPath`; calling it throws `RequestException`. Version is queried via `GET /` and stats via `GET /stats`. |
| **aria2** | JSON-RPC | Uses GID as hash. Secret token passes as `token:<secret>` in first parameter. Base64 `.torrent` adds via `aria2.addTorrent`. Data file deletion via RPC is limited. |
| **Porla** | JSON-RPC (`/api/v1/jsonrpc`) | Optional JWT bearer token (`Authorization: Bearer <token>`). `save_path` is required when adding torrents unless a preset supplies it. |

---

## 5. Verification Commands

Before concluding any work, AI agents must run and pass the following checks:

### Unit Tests
```bash
php vendor/bin/phpunit tests/Data tests/Exceptions tests/Providers tests/TorrentClientManagerTest.php
# or using Makefile:
make test-unit
```

### Static Analysis
```bash
php vendor/bin/phpstan analyse
```

### Code Style Checking
```bash
php vendor/bin/php-cs-fixer fix --dry-run --diff src/
```

### Integration Tests (Docker environment only)
```bash
make setup
make up
make test-integration
```
*Note: Integration tests require Docker and live containers. Do not run in environments without Docker.*

---

## 6. How to Implement a New Provider

1. Create `src/Providers/NewProvider.php` extending `AbstractProvider` and implementing `TorrentClientInterface`.
2. Implement required methods:
   - `initialize(): void` (setup authentication, base headers, or initial session handshake)
   - `addTorrent(string $source, array $options = []): bool`
   - `getTorrents(array $filters = []): array`
   - `getTorrent(string $hash): Torrent`
   - `pauseTorrent(string $hash): bool`
   - `resumeTorrent(string $hash): bool`
   - `removeTorrent(string $hash, bool $deleteFiles = false): bool`
   - `setDownloadPath(string $hash, string $path): bool`
   - `getServerStatus(): ServerStatus`
3. Map API response attributes to the `Torrent` DTO:
   - `hash` (string)
   - `name` (string)
   - `status` (int: 0=paused, 1=downloading, 2=seeding, 3=complete, 4=error, 5=removed)
   - `totalSize` (int)
   - `leftUntilDone` (int)
   - `downloadDir` (string)
   - `percentDone` (float 0.0 - 1.0)
4. Register the new driver in `TorrentClientManager::$registry`.
5. Add unit test suite in `tests/Providers/NewProviderTest.php` with mock HTTP responses.
6. Run `phpstan` and `phpunit` to verify zero errors.
