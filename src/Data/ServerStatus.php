<?php

declare(strict_types=1);

namespace Fatkulnurk\Torrent\Data;

readonly class ServerStatus
{
    public function __construct(
        public ?string $version = null,
        public ?int $apiVersion = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            version: $data['version'] ?? $data['coreVersion'] ?? null,
            apiVersion: $data['apiVersion'] ?? null,
        );
    }
}
