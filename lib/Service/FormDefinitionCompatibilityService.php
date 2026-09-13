<?php

declare(strict_types=1);

namespace OCA\CareForms\Service;

final class FormDefinitionCompatibilityService
{
    /**
     * Normalize a legacy CareForms definition into the canonical schema v1 shape.
     *
     * Canonical definitions are returned unchanged.
     *
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    public function normalize(array $definition): array
    {
        if (isset($definition['sections']) && is_array($definition['sections'])) {
            return $definition;
        }

        $legacyFields = $definition['fields'] ?? null;
        if (!is_array($legacyFields)) {
            return $definition;
        }

        $sections = [];

        foreach ($legacyFields as $section) {
            if (!is_array($section)) {
                continue;
            }

            $normalizedSection = [
                'id' => $section['id'] ?? '',
                'label' => $section['label'] ?? '',
                'fields' => [],
            ];

            if (isset($section['description']) && is_string($section['description'])) {
                $normalizedSection['description'] = $section['description'];
            }

            foreach (($section['fields'] ?? []) as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $normalizedField = $field;

                if (($normalizedField['type'] ?? null) === 'signature-placeholder') {
                    $normalizedField['type'] = 'signature';
                }

                $normalizedSection['fields'][] = $normalizedField;
            }

            $sections[] = $normalizedSection;
        }

        $normalized = $definition;
        unset($normalized['fields']);

        $normalized['schemaVersion'] = isset($normalized['schemaVersion'])
            && is_int($normalized['schemaVersion'])
            ? $normalized['schemaVersion']
            : FormSchemaValidator::DEFAULT_SCHEMA_VERSION;

        $normalized['sections'] = $sections;

        return $normalized;
    }

    /**
     * Normalize historical submission values without deleting unknown fields.
     *
     * Existing submissions created before strict type normalization can contain
     * numeric values as strings. The compatibility layer preserves every stored
     * key and only normalizes values when the target field type is unambiguous.
     *
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function normalizeSubmissionData(array $definition, array $data): array
    {
        $fields = [];

        foreach (($definition['sections'] ?? []) as $section) {
            if (!is_array($section)) {
                continue;
            }

            foreach (($section['fields'] ?? []) as $field) {
                if (is_array($field) && isset($field['id'], $field['type'])) {
                    $fields[(string)$field['id']] = (string)$field['type'];
                }
            }
        }

        foreach ($data as $fieldId => $value) {
            $type = $fields[$fieldId] ?? null;

            if ($type === 'number' && is_string($value) && $value !== '' && is_numeric($value)) {
                $data[$fieldId] = str_contains($value, '.')
                    ? (float)$value
                    : (int)$value;
            }

            if ($type === 'checkbox' && ($value === 0 || $value === 1 || $value === '0' || $value === '1')) {
                $data[$fieldId] = (bool)$value;
            }
        }

        return $data;
    }
}
