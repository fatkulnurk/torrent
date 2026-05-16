<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Providers;

use Fatkulnurk\Torrent\Data\ServerStatus;
use Fatkulnurk\Torrent\Data\Torrent;
use Fatkulnurk\Torrent\Exceptions\RequestException;
use Override;
use SimpleXMLElement;

class RTorrentProvider extends AbstractProvider
{
    private string $rpcEndpoint = 'RPC2';

    protected function initialize(): void
    {
        $this->rpcEndpoint = $this->config['rpc_endpoint'] ?? 'RPC2';
    }

    private function xmlRpc(string $method, array $params = []): mixed
    {
        $xml = $this->buildRequest($method, $params);

        $response = parent::request('POST', $this->rpcEndpoint, [
            'headers' => ['Content-Type' => 'text/xml'],
            'body' => $xml,
        ]);

        if (!is_string($response)) {
            throw new RequestException('Unexpected XML-RPC response type');
        }

        return $this->parseResponse($response);
    }

    private function buildRequest(string $method, array $params): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><methodCall><methodName>'
            . htmlspecialchars($method, ENT_XML1)
            . '</methodName><params>';

        foreach ($params as $param) {
            $xml .= '<param>' . $this->buildValue($param) . '</param>';
        }

        $xml .= '</params></methodCall>';

