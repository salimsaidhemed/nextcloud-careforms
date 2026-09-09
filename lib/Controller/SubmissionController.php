<?php

declare(strict_types=1);

namespace OCA\CareForms\Controller;

use OCA\CareForms\Db\Submission;
use OCA\CareForms\Db\SubmissionMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IUserSession;

class SubmissionController extends Controller
{
    public function __construct(
        string $appName,
        IRequest $request,
        private SubmissionMapper $mapper,
        private IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function index(): JSONResponse
    {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['message' => 'Authentication required.'], Response::STATUS_UNAUTHORIZED);
        }

        return new JSONResponse(array_map(
            static fn (Submission $submission): array => $submission->jsonSerialize(),
            $this->mapper->findAllByUser($userId),
        ));
    }

    #[NoAdminRequired]
    public function show(int $id): JSONResponse
    {
        $submission = $this->findOwnedSubmission($id);
        if ($submission instanceof JSONResponse) {
            return $submission;
        }

        return new JSONResponse($submission->jsonSerialize());
    }

    #[NoAdminRequired]
    public function create(string $formId, string $formVersion, array $data = []): JSONResponse
    {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['message' => 'Authentication required.'], Response::STATUS_UNAUTHORIZED);
        }

        if ($formId === '' || $formVersion === '') {
            return new JSONResponse(['message' => 'formId and formVersion are required.'], Response::STATUS_BAD_REQUEST);
        }

        $now = time();
        $submission = new Submission();
        $submission->setUserId($userId);
        $submission->setFormId($formId);
        $submission->setFormVersion($formVersion);
        $submission->setStatus('draft');
        $submission->setData($this->encodeData($data));
        $submission->setCreatedAt($now);
        $submission->setUpdatedAt($now);

        $saved = $this->mapper->insert($submission);
        return new JSONResponse($saved->jsonSerialize(), Response::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function update(int $id, array $data = []): JSONResponse
    {
        $submission = $this->findOwnedSubmission($id);
        if ($submission instanceof JSONResponse) {
            return $submission;
        }

        if ($submission->getStatus() !== 'draft') {
            return new JSONResponse(['message' => 'Submitted forms are read-only.'], Response::STATUS_CONFLICT);
        }

        $submission->setData($this->encodeData($data));
        $submission->setUpdatedAt(time());
        $saved = $this->mapper->update($submission);

        return new JSONResponse($saved->jsonSerialize());
    }

    #[NoAdminRequired]
    public function submit(int $id, array $data = []): JSONResponse
    {
        $submission = $this->findOwnedSubmission($id);
        if ($submission instanceof JSONResponse) {
            return $submission;
        }

        if ($submission->getStatus() !== 'draft') {
            return new JSONResponse(['message' => 'This form has already been submitted.'], Response::STATUS_CONFLICT);
        }

        $now = time();
        $submission->setData($this->encodeData($data));
        $submission->setStatus('submitted');
        $submission->setUpdatedAt($now);
        $submission->setSubmittedAt($now);
        $saved = $this->mapper->update($submission);

        return new JSONResponse($saved->jsonSerialize());
    }

    private function getUserId(): ?string
    {
        return $this->userSession->getUser()?->getUID();
    }

    private function findOwnedSubmission(int $id): Submission|JSONResponse
    {
        $userId = $this->getUserId();
        if ($userId === null) {
            return new JSONResponse(['message' => 'Authentication required.'], Response::STATUS_UNAUTHORIZED);
        }

        try {
            return $this->mapper->findByIdAndUser($id, $userId);
        } catch (DoesNotExistException) {
            return new JSONResponse(['message' => 'Submission not found.'], Response::STATUS_NOT_FOUND);
        }
    }

    private function encodeData(array $data): string
    {
        $encoded = json_encode($data, JSON_THROW_ON_ERROR);
        return $encoded === false ? '{}' : $encoded;
    }
}
