<?php

declare(strict_types=1);

namespace OCA\CareForms\Service;

use OCA\CareForms\Db\Submission;

final class SubmissionIntegrityPresenter
{
    public function __construct(
        private FormDefinitionService $definitions,
        private SubmissionIntegrityService $integrity,
    ) {
    }

    /** @return array<string, mixed> */
    public function serialize(Submission $submission): array
    {
        $serialized = $submission->jsonSerialize();
        $serialized['integrity'] = $this->verification($submission);
        return $serialized;
    }

    /** @return array{status: string, valid: bool|null, version: int|null, message: string} */
    public function verification(Submission $submission): array
    {
        if ($submission->getIntegrityHash() === null || $submission->getIntegrityHash() === '') {
            return $this->integrity->verify([], $this->record($submission));
        }
        if ($submission->getIntegrityVersion() !== SubmissionIntegrityService::VERSION) {
            return $this->integrity->verify([], $this->record($submission));
        }
        try {
            $definition = $this->definitions->getRestorableVersion($submission->getFormId(), (int)$submission->getFormVersion());
            return $this->integrity->verify($definition, $this->record($submission));
        } catch (\Throwable) {
            return [
                'status' => 'unavailable',
                'valid' => false,
                'version' => $submission->getIntegrityVersion(),
                'message' => 'The exact historical form definition is unavailable, so integrity cannot be verified.',
            ];
        }
    }

    /** @return array<string, mixed> */
    public function record(Submission $submission): array
    {
        return [
            'formId' => $submission->getFormId(),
            'formVersion' => $submission->getFormVersion(),
            'patientId' => $submission->getPatientId(),
            'data' => $submission->getData(),
            'signedBy' => $submission->getSignedBy(),
            'signerName' => $submission->getSignerName(),
            'signedAt' => $submission->getSignedAt(),
            'signatureData' => $submission->getSignatureData(),
            'integrityVersion' => $submission->getIntegrityVersion(),
            'definitionHash' => $submission->getDefinitionHash(),
            'integrityHash' => $submission->getIntegrityHash(),
        ];
    }
}
