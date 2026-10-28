<?php

declare(strict_types=1);

namespace OCA\CareForms\Service;

use OCA\CareForms\Db\Submission;
use OCA\CareForms\Db\SubmissionChangeRequest;
use OCA\CareForms\Db\SubmissionChangeRequestMapper;
use OCA\CareForms\Db\SubmissionMapper;

final class ChangeRequestService
{
    public function __construct(
        private SubmissionChangeRequestMapper $requests,
        private SubmissionMapper $submissions,
        private AuditService $audit,
    ) {
    }

    public function create(Submission $submission, string $requestedBy, string $instructions): SubmissionChangeRequest
    {
        $instructions = trim($instructions);
        if ($instructions === '') throw new \InvalidArgumentException('Change-request instructions are required.');
        if (mb_strlen($instructions) > 4000) throw new \InvalidArgumentException('Change-request instructions must not exceed 4000 characters.');
        $now = time();
        $request = new SubmissionChangeRequest();
        $request->setSubmissionId((int)$submission->getId());
        $request->setSequenceNumber($this->requests->nextSequence((int)$submission->getId()));
        $request->setRequestedBy($requestedBy);
        $request->setInstructions($instructions);
        $request->setStatus('open');
        $request->setCreatedAt($now);
        $saved = $this->requests->insert($request);

        $submission->setStatus('returned');
        $submission->setReviewedBy($requestedBy);
        $submission->setReviewedAt($now);
        $submission->setReviewNote($instructions);
        $submission->setUpdatedAt($now);
        $this->submissions->update($submission);
        $this->audit->log($requestedBy, 'CHANGE_REQUEST_CREATE', 'change_request', $saved->getId(), $submission->getFormId(), 'success', ['submissionId' => $submission->getId(), 'sequence' => $saved->getSequenceNumber()]);
        return $saved;
    }

    public function markResponded(SubmissionChangeRequest $request, int $addendumId, int $at, string $actorId, string $formId): void
    {
        $request->setStatus('responded');
        $request->setRespondedAt($at);
        $request->setResponseAddendumId($addendumId);
        $this->requests->update($request);
        $this->audit->log($actorId, 'CHANGE_REQUEST_RESPOND', 'change_request', $request->getId(), $formId, 'success', ['submissionId' => $request->getSubmissionId(), 'addendumId' => $addendumId]);
    }

    public function completeResponse(SubmissionChangeRequest $request, bool $approved, int $at, string $actorId, string $formId): void
    {
        $request->setStatus($approved ? 'resolved' : 'open');
        $request->setResolvedAt($approved ? $at : null);
        if (!$approved) {
            $request->setRespondedAt(null);
            $request->setResponseAddendumId(null);
        }
        $this->requests->update($request);
        $this->audit->log($actorId, $approved ? 'CHANGE_REQUEST_RESOLVE' : 'CHANGE_REQUEST_REOPEN', 'change_request', $request->getId(), $formId, 'success', ['submissionId' => $request->getSubmissionId()]);
    }
}
