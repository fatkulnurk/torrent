<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Providers;

use Fatkulnurk\Torrent\Data\ServerStatus;
use Fatkulnurk\Torrent\Data\Torrent;
use Fatkulnurk\Torrent\Exceptions\RequestException;
use Override;

class Aria2Provider extends AbstractProvider
{
    private const STATUS_KEYS = [
        'gid',
        'infoHash',
        'status',
        'totalLength',
        'completedLength',
        'uploadLength',
        'dir',
        'bittorrent',
        'files',
        'errorCode',
        'errorMessage',
    ];

    private int $requestId = 1;

    private function getSecretParam(): array
    {
        $secret = $this->config['secret'] ?? null;

        if ($secret !== null && $secret !== '') {
            return ["token:{$secret}"];
        }

        return [];
    }

    private function jsonRpc(string $method, array $params = []): mixed
    {
        $params = array_merge($this->getSecretParam(), $params);

        $response = parent::request('POST', 'jsonrpc', [
            'json' => [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => $this->requestId++,
            ],
        ]);

        if (is_string($response)) {
            $decoded = json_decode($response, true);

            if (!is_array($decoded)) {
                throw new RequestException('Failed to parse aria2 JSON-RPC response');
            }

            $response = $decoded;
        }

        if (!is_array($response) || !isset($response['id'])) {
            throw new RequestException('Invalid JSON-RPC response from aria2');
        }

        if (isset($response['error'])) {
            throw new RequestException(
                "aria2 RPC error: {$response['error']['message']}",
                $response['error']['code'] ?? 0
            );
        }

        return $response['result'] ?? null;
    }

    #[Override]
    public function addTorrent(string $source, array $options = []): bool
    {
        if (
            str_starts_with($source, 'magnet:?xt=urn:btih:')
            || str_starts_with($source, 'http://')
            || str_starts_with($source, 'https://')
        ) {
            $this->jsonRpc('aria2.addUri', [[$source], $options]);
        } else {
            $decoded = base64_decode($source, true);

            if ($decoded === false) {
                throw new RequestException('Invalid base64 encoded torrent data');
            }

            $this->jsonRpc('aria2.addTorrent', [$source, $options]);
        }

        return true;
    }

    #[Override]
    public function getTorrents(array $filters = []): array
    {
        $active = $this->jsonRpc('aria2.tellActive', [self::STATUS_KEYS]) ?? [];
        $waiting = $this->jsonRpc('aria2.tellWaiting', [0, 1000, self::STATUS_KEYS]) ?? [];
        $stopped = $this->jsonRpc('aria2.tellStopped', [0, 1000, self::STATUS_KEYS]) ?? [];

        $all = array_merge(
            is_array($active) ? $active : [],
            is_array($waiting) ? $waiting : [],
            is_array($stopped) ? $stopped : [],
        );

        return Torrent::collection(
            array_map(fn(array $item): array => $this->mapTorrent($item), $all)
        );
    }

    #[Override]
    public function getTorrent(string $hash): Torrent
    {
        $result = $this->jsonRpc('aria2.tellStatus', [$hash, self::STATUS_KEYS]);

        if (!is_array($result) || !isset($result['gid'])) {
            throw new RequestException("Torrent with GID {$hash} not found");
        }

        return Torrent::fromArray($this->mapTorrent($result));
    }

    #[Override]
    public function pauseTorrent(string $hash): bool
    {
        $this->jsonRpc('aria2.pause', [$hash]);

        return true;
    }

    #[Override]
    public function resumeTorrent(string $hash): bool
    {
        $this->jsonRpc('aria2.unpause', [$hash]);

        return true;
    }

    #[Override]
    public function removeTorrent(string $hash, bool $deleteFiles = false): bool
    {
        $this->jsonRpc('aria2.remove', [$hash]);

        return true;
    }

    #[Override]
    public function setDownloadPath(string $hash, string $path): bool
    {
        $this->jsonRpc('aria2.changeOption', [$hash, ['dir' => $path]]);

        return true;
    }

    #[Override]
    public function getServerStatus(): ServerStatus
    {
        $version = $this->jsonRpc('aria2.getVersion');

        if (!is_array($version)) {
            return new ServerStatus();
        }

        return ServerStatus::fromArray($version);
    }

    private function mapTorrent(array $data): array
    {
        $totalLength = (int) ($data['totalLength'] ?? 0);
        $completedLength = (int) ($data['completedLength'] ?? 0);
        $percentDone = $totalLength > 0 ? $completedLength / $totalLength : 0.0;

        $name = '';

        if (isset($data['bittorrent']['name'])) {
            $name = $data['bittorrent']['name'];
        } elseif (!empty($data['files'][0]['path'])) {
            $name = basename($data['files'][0]['path']);
        }

        return [
            'hash' => $data['gid'] ?? '',
            'hashString' => $data['infoHash'] ?? $data['gid'] ?? '',
            'name' => $name,
            'status' => $this->mapStatus($data['status'] ?? ''),
            'totalSize' => $totalLength,
            'leftUntilDone' => max(0, $totalLength - $completedLength),
            'downloadDir' => $data['dir'] ?? '',
            'percentDone' => $percentDone,
        ];
    }

    private function mapStatus(string $status): int
    {
        return match ($status) {
            'active' => 2,
            'waiting' => 1,
            'paused' => 0,
            'complete' => 3,
            'error' => 4,
            'removed' => 5,
            default => 0,
        };
    }
}
