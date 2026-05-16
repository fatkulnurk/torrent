# fatkulnurk/torrent

SDK PHP untuk mengelola torrent client (qBittorrent & Transmission) via API.

> **Untuk pemula**: Library ini menyatukan cara pakai berbagai torrent client dengan kode yang sama. Tinggal ganti nama driver-nya.

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

## Methods

| Method | Fungsi |
|--------|--------|
| `addTorrent($source, $options)` | Tambah torrent (magnet, file, atau base64) |
| `getTorrents($filters)` | Ambil daftar torrent |
| `getTorrent($hash)` | Ambil detail 1 torrent |
| `pauseTorrent($hash)` | Pause torrent |
| `resumeTorrent($hash)` | Resume torrent |
| `removeTorrent($hash, $deleteFiles?)` | Hapus torrent |
| `setDownloadPath($hash, $path)` | Pindah folder download |
| `getServerStatus()` | Status server |

## Custom Driver

```php
use Fatkulnurk\Torrent\TorrentClientManager;
use Fatkulnurk\Torrent\Providers\AbstractProvider;

class MyProvider extends AbstractProvider
{
    protected function initialize(): void {}

    public function addTorrent(string $source, array $options = []): bool { /* ... */ }
    // implement method lainnya...
}

TorrentClientManager::register('custom', MyProvider::class);
$client = TorrentClientManager::make('custom', 'http://...');
```

## License

MIT
