<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Providers;

use Fatkulnurk\Torrent\Data\ServerStatus;
use Fatkulnurk\Torrent\Data\Torrent;
use Fatkulnurk\Torrent\Exceptions\RequestException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use JsonException;
use Override;

class TransmissionProvider extends AbstractProvider
{
    private ?string $sessionId = null;

    private bool $hasRetried = false;

    public function __construct(
        string $baseUrl,
        array $config = []
    ) {
        if (isset($config['username']) && isset($config['password'])) {
            $config['auth'] = [
                $config['username'],
                $config['password'],
            ];
        }

        parent::__construct($baseUrl, $config);
    }

    protected function initialize(): void {}

    #[Override]
    protected function request(string $method, string $endpoint, array $options = []): mixed
    {
        $headers = $options['headers'] ?? [];

        if ($this->sessionId !== null) {
            $headers['X-Transmission-Session-Id'] = $this->sessionId;
        }

        $payload = [
            'method' => $method,
            'arguments' => $options['args'] ?? [],
        ];

        try {
            $response = parent::request('POST', $endpoint, [
                'headers' => $headers,
                'json' => $payload,
            ]);

            if (is_string($response)) {
                $response = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            }

            if (isset($response['result']) && $response['result'] !== 'success') {
                throw new RequestException(
                    "Transmission API error: {$response['result']}"
                );
            }

            if (isset($response['arguments'])) {
                return $response['arguments'];
            }

            return $response;
        } catch (JsonException $e) {
            throw new RequestException(
                "Failed to parse Transmission response: {$e->getMessage()}",
                0,
                $e
            );
        } catch (RequestException $e) {
            if ($e->getCode() === 409 && !$this->hasRetried) {
                $this->hasRetried = true;

                $previous = $e->getPrevious();
                if ($previous instanceof GuzzleRequestException) {
                    $response = $previous->getResponse();
                    $newSessionId = $response->getHeaderLine(
                        'X-Transmission-Session-Id'
                    );

                    if ($newSessionId !== '') {
                        $this->sessionId = $newSessionId;

                        return $this->request($method, $endpoint, $options);
                    }
                }
            }

            throw $e;
        }
    }

    #[Override]
    public function addTorrent(string $source, array $options = []): bool
    {
        $args = [];

        if (preg_match('/^magnet:\?xt=urn:btih:/i', $source)) {
            $args['filename'] = $source;
        } elseif (is_file($source)) {
            $args['metainfo'] = base64_encode(
                file_get_contents($source)
            );
        } else {
            $decoded = base64_decode($source, true);

            if ($decoded === false) {
                throw new RequestException('Invalid base64 encoded torrent data');
            }

            $args['metainfo'] = $decoded;
        }

        if (isset($options['savepath'])) {
            $args['download-dir'] = $options['savepath'];
        }

        unset($options['savepath']);

        $result = $this->request('torrent-add', 'transmission/rpc', [
            'args' => array_merge($options, $args),
        ]);

        return isset($result['torrent-added']) || isset($result['torrent-duplicate']);
    }

    #[Override]
    public function getTorrents(array $filters = []): array
    {
        $fields = $filters['fields'] ?? [
            'id',
            'name',
            'hashString',
            'status',
            'totalSize',
            'leftUntilDone',
            'downloadDir',
            'percentDone',
        ];

        $args = [
            'fields' => $fields,
        ];

        if (isset($filters['ids'])) {
            $args['ids'] = $filters['ids'];
        }

        $result = $this->request('torrent-get', 'transmission/rpc', [
            'args' => $args,
        ]);

        if (!is_array($result) || !isset($result['torrents'])) {
            return [];
        }

        return Torrent::collection($result['torrents']);
    }

    #[Override]
    public function getTorrent(string $hash): Torrent
    {
        $result = $this->request('torrent-get', 'transmission/rpc', [
            'args' => [
                'fields' => [
                    'id',
                    'name',
                    'hashString',
                    'status',
                    'totalSize',
                    'leftUntilDone',
                    'downloadDir',
                    'percentDone',
                ],
                'ids' => [$hash],
            ],
        ]);

        if (!is_array($result) || !isset($result['torrents']) || empty($result['torrents'])) {
            throw new RequestException("Torrent with hash {$hash} not found");
        }

        return Torrent::fromArray($result['torrents'][0]);
    }

    #[Override]
    public function pauseTorrent(string $hash): bool
    {
        $this->request('torrent-stop', 'transmission/rpc', [
            'args' => ['ids' => [$hash]],
        ]);

        return true;
    }

    #[Override]
    public function resumeTorrent(string $hash): bool
    {
        $this->request('torrent-start', 'transmission/rpc', [
            'args' => ['ids' => [$hash]],
        ]);

        return true;
    }

    #[Override]
    public function removeTorrent(string $hash, bool $deleteFiles = false): bool
    {
        $this->request('torrent-remove', 'transmission/rpc', [
            'args' => [
                'ids' => [$hash],
                'delete-local-data' => $deleteFiles,
            ],
        ]);

        return true;
    }

    #[Override]
    public function setDownloadPath(string $hash, string $path): bool
    {
        $this->request('torrent-set', 'transmission/rpc', [
            'args' => [
                'ids' => [$hash],
                'download-dir' => $path,
            ],
        ]);

        return true;
    }

    #[Override]
    public function getServerStatus(): ServerStatus
    {
        $data = $this->request('session-get', 'transmission/rpc');

        if (!is_array($data)) {
            return new ServerStatus();
        }

        return ServerStatus::fromArray($data);
    }
}
