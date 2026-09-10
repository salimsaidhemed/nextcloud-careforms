<?php

declare(strict_types=1);

namespace OCA\CareForms\Controller;

use OCA\CareForms\Db\PatientMapper;
use OCA\CareForms\Db\Submission;
use OCA\CareForms\Db\SubmissionMapper;
use OCA\CareForms\Service\AccessService;
use OCA\CareForms\Service\AuditService;
use OCA\CareForms\Service\FormVersionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

class SubmissionController extends Controller
{
    private const MAX_SIGNATURE_LENGTH = 200000;

    public function __construct(
        IRequest $request,
        private SubmissionMapper $mapper,
        private PatientMapper $patients,
        private IUserSession $userSession,
        private AccessService $accessService,
        private AuditService $auditService,
        private FormVersionService $formVersions,
    ) {
        parent::__construct('careforms', $request);
    }

    #[NoAdminRequired]
    public function index(): JSONResponse
    {
        $userId = $this->getUserId();
        if ($userId === null) return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        $submissions = array_values(array_filter(
            $this->mapper->findAllByUser($userId),
            fn (Submission $submission): bool => $this->accessService->canAccessForm($submission->getFormId(), $userId),
        ));
        return new JSONResponse(array_map(static fn (Submission $submission): array => $submission->jsonSerialize(), $submissions));
    }

    #[NoAdminRequired]
    public function show(int $id): JSONResponse
    {
        $submission = $this->findOwnedSubmission($id);
        if ($submission instanceof JSONResponse) return $submission;
        $userId = $this->getUserId();
        if ($userId !== null) $this->auditService->log($userId, 'SUBMISSION_VIEW', 'submission', $id, $submission->getFormId());
        return new JSONResponse($submission->jsonSerialize());
    }

