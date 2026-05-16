# fatkulnurk/torrent

**PHP torrent client SDK** — manage qBittorrent, Transmission, rTorrent, Deluge, rqbit, and aria2 through a single unified API. Add torrents, monitor downloads, pause/resume, and control your torrent server programmatically with PHP.

Perfect for automation scripts, web-based torrent managers, seedbox dashboards, and PHP applications that need BitTorrent client integration.

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

```php
use Fatkulnurk\Torrent\TorrentClientManager;

$client = TorrentClientManager::make('qbittorrent', 'http://192.168.1.10:8080', [
    'username' => 'admin',
    'password' => 'secret',
]);

$client->addTorrent('magnet:?xt=urn:btih:...');
$torrents = $client->getTorrents();
$client->pauseTorrent('hash123');
$client->removeTorrent('hash123', true);
```

### Transmission

```php
$client = TorrentClientManager::make('transmission', 'http://192.168.1.20:9091', [
    'username' => 'admin',
    'password' => 'secret',
]);

$client->addTorrent('magnet:?xt=urn:btih:...');
$torrents = $client->getTorrents();
```

### rTorrent

```php
$client = TorrentClientManager::make('rtorrent', 'http://192.168.1.30:8080');

$client->addTorrent('magnet:?xt=urn:btih:...');
$torrents = $client->getTorrents();
```

### Deluge

```php
$client = TorrentClientManager::make('deluge', 'http://192.168.1.40:8112', [
    'password' => 'deluge',
]);

$client->addTorrent('magnet:?xt=urn:btih:...');
$torrents = $client->getTorrents();
```

### rqbit

```php
$client = TorrentClientManager::make('rqbit', 'http://127.0.0.1:3030');

$client->addTorrent('magnet:?xt=urn:btih:...');
$torrents = $client->getTorrents();
```

### aria2

```php
$client = TorrentClientManager::make('aria2', 'http://127.0.0.1:6800', [
    'secret' => 'your-secret-token', // optional
]);

$client->addTorrent('magnet:?xt=urn:btih:...');
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

## Drivers

| Driver | Class | Protocol | Auth | Default Port |
|--------|-------|----------|------|-------------|
| `qbittorrent` | `QbittorrentProvider` | REST (cookie) | username + password | 8080 |
| `transmission` | `TransmissionProvider` | JSON-RPC | username + password (optional) | 9091 |
| `rtorrent` | `RTorrentProvider` | XML-RPC | none | 8080 (RPC2) |
| `deluge` | `DelugeProvider` | JSON-RPC | password | 8112 |
| `rqbit` | `RqbitProvider` | REST | none | 3030 |
| `aria2` | `Aria2Provider` | JSON-RPC | secret token (optional) | 6800 |

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
