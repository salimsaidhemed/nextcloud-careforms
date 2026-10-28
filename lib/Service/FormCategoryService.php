<?php

declare(strict_types=1);

namespace OCA\CareForms\Service;

use OCA\CareForms\Db\FormCategory;
use OCA\CareForms\Db\FormCategoryAssignment;
use OCA\CareForms\Db\FormCategoryAssignmentMapper;
use OCA\CareForms\Db\FormCategoryMapper;

final class FormCategoryService
{
    public const UNCATEGORIZED = 'uncategorized';

    public function __construct(
        private FormCategoryMapper $categories,
        private FormCategoryAssignmentMapper $assignments,
    ) {
    }

    /** @return FormCategory[] */
    public function all(): array
    {
        $this->ensureDefault();
        $items = $this->categories->findAllOrdered();
        usort($items, static function (FormCategory $a, FormCategory $b): int {
            if ($a->getCategoryKey() === self::UNCATEGORIZED && $b->getCategoryKey() === self::UNCATEGORIZED) return 0;
            if ($a->getCategoryKey() === self::UNCATEGORIZED) return 1;
            if ($b->getCategoryKey() === self::UNCATEGORIZED) return -1;
            return $a->getSortOrder() <=> $b->getSortOrder() ?: strcasecmp($a->getLabel(), $b->getLabel());
        });
        return $items;
    }

    public function create(string $label, string $userId): FormCategory
    {
        $label = $this->validLabel($label);
        foreach ($this->all() as $existing) {
            if (strcasecmp($existing->getLabel(), $label) === 0) throw new \InvalidArgumentException('A category with this label already exists.');
        }
        $key = $this->uniqueKey($label);
        $orders = array_map(static fn (FormCategory $item): int => $item->getCategoryKey() === self::UNCATEGORIZED ? 0 : $item->getSortOrder(), $this->all());
        $now = time();
        $item = new FormCategory();
        $item->setCategoryKey($key);
        $item->setLabel($label);
        $item->setSortOrder(($orders === [] ? 0 : max($orders)) + 10);
        $item->setCreatedBy($userId);
        $item->setCreatedAt($now);
        $item->setUpdatedAt($now);
        return $this->categories->insert($item);
    }

    public function get(string $key): FormCategory
    {
        return $this->require($key);
    }

    public function update(string $key, string $label, int $sortOrder): FormCategory
    {
        $item = $this->require($key);
        $label = $key === self::UNCATEGORIZED ? 'Uncategorized' : $this->validLabel($label);
        foreach ($this->all() as $existing) {
            if ($existing->getCategoryKey() !== $key && strcasecmp($existing->getLabel(), $label) === 0) throw new \InvalidArgumentException('A category with this label already exists.');
        }
        $item->setLabel($label);
        $item->setSortOrder($key === self::UNCATEGORIZED ? 100000 : max(0, min(99990, $sortOrder)));
        $item->setUpdatedAt(time());
        return $this->categories->update($item);
    }

    public function assign(string $formId, string $categoryKey): FormCategory
    {
        $category = $this->require($categoryKey);
        $assignment = $this->assignments->findByForm($formId) ?? new FormCategoryAssignment();
        $assignment->setFormId($formId);
        $assignment->setCategoryKey($categoryKey);
        $assignment->setUpdatedAt(time());
        $assignment->getId() === null ? $this->assignments->insert($assignment) : $this->assignments->update($assignment);
        return $category;
    }

    public function forForm(string $formId, ?string $legacyLabel = null): FormCategory
    {
        $assignment = $this->assignments->findByForm($formId);
        if ($assignment !== null) {
            $category = $this->categories->findByKey($assignment->getCategoryKey());
            if ($category !== null) return $category;
        }
        $label = trim((string)$legacyLabel);
        if ($label === '') return $this->assign($formId, self::UNCATEGORIZED);
        foreach ($this->all() as $category) {
            if (strcasecmp($category->getLabel(), $label) === 0) return $this->assign($formId, $category->getCategoryKey());
        }
        return $this->assign($formId, $this->create($label, 'migration')->getCategoryKey());
    }

    /** @param array<string, mixed> $definition @return array<string, mixed> */
    public function decorate(array $definition): array
    {
        $category = $this->forForm((string)$definition['id'], is_string($definition['category'] ?? null) ? $definition['category'] : null);
        $definition['categoryId'] = $category->getCategoryKey();
        $definition['category'] = $category->getLabel();
        $definition['categoryOrder'] = $category->getSortOrder();
        return $definition;
    }

    private function ensureDefault(): void
    {
        if ($this->categories->findByKey(self::UNCATEGORIZED) !== null) return;
        $now = time();
        $item = new FormCategory();
        $item->setCategoryKey(self::UNCATEGORIZED);
        $item->setLabel('Uncategorized');
        $item->setSortOrder(100000);
        $item->setCreatedBy('system');
        $item->setCreatedAt($now);
        $item->setUpdatedAt($now);
        try { $this->categories->insert($item); } catch (\Throwable) {}
    }

    private function require(string $key): FormCategory
    {
        $this->ensureDefault();
        $item = $this->categories->findByKey($key);
        if ($item === null) throw new \InvalidArgumentException('Unknown form category.');
        return $item;
    }

    private function validLabel(string $label): string
    {
        $label = trim($label);
        if ($label === '') throw new \InvalidArgumentException('Category label is required.');
        if (mb_strlen($label) > 128) throw new \InvalidArgumentException('Category label must not exceed 128 characters.');
        return $label;
    }

    private function uniqueKey(string $label): string
    {
        $base = strtolower($label);
        $base = preg_replace('/[^a-z0-9]+/', '-', $base) ?? '';
        $base = trim($base, '-') ?: 'category';
        $key = substr($base, 0, 56);
        $suffix = 2;
        while ($this->categories->findByKey($key) !== null) $key = substr($base, 0, 56) . '-' . $suffix++;
        return $key;
    }
}
