<?php

declare(strict_types=1);

namespace OCA\CareForms\Service;

final class SubmissionDataValidator
{
    private CalculationEngine $calculationEngine;

    public function __construct(?CalculationEngine $calculationEngine = null)
    {
        $this->calculationEngine = $calculationEngine ?? new CalculationEngine();
    }

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
                if (!is_string($fieldId)) continue;
                $value = $data[$fieldId] ?? null;
                $calculation = $field['calculation'] ?? null;
                if (is_array($calculation) && is_string($calculation['expression'] ?? null)) {
                    $result = $this->calculationEngine->evaluate(
                        $calculation['expression'],
                        $data,
                        isset($calculation['precision']) && is_int($calculation['precision']) ? $calculation['precision'] : null,
                    );
                    if ($result['status'] === 'error') {
                        $errors[] = sprintf('%s could not be calculated: %s', $field['label'] ?? $fieldId, $result['error'] ?? 'Invalid calculation.');
                        continue;
                    }
                    if ($result['status'] === 'empty') {
                        if (!$this->isEmpty($value)) {
                            $errors[] = sprintf('%s does not match its calculated value.', $field['label'] ?? $fieldId);
                        }
                    } elseif ((!is_int($value) && !is_float($value))
                        || abs((float)$value - (float)$result['value']) > 1.0e-9) {
                        $errors[] = sprintf('%s does not match its calculated value.', $field['label'] ?? $fieldId);
                    }
                }
                if ($required && $this->isRequiredValueEmpty($value, (string)($field['type'] ?? ''))) {
                    $errors[] = sprintf('%s is required.', $field['label'] ?? $fieldId);
                    continue;
                }
                if (!$this->isEmpty($value)) {
                    array_push($errors, ...$this->validateConstraints($field, $value));
                    $comparison = $field['validation']['compare'] ?? null;
                    if (is_array($comparison)) {
                        $sourceValue = $data[$comparison['field'] ?? ''] ?? null;
                        if (!$this->isEmpty($sourceValue)
                            && !$this->evaluateComparison((string)($comparison['operator'] ?? ''), $value, $sourceValue)) {
                            $errors[] = (string)($comparison['message'] ?? sprintf('%s does not match the related field.', $field['label'] ?? $fieldId));
                        }
                    }
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

    /**
     * @param array<string, mixed> $field
     * @return list<string>
     */
    private function validateConstraints(array $field, mixed $value): array
    {
        $label = (string)($field['label'] ?? $field['id'] ?? 'Field');
        $type = (string)($field['type'] ?? '');
        $errors = [];

        if (in_array($type, ['text', 'textarea'], true)) {
            if (!is_string($value)) return [sprintf('%s must be text.', $label)];
            $length = mb_strlen($value);
            if (isset($field['minLength']) && $length < (int)$field['minLength']) {
                $errors[] = sprintf('%s must contain at least %d characters.', $label, (int)$field['minLength']);
            }
            if (isset($field['maxLength']) && $length > (int)$field['maxLength']) {
                $errors[] = sprintf('%s must not exceed %d characters.', $label, (int)$field['maxLength']);
            }
            if (isset($field['pattern']) && @preg_match($this->patternExpression((string)$field['pattern']), $value) !== 1) {
                $errors[] = (string)($field['validationMessage'] ?? sprintf('%s has an invalid format.', $label));
            }
        }

        if ($type === 'number') {
            if (!is_int($value) && !is_float($value)) return [sprintf('%s must be a number.', $label)];
            $numeric = (float)$value;
            if (isset($field['min']) && $numeric < (float)$field['min']) {
                $errors[] = sprintf('%s must be at least %s.', $label, $this->numberLabel($field['min']));
            }
            if (isset($field['max']) && $numeric > (float)$field['max']) {
                $errors[] = sprintf('%s must not exceed %s.', $label, $this->numberLabel($field['max']));
            }
            if (isset($field['step'])) {
                $step = (float)$field['step'];
                $base = isset($field['min']) ? (float)$field['min'] : 0.0;
                $steps = ($numeric - $base) / $step;
                if (abs($steps - round($steps)) > 1.0e-9) {
                    $errors[] = sprintf('%s must use increments of %s.', $label, $this->numberLabel($field['step']));
                }
            }
        }

        return $errors;
    }

    private function evaluateComparison(string $operator, mixed $actual, mixed $expected): bool
    {
        return match ($operator) {
            'equals' => $actual === $expected,
            'notEquals' => $actual !== $expected,
            'greaterThan' => $actual > $expected,
            'greaterThanOrEqual' => $actual >= $expected,
            'lessThan' => $actual < $expected,
            'lessThanOrEqual' => $actual <= $expected,
            default => false,
        };
    }

    private function patternExpression(string $pattern): string
    {
        foreach (['~', '#', '%', '!', ';', '@', '`', '/', ',', '=', ':', '_', '+', '-'] as $delimiter) {
            if (!str_contains($pattern, $delimiter)) {
                return $delimiter . '^(?:' . $pattern . ')$' . $delimiter . 'uD';
            }
        }
        return "\x1F^(?:" . $pattern . ")$\x1FuD";
    }

    private function numberLabel(mixed $value): string
    {
        $label = (string)$value;
        return str_contains($label, '.') ? rtrim(rtrim($label, '0'), '.') : $label;
    }
}
