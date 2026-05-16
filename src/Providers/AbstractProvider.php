<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Providers;

use Fatkulnurk\Torrent\Contracts\TorrentClientInterface;
use Fatkulnurk\Torrent\Data\ServerStatus;
use Fatkulnurk\Torrent\Data\Torrent;
use Fatkulnurk\Torrent\Exceptions\RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;

abstract class AbstractProvider implements TorrentClientInterface
{
    protected Client $client;

    protected array $config;

    public function __construct(
        protected string $baseUrl,
        array $config = []
    ) {
        $this->config = array_merge(
            [
                'timeout' => 10.0,
                'verify_ssl' => true,
            ],
            $config
        );

        $clientOptions = [
            'base_uri' => $this->baseUrl,
            'timeout' => $this->config['timeout'],
            'verify' => $this->config['verify_ssl'],
        ];

        if (isset($this->config['auth'])) {
            $clientOptions['auth'] = $this->config['auth'];
        }

        $this->client = new Client($clientOptions);

        $this->initialize();
    }

    protected function initialize(): void {}

    protected function request(string $method, string $endpoint, array $options = []): mixed
    {
        try {
            $response = $this->client->request($method, $endpoint, $options);
            $body = (string) $response->getBody();
            $contentType = $response->getHeaderLine('Content-Type');

            return match (true) {
                str_contains($contentType, 'application/json') => json_decode(
                    $body,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                ),
                default => $body,
            };
        } catch (JsonException $e) {
            throw new RequestException(
                "Failed to parse JSON response: {$e->getMessage()}",
                0,
                $e
            );
        } catch (GuzzleException $e) {
            throw new RequestException(
                "HTTP request failed: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    public function addTorrent(string $source, array $options = []): bool
    {
        throw new RequestException('addTorrent not implemented in provider');
    }

    public function getTorrents(array $filters = []): array
    {
        throw new RequestException('getTorrents not implemented in provider');
    }

    public function getTorrent(string $hash): Torrent
    {
        throw new RequestException('getTorrent not implemented in provider');
    }

    public function pauseTorrent(string $hash): bool
    {
        throw new RequestException('pauseTorrent not implemented in provider');
    }

    public function resumeTorrent(string $hash): bool
    {
        throw new RequestException('resumeTorrent not implemented in provider');
    }

    public function removeTorrent(string $hash, bool $deleteFiles = false): bool
    {
        throw new RequestException('removeTorrent not implemented in provider');
    }

    public function setDownloadPath(string $hash, string $path): bool
    {
        throw new RequestException('setDownloadPath not implemented in provider');
    }

    public function getServerStatus(): ServerStatus
    {
        throw new RequestException('getServerStatus not implemented in provider');
    }
}
