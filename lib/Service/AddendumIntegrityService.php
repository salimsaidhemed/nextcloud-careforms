<?php

declare(strict_types=1);

namespace OCA\CareForms\Service;

final class AddendumIntegrityService
{
    /** @param array<string, mixed> $material */
    public function hash(array $material): string
    {
        ksort($material, SORT_STRING);
        return hash('sha256', json_encode($material, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $material */
    public function verify(array $material, ?string $storedHash): bool
    {
        return is_string($storedHash) && $storedHash !== '' && hash_equals($storedHash, $this->hash($material));
    }
}
