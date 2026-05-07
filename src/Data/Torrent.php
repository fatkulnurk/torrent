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
            hash: $data['hash'] ?? $data['hashString'] ?? '',
            name: $data['name'] ?? '',
            status: $data['status'] ?? 0,
            totalSize: $data['totalSize'] ?? 0,
            leftUntilDone: $data['leftUntilDone'] ?? 0,
            downloadDir: $data['downloadDir'] ?? '',
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
