<?php

declare(strict_types=1);

namespace OCA\CareForms\Service;

final class SubmissionDataValidator
{
    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $data
     * @return list<string>
     */
    public function validate(array $definition, array $data): array
    {
        $errors = [];
        foreach (($definition['sections'] ?? []) as $section) {
            if (!is_array($section) || !$this->logicMatches($section['logic']['showWhen'] ?? null, $data)) {
                continue;
            }
            foreach (($section['fields'] ?? []) as $field) {
                if (!is_array($field) || !$this->logicMatches($field['logic']['showWhen'] ?? null, $data)) {
                    continue;
                }
                if (($field['type'] ?? null) === 'signature') {
                    continue;
                }
                $required = ($field['required'] ?? false) === true
                    || (isset($field['logic']['requiredWhen'])
                        && $this->evaluateRule($field['logic']['requiredWhen'], $data));
                $fieldId = $field['id'] ?? null;
                if ($required && is_string($fieldId) && $this->isRequiredValueEmpty($data[$fieldId] ?? null, (string)($field['type'] ?? ''))) {
                    $errors[] = sprintf('%s is required.', $field['label'] ?? $fieldId);
                }
            }
        }
        return $errors;
    }

    /** @param array<string, mixed> $data */
    private function logicMatches(mixed $rule, array $data): bool
    {
        return !is_array($rule) || $this->evaluateRule($rule, $data);
    }

    /** @param array<string, mixed> $data */
    private function evaluateRule(mixed $rule, array $data): bool
    {
        if (!is_array($rule)) return false;
        $actual = $data[$rule['field'] ?? ''] ?? null;
        $operator = $rule['operator'] ?? null;
        $expected = $rule['value'] ?? null;
        return match ($operator) {
            'equals' => $actual === $expected,
            'notEquals' => $actual !== $expected,
            'isEmpty' => $this->isEmpty($actual),
            'isNotEmpty' => !$this->isEmpty($actual),
            'contains' => is_array($actual)
                ? in_array($expected, $actual, true)
                : (is_string($actual) && str_contains($actual, (string)$expected)),
            default => false,
        };
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null
            || (is_string($value) && trim($value) === '')
            || (is_array($value) && $value === []);
    }

    private function isRequiredValueEmpty(mixed $value, string $fieldType): bool
    {
        if ($fieldType === 'checkbox' && $value === false) return true;
        return $this->isEmpty($value);
    }
}
