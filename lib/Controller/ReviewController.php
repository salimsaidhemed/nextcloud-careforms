<?php

declare(strict_types=1);

namespace OCA\CareForms\Controller;

use OCA\CareForms\Db\SubmissionMapper;
use OCA\CareForms\Service\AccessService;
use OCA\CareForms\Service\AuditService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class ReviewController extends Controller
{
    public function __construct(
        IRequest $request,
        private SubmissionMapper $submissions,
        private AccessService $accessService,
        private AuditService $auditService,
    ) {
        parent::__construct('careforms', $request);
    }

    #[NoAdminRequired]
    public function index(): JSONResponse
    {
        $userId = $this->requireReviewer();
        if ($userId instanceof JSONResponse) return $userId;

        $items = array_values(array_filter(
            $this->submissions->findAwaitingReview(),
            fn ($submission): bool => $this->accessService->canAccessForm($submission->getFormId(), $userId),
        ));
        return new JSONResponse(array_map(static fn ($submission): array => $submission->jsonSerialize(), $items));
    }

    #[NoAdminRequired]
    public function decide(int $id, string $action, ?string $note = null): JSONResponse
    {
        $userId = $this->requireReviewer();
        if ($userId instanceof JSONResponse) return $userId;

        try { $submission = $this->submissions->findById($id); }
        catch (DoesNotExistException | MultipleObjectsReturnedException) { return new JSONResponse(['message' => 'Submission not found.'], Http::STATUS_NOT_FOUND); }

        if (!$this->accessService->canAccessForm($submission->getFormId(), $userId)) {
            return new JSONResponse(['message' => 'You do not have permission to review this form.'], Http::STATUS_FORBIDDEN);
        }
        if ($submission->getStatus() !== 'submitted') {
            return new JSONResponse(['message' => 'Only submitted forms can be reviewed.'], Http::STATUS_CONFLICT);
        }
        if (!in_array($action, ['approve', 'return'], true)) {
            return new JSONResponse(['message' => 'Review action must be approve or return.'], Http::STATUS_BAD_REQUEST);
        }

        $note = trim((string)$note);
        if ($action === 'return' && $note === '') {
            return new JSONResponse(['message' => 'A return note is required so the submitter knows what to correct.'], Http::STATUS_BAD_REQUEST);
        }

        $submission->setStatus($action === 'approve' ? 'approved' : 'returned');
        $submission->setReviewedBy($userId);
        $submission->setReviewedAt(time());
        $submission->setReviewNote($note !== '' ? $note : null);
        $submission->setUpdatedAt(time());
        $saved = $this->submissions->update($submission);

        $this->auditService->log(
            $userId,
            'SUBMISSION_REVIEW',
            'submission',
            $saved->getId(),
            $saved->getFormId(),
            'success',
            ['decision' => $action]
        );

        return new JSONResponse($saved->jsonSerialize());
    }

    private function requireReviewer(): string|JSONResponse
    {
        $userId = $this->accessService->currentUserId();
        if ($userId === null) return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        if (!in_array('submission.review', $this->accessService->capabilities($userId), true)) {
            return new JSONResponse(['message' => 'You do not have permission to review submissions.'], Http::STATUS_FORBIDDEN);
        }
        return $userId;
    }
}
