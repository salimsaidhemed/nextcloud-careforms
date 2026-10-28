<?php

declare(strict_types=1);

namespace OCA\CareForms\Controller;

use OCA\CareForms\Db\Submission;
use OCA\CareForms\Db\SubmissionAddendum;
use OCA\CareForms\Db\SubmissionAddendumMapper;
use OCA\CareForms\Db\SubmissionChangeRequest;
use OCA\CareForms\Db\SubmissionChangeRequestMapper;
use OCA\CareForms\Db\SubmissionMapper;
use OCA\CareForms\Service\AccessService;
use OCA\CareForms\Service\AddendumIntegrityService;
use OCA\CareForms\Service\AuditService;
use OCA\CareForms\Service\ChangeRequestService;
use OCA\CareForms\Service\FormDefinitionService;
use OCA\CareForms\Service\SubmissionDataValidator;
use OCA\CareForms\Service\SubmissionIntegrityPresenter;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class SubmissionAddendumController extends Controller
{
    public function __construct(
        IRequest $request,
        private SubmissionMapper $submissions,
        private SubmissionAddendumMapper $addenda,
        private SubmissionChangeRequestMapper $requests,
        private AccessService $access,
        private AuditService $audit,
        private FormDefinitionService $definitions,
        private SubmissionDataValidator $validator,
        private AddendumIntegrityService $integrity,
        private ChangeRequestService $changeRequests,
        private SubmissionIntegrityPresenter $submissionPresenter,
    ) {
        parent::__construct('careforms', $request);
    }

    #[NoAdminRequired]
    public function pending(): JSONResponse
    {
        $userId = $this->access->currentUserId();
        if ($userId === null) return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        $items = [];
        foreach ($this->addenda->findByStatus('submitted') as $item) {
            try { $submission = $this->submissions->findById($item->getSubmissionId()); }
            catch (DoesNotExistException | MultipleObjectsReturnedException) { continue; }
            if (!$this->access->canReviewForm($submission->getFormId(), $userId)) continue;
            if ($item->getAuthorId() === $userId) continue;
            $items[] = ['addendum' => $this->serialize($item, $submission), 'submission' => $this->submissionPresenter->serialize($submission)];
        }
        return new JSONResponse($items);
    }

    #[NoAdminRequired]
    public function index(int $submissionId): JSONResponse
    {
        $submission = $this->authorizedSubmission($submissionId, false);
        if ($submission instanceof JSONResponse) return $submission;
        $userId = (string)$this->access->currentUserId();
        $items = array_map(fn (SubmissionAddendum $item): array => $this->serialize($item, $submission), $this->addenda->findBySubmission($submissionId));
        $this->audit->log($userId, 'ADDENDUM_VIEW', 'submission', $submissionId, $submission->getFormId());
        return new JSONResponse($items);
    }

    #[NoAdminRequired]
    public function create(int $submissionId, string $reason, string $note = '', array $changes = [], ?int $changeRequestId = null): JSONResponse
    {
        $submission = $this->authorizedSubmission($submissionId, true);
        if ($submission instanceof JSONResponse) return $submission;
        if ($submission->getSubmittedAt() === null || !in_array($submission->getStatus(), ['submitted', 'approved', 'returned'], true)) {
            return new JSONResponse(['message' => 'Addenda may only be attached to a finalized submission.'], Http::STATUS_CONFLICT);
        }
        $payload = $this->payload($submission, $reason, $note, $changes);
        if ($payload instanceof JSONResponse) return $payload;
        $changeRequest = $this->linkedRequest($changeRequestId, $submissionId);
        if ($changeRequest instanceof JSONResponse) return $changeRequest;
        $now = time();
        $item = new SubmissionAddendum();
        $item->setSubmissionId($submissionId);
        $item->setSequenceNumber($this->addenda->nextSequence($submissionId));
        $item->setChangeRequestId($changeRequest?->getId());
        $item->setAuthorId((string)$this->access->currentUserId());
        $item->setReason(trim($reason));
        $item->setData($payload);
        $item->setStatus('draft');
        $item->setCreatedAt($now);
        $item->setUpdatedAt($now);
        try { $saved = $this->addenda->insert($item); }
        catch (\Throwable) { return new JSONResponse(['message' => 'Could not create the addendum. Please try again.'], Http::STATUS_CONFLICT); }
        $this->audit->log($item->getAuthorId(), 'ADDENDUM_CREATE', 'addendum', $saved->getId(), $submission->getFormId(), 'success', ['submissionId' => $submissionId, 'sequence' => $saved->getSequenceNumber()]);
        return new JSONResponse($this->serialize($saved, $submission), Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function update(int $id, string $reason, string $note = '', array $changes = []): JSONResponse
    {
        $item = $this->findAddendum($id);
        if ($item instanceof JSONResponse) return $item;
        $submission = $this->authorizedSubmission($item->getSubmissionId(), true);
        if ($submission instanceof JSONResponse) return $submission;
        $userId = (string)$this->access->currentUserId();
        if ($item->getAuthorId() !== $userId) return new JSONResponse(['message' => 'Only the addendum author can edit this draft.'], Http::STATUS_FORBIDDEN);
        if ($item->getStatus() !== 'draft') return new JSONResponse(['message' => 'Submitted addenda are immutable. Create another addendum instead.'], Http::STATUS_CONFLICT);
        $payload = $this->payload($submission, $reason, $note, $changes, $item->getId());
        if ($payload instanceof JSONResponse) return $payload;
        $item->setReason(trim($reason));
        $item->setData($payload);
        $item->setUpdatedAt(time());
        $saved = $this->addenda->update($item);
        $this->audit->log($userId, 'ADDENDUM_UPDATE', 'addendum', $id, $submission->getFormId());
        return new JSONResponse($this->serialize($saved, $submission));
    }

    #[NoAdminRequired]
    public function submit(int $id): JSONResponse
    {
        $item = $this->findAddendum($id);
        if ($item instanceof JSONResponse) return $item;
        $submission = $this->authorizedSubmission($item->getSubmissionId(), true);
        if ($submission instanceof JSONResponse) return $submission;
        $userId = (string)$this->access->currentUserId();
        if ($item->getAuthorId() !== $userId) return new JSONResponse(['message' => 'Only the addendum author can submit it.'], Http::STATUS_FORBIDDEN);
        if ($item->getStatus() !== 'draft') return new JSONResponse(['message' => 'This addendum has already been finalized.'], Http::STATUS_CONFLICT);
        if ($this->submissionPresenter->verification($submission)['valid'] !== true) {
            return new JSONResponse(['message' => 'The original submission integrity could not be verified. This addendum cannot be finalized.'], Http::STATUS_CONFLICT);
        }
        $decoded = json_decode($item->getData(), true);
        $errors = $this->validateEffectiveData($submission, is_array($decoded['changes'] ?? null) ? $decoded['changes'] : [], $item->getId());
        if ($errors !== []) return new JSONResponse(['message' => 'The addendum would make the effective record invalid.', 'errors' => $errors], Http::STATUS_BAD_REQUEST);
        $now = time();
        $item->setStatus('submitted');
        $item->setSubmittedAt($now);
        $item->setUpdatedAt($now);
        $item->setIntegrityHash($this->integrity->hash($this->integrityMaterial($item, $submission)));
        $saved = $this->addenda->update($item);
        if ($item->getChangeRequestId() !== null) {
            $request = $this->linkedRequest($item->getChangeRequestId(), (int)$submission->getId(), false);
            if ($request instanceof SubmissionChangeRequest) $this->changeRequests->markResponded($request, (int)$saved->getId(), $now, $userId, $submission->getFormId());
        }
        $this->audit->log($userId, 'ADDENDUM_SUBMIT', 'addendum', $id, $submission->getFormId(), 'success', ['submissionId' => $submission->getId()]);
        return new JSONResponse($this->serialize($saved, $submission));
    }

    #[NoAdminRequired]
    public function review(int $id, string $action, ?string $note = null): JSONResponse
    {
        $item = $this->findAddendum($id);
        if ($item instanceof JSONResponse) return $item;
        $submission = $this->authorizedSubmission($item->getSubmissionId(), false);
        if ($submission instanceof JSONResponse) return $submission;
        $userId = (string)$this->access->currentUserId();
        if (!$this->access->canReviewForm($submission->getFormId(), $userId)) return new JSONResponse(['message' => 'You do not have permission to review this addendum.'], Http::STATUS_FORBIDDEN);
        if ($item->getAuthorId() === $userId) return new JSONResponse(['message' => 'Addendum authors cannot review their own addenda.'], Http::STATUS_CONFLICT);
        if ($item->getStatus() !== 'submitted') return new JSONResponse(['message' => 'Only submitted addenda can be reviewed.'], Http::STATUS_CONFLICT);
        if (!in_array($action, ['approve', 'return'], true)) return new JSONResponse(['message' => 'Review action must be approve or return.'], Http::STATUS_BAD_REQUEST);
        $note = trim((string)$note);
        if ($action === 'return' && $note === '') return new JSONResponse(['message' => 'A return note is required.'], Http::STATUS_BAD_REQUEST);
        if (!$this->integrity->verify($this->integrityMaterial($item, $submission), $item->getIntegrityHash())) return new JSONResponse(['message' => 'Addendum integrity verification failed.'], Http::STATUS_CONFLICT);
        if ($action === 'approve') {
            $decoded = json_decode($item->getData(), true);
            $errors = $this->validateEffectiveData($submission, is_array($decoded['changes'] ?? null) ? $decoded['changes'] : [], $item->getId());
            if ($errors !== []) {
                return new JSONResponse([
                    'message' => 'This addendum can no longer be approved because the effective record would be invalid.',
                    'errors' => $errors,
                ], Http::STATUS_CONFLICT);
            }
        }
        $reviewedAt = time();
        $item->setStatus($action === 'approve' ? 'approved' : 'returned');
        $item->setReviewedBy($userId);
        $item->setReviewedAt($reviewedAt);
        $item->setReviewNote($note !== '' ? $note : null);
        $item->setUpdatedAt($reviewedAt);
        $saved = $this->addenda->update($item);
        if ($item->getChangeRequestId() !== null) {
            $request = $this->linkedRequest($item->getChangeRequestId(), (int)$submission->getId(), false);
            if ($request instanceof SubmissionChangeRequest) $this->changeRequests->completeResponse($request, $action === 'approve', $reviewedAt, $userId, $submission->getFormId());
        }
        $hasOutstandingRequest = false;
        foreach ($this->requests->findBySubmission((int)$submission->getId()) as $request) {
            if ($request->getStatus() !== 'resolved') { $hasOutstandingRequest = true; break; }
        }
        if ($action === 'approve' && $submission->getStatus() === 'returned' && !$hasOutstandingRequest) {
            $previousReviewNote = trim((string)$submission->getReviewNote());
            $approvalNote = 'Approved through addendum #' . $item->getSequenceNumber() . '.';
            if ($previousReviewNote !== '') $approvalNote .= ' Previous correction request: ' . $previousReviewNote;
            $submission->setStatus('approved');
            $submission->setReviewedBy($userId);
            $submission->setReviewedAt($reviewedAt);
            $submission->setReviewNote($approvalNote);
            $submission->setUpdatedAt($reviewedAt);
            $this->submissions->update($submission);
        }
        $this->audit->log($userId, 'ADDENDUM_REVIEW', 'addendum', $id, $submission->getFormId(), 'success', ['decision' => $action, 'submissionId' => $submission->getId()]);
        return new JSONResponse($this->serialize($saved, $submission));
    }

    private function authorizedSubmission(int $id, bool $write): Submission|JSONResponse
    {
        $userId = $this->access->currentUserId();
        if ($userId === null) return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        try { $submission = $this->submissions->findById($id); }
        catch (DoesNotExistException | MultipleObjectsReturnedException) { return new JSONResponse(['message' => 'Submission not found.'], Http::STATUS_NOT_FOUND); }
        $owner = $submission->getUserId() === $userId && $this->access->canFillForm($submission->getFormId(), $userId);
        $reviewer = $this->access->canReviewForm($submission->getFormId(), $userId);
        $reporter = !$write && $this->access->canReportOnForm($submission->getFormId(), $userId);
        if (!$owner && !$reviewer && !$reporter) return new JSONResponse(['message' => 'You do not have permission to access addenda for this submission.'], Http::STATUS_FORBIDDEN);
        return $submission;
    }

    private function findAddendum(int $id): SubmissionAddendum|JSONResponse
    {
        try { return $this->addenda->findById($id); }
        catch (DoesNotExistException | MultipleObjectsReturnedException) { return new JSONResponse(['message' => 'Addendum not found.'], Http::STATUS_NOT_FOUND); }
    }

    private function linkedRequest(?int $id, int $submissionId, bool $requireOpen = true): SubmissionChangeRequest|JSONResponse|null
    {
        if ($id === null) return null;
        try { $request = $this->requests->findById($id); }
        catch (DoesNotExistException | MultipleObjectsReturnedException) { return new JSONResponse(['message' => 'The selected change request was not found.'], Http::STATUS_NOT_FOUND); }
        if ($request->getSubmissionId() !== $submissionId) return new JSONResponse(['message' => 'The selected change request belongs to another submission.'], Http::STATUS_BAD_REQUEST);
        if ($requireOpen && $request->getStatus() !== 'open') return new JSONResponse(['message' => 'The selected change request already has a response.'], Http::STATUS_CONFLICT);
        return $request;
    }

    private function payload(Submission $submission, string $reason, string $note, array $changes, ?int $excludeId = null): string|JSONResponse
    {
        if (trim($reason) === '') return new JSONResponse(['message' => 'An addendum reason is required.'], Http::STATUS_BAD_REQUEST);
        $note = trim($note);
        if ($note === '' && $changes === []) return new JSONResponse(['message' => 'Add a narrative note or at least one field correction.'], Http::STATUS_BAD_REQUEST);
        $definition = $this->definitions->getVersion($submission->getFormId(), (int)$submission->getFormVersion());
        $fieldIds = [];
        foreach ($definition['sections'] ?? [] as $section) foreach ($section['fields'] ?? [] as $field) {
            if (is_string($field['id'] ?? null)
                && !in_array($field['type'] ?? null, ['signature', 'signature-placeholder'], true)
                && ($field['readOnly'] ?? false) !== true
                && !isset($field['calculation'])) $fieldIds[$field['id']] = $field;
        }
        foreach ($changes as $fieldId => $value) {
            if (!is_string($fieldId) || !isset($fieldIds[$fieldId])) {
                return new JSONResponse(['message' => 'Addendum changes contain an unknown or protected field: ' . (string)$fieldId], Http::STATUS_BAD_REQUEST);
            }
            $valueError = $this->changeValueError($fieldIds[$fieldId], $value);
            if ($valueError !== null) return new JSONResponse(['message' => $valueError], Http::STATUS_BAD_REQUEST);
        }
        if ($changes !== []) {
            $errors = $this->validateEffectiveData($submission, $changes, $excludeId);
            if ($errors !== []) return new JSONResponse(['message' => 'The proposed field correction is invalid.', 'errors' => $errors], Http::STATUS_BAD_REQUEST);
        }
        return json_encode(['note' => $note, 'changes' => $changes], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $field */
    private function changeValueError(array $field, mixed $value): ?string
    {
        $label = (string)($field['label'] ?? $field['id'] ?? 'Field');
        $type = (string)($field['type'] ?? '');
        if (in_array($type, ['text', 'textarea', 'date', 'time'], true) && !is_string($value)) return $label . ' must be text.';
        if ($type === 'number' && !is_int($value) && !is_float($value)) return $label . ' must be numeric.';
        if ($type === 'checkbox' && !is_bool($value)) return $label . ' must be Yes or No.';
        if ($type === 'choice-group' && (!is_string($value) || !in_array($value, $field['options'] ?? [], true))) return $label . ' must use one of its configured choices.';
        if ($type === 'checkbox-group') {
            if (!is_array($value)) return $label . ' must be a list of choices.';
            foreach ($value as $option) if (!is_string($option) || !in_array($option, $field['options'] ?? [], true)) return $label . ' contains an unsupported choice.';
        }
        return null;
    }

    /** @param array<string, mixed> $changes @return list<string> */
    private function validateEffectiveData(Submission $submission, array $changes, ?int $excludeId = null): array
    {
        $effective = json_decode($submission->getData(), true);
        $effective = is_array($effective) ? $effective : [];
        foreach ($this->addenda->findBySubmission((int)$submission->getId()) as $item) {
            if ($item->getId() === $excludeId || $item->getStatus() !== 'approved') continue;
            $payload = json_decode($item->getData(), true);
            foreach (is_array($payload['changes'] ?? null) ? $payload['changes'] : [] as $key => $value) $effective[$key] = $value;
        }
        foreach ($changes as $key => $value) $effective[$key] = $value;
        $definition = $this->definitions->getVersion($submission->getFormId(), (int)$submission->getFormVersion());
        return $this->validator->validate($definition, $effective);
    }

    /** @return array<string, mixed> */
    private function integrityMaterial(SubmissionAddendum $item, Submission $submission): array
    {
        $material = [
            'submissionId' => $submission->getId(),
            'submissionIntegrityHash' => $submission->getIntegrityHash(),
            'sequenceNumber' => $item->getSequenceNumber(),
            'authorId' => $item->getAuthorId(),
            'reason' => $item->getReason(),
            'data' => $item->getData(),
            'createdAt' => $item->getCreatedAt(),
            'submittedAt' => $item->getSubmittedAt(),
        ];
        if ($item->getChangeRequestId() !== null) $material['changeRequestId'] = $item->getChangeRequestId();
        return $material;
    }

    /** @return array<string, mixed> */
    private function serialize(SubmissionAddendum $item, Submission $submission): array
    {
        $result = $item->jsonSerialize();
        $result['integrityValid'] = $item->getStatus() === 'draft' ? null : $this->integrity->verify($this->integrityMaterial($item, $submission), $item->getIntegrityHash());
        return $result;
    }
}
