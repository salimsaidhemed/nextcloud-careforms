<?php

declare(strict_types=1);

namespace OCA\CareForms\Service;

final class FormSchemaValidator
{
    private CalculationEngine $calculationEngine;

    public function __construct(?CalculationEngine $calculationEngine = null)
    {
        $this->calculationEngine = $calculationEngine ?? new CalculationEngine();
    }

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

    public const CROSS_FIELD_EQUALITY_OPERATORS = ['equals', 'notEquals'];
    public const CROSS_FIELD_ORDERED_OPERATORS = [
        'greaterThan',
        'greaterThanOrEqual',
        'lessThan',
        'lessThanOrEqual',
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
        $fieldTypes = [];
        $fieldOptions = [];

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
                $this->validateLogic($section['logic'], $sectionPath . '.logic', false, $errors);
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
                if (is_string($fieldId) && $fieldId !== '') {
                    $fieldTypes[$fieldId] = $type;
                    $fieldOptions[$fieldId] = is_array($field['options'] ?? null) ? $field['options'] : [];
                }

                if (array_key_exists('logic', $field)) {
                    $this->validateLogic($field['logic'], $fieldPath . '.logic', true, $errors);
                }
                if (array_key_exists('validation', $field)) {
                    $this->validateFieldValidation($field['validation'], $fieldPath . '.validation', $errors);
                }
                if (array_key_exists('calculation', $field)) {
                    $this->validateCalculation($field, $fieldPath, $errors);
                }
                if (array_key_exists('optionDependency', $field)) {
                    $this->validateOptionDependency($field, $fieldPath, $errors);
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
                    $step = $field['step'] ?? null;

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
                    if (array_key_exists('step', $field)
                        && ((!is_int($step) && !is_float($step)) || $step <= 0)) {
                        $errors[] = $fieldPath . '.step must be a number greater than zero.';
                    }
                } elseif (array_key_exists('min', $field) || array_key_exists('max', $field) || array_key_exists('step', $field)) {
                    $errors[] = $fieldPath . '.min/max/step are only valid for number fields.';
                }

                $this->validateTextConstraints($field, $type, $fieldPath, $errors);

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
            $fields = $section['fields'] ?? null;
            if (!is_array($fields)) {
                continue;
            }
            foreach ($fields as $fieldIndex => $field) {
                if (!is_array($field)) {
                    continue;
                }
                $this->validateLogicReferences(
                    $field['logic'] ?? null,
                    sprintf('%s.fields[%d].logic', $sectionPath, $fieldIndex),
                    $fieldIds,
                    $errors,
                );
                $this->validateComparisonReference(
                    $field['validation'] ?? null,
                    (string)($field['id'] ?? ''),
                    (string)($field['type'] ?? ''),
                    sprintf('%s.fields[%d].validation', $sectionPath, $fieldIndex),
                    $fieldTypes,
                    $errors,
                );
                $this->validateCalculationReferences(
                    $field,
                    sprintf('%s.fields[%d]', $sectionPath, $fieldIndex),
                    $fieldTypes,
                    $errors,
                );
                $this->validateOptionDependencyReferences(
                    $field,
                    sprintf('%s.fields[%d]', $sectionPath, $fieldIndex),
                    $fieldTypes,
                    $fieldOptions,
                    $errors,
                );
            }
        }

        $this->validateCalculationCycles($sections, $errors);
        $this->validateOptionDependencyCycles($sections, $errors);

