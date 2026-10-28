<?php

declare(strict_types=1);

namespace OCA\CareForms\Controller;

use OCA\CareForms\Db\Submission;
use OCA\CareForms\Db\SubmissionAddendumMapper;
use OCA\CareForms\Db\SubmissionChangeRequestMapper;
use OCA\CareForms\Db\SubmissionMapper;
use OCA\CareForms\Service\AccessService;
use OCA\CareForms\Service\AuditService;
use OCA\CareForms\Service\ChangeRequestService;
use OCA\CareForms\Service\FormDefinitionService;
use OCA\CareForms\Service\RecordHistoryPresenter;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class SubmissionHistoryController extends Controller
{
    public function __construct(
        IRequest $request,
        private SubmissionMapper $submissions,
        private SubmissionAddendumMapper $addenda,
        private SubmissionChangeRequestMapper $requests,
        private AccessService $access,
        private AuditService $audit,
        private ChangeRequestService $changeRequests,
        private FormDefinitionService $definitions,
        private RecordHistoryPresenter $presenter,
    ) {
        parent::__construct('careforms', $request);
    }

    #[NoAdminRequired]
    public function index(int $submissionId): JSONResponse
    {
        $submission = $this->authorizedSubmission($submissionId);
        if ($submission instanceof JSONResponse) return $submission;
        $definition = $this->definitions->getVersion($submission->getFormId(), (int)$submission->getFormVersion());
        $labels = [];
        foreach ($definition['sections'] ?? [] as $section) foreach ($section['fields'] ?? [] as $field) {
            if (is_string($field['id'] ?? null)) $labels[$field['id']] = (string)($field['label'] ?? $field['id']);
        }
        $effective = json_decode($submission->getData(), true);
        $effective = is_array($effective) ? $effective : [];
        $events = [[
            'type' => 'original',
            'timestamp' => $submission->getSubmittedAt(),
            'actorId' => $submission->getSignedBy() ?? $submission->getUserId(),
            'title' => 'Original signed submission',
            'status' => 'immutable',
        ]];
        $requestItems = $this->requests->findBySubmission($submissionId);
        $requestsById = [];
        foreach ($requestItems as $request) {
            $requestsById[(int)$request->getId()] = $request;
            $events[] = [
                'type' => 'change_request',
                'timestamp' => $request->getCreatedAt(),
                'actorId' => $request->getRequestedBy(),
                'title' => 'Change request #' . $request->getSequenceNumber(),
                'status' => $request->getStatus(),
                'request' => $request->jsonSerialize(),
            ];
        }
        foreach ($this->addenda->findBySubmission($submissionId) as $addendum) {
            $payload = json_decode($addendum->getData(), true);
            $changes = is_array($payload['changes'] ?? null) ? $payload['changes'] : [];
            $events[] = [
                'type' => 'addendum',
                'timestamp' => $addendum->getSubmittedAt() ?? $addendum->getCreatedAt(),
                'actorId' => $addendum->getAuthorId(),
                'title' => 'Addendum #' . $addendum->getSequenceNumber(),
                'status' => $addendum->getStatus(),
                'addendum' => $addendum->jsonSerialize(),
                'changeRequestSequence' => $addendum->getChangeRequestId() !== null && isset($requestsById[$addendum->getChangeRequestId()])
                    ? $requestsById[$addendum->getChangeRequestId()]->getSequenceNumber()
                    : null,
                'comparisons' => $this->presenter->compare($effective, $changes, $labels),
            ];
            if ($addendum->getReviewedAt() !== null) {
                $events[] = [
                    'type' => 'review_decision',
                    'timestamp' => $addendum->getReviewedAt(),
                    'actorId' => $addendum->getReviewedBy(),
                    'title' => $addendum->getStatus() === 'approved' ? 'Addendum approved' : 'Addendum returned',
                    'status' => $addendum->getStatus(),
                    'note' => $addendum->getReviewNote(),
                    'addendumId' => $addendum->getId(),
                ];
            }
            if ($addendum->getStatus() === 'approved') foreach ($changes as $fieldId => $value) $effective[$fieldId] = $value;
        }
        $reviewNote = (string)$submission->getReviewNote();
        $requestAlreadyRepresentsReturn = $submission->getStatus() === 'returned' && $requestItems !== [];
        if ($submission->getReviewedAt() !== null
            && !str_starts_with($reviewNote, 'Approved through addendum #')
            && !$requestAlreadyRepresentsReturn) {
            $events[] = [
                'type' => 'submission_review',
                'timestamp' => $submission->getReviewedAt(),
                'actorId' => $submission->getReviewedBy(),
                'title' => $submission->getStatus() === 'approved' ? 'Original submission approved' : 'Changes requested',
                'status' => $submission->getStatus(),
                'note' => $submission->getReviewNote(),
            ];
        }
        usort($events, static fn (array $a, array $b): int => (($a['timestamp'] ?? 0) <=> ($b['timestamp'] ?? 0)) ?: strcmp($a['type'], $b['type']));
        $this->audit->log((string)$this->access->currentUserId(), 'SUBMISSION_HISTORY_VIEW', 'submission', $submissionId, $submission->getFormId());
        return new JSONResponse([
            'events' => $events,
            'effectiveData' => $effective,
            'openRequests' => array_values(array_map(
                static fn ($request): array => $request->jsonSerialize(),
                array_filter($requestItems, static fn ($request): bool => $request->getStatus() === 'open'),
            )),
        ]);
    }

    #[NoAdminRequired]
    public function createRequest(int $submissionId, string $instructions): JSONResponse
    {
        $submission = $this->authorizedSubmission($submissionId);
        if ($submission instanceof JSONResponse) return $submission;
        $userId = (string)$this->access->currentUserId();
        if (!$this->access->canReviewForm($submission->getFormId(), $userId)) {
            $this->audit->log($userId, 'CHANGE_REQUEST_CREATE', 'submission', $submissionId, $submission->getFormId(), 'denied', ['reason' => 'review_permission']);
            return new JSONResponse(['message' => 'You do not have permission to request changes for this form.'], Http::STATUS_FORBIDDEN);
        }
        if ($submission->getSubmittedAt() === null || $submission->getStatus() === 'draft') {
            $this->audit->log($userId, 'CHANGE_REQUEST_CREATE', 'submission', $submissionId, $submission->getFormId(), 'denied', ['reason' => 'not_finalized']);
            return new JSONResponse(['message' => 'Changes can only be requested for a finalized submission.'], Http::STATUS_CONFLICT);
        }
        try { $saved = $this->changeRequests->create($submission, $userId, $instructions); }
        catch (\InvalidArgumentException $e) { return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST); }
        catch (\Throwable) { return new JSONResponse(['message' => 'Could not create the change request. Please try again.'], Http::STATUS_CONFLICT); }
        return new JSONResponse($saved->jsonSerialize(), Http::STATUS_CREATED);
    }

    private function authorizedSubmission(int $id): Submission|JSONResponse
    {
        $userId = $this->access->currentUserId();
        if ($userId === null) return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        try { $submission = $this->submissions->findById($id); }
        catch (DoesNotExistException | MultipleObjectsReturnedException) { return new JSONResponse(['message' => 'Submission not found.'], Http::STATUS_NOT_FOUND); }
        $owner = $submission->getUserId() === $userId && $this->access->canFillForm($submission->getFormId(), $userId);
        if (!$owner && !$this->access->canReviewForm($submission->getFormId(), $userId) && !$this->access->canReportOnForm($submission->getFormId(), $userId)) {
            return new JSONResponse(['message' => 'You do not have permission to view this record history.'], Http::STATUS_FORBIDDEN);
        }
        return $submission;
    }
}
