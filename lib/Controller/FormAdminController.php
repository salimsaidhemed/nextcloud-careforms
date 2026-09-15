<?php

declare(strict_types=1);

namespace OCA\CareForms\Controller;

use OCA\CareForms\Service\AccessService;
use OCA\CareForms\Service\AuditService;
use OCA\CareForms\Service\FormVersionService;
use OCA\CareForms\Service\FormDefinitionService;
use OCA\CareForms\Service\FormSchemaValidator;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class FormAdminController extends Controller
{

    public function __construct(
        IRequest $request,
        private AccessService $accessService,
        private AuditService $auditService,
        private FormVersionService $formVersions,
        private FormDefinitionService $definitions,
        private FormSchemaValidator $schemaValidator,
    ) {
        parent::__construct('careforms', $request);
    }

    #[NoAdminRequired]
    public function index(): JSONResponse
    {
        $userId = $this->accessService->currentUserId();
        if ($userId === null) return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        if (!$this->accessService->canManageForms($userId)) return new JSONResponse(['message' => 'You do not have permission to manage forms.'], Http::STATUS_FORBIDDEN);

        $forms = [];
        foreach ($this->definitions->all() as $candidate) {
            $formId = (string)$candidate['id'];
            $publishedVersion = $this->formVersions->publishedVersion($formId);
            $definition = $this->definitions->getVersion($formId, $publishedVersion);
            $versions = array_map(static fn ($version): array => $version->jsonSerialize(), $this->formVersions->versions($formId));
            $draft = $this->formVersions->draft($formId);
            $forms[] = [
                'id' => $formId,
                'name' => (string)$definition['name'],
                'category' => (string)$definition['category'],
                'enabled' => $this->accessService->isFormEnabled($formId),
                'publishedVersion' => $publishedVersion,
                'nextVersion' => $this->formVersions->nextVersion($formId),
                'draftVersion' => $draft?->getVersionNumber(),
                'versions' => $versions,
            ];
        }
        return new JSONResponse($forms);
    }

    #[NoAdminRequired]
    public function export(string $formId): JSONResponse
    {
        $userId = $this->requireManager();
        if ($userId instanceof JSONResponse) return $userId;
        if (!$this->definitions->exists($formId)) return new JSONResponse(['message' => 'Unknown CareForms form.'], Http::STATUS_NOT_FOUND);

        try {
            $definition = $this->definitions->getVersion($formId, $this->formVersions->publishedVersion($formId));
        } catch (\Throwable $e) {
            return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        $this->auditService->log($userId, 'FORM_EXPORT', 'form', null, $formId, 'success', ['schemaVersion' => $definition['schemaVersion'] ?? null]);

        return new JSONResponse([
            'filename' => $formId . '-v' . ($definition['version'] ?? 1) . '.json',
            'definition' => $definition,
        ]);
    }

    #[NoAdminRequired]
    public function validateImport(array $definition): JSONResponse
    {
        $userId = $this->requireManager();
        if ($userId instanceof JSONResponse) return $userId;

        $errors = $this->schemaValidator->validate($definition);
        $formId = is_string($definition['id'] ?? null) ? $definition['id'] : null;

        $this->auditService->log(
            $userId,
            'FORM_IMPORT_VALIDATE',
            'form',
            null,
            $formId,
            $errors === [] ? 'success' : 'failure',
            ['schemaVersion' => $definition['schemaVersion'] ?? null, 'errorCount' => count($errors)],
        );

        $exists = $formId !== null && $this->definitions->exists($formId);
        $draft = $exists ? $this->formVersions->draft($formId) : null;

        return new JSONResponse([
            'valid' => $errors === [],
            'errors' => $errors,
            'summary' => [
                'id' => $formId,
                'name' => is_string($definition['name'] ?? null) ? $definition['name'] : null,
                'category' => is_string($definition['category'] ?? null) ? $definition['category'] : null,
                'schemaVersion' => $definition['schemaVersion'] ?? null,
                'version' => $definition['version'] ?? null,
                'conflictsExisting' => $exists,
                'expectedVersion' => $exists ? $this->formVersions->nextVersion($formId) : 1,
                'draftExists' => $draft !== null,
                'draftVersion' => $draft?->getVersionNumber(),
            ],
        ]);
    }

    #[NoAdminRequired]
    public function importDefinition(array $definition): JSONResponse
    {
        $userId = $this->requireManager();
        if ($userId instanceof JSONResponse) return $userId;

        $errors = $this->schemaValidator->validate($definition);
        if ($errors !== []) {
            return new JSONResponse([
                'message' => 'The form definition is not valid.',
                'errors' => $errors,
            ], Http::STATUS_UNPROCESSABLE_ENTITY);
        }

        $formId = (string)$definition['id'];
        $existing = $this->definitions->exists($formId);

        try {
            if (!$existing) {
                if ((int)$definition['version'] !== 1) {
                    return new JSONResponse([
                        'message' => 'A newly imported form must start at version 1.',
                    ], Http::STATUS_CONFLICT);
                }

                $imported = $this->definitions->importNew($definition, $userId);
                $this->formVersions->ensureSeeded($formId);
                $action = 'FORM_IMPORT';
            } else {
                $draft = $this->formVersions->draft($formId);
                if ($draft !== null) {
                    return new JSONResponse([
                        'message' => sprintf(
                            'Form "%s" already has draft version %d. Publish or archive it before importing another version.',
                            $formId,
                            $draft->getVersionNumber(),
                        ),
                    ], Http::STATUS_CONFLICT);
                }

                $expectedVersion = $this->formVersions->nextVersion($formId);
                if ((int)$definition['version'] !== $expectedVersion) {
                    return new JSONResponse([
                        'message' => sprintf(
                            'The next version for form "%s" must be %d.',
                            $formId,
                            $expectedVersion,
                        ),
                    ], Http::STATUS_CONFLICT);
                }

                $imported = $this->definitions->importVersion($definition, $userId);
                $draft = $this->formVersions->createDraft($formId, $userId);
                if ($draft->getVersionNumber() !== (int)$definition['version']) {
                    throw new \LogicException('Imported definition version does not match the created draft version.');
                }
                $action = 'FORM_VERSION_IMPORT';
            }
        } catch (\LogicException $e) {
            return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_CONFLICT);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_UNPROCESSABLE_ENTITY);
        }

        $this->auditService->log(
            $userId,
            $action,
            'form',
            null,
            $formId,
            'success',
            [
                'schemaVersion' => $imported['schemaVersion'],
                'version' => $imported['version'],
                'status' => $existing ? 'draft' : 'published',
            ],
        );

        return new JSONResponse([
            'definition' => $imported,
            'enabled' => $this->accessService->isFormEnabled($formId),
            'publishedVersion' => $this->formVersions->publishedVersion($formId),
            'draftVersion' => $existing ? (int)$imported['version'] : null,
        ], Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function createForm(string $name, string $category = 'General', string $description = ''): JSONResponse
    {
        $userId = $this->requireManager();
        if ($userId instanceof JSONResponse) return $userId;

        $base = strtolower(trim($name));
        $base = preg_replace('/[^a-z0-9]+/', '-', $base) ?? '';
        $base = trim($base, '-');
        if ($base === '') $base = 'new-form';
        $formId = $base;
        $suffix = 2;
        while ($this->definitions->exists($formId)) $formId = $base . '-' . $suffix++;

        $definition = [
            'schemaVersion' => FormSchemaValidator::DEFAULT_SCHEMA_VERSION,
            'id' => $formId,
            'name' => trim($name),
            'category' => trim($category) !== '' ? trim($category) : 'General',
            'description' => trim($description),
            'version' => 1,
            'sections' => [[
                'id' => 'general',
                'label' => 'General',
                'description' => '',
                'fields' => [[
                    'id' => 'new_field',
                    'type' => 'text',
                    'label' => 'New field',
                ]],
            ]],
        ];

        $errors = $this->schemaValidator->validate($definition);
        if ($errors !== []) return new JSONResponse(['message' => 'The new form is invalid.', 'errors' => $errors], Http::STATUS_UNPROCESSABLE_ENTITY);

        try {
            $draft = $this->formVersions->createInitialDraft($formId, $userId);
            $definition['version'] = $draft->getVersionNumber();
            $created = $this->definitions->importNew($definition, $userId);
        } catch (\Throwable $e) {
            return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        $this->auditService->log($userId, 'FORM_CREATE', 'form_version', $draft->getId(), $formId, 'success', ['version' => $draft->getVersionNumber(), 'source' => 'designer']);
        return new JSONResponse(['definition' => $created, 'draft' => $draft->jsonSerialize()], Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function update(string $formId, bool $enabled): JSONResponse
    {
        $userId = $this->requireManager();
        if ($userId instanceof JSONResponse) return $userId;
        if (!$this->definitions->exists($formId)) return new JSONResponse(['message' => 'Unknown CareForms form.'], Http::STATUS_NOT_FOUND);
        $this->accessService->setFormEnabled($formId, $enabled);
        $this->auditService->log($userId, 'ADMIN_SETTING_CHANGE', 'form', null, $formId, 'success', ['setting' => 'enabled', 'enabled' => $enabled]);
        return new JSONResponse(['id' => $formId, 'enabled' => $this->accessService->isFormEnabled($formId)]);
    }

    #[NoAdminRequired]
    public function createDraft(string $formId): JSONResponse
    {
        $userId = $this->requireManager();
        if ($userId instanceof JSONResponse) return $userId;
        if (!$this->definitions->exists($formId)) return new JSONResponse(['message' => 'Unknown CareForms form.'], Http::STATUS_NOT_FOUND);
        try {
            $publishedVersion = $this->formVersions->publishedVersion($formId);
            $draft = $this->formVersions->createDraft($formId, $userId);
            $this->definitions->cloneVersion($formId, $publishedVersion, $draft->getVersionNumber(), $userId);
        } catch (\LogicException $e) {
            return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_CONFLICT);
        } catch (\Throwable $e) {
            return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
        $this->auditService->log($userId, 'FORM_CREATE', 'form_version', $draft->getId(), $formId, 'success', ['version' => $draft->getVersionNumber(), 'clonedFrom' => $publishedVersion]);
        return new JSONResponse($draft->jsonSerialize(), Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function designer(string $formId, int $version): JSONResponse
    {
        $userId = $this->requireManager();
        if ($userId instanceof JSONResponse) return $userId;

        $draft = $this->formVersions->draft($formId);
        if ($draft === null || $draft->getVersionNumber() !== $version) {
            return new JSONResponse(['message' => 'Only the current draft can be opened in the form designer.'], Http::STATUS_CONFLICT);
        }

        try {
            $definition = $this->definitions->getVersion($formId, $version);
        } catch (\Throwable $e) {
            return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
        }

        return new JSONResponse([
            'formId' => $formId,
            'version' => $version,
            'status' => 'draft',
            'definition' => $definition,
        ]);
    }

    #[NoAdminRequired]
    public function saveDesigner(string $formId, int $version, array $definition): JSONResponse
    {
        $userId = $this->requireManager();
        if ($userId instanceof JSONResponse) return $userId;

        $draft = $this->formVersions->draft($formId);
        if ($draft === null || $draft->getVersionNumber() !== $version) {
            return new JSONResponse(['message' => 'Only the current draft can be saved from the form designer.'], Http::STATUS_CONFLICT);
        }

        $errors = $this->schemaValidator->validate($definition);
        if ($errors !== []) {
            return new JSONResponse([
                'message' => 'The draft contains validation errors and was not saved: ' . implode(' ', $errors),
                'errors' => $errors,
            ], Http::STATUS_UNPROCESSABLE_ENTITY);
        }

        try {
            $saved = $this->definitions->saveDraft($formId, $version, $definition, $userId);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        $this->auditService->log($userId, 'FORM_UPDATE', 'form_version', $draft->getId(), $formId, 'success', [
            'version' => $version,
            'source' => 'designer',
        ]);

        return new JSONResponse(['definition' => $saved, 'saved' => true]);
    }

    #[NoAdminRequired]
    public function publish(string $formId, int $version): JSONResponse
    {
        $userId = $this->requireManager();
        if ($userId instanceof JSONResponse) return $userId;
        if (!$this->definitions->exists($formId)) return new JSONResponse(['message' => 'Unknown CareForms form.'], Http::STATUS_NOT_FOUND);
        try { $published = $this->formVersions->publish($formId, $version); }
        catch (\InvalidArgumentException $e) { return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND); }
        catch (\LogicException $e) { return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_CONFLICT); }
        $this->auditService->log($userId, 'FORM_PUBLISH', 'form_version', $published->getId(), $formId, 'success', ['version' => $version]);
        return new JSONResponse($published->jsonSerialize());
    }

    #[NoAdminRequired]
    public function archive(string $formId, int $version): JSONResponse
    {
        $userId = $this->requireManager();
        if ($userId instanceof JSONResponse) return $userId;
        if (!$this->definitions->exists($formId)) return new JSONResponse(['message' => 'Unknown CareForms form.'], Http::STATUS_NOT_FOUND);
        try { $archived = $this->formVersions->archiveDraft($formId, $version); }
        catch (\InvalidArgumentException $e) { return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND); }
        catch (\LogicException $e) { return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_CONFLICT); }
        $this->auditService->log($userId, 'FORM_UPDATE', 'form_version', $archived->getId(), $formId, 'success', ['version' => $version, 'status' => 'archived']);
        return new JSONResponse($archived->jsonSerialize());
    }

    private function requireManager(): string|JSONResponse
    {
        $userId = $this->accessService->currentUserId();
        if ($userId === null) return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        if (!$this->accessService->canManageForms($userId)) return new JSONResponse(['message' => 'You do not have permission to manage forms.'], Http::STATUS_FORBIDDEN);
        return $userId;
    }
}
