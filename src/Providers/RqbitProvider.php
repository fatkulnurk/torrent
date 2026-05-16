<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Providers;

use Fatkulnurk\Torrent\Data\ServerStatus;
use Fatkulnurk\Torrent\Data\Torrent;
use Fatkulnurk\Torrent\Exceptions\RequestException;
use Override;

class RqbitProvider extends AbstractProvider
{
    #[Override]
    public function addTorrent(string $source, array $options = []): bool
    {
        $body = $source;

        if (
            !str_starts_with($source, 'magnet:?xt=urn:btih:')
            && !str_starts_with($source, 'http://')
            && !str_starts_with($source, 'https://')
        ) {
            $decoded = base64_decode($source, true);

            if ($decoded === false) {
                throw new RequestException('Invalid base64 encoded torrent data');
            }

            $body = $decoded;
        }

        $requestOptions = ['body' => $body];

        if ($options !== []) {
            $requestOptions['query'] = $options;
        }

        $this->request('POST', 'torrents', $requestOptions);

        return true;
    }

    #[Override]
    public function getTorrents(array $filters = []): array
    {
        $response = $this->request('GET', 'torrents', [
            'query' => ['with_stats' => 'true'],
        ]);

        if (!is_array($response) || !isset($response['torrents'])) {
            return [];
        }

        $mapped = [];

        foreach ($response['torrents'] as $item) {
            $mapped[] = $this->mapTorrent($item);
        }

        return Torrent::collection($mapped);
    }

    #[Override]
    public function getTorrent(string $hash): Torrent
    {
        $response = $this->request('GET', "torrents/{$hash}");

        if (!is_array($response) || !isset($response['info_hash'])) {
            throw new RequestException("Torrent with hash {$hash} not found");
        }

        try {
            $stats = $this->request('GET', "torrents/{$hash}/stats/v1");

            if (is_array($stats)) {
                $response['stats'] = $stats;
            }
        } catch (RequestException) {
        }

        return Torrent::fromArray($this->mapTorrent($response));
    }

    #[Override]
    public function pauseTorrent(string $hash): bool
    {
        $this->request('POST', "torrents/{$hash}/pause");

        return true;
    }

    #[Override]
    public function resumeTorrent(string $hash): bool
    {
        $this->request('POST', "torrents/{$hash}/start");

        return true;
    }

    #[Override]
    public function removeTorrent(string $hash, bool $deleteFiles = false): bool
    {
        $endpoint = $deleteFiles
            ? "torrents/{$hash}/delete"
            : "torrents/{$hash}/forget";

        $this->request('POST', $endpoint);

        return true;
    }

    #[Override]
    public function setDownloadPath(string $hash, string $path): bool
    {
        throw new RequestException('setDownloadPath is not supported by rqbit API');
    }

    #[Override]
    public function getServerStatus(): ServerStatus
    {
        $response = $this->request('GET', 'stats');

        if (!is_array($response)) {
            return new ServerStatus();
        }

        return ServerStatus::fromArray($response);
    }

    private function mapTorrent(array $data): array
    {
        $stats = $data['stats'] ?? null;

        $totalBytes = (int) ($stats['total_bytes'] ?? 0);
        $progressBytes = (int) ($stats['progress_bytes'] ?? 0);
        $percentDone = $totalBytes > 0 ? $progressBytes / $totalBytes : 0.0;

        return [
            'hash' => $data['info_hash'] ?? '',
            'hashString' => $data['info_hash'] ?? '',
            'name' => $data['name'] ?? '',
            'status' => $this->mapStatus($stats['state'] ?? null),
            'totalSize' => $totalBytes,
            'leftUntilDone' => max(0, $totalBytes - $progressBytes),
            'downloadDir' => $data['output_folder'] ?? '',
            'percentDone' => $percentDone,
        ];
    }

    private function mapStatus(?string $state): int
    {
        return match ($state) {
            'paused' => 0,
            'initializing' => 1,
            'live' => 2,
            'error' => 4,
            default => 0,
        };
    }
}
