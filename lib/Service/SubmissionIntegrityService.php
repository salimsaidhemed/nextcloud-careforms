<?php

declare(strict_types=1);

namespace OCA\CareForms\Service;

final class SubmissionIntegrityService
{
    public const VERSION = 2;

    /** @param array<string, mixed> $definition */
    public function definitionHash(array $definition): string
    {
        return hash('sha256', $this->canonicalJson($definition));
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $record
     * @return array{integrityVersion: int, definitionHash: string, integrityHash: string}
     */
    public function create(array $definition, array $record): array
    {
        $definitionHash = $this->definitionHash($definition);
        return [
            'integrityVersion' => self::VERSION,
            'definitionHash' => $definitionHash,
            'integrityHash' => hash('sha256', $this->v2Material($record, $definitionHash)),
        ];
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $record
     * @return array{status: string, valid: bool|null, version: int|null, message: string}
     */
    public function verify(array $definition, array $record): array
    {
        $storedHash = $record['integrityHash'] ?? null;
        if (!is_string($storedHash) || $storedHash === '') {
            return ['status' => 'unsigned', 'valid' => null, 'version' => null, 'message' => 'This record has not been electronically signed.'];
        }

        if (($record['integrityVersion'] ?? null) === self::VERSION) {
            $storedDefinitionHash = $record['definitionHash'] ?? null;
            $actualDefinitionHash = $this->definitionHash($definition);
            if (!is_string($storedDefinitionHash) || !hash_equals($storedDefinitionHash, $actualDefinitionHash)) {
                return ['status' => 'invalid', 'valid' => false, 'version' => self::VERSION, 'message' => 'The captured form definition no longer matches this signed submission.'];
            }
            $expected = hash('sha256', $this->v2Material($record, $storedDefinitionHash));
            if (!hash_equals($storedHash, $expected)) {
                return ['status' => 'invalid', 'valid' => false, 'version' => self::VERSION, 'message' => 'Signed submission content or signature verification failed.'];
            }
            return ['status' => 'valid', 'valid' => true, 'version' => self::VERSION, 'message' => 'Signature, submission data, and form version verified.'];
        }

        $expected = hash('sha256', implode('|', [
            (string)($record['formId'] ?? ''),
            (string)($record['formVersion'] ?? ''),
            (string)($record['patientId'] ?? ''),
            (string)($record['data'] ?? '{}'),
            (string)($record['signedBy'] ?? ''),
            (string)($record['signedAt'] ?? ''),
        ]));
        if (hash_equals($storedHash, $expected)) {
            return ['status' => 'legacy-valid', 'valid' => true, 'version' => 1, 'message' => 'Legacy signature verified. Its original format did not bind the signature image or definition hash.'];
        }
        return ['status' => 'invalid', 'valid' => false, 'version' => 1, 'message' => 'Legacy signed submission verification failed.'];
    }

    /** @param array<string, mixed> $record */
    private function v2Material(array $record, string $definitionHash): string
    {
        return $this->canonicalJson([
            'integrityVersion' => self::VERSION,
            'formId' => (string)($record['formId'] ?? ''),
            'formVersion' => (string)($record['formVersion'] ?? ''),
            'definitionHash' => $definitionHash,
            'patientId' => $record['patientId'] ?? null,
            'data' => (string)($record['data'] ?? '{}'),
            'signedBy' => (string)($record['signedBy'] ?? ''),
            'signerName' => (string)($record['signerName'] ?? ''),
            'signedAt' => $record['signedAt'] ?? null,
            'signatureData' => (string)($record['signatureData'] ?? ''),
        ]);
    }

    private function canonicalJson(mixed $value): string
    {
        return json_encode($this->canonicalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        if (array_is_list($value)) return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) $value[$key] = $this->canonicalize($item);
        return $value;
    }
}
