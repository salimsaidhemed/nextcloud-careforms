<?php

declare(strict_types=1);

namespace OCA\CareForms\Service;

final class RecordHistoryPresenter
{
    /**
     * @param array<string, mixed> $effective
     * @param array<string, mixed> $changes
     * @param array<string, string> $labels
     * @return list<array<string, mixed>>
     */
    public function compare(array $effective, array $changes, array $labels): array
    {
        $result = [];
        foreach ($changes as $fieldId => $after) {
            $exists = array_key_exists($fieldId, $effective) && !$this->emptyValue($effective[$fieldId]);
            $before = $effective[$fieldId] ?? null;
            $result[] = [
                'fieldId' => $fieldId,
                'label' => $labels[$fieldId] ?? $fieldId,
                'before' => $before,
                'after' => $after,
                'changeType' => $before === $after ? 'unchanged' : ($exists ? 'replaced' : 'added'),
            ];
        }
        return $result;
    }

    private function emptyValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
