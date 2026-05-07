# fatkulnurk/torrent

A unified, type-safe PHP SDK for interacting with various torrent clients (qBittorrent, Transmission) via HTTP/JSON APIs.

## Requirements

- PHP 8.3+
- Guzzle HTTP 7.8+

## Installation

```bash
composer require fatkulnurk/torrent
```

## Usage

### qBittorrent

```php
use Fatkulnurk\Torrent\TorrentClientManager;

$qb = TorrentClientManager::make('qbittorrent', 'http://192.168.1.10:8080', [
    'username' => 'admin',
    'password' => 'secret',
]);

$qb->addTorrent('magnet:?xt=urn:btih:...', ['savepath' => '/downloads']);

$torrents = $qb->getTorrents();

$qb->pauseTorrent('hash123');

$qb->removeTorrent('hash123', true);
```

### Transmission

```php
use Fatkulnurk\Torrent\TorrentClientManager;

$tr = TorrentClientManager::make('transmission', 'http://192.168.1.20:9091', [
    'username' => 'transmission',
    'password' => 'secret',
]);

$tr->addTorrent('magnet:?xt=urn:btih:...', ['savepath' => '/downloads']);

$torrents = $tr->getTorrents();

$tr->pauseTorrent('hash123');
```

### Custom Provider

You can add custom providers (e.g., Deluge, rTorrent) without modifying the core SDK:

```php
use Fatkulnurk\Torrent\TorrentClientManager;
use Fatkulnurk\Torrent\Providers\AbstractProvider;
use Fatkulnurk\Torrent\Contracts\TorrentClientInterface;

class DelugeProvider extends AbstractProvider implements TorrentClientInterface
{
    protected function initialize(): void
    {
        // Custom initialization logic
    }

    #[Override]
    public function addTorrent(string $source, array $options = []): bool
    {
        // Custom implementation
    }

    // Implement other interface methods...
}

TorrentClientManager::register('deluge', DelugeProvider::class);

$del = TorrentClientManager::make('deluge', 'http://192.168.1.30:8112', [
    'password' => 'pass',
]);
```

## API Reference

### TorrentClientInterface

| Method | Description |
|--------|-------------|
| `addTorrent(string $source, array $options = []): bool` | Add a torrent from magnet URI, file path, or base64 string |
| `getTorrents(array $filters = []): array` | Get all torrents with optional filters |
| `getTorrent(string $hash): array` | Get a specific torrent by hash |
| `pauseTorrent(string $hash): bool` | Pause a torrent |
| `resumeTorrent(string $hash): bool` | Resume a torrent |
| `removeTorrent(string $hash, bool $deleteFiles = false): bool` | Remove a torrent optionally deleting files |
| `setDownloadPath(string $hash, string $path): bool` | Set download path for a torrent |
| `getServerStatus(): array` | Get server status information |

## License

MIT