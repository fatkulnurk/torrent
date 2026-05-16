<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Providers;

use Fatkulnurk\Torrent\Data\ServerStatus;
use Fatkulnurk\Torrent\Data\Torrent;
use Fatkulnurk\Torrent\Exceptions\AuthenticationException;
use Fatkulnurk\Torrent\Exceptions\RequestException;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Override;

class QbittorrentProvider extends AbstractProvider
{
    private ?string $sid = null;

    protected function initialize(): void
    {
        $this->authenticate();
    }

    private function authenticate(): void
    {
        $username = $this->config['username'] ?? null;
        $password = $this->config['password'] ?? null;

        if ($username === null || $password === null) {
            throw new AuthenticationException(
                'Username and password are required for qBittorrent authentication'
            );
        }

        try {
            $response = $this->client->request('POST', 'api/v2/auth/login', [
                'form_params' => [
                    'username' => $username,
                    'password' => $password,
                ],
            ]);

            $setCookie = $response->getHeaderLine('Set-Cookie');

            if (empty($setCookie)) {
                throw new AuthenticationException(
                    'No Set-Cookie header received from qBittorrent'
                );
            }

            if (preg_match('/SID=([^;]+)/', $setCookie, $matches)) {
                $this->sid = $matches[1];
            }

            if ($this->sid === null) {
                throw new AuthenticationException(
                    'Failed to extract SID from Set-Cookie header'
                );
            }
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (RequestException $e) {
            throw new AuthenticationException(
                "Authentication failed: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        } catch (GuzzleException $e) {
            throw new AuthenticationException(
                "Authentication request failed: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    #[Override]
    protected function request(string $method, string $endpoint, array $options = []): mixed
    {
        if ($this->sid !== null) {
            $options['headers'] = array_merge(
                $options['headers'] ?? [],
                ['Cookie' => "SID={$this->sid}"]
            );
        }

        $response = parent::request($method, $endpoint, $options);

        if (is_string($response)) {
            try {
                return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                // qBittorrent returns "Ok." for successful mutating operations
                return ['saveData' => true];
            }
        }

        return $response;
    }

    #[Override]
    public function addTorrent(string $source, array $options = []): bool
    {
        if (preg_match('/^magnet:\?xt=urn:btih:/i', $source)) {
            $response = $this->request('POST', 'api/v2/torrents/add', [
                'form_params' => array_merge(
                    $options,
                    ['urls' => $source]
                ),
            ]);
        } elseif (is_file($source)) {
            $response = $this->request('POST', 'api/v2/torrents/add', [
                'multipart' => [
                    [
                        'name' => 'torrents',
                        'contents' => fopen($source, 'r'),
                        'filename' => basename($source),
                    ],
                ],
            ]);
        } else {
            $decoded = base64_decode($source, true);

            if ($decoded === false) {
                throw new RequestException('Invalid base64 encoded torrent data');
            }

            $response = $this->request('POST', 'api/v2/torrents/add', [
                'form_params' => [
                    'torrent_files' => $decoded,
                ],
            ]);
        }

        return $response['saveData'] === true;
    }

    #[Override]
    public function getTorrents(array $filters = []): array
    {
        $data = $this->request('GET', 'api/v2/torrents/info', [
            'query' => $filters,
        ]);

        if (!is_array($data)) {
            return [];
        }

        return Torrent::collection($data);
    }

    #[Override]
    public function getTorrent(string $hash): Torrent
    {
        $data = $this->request('GET', 'api/v2/torrents/info', [
            'query' => ['hash' => $hash],
        ]);

        if (empty($data)) {
            throw new RequestException("Torrent with hash {$hash} not found");
        }

        if (!is_array($data) || !isset($data[0])) {
            throw new RequestException("Torrent with hash {$hash} not found");
        }

        return Torrent::fromArray($data[0]);
    }

    #[Override]
    public function pauseTorrent(string $hash): bool
    {
        $response = $this->request('POST', 'api/v2/torrents/pause', [
            'form_params' => ['hashes' => [$hash]],
        ]);

        return $response['saveData'] ?? true;
    }

    #[Override]
    public function resumeTorrent(string $hash): bool
    {
        $response = $this->request('POST', 'api/v2/torrents/resume', [
            'form_params' => ['hashes' => [$hash]],
        ]);

        return $response['saveData'] ?? true;
    }

    #[Override]
    public function removeTorrent(string $hash, bool $deleteFiles = false): bool
    {
        $response = $this->request('POST', 'api/v2/torrents/delete', [
            'form_params' => [
                'hashes' => [$hash],
                'deleteFiles' => $deleteFiles,
            ],
        ]);

        return $response['saveData'] ?? true;
    }

    #[Override]
    public function setDownloadPath(string $hash, string $path): bool
    {
        return $this->request('POST', 'api/v2/torrents/setLocation', [
            'form_params' => [
                'hashes' => [$hash],
                'location' => $path,
            ],
        ])['saveData'] ?? true;
    }

    #[Override]
    public function getServerStatus(): ServerStatus
    {
        $data = $this->request('GET', 'api/v2/app/coreVersion');

        if (!is_array($data)) {
            return new ServerStatus();
        }

        return ServerStatus::fromArray($data);
    }
}
