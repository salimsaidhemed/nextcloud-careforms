<?php

declare(strict_types=1);

namespace OCA\CareForms\Service;

final class FormDefinitionService
{
    private const DEFINITIONS = [
        'home-health-aide-note' => 'home-health-aide-note.json',
    ];

    public function __construct(
        private FormSchemaValidator $validator,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $definitions = [];

        foreach (array_keys(self::DEFINITIONS) as $formId) {
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
            if (!isset(self::DEFINITIONS[$formId])) {
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
        if (!isset(self::DEFINITIONS[$formId])) {
            throw new \InvalidArgumentException(sprintf('Unknown CareForms form definition "%s".', $formId));
        }

        $path = dirname(__DIR__, 2) . '/forms/' . self::DEFINITIONS[$formId];
        $json = @file_get_contents($path);

        if ($json === false) {
            throw new \RuntimeException(sprintf('Unable to read CareForms form definition "%s".', $formId));
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

        $this->validator->assertValid($definition);

        if (($definition['id'] ?? null) !== $formId) {
            throw new \RuntimeException(sprintf(
                'CareForms form definition ID mismatch: expected "%s".',
                $formId,
            ));
        }

        return $definition;
    }
}
