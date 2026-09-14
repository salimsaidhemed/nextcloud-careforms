<?php

declare(strict_types=1);

namespace OCA\CareForms\Service;

use OCA\CareForms\Db\FormDefinitionRecord;
use OCA\CareForms\Db\FormDefinitionRecordMapper;

final class FormDefinitionService
{
    private const DEFINITIONS = [
        'home-health-aide-note' => 'home-health-aide-note.json',
        'nurses-progress-note' => 'nurses-progress-note.json',
    ];

    public function __construct(
        private FormSchemaValidator $validator,
        private FormDefinitionCompatibilityService $compatibility,
        private FormDefinitionRecordMapper $records,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $ids = array_unique(array_merge(array_keys(self::DEFINITIONS), $this->dynamicFormIds()));
        $definitions = [];

        foreach ($ids as $formId) {
            $definitions[] = $this->get($formId);
        }

        return $definitions;
    }

    /**
     * @param list<string> $formIds
     * @return list<array<string, mixed>>
     */
    public function forForms(array $formIds): array
    {
        $definitions = [];

        foreach ($formIds as $formId) {
            if (!$this->exists($formId)) {
                continue;
            }

            $definitions[] = $this->get($formId);
        }

        return $definitions;
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $formId): array
    {
        $record = $this->records->findLatestByForm($formId);

        if ($record !== null) {
            $json = $record->getDefinitionJson();
        } elseif (isset(self::DEFINITIONS[$formId])) {
            $path = dirname(__DIR__, 2) . '/forms/' . self::DEFINITIONS[$formId];
            $json = @file_get_contents($path);

            if ($json === false) {
                throw new \RuntimeException(sprintf('Unable to read CareForms form definition "%s".', $formId));
            }
        } else {
            throw new \InvalidArgumentException(sprintf('Unknown CareForms form definition "%s".', $formId));
        }

        try {
            $definition = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException(
                sprintf('CareForms form definition "%s" contains invalid JSON.', $formId),
                0,
                $e,
            );
        }

        if (!is_array($definition)) {
            throw new \RuntimeException(sprintf('CareForms form definition "%s" must decode to an object.', $formId));
        }

        $definition = $this->compatibility->normalize($definition);
        $this->validator->assertValid($definition);

        if (($definition['id'] ?? null) !== $formId) {
            throw new \RuntimeException(sprintf(
                'CareForms form definition ID mismatch: expected "%s".',
                $formId,
            ));
        }

        return $definition;
    }

    public function exists(string $formId): bool
    {
        return isset(self::DEFINITIONS[$formId]) || $this->records->findLatestByForm($formId) !== null;
    }

    /** @return list<string> */
    public function dynamicFormIds(): array
    {
        $ids = [];
        foreach ($this->records->findAllRecords() as $record) {
            $ids[$record->getFormId()] = true;
        }

        return array_keys($ids);
    }

    /**
     * Persist a brand-new validated form definition.
     *
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    public function importNew(array $definition, string $userId): array
    {
        $definition = $this->compatibility->normalize($definition);
        $this->validator->assertValid($definition);

        $formId = (string)$definition['id'];
        $version = (int)$definition['version'];

        if ($this->exists($formId)) {
            throw new \LogicException(sprintf('A CareForms form with ID "%s" already exists.', $formId));
        }

        $json = json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $record = new FormDefinitionRecord();
        $record->setFormId($formId);
        $record->setFormVersion($version);
        $record->setSchemaVersion((int)$definition['schemaVersion']);
        $record->setDefinitionJson($json);
        $record->setCreatedBy($userId);
        $record->setCreatedAt(time());
        $this->records->insert($record);

        return $definition;
    }
}