        return $xml;
    }

    private function buildValue(mixed $value): string
    {
        return match (true) {
            is_int($value) => '<value><int>' . $value . '</int></value>',
            is_float($value) => '<value><double>' . $value . '</double></value>',
            is_bool($value) => '<value><boolean>' . ($value ? '1' : '0') . '</boolean></value>',
            is_array($value) => '<value><array><data>'
                . implode('', array_map(fn(mixed $item): string => $this->buildValue($item), $value))
                . '</data></array></value>',
            default => '<value><string>' . htmlspecialchars((string) $value, ENT_XML1) . '</string></value>',
        };
    }

    private function parseResponse(string $xml): mixed
    {
        $response = simplexml_load_string($xml);

        if ($response === false) {
            throw new RequestException('Failed to parse XML-RPC response');
        }

        if (isset($response->fault)) {
            $faultCode = 0;
            $faultString = 'Unknown XML-RPC fault';

            foreach ($response->fault->value->struct->member as $member) {
                $name = (string) $member->name;
                if ($name === 'faultCode') {
                    $faultCode = (int) $this->extractValue($member->value);
                }
                if ($name === 'faultString') {
                    $faultString = (string) $this->extractValue($member->value);
                }
            }

            throw new RequestException("XML-RPC fault: {$faultString}", $faultCode);
        }

        if (!isset($response->params->param->value)) {
            return null;
        }

        return $this->extractValue($response->params->param->value);
    }

    private function extractValue(SimpleXMLElement $value): mixed
    {
        if (isset($value->string)) {
            return (string) $value->string;
        }
        if (isset($value->int)) {
            return (int) $value->int;
        }
        if (isset($value->i4)) {
            return (int) $value->i4;
        }
        if (isset($value->i8)) {
            return (int) (string) $value->i8;
        }
        if (isset($value->double)) {
            return (float) $value->double;
        }
        if (isset($value->boolean)) {
            return (bool) (int) $value->boolean;
        }
        if (isset($value->array)) {
            $result = [];
            foreach ($value->array->data->value as $item) {
                $result[] = $this->extractValue($item);
            }
            return $result;
        }
        if (isset($value->struct)) {
            $result = [];
            foreach ($value->struct->member as $member) {
                $result[(string) $member->name] = $this->extractValue($member->value);
            }
            return $result;
        }

        return (string) $value;
    }

    #[Override]
    public function addTorrent(string $source, array $options = []): bool
    {
        if (preg_match('/^magnet:\?xt=urn:btih:/i', $source)) {
            $this->xmlRpc('load.start', [$source]);
        } elseif (is_file($source)) {
            $data = base64_encode(file_get_contents($source));
            $this->xmlRpc('load.raw_start', [$data]);
        } else {
            $decoded = base64_decode($source, true);

            if ($decoded === false) {
                throw new RequestException('Invalid base64 encoded torrent data');
            }

            $this->xmlRpc('load.raw_start', [$decoded]);
        }

        return true;
    }

    #[Override]
    public function getTorrents(array $filters = []): array
    {
        try {
            $result = $this->xmlRpc('d.multicall2', [
                'main',
                'd.get_hash=',
                'd.get_name=',
                'd.get_state=',
                'd.get_size_bytes=',
                'd.get_left_bytes=',
                'd.get_directory=',
                'd.get_complete=',
                'd.is_open=',
                'd.is_active=',
            ]);

            if (!is_array($result)) {
                return [];
            }

            return Torrent::collection(
                array_map(fn(array $data): array => $this->torrentFieldsToArray($data), $result)
            );
        } catch (RequestException $e) {
            if (!str_contains($e->getMessage(), 'invalid target')) {
                throw $e;
            }

            return $this->getTorrentsFallback();
        }
    }

    private function getTorrentsFallback(): array
    {
        try {
            $hashes = $this->xmlRpc('download_list', []);
        } catch (RequestException) {
            return [];
        }

        if (!is_array($hashes)) {
            return [];
        }

        $torrents = [];

        foreach ($hashes as $hash) {
            try {
                $torrents[] = $this->fetchTorrentFields($hash);
            } catch (RequestException) {
            }
        }

        return Torrent::collection($torrents);
    }

    #[Override]
    public function getTorrent(string $hash): Torrent
    {
        try {
            $result = $this->xmlRpc('d.multicall2', [
                'main',
                'd.get_hash=',
                'd.get_name=',
                'd.get_state=',
                'd.get_size_bytes=',
                'd.get_left_bytes=',
                'd.get_directory=',
                'd.get_complete=',
                'd.is_open=',
                'd.is_active=',
            ]);

            if (is_array($result)) {
                foreach ($result as $item) {
                    if ($item[0] === $hash) {
                        return Torrent::fromArray($this->torrentFieldsToArray($item));
                    }
                }
            }

            throw new RequestException("Torrent with hash {$hash} not found");
        } catch (RequestException $e) {
            if (!str_contains($e->getMessage(), 'invalid target')) {
                throw $e;
            }
        }

        return Torrent::fromArray($this->fetchTorrentFields($hash));
    }

    private function fetchTorrentFields(string $hash): array
    {
        $hashResult = $this->xmlRpc('d.get_hash=', [$hash]);

        if (!is_string($hashResult) || $hashResult === '') {
            throw new RequestException("Torrent with hash {$hash} not found");
        }

        return $this->torrentFieldsToArray([
            $hashResult,
            $this->xmlRpc('d.get_name=', [$hash]),
            $this->xmlRpc('d.get_state=', [$hash]),
            $this->xmlRpc('d.get_size_bytes=', [$hash]),
            $this->xmlRpc('d.get_left_bytes=', [$hash]),
            $this->xmlRpc('d.get_directory=', [$hash]),
            $this->xmlRpc('d.get_complete=', [$hash]),
            '',
            '',
        ]);
    }

    private function torrentFieldsToArray(array $data): array
    {
        $state = (int) ($data[2] ?? 0);
        $complete = (float) ($data[6] ?? 0.0);

        $status = match (true) {
            $complete >= 1.0 => 2,
            $state === 1 => 1,
            default => 0,
        };

        return [
            'hash' => $data[0] ?? '',
            'hashString' => $data[0] ?? '',
            'name' => $data[1] ?? '',
            'status' => $status,
            'totalSize' => (int) ($data[3] ?? 0),
            'leftUntilDone' => (int) ($data[4] ?? 0),
            'downloadDir' => $data[5] ?? '',
            'percentDone' => $complete,
        ];
    }

    #[Override]
    public function pauseTorrent(string $hash): bool
    {
        $this->xmlRpc('d.stop', [$hash]);
        return true;
    }

    #[Override]
    public function resumeTorrent(string $hash): bool
    {
        $this->xmlRpc('d.start', [$hash]);
        return true;
    }

    #[Override]
    public function removeTorrent(string $hash, bool $deleteFiles = false): bool
    {
        $this->xmlRpc('d.erase', [$hash]);
        return true;
    }

    #[Override]
    public function setDownloadPath(string $hash, string $path): bool
    {
        $this->xmlRpc('d.set_directory', [$hash, $path]);
        return true;
    }

    #[Override]
    public function getServerStatus(): ServerStatus
    {
        $version = $this->xmlRpc('system.client_version');

        return new ServerStatus(
            version: is_string($version) ? $version : null,
        );
    }
}
