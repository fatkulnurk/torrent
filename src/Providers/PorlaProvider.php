<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Providers;

use Fatkulnurk\Torrent\Data\ServerStatus;
use Fatkulnurk\Torrent\Data\Torrent;
use Fatkulnurk\Torrent\Exceptions\RequestException;
use Override;

class PorlaProvider extends AbstractProvider
{
    private const PAGE_SIZE = 100;

    /** libtorrent torrent_flags::paused (bit 4) */
    private const FLAG_PAUSED = 1 << 4;

    private int $requestId = 1;

    private function jsonRpc(string $method, array $params = []): mixed
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        $token = $this->config['token'] ?? null;

        if ($token !== null && $token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $response = parent::request('POST', 'api/v1/jsonrpc', [
            'headers' => $headers,
            'json' => [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params === [] ? new \stdClass() : $params,
                'id' => $this->requestId++,
            ],
        ]);

        if (is_string($response)) {
            $decoded = json_decode($response, true);

            if (!is_array($decoded)) {
                throw new RequestException('Failed to parse Porla JSON-RPC response');
            }

            $response = $decoded;
        }

        if (!is_array($response) || !array_key_exists('id', $response)) {
            throw new RequestException('Invalid JSON-RPC response from Porla');
        }

        if (isset($response['error'])) {
            $message = is_array($response['error'])
                ? (string) ($response['error']['message'] ?? 'Unknown error')
                : (string) $response['error'];

            throw new RequestException(
                "Porla RPC error: {$message}",
                is_array($response['error']) ? (int) ($response['error']['code'] ?? 0) : 0
            );
        }

        return $response['result'] ?? null;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function toInfoHash(string $hash): array
    {
        $normalized = strtolower(trim($hash));

        if (strlen($normalized) === 64 && ctype_xdigit($normalized)) {
            return [null, $normalized];
        }

        return [$normalized, null];
    }

    private function fromInfoHash(mixed $infoHash): string
    {
        if (!is_array($infoHash)) {
            return is_string($infoHash) ? $infoHash : '';
        }

        $v1 = $infoHash[0] ?? null;
        $v2 = $infoHash[1] ?? null;

        if (is_string($v1) && $v1 !== '') {
            return $v1;
        }

        if (is_string($v2) && $v2 !== '') {
            return $v2;
        }

        return '';
    }

    private function infoHashMatches(mixed $infoHash, string $hash): bool
    {
        if (!is_array($infoHash)) {
            return is_string($infoHash) && strcasecmp($infoHash, $hash) === 0;
        }

        foreach ($infoHash as $part) {
            if (is_string($part) && $part !== '' && strcasecmp($part, $hash) === 0) {
                return true;
            }
        }

        return false;
    }

    private function mapStatus(array $data): int
    {
        $flags = (int) ($data['flags'] ?? 0);
        $progress = (float) ($data['progress'] ?? 0.0);

        if (($flags & self::FLAG_PAUSED) !== 0) {
            return 0;
        }

        if ($progress >= 1.0) {
            return 2;
        }

        if ($progress > 0.0) {
            return 1;
        }

        return 0;
    }

    private function mapTorrent(array $data): array
    {
        $totalSize = (int) ($data['size'] ?? $data['total'] ?? 0);
        $totalDone = (int) ($data['total_done'] ?? 0);
        $progress = (float) ($data['progress'] ?? 0.0);

        if ($progress <= 0.0 && $totalSize > 0 && $totalDone > 0) {
            $progress = $totalDone / $totalSize;
        }

        $leftUntilDone = max(0, $totalSize - $totalDone);

        return [
            'hash' => $this->fromInfoHash($data['info_hash'] ?? null),
            'hashString' => $this->fromInfoHash($data['info_hash'] ?? null),
            'name' => $data['name'] ?? '',
            'status' => $this->mapStatus($data),
            'totalSize' => $totalSize,
            'leftUntilDone' => $leftUntilDone,
            'downloadDir' => $data['save_path'] ?? '',
            'percentDone' => $progress,
        ];
    }

    private function listPage(int $page, int $pageSize = self::PAGE_SIZE, array $extra = []): array
    {
        $params = array_merge([
            'page' => $page,
            'page_size' => $pageSize,
        ], $extra);

        $result = $this->jsonRpc('torrents.list', $params);

        return is_array($result) ? $result : [];
    }

    /**
     * @return array<int, array>
     */
    private function listAllTorrents(array $extra = []): array
    {
        $page = 0;
        $all = [];

        do {
            $result = $this->listPage($page, self::PAGE_SIZE, $extra);
            $torrents = $result['torrents'] ?? [];

            if (!is_array($torrents) || $torrents === []) {
                break;
            }

            foreach ($torrents as $torrent) {
                if (is_array($torrent)) {
                    $all[] = $torrent;
                }
            }

            $total = (int) ($result['torrents_total'] ?? count($all));
            $pageSize = (int) ($result['page_size'] ?? self::PAGE_SIZE);
            $page++;
        } while (count($all) < $total && count($torrents) >= $pageSize);

        return $all;
    }

    #[Override]
    public function addTorrent(string $source, array $options = []): bool
    {
        $params = $options;

        if (preg_match('/^magnet:\?xt=urn:btih:/i', $source)) {
            $params['magnet_uri'] = $source;
        } elseif (is_file($source)) {
            $params['ti'] = base64_encode((string) file_get_contents($source));
        } else {
            if (base64_decode($source, true) === false) {
                throw new RequestException('Invalid base64 encoded torrent data');
            }

            $params['ti'] = $source;
        }

        if (!isset($params['save_path']) && isset($this->config['save_path'])) {
            $params['save_path'] = $this->config['save_path'];
        }

        if (isset($params['savepath']) && !isset($params['save_path'])) {
            $params['save_path'] = $params['savepath'];
        }

        unset($params['savepath']);

        $this->jsonRpc('torrents.add', $params);

        return true;
    }

    #[Override]
    public function getTorrents(array $filters = []): array
    {
        $extra = [];

        if ($filters !== []) {
            if (isset($filters['filters']) && is_array($filters['filters'])) {
                $extra['filters'] = $filters['filters'];
            } else {
                $extra['filters'] = $filters;
            }

            if (isset($filters['page'])) {
                $extra['page'] = $filters['page'];
            }

            if (isset($filters['page_size'])) {
                $extra['page_size'] = $filters['page_size'];
            }

            if (isset($filters['order_by'])) {
                $extra['order_by'] = $filters['order_by'];
            }

            if (isset($filters['order_by_dir'])) {
                $extra['order_by_dir'] = $filters['order_by_dir'];
            }
        }

        if (isset($extra['page']) || isset($extra['page_size'])) {
            $result = $this->listPage(
                (int) ($extra['page'] ?? 0),
                (int) ($extra['page_size'] ?? self::PAGE_SIZE),
                $extra
            );
            $torrents = $result['torrents'] ?? [];

            if (!is_array($torrents)) {
                return [];
            }

            return Torrent::collection(
                array_map(fn(array $item): array => $this->mapTorrent($item), $torrents)
            );
        }

        $torrents = $this->listAllTorrents($extra);

        return Torrent::collection(
            array_map(fn(array $item): array => $this->mapTorrent($item), $torrents)
        );
    }

    #[Override]
    public function getTorrent(string $hash): Torrent
    {
        foreach ($this->listAllTorrents() as $item) {
            if ($this->infoHashMatches($item['info_hash'] ?? null, $hash)) {
                return Torrent::fromArray($this->mapTorrent($item));
            }
        }

        throw new RequestException("Torrent with hash {$hash} not found");
    }

    #[Override]
    public function pauseTorrent(string $hash): bool
    {
        $this->jsonRpc('torrents.pause', [
            'info_hash' => $this->toInfoHash($hash),
        ]);

        return true;
    }

    #[Override]
    public function resumeTorrent(string $hash): bool
    {
        $this->jsonRpc('torrents.resume', [
            'info_hash' => $this->toInfoHash($hash),
        ]);

        return true;
    }

    #[Override]
    public function removeTorrent(string $hash, bool $deleteFiles = false): bool
    {
        $this->jsonRpc('torrents.remove', [
            'info_hashes' => [$this->toInfoHash($hash)],
            'remove_data' => $deleteFiles,
        ]);

        return true;
    }

    #[Override]
    public function setDownloadPath(string $hash, string $path): bool
    {
        $this->jsonRpc('torrents.move', [
            'info_hash' => $this->toInfoHash($hash),
            'path' => $path,
        ]);

        return true;
    }

    #[Override]
    public function getServerStatus(): ServerStatus
    {
        $result = $this->jsonRpc('sys.versions');

        if (!is_array($result)) {
            return new ServerStatus();
        }

        $version = null;

        if (isset($result['porla']['version']) && is_string($result['porla']['version'])) {
            $version = $result['porla']['version'];
        } elseif (isset($result['version']) && is_string($result['version'])) {
            $version = $result['version'];
        }

        return new ServerStatus(version: $version);
    }
}
