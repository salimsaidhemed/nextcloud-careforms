<?php

declare(strict_types=1);

namespace OCA\CareForms\Controller;

use OCA\CareForms\Db\SubmissionMapper;
use OCA\CareForms\Service\AccessService;
use OCA\CareForms\Service\AuditService;
use OCA\CareForms\Service\ChangeRequestService;
use OCA\CareForms\Service\SubmissionIntegrityPresenter;
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
        private ChangeRequestService $changeRequests,
        private SubmissionIntegrityPresenter $submissionPresenter,
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
            fn ($submission): bool => $this->accessService->canReviewForm($submission->getFormId(), $userId),
        ));
        return new JSONResponse(array_map(fn ($submission): array => $this->submissionPresenter->serialize($submission), $items));
    }

    #[NoAdminRequired]
    public function decide(int $id, string $action, ?string $note = null): JSONResponse
    {
        $userId = $this->requireReviewer();
        if ($userId instanceof JSONResponse) return $userId;

        try { $submission = $this->submissions->findById($id); }
        catch (DoesNotExistException | MultipleObjectsReturnedException) { return new JSONResponse(['message' => 'Submission not found.'], Http::STATUS_NOT_FOUND); }

        if (!$this->accessService->canReviewForm($submission->getFormId(), $userId)) {
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
        if ($action === 'approve') {
            $verification = $this->submissionPresenter->verification($submission);
            if ($verification['valid'] !== true) {
                $this->auditService->log($userId, 'SUBMISSION_REVIEW', 'submission', $id, $submission->getFormId(), 'denied', ['reason' => 'integrity']);
                return new JSONResponse(['message' => 'This submission cannot be approved because its integrity could not be verified.', 'integrity' => $verification], Http::STATUS_CONFLICT);
            }
        }

        if ($action === 'return') {
            try { $this->changeRequests->create($submission, $userId, $note); }
            catch (\InvalidArgumentException $e) { return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST); }
            catch (\Throwable) { return new JSONResponse(['message' => 'Could not create the change request. Please try again.'], Http::STATUS_CONFLICT); }
            $this->auditService->log($userId, 'SUBMISSION_REVIEW', 'submission', $id, $submission->getFormId(), 'success', ['decision' => 'return']);
            return new JSONResponse($this->submissionPresenter->serialize($submission));
        }

        $submission->setStatus('approved');
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

        return new JSONResponse($this->submissionPresenter->serialize($saved));
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
