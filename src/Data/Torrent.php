<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Data;

readonly class Torrent
{
    public function __construct(
        public string $hash = '',
        public string $name = '',
        public int $status = 0,
        public int $totalSize = 0,
        public int $leftUntilDone = 0,
        public string $downloadDir = '',
        public float $percentDone = 0.0,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            hash: (string) ($data['hash'] ?? $data['hashString'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            status: (int) ($data['status'] ?? 0),
            totalSize: (int) ($data['totalSize'] ?? 0),
            leftUntilDone: (int) ($data['leftUntilDone'] ?? 0),
            downloadDir: (string) ($data['downloadDir'] ?? ''),
            percentDone: (float) ($data['percentDone'] ?? 0.0),
        );
    }

    /**
     * @return self[]
     */
    public static function collection(array $data): array
    {
        return array_map(
            fn(array $item): self => self::fromArray($item),
            $data
        );
    }
}