        return $errors;
    }

    /** @param array<string, mixed> $field @param list<string> $errors */
    private function validateOptionDependency(array $field, string $path, array &$errors): void
    {
        $dependency = $field['optionDependency'];
        if (!is_array($dependency)) {
            $errors[] = $path . '.optionDependency must be an object.';
            return;
        }
        $unexpected = array_diff(array_keys($dependency), ['field', 'rules']);
        if ($unexpected !== []) {
            $errors[] = $path . '.optionDependency contains unsupported properties: ' . implode(', ', $unexpected) . '.';
        }
        if (!in_array($field['type'] ?? null, ['choice-group', 'checkbox-group'], true)) {
            $errors[] = $path . '.optionDependency is only valid for choice-group and checkbox-group fields.';
        }
        if (!is_string($dependency['field'] ?? null) || preg_match('/^[a-z][a-z0-9_]*$/', $dependency['field']) !== 1) {
            $errors[] = $path . '.optionDependency.field must be a valid field ID.';
        }
        $rules = $dependency['rules'] ?? null;
        if (!is_array($rules) || $rules === []) {
            $errors[] = $path . '.optionDependency.rules must be a non-empty array.';
            return;
        }
        $seen = [];
        foreach ($rules as $index => $rule) {
            $rulePath = sprintf('%s.optionDependency.rules[%d]', $path, $index);
            if (!is_array($rule)) { $errors[] = $rulePath . ' must be an object.'; continue; }
            $unexpectedRule = array_diff(array_keys($rule), ['value', 'options']);
            if ($unexpectedRule !== []) $errors[] = $rulePath . ' contains unsupported properties: ' . implode(', ', $unexpectedRule) . '.';
            $value = $rule['value'] ?? null;
            if (!is_string($value) || trim($value) === '') {
                $errors[] = $rulePath . '.value must be a non-empty string.';
            } elseif (isset($seen[$value])) {
                $errors[] = $path . '.optionDependency.rules must not repeat controller value "' . $value . '".';
            } else {
                $seen[$value] = true;
            }
            $this->validateStringList($rule['options'] ?? null, $rulePath . '.options', $errors);
        }
    }

    /** @param array<string, mixed> $field @param array<string, string> $fieldTypes @param array<string, array<int, mixed>> $fieldOptions @param list<string> $errors */
    private function validateOptionDependencyReferences(array $field, string $path, array $fieldTypes, array $fieldOptions, array &$errors): void
    {
        $dependency = $field['optionDependency'] ?? null;
        if (!is_array($dependency) || !is_string($dependency['field'] ?? null)) return;
        $sourceId = $dependency['field'];
        $targetId = (string)($field['id'] ?? '');
        if (!isset($fieldTypes[$sourceId])) {
            $errors[] = sprintf('%s.optionDependency.field references unknown field ID "%s".', $path, $sourceId);
            return;
        }
        if ($sourceId === $targetId) {
            $errors[] = $path . '.optionDependency.field must reference a different field.';
            return;
        }
        if (!in_array($fieldTypes[$sourceId], ['choice-group', 'checkbox-group'], true)) {
            $errors[] = $path . '.optionDependency.field must reference a choice-group or checkbox-group field.';
        }
        foreach (is_array($dependency['rules'] ?? null) ? $dependency['rules'] : [] as $index => $rule) {
            if (!is_array($rule)) continue;
            $value = $rule['value'] ?? null;
            if (is_string($value) && !in_array($value, $fieldOptions[$sourceId] ?? [], true)) {
                $errors[] = sprintf('%s.optionDependency.rules[%d].value "%s" is not an option of field "%s".', $path, $index, $value, $sourceId);
            }
            foreach (is_array($rule['options'] ?? null) ? $rule['options'] : [] as $option) {
                if (is_string($option) && !in_array($option, $fieldOptions[$targetId] ?? [], true)) {
                    $errors[] = sprintf('%s.optionDependency.rules[%d].options contains "%s", which is not an option of field "%s".', $path, $index, $option, $targetId);
                }
            }
        }
    }

    /** @param array<int, mixed> $sections @param list<string> $errors */
    private function validateOptionDependencyCycles(array $sections, array &$errors): void
    {
        $dependencies=[]; $paths=[];
        foreach ($sections as $sectionIndex => $section) {
            if (!is_array($section) || !is_array($section['fields'] ?? null)) continue;
            foreach ($section['fields'] as $fieldIndex => $field) {
                if (!is_array($field) || !is_string($field['id'] ?? null) || !is_array($field['optionDependency'] ?? null)) continue;
                $source=$field['optionDependency']['field'] ?? null;
                if (is_string($source)) $dependencies[$field['id']]=$source;
                $paths[$field['id']]=sprintf('sections[%d].fields[%d].optionDependency', $sectionIndex, $fieldIndex);
            }
        }
        $visiting=[]; $visited=[]; $stack=[]; $cycles=[];
        $walk=function(string $id) use (&$walk,&$visiting,&$visited,&$stack,&$cycles,$dependencies): void {
            if (isset($visiting[$id])) { foreach (array_slice($stack,$visiting[$id]) as $item) $cycles[$item]=true; return; }
            if (isset($visited[$id])) return;
            $visiting[$id]=count($stack); $stack[]=$id;
            $next=$dependencies[$id] ?? null; if (is_string($next) && isset($dependencies[$next])) $walk($next);
            array_pop($stack); unset($visiting[$id]); $visited[$id]=true;
        };
        foreach (array_keys($dependencies) as $id) $walk($id);
        foreach (array_keys($cycles) as $id) $errors[]=$paths[$id] . ' contains a circular option dependency.';
    }

    /** @param array<string, mixed> $field @param list<string> $errors */
    private function validateCalculation(array $field, string $path, array &$errors): void
    {
        $calculation = $field['calculation'];
        if (!is_array($calculation)) {
            $errors[] = $path . '.calculation must be an object.';
            return;
        }
        $unexpected = array_diff(array_keys($calculation), ['expression', 'precision']);
        if ($unexpected !== []) {
            $errors[] = $path . '.calculation contains unsupported properties: ' . implode(', ', $unexpected) . '.';
        }
        if (($field['type'] ?? null) !== 'number') {
            $errors[] = $path . '.calculation is only valid for number fields.';
        }
        if (($field['readOnly'] ?? false) !== true) {
            $errors[] = $path . '.readOnly must be true for calculated fields.';
        }
        $expression = $calculation['expression'] ?? null;
        if (!is_string($expression) || trim($expression) === '') {
            $errors[] = $path . '.calculation.expression must be a non-empty string.';
        } elseif (strlen($expression) > 1000) {
            $errors[] = $path . '.calculation.expression must not exceed 1000 characters.';
        } else {
            try {
                $this->calculationEngine->parse($expression);
            } catch (\InvalidArgumentException $error) {
                $errors[] = $path . '.calculation.expression is invalid: ' . $error->getMessage();
            }
        }
        if (array_key_exists('precision', $calculation)
            && (!is_int($calculation['precision']) || $calculation['precision'] < 0 || $calculation['precision'] > 10)) {
            $errors[] = $path . '.calculation.precision must be an integer between 0 and 10.';
        }
    }

    /** @param array<string, mixed> $field @param array<string, string> $fieldTypes @param list<string> $errors */
    private function validateCalculationReferences(array $field, string $path, array $fieldTypes, array &$errors): void
    {
        $calculation = $field['calculation'] ?? null;
        $expression = is_array($calculation) ? ($calculation['expression'] ?? null) : null;
        if (!is_string($expression) || trim($expression) === '') return;
        try {
            $references = $this->calculationEngine->references($expression);
        } catch (\InvalidArgumentException) {
            return;
        }
        foreach ($references as $reference) {
            if ($reference === ($field['id'] ?? null)) {
                $errors[] = $path . '.calculation.expression must not reference its own field.';
            } elseif (!isset($fieldTypes[$reference])) {
                $errors[] = sprintf('%s.calculation.expression references unknown field ID "%s".', $path, $reference);
            } elseif ($fieldTypes[$reference] !== 'number') {
                $errors[] = sprintf('%s.calculation.expression field "%s" must be a number field.', $path, $reference);
            }
        }
    }

    /** @param array<int, mixed> $sections @param list<string> $errors */
    private function validateCalculationCycles(array $sections, array &$errors): void
    {
        $dependencies = [];
        $paths = [];
        foreach ($sections as $sectionIndex => $section) {
            if (!is_array($section) || !is_array($section['fields'] ?? null)) continue;
            foreach ($section['fields'] as $fieldIndex => $field) {
                if (!is_array($field) || !is_string($field['id'] ?? null) || !is_array($field['calculation'] ?? null)) continue;
                $expression = $field['calculation']['expression'] ?? null;
                if (!is_string($expression)) continue;
                try { $dependencies[$field['id']] = $this->calculationEngine->references($expression); }
                catch (\InvalidArgumentException) { $dependencies[$field['id']] = []; }
                $paths[$field['id']] = sprintf('sections[%d].fields[%d].calculation.expression', $sectionIndex, $fieldIndex);
            }
        }
        foreach ($dependencies as $id => $items) {
            $dependencies[$id] = array_values(array_filter($items, static fn (string $item): bool => isset($dependencies[$item])));
        }
        $visiting = [];
        $visited = [];
        $reported = [];
        $walk = function (string $id) use (&$walk, &$visiting, &$visited, &$reported, $dependencies, $paths, &$errors): bool {
            if (isset($visiting[$id])) return true;
            if (isset($visited[$id])) return false;
            $visiting[$id] = true;
            $cyclic = false;
            foreach ($dependencies[$id] ?? [] as $next) {
                if ($walk($next)) $cyclic = true;
            }
            unset($visiting[$id]);
            $visited[$id] = true;
            if ($cyclic && !isset($reported[$id])) {
                $errors[] = $paths[$id] . ' contains a circular calculated-field dependency.';
                $reported[$id] = true;
            }
            return $cyclic;
        };
        foreach (array_keys($dependencies) as $id) $walk($id);
    }

    /** @param list<string> $errors */
    private function validateFieldValidation(mixed $validation, string $path, array &$errors): void
    {
        if (!is_array($validation)) {
            $errors[] = $path . ' must be an object.';
            return;
        }
        $unexpected = array_diff(array_keys($validation), ['compare']);
        if ($unexpected !== []) {
            $errors[] = $path . ' contains unsupported properties: ' . implode(', ', $unexpected) . '.';
        }
        if (!array_key_exists('compare', $validation)) {
            $errors[] = $path . '.compare is required.';
            return;
        }
        $rule = $validation['compare'];
        if (!is_array($rule)) {
            $errors[] = $path . '.compare must be an object.';
            return;
        }
        $unexpectedRule = array_diff(array_keys($rule), ['field', 'operator', 'message']);
        if ($unexpectedRule !== []) {
            $errors[] = $path . '.compare contains unsupported properties: ' . implode(', ', $unexpectedRule) . '.';
        }
        if (!is_string($rule['field'] ?? null) || preg_match('/^[a-z][a-z0-9_]*$/', $rule['field']) !== 1) {
            $errors[] = $path . '.compare.field must be a valid field ID.';
        }
        $operators = array_merge(self::CROSS_FIELD_EQUALITY_OPERATORS, self::CROSS_FIELD_ORDERED_OPERATORS);
        if (!is_string($rule['operator'] ?? null) || !in_array($rule['operator'], $operators, true)) {
            $errors[] = $path . '.compare.operator must be one of: ' . implode(', ', $operators) . '.';
        }
        $message = $rule['message'] ?? null;
        if (!is_string($message) || trim($message) === '') {
            $errors[] = $path . '.compare.message must be a non-empty string.';
        } elseif (mb_strlen($message) > 1000) {
            $errors[] = $path . '.compare.message must not exceed 1000 characters.';
        }
    }

    /**
     * @param array<string, string> $fieldTypes
     * @param list<string> $errors
     */
    private function validateComparisonReference(
        mixed $validation,
        string $targetId,
        string $targetType,
        string $path,
        array $fieldTypes,
        array &$errors,
    ): void {
        if (!is_array($validation) || !is_array($validation['compare'] ?? null)) return;
        $rule = $validation['compare'];
        $sourceId = $rule['field'] ?? null;
        $operator = $rule['operator'] ?? null;
        if (!is_string($sourceId) || preg_match('/^[a-z][a-z0-9_]*$/', $sourceId) !== 1) return;
        if (!isset($fieldTypes[$sourceId])) {
            $errors[] = sprintf('%s.compare.field references unknown field ID "%s".', $path, $sourceId);
            return;
        }
        if ($sourceId === $targetId) {
            $errors[] = $path . '.compare.field must reference a different field.';
            return;
        }
        if (!is_string($operator)) return;
        $operators = array_merge(self::CROSS_FIELD_EQUALITY_OPERATORS, self::CROSS_FIELD_ORDERED_OPERATORS);
        if (!in_array($operator, $operators, true)) return;
        $allowed = $this->comparisonOperatorsFor($targetType, $fieldTypes[$sourceId]);
        if (!in_array($operator, $allowed, true)) {
            $errors[] = sprintf(
                '%s.compare operator %s is not compatible with %s and %s fields.',
                $path,
                $operator,
                $targetType,
                $fieldTypes[$sourceId],
            );
        }
    }

    /** @return list<string> */
    private function comparisonOperatorsFor(string $targetType, string $sourceType): array
    {
        $targetFamily = $this->comparisonFamily($targetType);
        $sourceFamily = $this->comparisonFamily($sourceType);
        if ($targetFamily === null || $targetFamily !== $sourceFamily) return [];
        if (in_array($targetFamily, ['number', 'date', 'time'], true)) {
            return array_merge(self::CROSS_FIELD_EQUALITY_OPERATORS, self::CROSS_FIELD_ORDERED_OPERATORS);
        }
        return self::CROSS_FIELD_EQUALITY_OPERATORS;
    }

    private function comparisonFamily(string $type): ?string
    {
        return match ($type) {
            'text', 'textarea', 'choice-group' => 'text',
            'number', 'date', 'time', 'checkbox' => $type,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $field
     * @param list<string> $errors
     */
    private function validateTextConstraints(array $field, string $type, string $path, array &$errors): void
    {
        $keys = ['minLength', 'maxLength', 'pattern', 'validationMessage'];
        $present = array_values(array_filter($keys, static fn (string $key): bool => array_key_exists($key, $field)));
        if ($present === []) return;

        if (!in_array($type, ['text', 'textarea'], true)) {
            $errors[] = $path . '.minLength/maxLength/pattern/validationMessage are only valid for text and textarea fields.';
            return;
        }

        $minLength = $field['minLength'] ?? null;
        $maxLength = $field['maxLength'] ?? null;
        if (array_key_exists('minLength', $field) && (!is_int($minLength) || $minLength < 0)) {
            $errors[] = $path . '.minLength must be an integer greater than or equal to zero.';
        }
        if (array_key_exists('maxLength', $field) && (!is_int($maxLength) || $maxLength < 0)) {
            $errors[] = $path . '.maxLength must be an integer greater than or equal to zero.';
        }
        if (is_int($minLength) && is_int($maxLength) && $minLength > $maxLength) {
            $errors[] = $path . '.minLength must not be greater than maxLength.';
        }

        if (array_key_exists('pattern', $field)) {
            if (!is_string($field['pattern']) || $field['pattern'] === '') {
                $errors[] = $path . '.pattern must be a non-empty string.';
            } elseif (mb_strlen($field['pattern']) > 1000) {
                $errors[] = $path . '.pattern must not exceed 1000 characters.';
            } elseif (@preg_match($this->patternExpression($field['pattern']), '') === false) {
                $errors[] = $path . '.pattern must be a valid regular expression.';
            }
        }

        if (array_key_exists('validationMessage', $field)) {
            if (!is_string($field['validationMessage']) || trim($field['validationMessage']) === '') {
                $errors[] = $path . '.validationMessage must be a non-empty string.';
            } elseif (mb_strlen($field['validationMessage']) > 1000) {
                $errors[] = $path . '.validationMessage must not exceed 1000 characters.';
            } elseif (!array_key_exists('pattern', $field)) {
                $errors[] = $path . '.validationMessage requires pattern.';
            }
        }
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

    /** @param list<string> $errors */
    private function validateLogic(mixed $logic, string $path, bool $allowRequiredWhen, array &$errors): void
    {
        if (!is_array($logic)) {
            $errors[] = $path . ' must be an object.';
            return;
        }
        if (!$allowRequiredWhen && array_key_exists('requiredWhen', $logic)) {
            $errors[] = $path . '.requiredWhen is only valid for fields.';
        }
        $ruleNames = $allowRequiredWhen ? ['showWhen', 'requiredWhen'] : ['showWhen'];
        $present = array_values(array_filter($ruleNames, static fn (string $name): bool => array_key_exists($name, $logic)));
        if ($present === []) {
            $errors[] = $path . '.' . implode(' or ', $ruleNames) . ' is required.';
            return;
        }
        foreach ($present as $ruleName) {
            $this->validateLogicRule($logic[$ruleName], $path . '.' . $ruleName, $errors);
        }
    }

    /** @param list<string> $errors */
    private function validateLogicRule(mixed $rule, string $path, array &$errors): void
    {
        if (!is_array($rule)) {
            $errors[] = $path . ' must be an object.';
            return;
        }
        $field = $rule['field'] ?? null;
        if (!is_string($field) || preg_match('/^[a-z][a-z0-9_]*$/', $field) !== 1) {
            $errors[] = $path . '.field must be a valid field ID.';
        }
        $operator = $rule['operator'] ?? null;
        if (!is_string($operator) || !in_array($operator, self::LOGIC_VISIBILITY_OPERATORS, true)) {
            $errors[] = sprintf('%s.operator must be one of: %s.', $path, implode(', ', self::LOGIC_VISIBILITY_OPERATORS));
            return;
        }
        if (in_array($operator, ['equals', 'notEquals', 'contains'], true) && !array_key_exists('value', $rule)) {
            $errors[] = $path . '.value is required for operator ' . $operator . '.';
        }
        if (in_array($operator, ['isEmpty', 'isNotEmpty'], true) && array_key_exists('value', $rule)) {
            $errors[] = $path . '.value is not valid for operator ' . $operator . '.';
        }
    }

    /**
     * @param array<string, bool> $fieldIds
     * @param list<string> $errors
     */
    private function validateLogicReferences(mixed $logic, string $path, array $fieldIds, array &$errors): void
    {
        if (!is_array($logic)) return;
        foreach (['showWhen', 'requiredWhen'] as $ruleName) {
            if (!is_array($logic[$ruleName] ?? null)) continue;
            $field = $logic[$ruleName]['field'] ?? null;
            if (is_string($field) && preg_match('/^[a-z][a-z0-9_]*$/', $field) === 1 && !isset($fieldIds[$field])) {
                $errors[] = sprintf('%s.%s.field references unknown field ID "%s".', $path, $ruleName, $field);
            }
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
