<?php

declare(strict_types=1);

namespace OCA\CareForms\Service;

final class FormSchemaValidator
{
    public const DEFAULT_SCHEMA_VERSION = 1;
    public const SUPPORTED_SCHEMA_VERSIONS = [1];

    public const FIELD_TYPES = [
        'text',
        'number',
        'date',
        'time',
        'textarea',
        'checkbox',
        'checkbox-group',
        'choice-group',
        'signature',
    ];

    public const FIELD_WIDTHS = ['full', 'half', 'third'];

    public const LOGIC_VISIBILITY_OPERATORS = [
        'equals',
        'notEquals',
        'isEmpty',
        'isNotEmpty',
        'contains',
    ];

    public const FIELD_SOURCES = [
        'manual',
        'patient.name',
        'patient.mr_number',
        'patient.date_of_birth',
        'current_user.display_name',
        'system.current_date',
    ];

    /**
     * @return list<string>
     */
    public function validate(array $definition): array
    {
        $errors = [];

        $schemaVersion = $definition['schemaVersion'] ?? null;
        if (!is_int($schemaVersion)) {
            $errors[] = 'schemaVersion must be an integer.';
        } elseif (!in_array($schemaVersion, self::SUPPORTED_SCHEMA_VERSIONS, true)) {
            $errors[] = sprintf(
                'Unsupported CareForms schemaVersion %d. Supported versions: %s.',
                $schemaVersion,
                implode(', ', self::SUPPORTED_SCHEMA_VERSIONS),
            );
        }

        $this->validateIdentifier($definition['id'] ?? null, 'Form id', '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $errors);
        $this->validateRequiredString($definition['name'] ?? null, 'Form name', 255, $errors);
        $this->validateRequiredString($definition['category'] ?? null, 'Form category', 128, $errors);

        $version = $definition['version'] ?? null;
        if (!is_int($version) || $version < 1) {
            $errors[] = 'Form version must be an integer greater than or equal to 1.';
        }

        if (array_key_exists('description', $definition) && !is_string($definition['description'])) {
            $errors[] = 'Form description must be a string.';
        }

        if (array_key_exists('tags', $definition)) {
            $this->validateStringList($definition['tags'], 'Form tags', $errors);
        }

        $sections = $definition['sections'] ?? null;
        if (!is_array($sections) || $sections === []) {
            $errors[] = 'Form sections must be a non-empty array.';
            return $errors;
        }

        $sectionIds = [];
        $fieldIds = [];

        foreach ($sections as $sectionIndex => $section) {
            $sectionPath = sprintf('sections[%d]', $sectionIndex);

            if (!is_array($section)) {
                $errors[] = $sectionPath . ' must be an object.';
                continue;
            }

            $sectionId = $section['id'] ?? null;
            $this->validateIdentifier($sectionId, $sectionPath . '.id', '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $errors);

            if (is_string($sectionId) && $sectionId !== '') {
                if (isset($sectionIds[$sectionId])) {
                    $errors[] = sprintf('Duplicate section id "%s".', $sectionId);
                }
                $sectionIds[$sectionId] = true;
            }

            $this->validateRequiredString($section['label'] ?? null, $sectionPath . '.label', 255, $errors);

            if (array_key_exists('description', $section) && !is_string($section['description'])) {
                $errors[] = $sectionPath . '.description must be a string.';
            }

            if (array_key_exists('collapsible', $section) && !is_bool($section['collapsible'])) {
                $errors[] = $sectionPath . '.collapsible must be a boolean.';
            }

            if (array_key_exists('logic', $section)) {
                $this->validateLogic($section['logic'], $sectionPath . '.logic', $errors);
            }

            $fields = $section['fields'] ?? null;
            if (!is_array($fields) || $fields === []) {
                $errors[] = $sectionPath . '.fields must be a non-empty array.';
                continue;
            }

            foreach ($fields as $fieldIndex => $field) {
                $fieldPath = sprintf('%s.fields[%d]', $sectionPath, $fieldIndex);

                if (!is_array($field)) {
                    $errors[] = $fieldPath . ' must be an object.';
                    continue;
                }

                $fieldId = $field['id'] ?? null;
                $this->validateIdentifier($fieldId, $fieldPath . '.id', '/^[a-z][a-z0-9_]*$/', $errors);

                if (is_string($fieldId) && $fieldId !== '') {
                    if (isset($fieldIds[$fieldId])) {
                        $errors[] = sprintf('Duplicate field id "%s". Field IDs must be unique across the form.', $fieldId);
                    }
                    $fieldIds[$fieldId] = true;
                }

                $this->validateRequiredString($field['label'] ?? null, $fieldPath . '.label', 255, $errors);

                $type = $field['type'] ?? null;
                if (!is_string($type) || !in_array($type, self::FIELD_TYPES, true)) {
                    $errors[] = sprintf(
                        '%s.type must be one of: %s.',
                        $fieldPath,
                        implode(', ', self::FIELD_TYPES),
                    );
                    continue;
                }

                if (array_key_exists('logic', $field)) {
                    $this->validateLogic($field['logic'], $fieldPath . '.logic', $errors);
                }

                if (array_key_exists('required', $field) && !is_bool($field['required'])) {
                    $errors[] = $fieldPath . '.required must be a boolean.';
                }

                if (array_key_exists('readOnly', $field) && !is_bool($field['readOnly'])) {
                    $errors[] = $fieldPath . '.readOnly must be a boolean.';
                }

                if (array_key_exists('helpText', $field) && !is_string($field['helpText'])) {
                    $errors[] = $fieldPath . '.helpText must be a string.';
                }

                if (array_key_exists('placeholder', $field) && !is_string($field['placeholder'])) {
                    $errors[] = $fieldPath . '.placeholder must be a string.';
                }

                if (array_key_exists('unit', $field) && !is_string($field['unit'])) {
                    $errors[] = $fieldPath . '.unit must be a string.';
                }

                if (array_key_exists('width', $field)
                    && (!is_string($field['width']) || !in_array($field['width'], self::FIELD_WIDTHS, true))) {
                    $errors[] = sprintf('%s.width must be one of: %s.', $fieldPath, implode(', ', self::FIELD_WIDTHS));
                }

                if (array_key_exists('source', $field)
                    && (!is_string($field['source']) || !in_array($field['source'], self::FIELD_SOURCES, true))) {
                    $errors[] = sprintf('%s.source must be one of: %s.', $fieldPath, implode(', ', self::FIELD_SOURCES));
                }

                $hasOptions = array_key_exists('options', $field);
                if (in_array($type, ['checkbox-group', 'choice-group'], true)) {
                    if (!$hasOptions) {
                        $errors[] = $fieldPath . '.options is required for ' . $type . '.';
                    } else {
                        $this->validateStringList($field['options'], $fieldPath . '.options', $errors);
                    }
                } elseif ($hasOptions) {
                    $errors[] = $fieldPath . '.options is only valid for checkbox-group and choice-group.';
                }

                if ($type === 'number') {
                    $min = $field['min'] ?? null;
                    $max = $field['max'] ?? null;

                    if (array_key_exists('min', $field) && !is_int($min) && !is_float($min)) {
                        $errors[] = $fieldPath . '.min must be numeric.';
                    }
                    if (array_key_exists('max', $field) && !is_int($max) && !is_float($max)) {
                        $errors[] = $fieldPath . '.max must be numeric.';
                    }
                    if ((is_int($min) || is_float($min))
                        && (is_int($max) || is_float($max))
                        && $min > $max) {
                        $errors[] = $fieldPath . '.min must not be greater than max.';
                    }
                } elseif (array_key_exists('min', $field) || array_key_exists('max', $field)) {
                    $errors[] = $fieldPath . '.min/max are only valid for number fields.';
                }

                if ($type === 'textarea') {
                    if (array_key_exists('rows', $field)
                        && (!is_int($field['rows']) || $field['rows'] < 1 || $field['rows'] > 50)) {
                        $errors[] = $fieldPath . '.rows must be an integer between 1 and 50.';
                    }
                } elseif (array_key_exists('rows', $field)) {
                    $errors[] = $fieldPath . '.rows is only valid for textarea fields.';
                }

                if (array_key_exists('defaultValue', $field)) {
                    $this->validateDefaultValue($field['defaultValue'], $type, $fieldPath . '.defaultValue', $field['options'] ?? [], $errors);
                }
            }
        }

        // Logic references are validated after every field ID is known, so a rule
        // may safely reference a field declared later in the definition.
        foreach ($sections as $sectionIndex => $section) {
            if (!is_array($section)) {
                continue;
            }
            $sectionPath = sprintf('sections[%d]', $sectionIndex);
            $this->validateLogicReferences($section['logic'] ?? null, $sectionPath . '.logic', $fieldIds, $errors);
            foreach (($section['fields'] ?? []) as $fieldIndex => $field) {
                if (!is_array($field)) {
                    continue;
                }
                $this->validateLogicReferences(
                    $field['logic'] ?? null,
                    sprintf('%s.fields[%d].logic', $sectionPath, $fieldIndex),
                    $fieldIds,
                    $errors,
                );
            }
        }

        return $errors;
    }

    /** @param list<string> $errors */
    private function validateLogic(mixed $logic, string $path, array &$errors): void
    {
        if (!is_array($logic)) {
            $errors[] = $path . ' must be an object.';
            return;
        }
        if (!array_key_exists('showWhen', $logic)) {
            $errors[] = $path . '.showWhen is required.';
            return;
        }
        $rule = $logic['showWhen'];
        if (!is_array($rule)) {
            $errors[] = $path . '.showWhen must be an object.';
            return;
        }
        $field = $rule['field'] ?? null;
        if (!is_string($field) || preg_match('/^[a-z][a-z0-9_]*$/', $field) !== 1) {
            $errors[] = $path . '.showWhen.field must be a valid field ID.';
        }
        $operator = $rule['operator'] ?? null;
        if (!is_string($operator) || !in_array($operator, self::LOGIC_VISIBILITY_OPERATORS, true)) {
            $errors[] = sprintf('%s.showWhen.operator must be one of: %s.', $path, implode(', ', self::LOGIC_VISIBILITY_OPERATORS));
            return;
        }
        if (in_array($operator, ['equals', 'notEquals', 'contains'], true) && !array_key_exists('value', $rule)) {
            $errors[] = $path . '.showWhen.value is required for operator ' . $operator . '.';
        }
        if (in_array($operator, ['isEmpty', 'isNotEmpty'], true) && array_key_exists('value', $rule)) {
            $errors[] = $path . '.showWhen.value is not valid for operator ' . $operator . '.';
        }
    }

    /**
     * @param array<string, bool> $fieldIds
     * @param list<string> $errors
     */
    private function validateLogicReferences(mixed $logic, string $path, array $fieldIds, array &$errors): void
    {
        if (!is_array($logic) || !is_array($logic['showWhen'] ?? null)) {
            return;
        }
        $field = $logic['showWhen']['field'] ?? null;
        if (is_string($field) && preg_match('/^[a-z][a-z0-9_]*$/', $field) === 1 && !isset($fieldIds[$field])) {
            $errors[] = sprintf('%s.showWhen.field references unknown field ID "%s".', $path, $field);
        }
    }

    public function assertValid(array $definition): void
    {
        $errors = $this->validate($definition);

        if ($errors !== []) {
            throw new \InvalidArgumentException(
                "Invalid CareForms form definition:\n- " . implode("\n- ", $errors),
            );
        }
    }

    /**
     * @param list<string> $errors
     */
    private function validateIdentifier(mixed $value, string $label, string $pattern, array &$errors): void
    {
        if (!is_string($value) || $value === '' || preg_match($pattern, $value) !== 1) {
            $errors[] = $label . ' has an invalid identifier format.';
        }
    }

    /**
     * @param list<string> $errors
     */
    private function validateRequiredString(mixed $value, string $label, int $maxLength, array &$errors): void
    {
        if (!is_string($value) || trim($value) === '') {
            $errors[] = $label . ' must be a non-empty string.';
            return;
        }

        if (mb_strlen($value) > $maxLength) {
            $errors[] = sprintf('%s must not exceed %d characters.', $label, $maxLength);
        }
    }

    /**
     * @param list<string> $errors
     */
    private function validateStringList(mixed $value, string $label, array &$errors): void
    {
        if (!is_array($value) || $value === []) {
            $errors[] = $label . ' must be a non-empty array.';
            return;
        }

        $seen = [];
        foreach ($value as $index => $item) {
            if (!is_string($item) || trim($item) === '') {
                $errors[] = sprintf('%s[%d] must be a non-empty string.', $label, $index);
                continue;
            }

            if (isset($seen[$item])) {
                $errors[] = sprintf('%s contains duplicate value "%s".', $label, $item);
            }
            $seen[$item] = true;
        }
    }

    /**
     * @param list<string> $errors
     */
    private function validateDefaultValue(mixed $value, string $type, string $label, array $options, array &$errors): void
    {
        switch ($type) {
            case 'number':
                if (!is_int($value) && !is_float($value)) {
                    $errors[] = $label . ' must be numeric.';
                }
                break;
            case 'checkbox':
                if (!is_bool($value)) {
                    $errors[] = $label . ' must be a boolean.';
                }
                break;
            case 'checkbox-group':
                if (!is_array($value)) {
                    $errors[] = $label . ' must be an array.';
                    break;
                }
                foreach ($value as $item) {
                    if (!is_string($item) || !in_array($item, $options, true)) {
                        $errors[] = $label . ' contains a value not present in options.';
                        break;
                    }
                }
                break;
            case 'choice-group':
                if (!is_string($value) || !in_array($value, $options, true)) {
                    $errors[] = $label . ' must match one of the configured options.';
                }
                break;
            case 'signature':
                $errors[] = $label . ' is not supported for signature fields.';
                break;
            default:
                if (!is_string($value)) {
                    $errors[] = $label . ' must be a string.';
                }
                break;
        }
    }
}
