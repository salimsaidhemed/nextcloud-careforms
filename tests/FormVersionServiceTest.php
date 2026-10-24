<?php

declare(strict_types=1);

namespace OCP\AppFramework\Db {
    class DoesNotExistException extends \RuntimeException {}
    class MultipleObjectsReturnedException extends \RuntimeException {}
}

namespace OCA\CareForms\Db {
    use OCP\AppFramework\Db\DoesNotExistException;

    class FormVersion
    {
        private ?int $id = null;
        private string $formId = '';
        private int $versionNumber = 0;
        private string $status = 'draft';
        private string $createdBy = '';
        private int $createdAt = 0;
        private int $updatedAt = 0;
        private ?int $publishedAt = null;

        public function getId(): ?int { return $this->id; }
        public function setId(int $id): void { $this->id = $id; }
        public function getFormId(): string { return $this->formId; }
        public function setFormId(string $formId): void { $this->formId = $formId; }
        public function getVersionNumber(): int { return $this->versionNumber; }
        public function setVersionNumber(int $version): void { $this->versionNumber = $version; }
        public function getStatus(): string { return $this->status; }
        public function setStatus(string $status): void { $this->status = $status; }
        public function setCreatedBy(string $createdBy): void { $this->createdBy = $createdBy; }
        public function setCreatedAt(int $createdAt): void { $this->createdAt = $createdAt; }
        public function setUpdatedAt(int $updatedAt): void { $this->updatedAt = $updatedAt; }
        public function setPublishedAt(?int $publishedAt): void { $this->publishedAt = $publishedAt; }
    }

    class FormVersionMapper
    {
        /** @var list<FormVersion> */
        private array $versions;

        /** @param list<FormVersion> $versions */
        public function __construct(array $versions) { $this->versions = $versions; }
        /** @return list<FormVersion> */
        public function findAllByForm(string $formId): array
        {
            return array_values(array_filter($this->versions, static fn (FormVersion $version): bool => $version->getFormId() === $formId));
        }
        public function findDraft(string $formId): ?FormVersion
        {
            foreach ($this->findAllByForm($formId) as $version) {
                if ($version->getStatus() === 'draft') return $version;
            }
            return null;
        }
        public function findPublished(string $formId): ?FormVersion
        {
            foreach ($this->findAllByForm($formId) as $version) {
                if ($version->getStatus() === 'published') return $version;
            }
            return null;
        }
        public function findByFormAndVersion(string $formId, int $number): FormVersion
        {
            foreach ($this->findAllByForm($formId) as $version) {
                if ($version->getVersionNumber() === $number) return $version;
            }
            throw new DoesNotExistException();
        }
        public function nextVersion(string $formId): int
        {
            $numbers = array_map(static fn (FormVersion $version): int => $version->getVersionNumber(), $this->findAllByForm($formId));
            return $numbers === [] ? 1 : max($numbers) + 1;
        }
        public function insert(FormVersion $version): FormVersion
        {
            $version->setId(count($this->versions) + 1);
            $this->versions[] = $version;
            return $version;
        }
    }
}

namespace {
    use OCA\CareForms\Db\FormVersion;
    use OCA\CareForms\Db\FormVersionMapper;
    use OCA\CareForms\Service\FormVersionService;

    require_once __DIR__ . '/../lib/Service/FormVersionService.php';

    function lifecycleVersion(string $formId, int $number, string $status): FormVersion
    {
        $version = new FormVersion();
        $version->setFormId($formId);
        $version->setVersionNumber($number);
        $version->setStatus($status);
        return $version;
    }

    $mapper = new FormVersionMapper([
        lifecycleVersion('assessment', 1, 'archived'),
        lifecycleVersion('assessment', 2, 'published'),
    ]);
    $service = new FormVersionService($mapper);

    if ($service->version('assessment', 1)->getStatus() !== 'archived') {
        throw new RuntimeException('Historical versions must be addressable for restore.');
    }

    try {
        $service->version('assessment', 99);
        throw new RuntimeException('A missing restore source must be rejected.');
    } catch (InvalidArgumentException) {
        // Expected.
    }

    $draft = $service->createDraftStrict('assessment', 'admin');
    if ($draft->getVersionNumber() !== 3 || $draft->getStatus() !== 'draft') {
        throw new RuntimeException('Restore must allocate the next monotonic draft version.');
    }

    try {
        $service->createDraftStrict('assessment', 'admin');
        throw new RuntimeException('A second restore must not reuse an existing draft.');
    } catch (LogicException) {
        // Expected.
    }

    fwrite(STDOUT, "FormVersionService tests passed.\n");
}
