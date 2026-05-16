<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Providers;

use Fatkulnurk\Torrent\Data\ServerStatus;
use Fatkulnurk\Torrent\Data\Torrent;
use Fatkulnurk\Torrent\Exceptions\AuthenticationException;
use Fatkulnurk\Torrent\Exceptions\RequestException;
use GuzzleHttp\Exception\GuzzleException;
use Override;

class DelugeProvider extends AbstractProvider
{
    private const STATUS_KEYS = [
        'hash',
        'name',
        'state',
        'total_size',
        'total_done',
        'download_location',
        'progress',
    ];

    private ?string $sessionCookie = null;
    private int $requestId = 1;

    protected function initialize(): void
    {
        $this->authenticate();
    }

    private function authenticate(): void
    {
        $password = $this->config['password'] ?? null;

        if ($password === null) {
            throw new AuthenticationException(
                'Password is required for Deluge authentication'
            );
        }

        try {
            $response = $this->client->request('POST', 'json', [
                'json' => [
                    'method' => 'auth.login',
                    'params' => [$password],
                    'id' => $this->requestId++,
                ],
            ]);

            $setCookie = $response->getHeaderLine('Set-Cookie');

            if (!empty($setCookie)) {
                $this->sessionCookie = explode(';', $setCookie)[0];
            }

            $body = json_decode((string) $response->getBody(), true);

            if (!isset($body['result']) || $body['result'] !== true) {
                throw new AuthenticationException('Deluge authentication failed');
            }

            $this->connectToHost();
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (GuzzleException $e) {
            throw new AuthenticationException(
                "Authentication request failed: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    private function connectToHost(): void
    {
        $hosts = $this->jsonRpc('web.get_hosts');

        if (!is_array($hosts) || $hosts === []) {
            throw new AuthenticationException('No Deluge hosts available');
        }

        $hostId = is_array($hosts[0]) ? $hosts[0][0] : null;

        if ($hostId === null) {
            throw new AuthenticationException('Failed to get Deluge host ID');
        }

        $this->jsonRpc('web.connect', [$hostId]);
    }

    private function jsonRpc(string $method, array $params = []): mixed
    {
        $headers = [];

        if ($this->sessionCookie !== null) {
            $headers['Cookie'] = $this->sessionCookie;
        }

        $response = parent::request('POST', 'json', [
            'headers' => $headers,
            'json' => [
                'method' => $method,
                'params' => $params,
                'id' => $this->requestId++,
            ],
        ]);

        if (is_string($response)) {
            $decoded = json_decode($response, true);

            if (!is_array($decoded)) {
                throw new RequestException('Failed to parse Deluge JSON response');
            }

            $response = $decoded;
        }

        if (!is_array($response) || !isset($response['id'])) {
            throw new RequestException('Invalid JSON-RPC response');
        }

        if (isset($response['error'])) {
            throw new RequestException(
                "Deluge API error: {$response['error']['message']}",
                $response['error']['code'] ?? 0
            );
        }

        return $response['result'] ?? null;
    }

    private function mapStatus(string $state): int
    {
        return match ($state) {
            'Downloading' => 1,
            'Seeding' => 2,
            default => 0,
        };
    }

    private function mapTorrent(array $data): array
    {
        return [
            'hash' => $data['hash'] ?? '',
            'hashString' => $data['hash'] ?? '',
            'name' => $data['name'] ?? '',
            'status' => $this->mapStatus($data['state'] ?? ''),
            'totalSize' => (int) ($data['total_size'] ?? 0),
            'leftUntilDone' => (int) (($data['total_size'] ?? 0) - ($data['total_done'] ?? 0)),
            'downloadDir' => $data['download_location'] ?? '',
            'percentDone' => (float) (($data['progress'] ?? 0) / 100),
        ];
    }

    #[Override]
    public function addTorrent(string $source, array $options = []): bool
    {
        if (preg_match('/^magnet:\?xt=urn:btih:/i', $source)) {
            $this->jsonRpc('core.add_torrent_magnet', [$source, $options]);
        } elseif (is_file($source)) {
            $filename = basename($source);
            $data = base64_encode(file_get_contents($source));
            $this->jsonRpc('core.add_torrent_file', [$filename, $data, $options]);
        } else {
            $decoded = base64_decode($source, true);

            if ($decoded === false) {
                throw new RequestException('Invalid base64 encoded torrent data');
            }

            $this->jsonRpc('core.add_torrent_file', ['torrent.torrent', $source, $options]);
        }

        return true;
    }

    #[Override]
    public function getTorrents(array $filters = []): array
    {
        $result = $this->jsonRpc('core.get_torrents_status', [
            $filters,
            self::STATUS_KEYS,
        ]);

        if (!is_array($result)) {
            return [];
        }

        return Torrent::collection(
            array_map(fn(array $data): array => $this->mapTorrent($data), array_values($result))
        );
    }

    #[Override]
    public function getTorrent(string $hash): Torrent
    {
        $result = $this->jsonRpc('core.get_torrent_status', [
            $hash,
            self::STATUS_KEYS,
        ]);

        if (!is_array($result) || empty($result)) {
            throw new RequestException("Torrent with hash {$hash} not found");
        }

        return Torrent::fromArray($this->mapTorrent($result));
    }

    #[Override]
    public function pauseTorrent(string $hash): bool
    {
        $this->jsonRpc('core.pause_torrent', [[$hash]]);
        return true;
    }

    #[Override]
    public function resumeTorrent(string $hash): bool
    {
        $this->jsonRpc('core.resume_torrent', [[$hash]]);
        return true;
    }

    #[Override]
    public function removeTorrent(string $hash, bool $deleteFiles = false): bool
    {
        $this->jsonRpc('core.remove_torrent', [$hash, $deleteFiles]);
        return true;
    }

    #[Override]
    public function setDownloadPath(string $hash, string $path): bool
    {
        $this->jsonRpc('core.set_torrent_options', [[$hash], ['download_location' => $path]]);
        return true;
    }

    #[Override]
    public function getServerStatus(): ServerStatus
    {
        $version = $this->jsonRpc('daemon.get_version');

        return new ServerStatus(
            version: is_string($version) ? $version : null,
        );
    }
}