    #[NoAdminRequired]
    public function create(string $formId, string|int $formVersion, ?int $patientId = null, array $data = []): JSONResponse
    {
        $userId = $this->getUserId();
        if ($userId === null) return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        if (!$this->accessService->canAccessForm($formId, $userId)) {
            $this->auditService->log($userId, 'SUBMISSION_CREATE', 'submission', null, $formId, 'denied', ['reason' => 'form_access']);
            return new JSONResponse(['message' => 'You do not have permission to use this form.'], Http::STATUS_FORBIDDEN);
        }

        $requestedVersion = (int)$formVersion;
        $publishedVersion = $this->formVersions->publishedVersion($formId);
        if ($requestedVersion !== $publishedVersion) {
            $this->auditService->log($userId, 'SUBMISSION_CREATE', 'submission', null, $formId, 'denied', ['reason' => 'form_version_changed']);
            return new JSONResponse([
                'message' => 'This form version is no longer current. Refresh CareForms and start the published version.',
                'publishedVersion' => $publishedVersion,
            ], Http::STATUS_CONFLICT);
        }
        if ($patientId === null || $patientId <= 0) return new JSONResponse(['message' => 'A patient must be selected before starting a form.'], Http::STATUS_BAD_REQUEST);

        try { $patient = $this->patients->find($patientId); }
        catch (DoesNotExistException | MultipleObjectsReturnedException) { return new JSONResponse(['message' => 'Selected patient was not found.'], Http::STATUS_NOT_FOUND); }
        if ($patient->getStatus() !== 'active') return new JSONResponse(['message' => 'Selected patient is not active.'], Http::STATUS_CONFLICT);

        $now = time();
        $submission = new Submission();
        $submission->setUserId($userId);
        $submission->setPatientId($patientId);
        $submission->setFormId($formId);
        $submission->setFormVersion((string)$publishedVersion);
        $submission->setStatus('draft');
        $submission->setData($this->encodeData($data));
        $submission->setCreatedAt($now);
        $submission->setUpdatedAt($now);
        $saved = $this->mapper->insert($submission);
        $this->auditService->log($userId, 'SUBMISSION_CREATE', 'submission', $saved->getId(), $formId, 'success', ['version' => $publishedVersion]);
        return new JSONResponse($saved->jsonSerialize(), Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function update(int $id, array $data = []): JSONResponse
    {
        $submission = $this->findOwnedSubmission($id);
        if ($submission instanceof JSONResponse) return $submission;
        $userId = $this->getUserId();
        if (!in_array($submission->getStatus(), ['draft', 'returned'], true)) {
            if ($userId !== null) $this->auditService->log($userId, 'SUBMISSION_UPDATE', 'submission', $id, $submission->getFormId(), 'denied', ['reason' => 'read_only_status']);
            return new JSONResponse(['message' => 'This form is read-only in its current workflow state.'], Http::STATUS_CONFLICT);
        }
        $submission->setData($this->encodeData($data));
        $submission->setUpdatedAt(time());
        $saved = $this->mapper->update($submission);
        if ($userId !== null) $this->auditService->log($userId, 'SUBMISSION_UPDATE', 'submission', $id, $submission->getFormId());
        return new JSONResponse($saved->jsonSerialize());
    }

    #[NoAdminRequired]
    public function submit(int $id, array $data = [], ?string $signatureData = null): JSONResponse
    {
        $submission = $this->findOwnedSubmission($id);
        if ($submission instanceof JSONResponse) return $submission;
        $user = $this->userSession->getUser();
        $userId = $user?->getUID();
        if ($userId === null) return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        if (!in_array($submission->getStatus(), ['draft', 'returned'], true)) {
            $this->auditService->log($userId, 'SUBMISSION_SUBMIT', 'submission', $id, $submission->getFormId(), 'denied', ['reason' => 'already_submitted']);
            return new JSONResponse(['message' => 'This form cannot be submitted in its current workflow state.'], Http::STATUS_CONFLICT);
        }
        if ($submission->getPatientId() === null) return new JSONResponse(['message' => 'This legacy draft has no patient assigned and cannot be submitted.'], Http::STATUS_CONFLICT);

        $signatureData = trim((string)$signatureData);
        if ($signatureData === '' || !str_starts_with($signatureData, 'data:image/png;base64,')) {
            return new JSONResponse(['message' => 'Draw your signature before submitting the form.'], Http::STATUS_BAD_REQUEST);
        }
        if (strlen($signatureData) > self::MAX_SIGNATURE_LENGTH) {
            return new JSONResponse(['message' => 'The captured signature is too large. Clear it and sign again.'], Http::STATUS_BAD_REQUEST);
        }

        $now = time();
        $encodedData = $this->encodeData($data);
        $integrityMaterial = implode('|', [
            $submission->getFormId(),
            $submission->getFormVersion(),
            (string)$submission->getPatientId(),
            $encodedData,
            $userId,
            (string)$now,
        ]);

        $submission->setData($encodedData);
        $submission->setStatus('submitted');
        $submission->setUpdatedAt($now);
        $submission->setSubmittedAt($now);
        $submission->setReviewedBy(null);
        $submission->setReviewedAt(null);
        $submission->setReviewNote(null);
        $submission->setSignedBy($userId);
        $submission->setSignerName($user->getDisplayName());
        $submission->setSignedAt($now);
        $submission->setSignatureData($signatureData);
        $submission->setIntegrityHash(hash('sha256', $integrityMaterial));
        $saved = $this->mapper->update($submission);
        $this->auditService->log($userId, 'SUBMISSION_SUBMIT', 'submission', $id, $submission->getFormId(), 'success', ['version' => (int)$submission->getFormVersion(), 'signed' => true]);
        return new JSONResponse($saved->jsonSerialize());
    }

    private function getUserId(): ?string
    {
        return $this->userSession->getUser()?->getUID();
    }

    private function findOwnedSubmission(int $id): Submission|JSONResponse
    {
        $userId = $this->getUserId();
        if ($userId === null) return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        try { $submission = $this->mapper->findByIdAndUser($id, $userId); }
        catch (DoesNotExistException | MultipleObjectsReturnedException) { return new JSONResponse(['message' => 'Submission not found.'], Http::STATUS_NOT_FOUND); }
        if (!$this->accessService->canAccessForm($submission->getFormId(), $userId)) {
            $this->auditService->log($userId, 'SUBMISSION_VIEW', 'submission', $id, $submission->getFormId(), 'denied', ['reason' => 'form_access']);
            return new JSONResponse(['message' => 'You no longer have permission to access this form.'], Http::STATUS_FORBIDDEN);
        }
        return $submission;
    }

    private function encodeData(array $data): string
    {
        $encoded = json_encode($data, JSON_THROW_ON_ERROR);
        return $encoded === false ? '{}' : $encoded;
    }
}
