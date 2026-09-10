<?php

declare(strict_types=1);

namespace OCA\CareForms\Service;

use OCA\CareForms\Db\FormVersion;
use OCA\CareForms\Db\FormVersionMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

class FormVersionService
{
    public function __construct(private FormVersionMapper $mapper) {}

    public function ensureSeeded(string $formId): void
    {
        if ($this->mapper->findAllByForm($formId) !== []) return;
        $now = time();
        $version = new FormVersion();
        $version->setFormId($formId);
        $version->setVersionNumber(1);
        $version->setStatus('published');
        $version->setCreatedBy('system');
        $version->setCreatedAt($now);
        $version->setUpdatedAt($now);
        $version->setPublishedAt($now);
        $this->mapper->insert($version);
    }

    /** @return FormVersion[] */
    public function versions(string $formId): array
    {
        $this->ensureSeeded($formId);
        return $this->mapper->findAllByForm($formId);
    }

    public function publishedVersion(string $formId): int
    {
        $this->ensureSeeded($formId);
        return $this->mapper->findPublished($formId)?->getVersionNumber() ?? 1;
    }

    public function createDraft(string $formId, string $userId): FormVersion
    {
        $this->ensureSeeded($formId);
        $existing = $this->mapper->findDraft($formId);
        if ($existing !== null) return $existing;
        $now = time();
        $version = new FormVersion();
        $version->setFormId($formId);
        $version->setVersionNumber($this->mapper->nextVersion($formId));
        $version->setStatus('draft');
        $version->setCreatedBy($userId);
        $version->setCreatedAt($now);
        $version->setUpdatedAt($now);
        return $this->mapper->insert($version);
    }

    public function publish(string $formId, int $version): FormVersion
    {
        $this->ensureSeeded($formId);
        try {
            $target = $this->mapper->findByFormAndVersion($formId, $version);
        } catch (DoesNotExistException | MultipleObjectsReturnedException) {
            throw new \InvalidArgumentException('Form version not found.');
        }
        if ($target->getStatus() !== 'draft') throw new \LogicException('Only a draft version can be published.');
        $now = time();
        $current = $this->mapper->findPublished($formId);
        if ($current !== null) {
            $current->setStatus('archived');
            $current->setArchivedAt($now);
            $current->setUpdatedAt($now);
            $this->mapper->update($current);
        }
        $target->setStatus('published');
        $target->setPublishedAt($now);
        $target->setUpdatedAt($now);
        return $this->mapper->update($target);
    }

    public function archiveDraft(string $formId, int $version): FormVersion
    {
        try {
            $target = $this->mapper->findByFormAndVersion($formId, $version);
        } catch (DoesNotExistException | MultipleObjectsReturnedException) {
            throw new \InvalidArgumentException('Form version not found.');
        }
        if ($target->getStatus() !== 'draft') throw new \LogicException('Only a draft can be archived directly. Published versions are archived when a replacement is published.');
        $now = time();
        $target->setStatus('archived');
        $target->setArchivedAt($now);
        $target->setUpdatedAt($now);
        return $this->mapper->update($target);
    }
}
