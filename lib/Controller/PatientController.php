<?php

declare(strict_types=1);

namespace OCA\CareForms\Controller;

use OCA\CareForms\Db\Patient;
use OCA\CareForms\Db\PatientMapper;
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

class PatientController extends Controller
{
    public function __construct(
        IRequest $request,
        private PatientMapper $patients,
        private SubmissionMapper $submissions,
        private AccessService $accessService,
        private AuditService $auditService,
    ) {
        parent::__construct('careforms', $request);
    }

    #[NoAdminRequired]
    public function index(): JSONResponse
    {
        $userId = $this->accessService->currentUserId();
        if ($userId === null) return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        if (!$this->accessService->canSelectPatients($userId) && !$this->accessService->canViewPatientDetails($userId)) {
            return new JSONResponse(['message' => 'You do not have permission to view patients.'], Http::STATUS_FORBIDDEN);
        }
        return new JSONResponse(array_map(static fn (Patient $p): array => $p->jsonSerialize(), $this->patients->findAll()));
    }

    #[NoAdminRequired]
    public function create(string $medicalRecordNumber, string $firstName, string $lastName, ?string $dateOfBirth = null): JSONResponse
    {
        $userId = $this->accessService->currentUserId();
        if ($userId === null) return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        if (!$this->accessService->canManagePatients($userId)) return new JSONResponse(['message' => 'You do not have permission to add patients.'], Http::STATUS_FORBIDDEN);
        $medicalRecordNumber = trim($medicalRecordNumber); $firstName = trim($firstName); $lastName = trim($lastName);
        if ($medicalRecordNumber === '' || $firstName === '' || $lastName === '') return new JSONResponse(['message' => 'MR#, first name and last name are required.'], Http::STATUS_BAD_REQUEST);

        $now = time();
        $patient = new Patient();
        $patient->setMedicalRecordNumber($medicalRecordNumber);
        $patient->setFirstName($firstName);
        $patient->setLastName($lastName);
        $patient->setDateOfBirth($dateOfBirth ?: null);
        $patient->setStatus('active');
        $patient->setCreatedAt($now); $patient->setUpdatedAt($now);
        try { $saved = $this->patients->insert($patient); }
        catch (\Throwable $e) { return new JSONResponse(['message' => 'Could not add patient. The MR# may already exist.'], Http::STATUS_CONFLICT); }
        $this->auditService->log($userId, 'PATIENT_CREATE', 'patient', $saved->getId(), null, 'success', ['mrn' => $medicalRecordNumber]);
        return new JSONResponse($saved->jsonSerialize(), Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function show(int $id): JSONResponse
    {
        $userId = $this->accessService->currentUserId();
        if ($userId === null) return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        if (!$this->accessService->canViewPatientDetails($userId)) return new JSONResponse(['message' => 'You do not have permission to view patient details.'], Http::STATUS_FORBIDDEN);
        try { $patient = $this->patients->find($id); }
        catch (DoesNotExistException | MultipleObjectsReturnedException) { return new JSONResponse(['message' => 'Patient not found.'], Http::STATUS_NOT_FOUND); }
        $records = array_map(static fn ($s): array => $s->jsonSerialize(), $this->submissions->findAllByPatient($id));
        $this->auditService->log($userId, 'PATIENT_VIEW', 'patient', $id);
        return new JSONResponse(['patient' => $patient->jsonSerialize(), 'submissions' => $records]);
    }
}
