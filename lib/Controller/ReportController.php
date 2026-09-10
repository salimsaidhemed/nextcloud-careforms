<?php

declare(strict_types=1);

namespace OCA\CareForms\Controller;

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

class ReportController extends Controller
{
    public function __construct(
        IRequest $request,
        private SubmissionMapper $mapper,
        private PatientMapper $patients,
        private AccessService $accessService,
        private AuditService $auditService,
    ) {
        parent::__construct('careforms', $request);
    }

    #[NoAdminRequired]
    public function overview(): JSONResponse
    {
        $userId = $this->requireReportAccess();
        if ($userId instanceof JSONResponse) return $userId;

        $submissions = $this->mapper->findAllReportable();
        $now = time();
        $thirtyDaysAgo = $now - (30 * 86400);
        $formTotals = [
            AccessService::FORM_HOME_HEALTH_AIDE => 0,
            AccessService::FORM_NURSES_PROGRESS_NOTE => 0,
        ];
        $recent = 0;
        $workers = [];
        $visitTypes = [];
        $painYes = 0;
        $medChangesYes = 0;
        $newOrdersYes = 0;
        $woundDocumented = 0;
        $aideMentalStatus = [];
        $aideActivities = [];
        $mealPercents = [];
        $daily = [];

        foreach ($submissions as $submission) {
            $formId = $submission->getFormId();
            $submittedAt = $submission->getSubmittedAt();
            $data = json_decode($submission->getData(), true);
            $data = is_array($data) ? $data : [];

            $formTotals[$formId] = ($formTotals[$formId] ?? 0) + 1;
            $workers[$submission->getUserId()] = true;
            if ($submittedAt !== null && $submittedAt >= $thirtyDaysAgo) $recent++;
            if ($submittedAt !== null) {
                $day = date('Y-m-d', $submittedAt);
                $daily[$day] = ($daily[$day] ?? 0) + 1;
            }

            if ($formId === AccessService::FORM_NURSES_PROGRESS_NOTE) {
                $visitType = trim((string)($data['visit_type'] ?? ''));
                if ($visitType !== '') $visitTypes[$visitType] = ($visitTypes[$visitType] ?? 0) + 1;
                if (($data['pain_present'] ?? null) === 'Yes') $painYes++;
                if (($data['medication_changes'] ?? null) === 'Yes') $medChangesYes++;
                if (($data['new_orders'] ?? null) === 'Yes') $newOrdersYes++;
                if (trim((string)($data['wound_location'] ?? '')) !== '') $woundDocumented++;
            }

            if ($formId === AccessService::FORM_HOME_HEALTH_AIDE) {
                foreach (($data['mental_status'] ?? []) as $value) $aideMentalStatus[$value] = ($aideMentalStatus[$value] ?? 0) + 1;
                foreach (($data['activity'] ?? []) as $value) $aideActivities[$value] = ($aideActivities[$value] ?? 0) + 1;
                if (isset($data['meal_eaten_percent']) && $data['meal_eaten_percent'] !== '') $mealPercents[] = (float)$data['meal_eaten_percent'];
            }
        }

        ksort($daily);
        arsort($visitTypes);
        arsort($aideMentalStatus);
        arsort($aideActivities);
        $this->auditService->log($userId, 'REPORT_VIEW', 'report', null, null, 'success', ['report' => 'overview']);

        return new JSONResponse([
            'generatedAt' => $now,
            'summary' => [
                'submittedTotal' => count($submissions),
                'submittedLast30Days' => $recent,
                'activeSubmitters' => count($workers),
                'aideSubmitted' => $formTotals[AccessService::FORM_HOME_HEALTH_AIDE] ?? 0,
                'nurseSubmitted' => $formTotals[AccessService::FORM_NURSES_PROGRESS_NOTE] ?? 0,
            ],
            'trend' => array_map(fn ($date, $count) => ['date' => $date, 'count' => $count], array_keys($daily), array_values($daily)),
            'nursing' => [
                'visitTypes' => $visitTypes,
                'painPresent' => $painYes,
                'medicationChanges' => $medChangesYes,
                'newOrders' => $newOrdersYes,
                'woundDocumented' => $woundDocumented,
            ],
            'aide' => [
                'mentalStatus' => $aideMentalStatus,
                'activities' => $aideActivities,
                'averageMealEatenPercent' => count($mealPercents) ? round(array_sum($mealPercents) / count($mealPercents), 1) : null,
            ],
        ]);
    }

    #[NoAdminRequired]
    public function submissions(): JSONResponse
    {
        $userId = $this->accessService->currentUserId();
        if ($userId === null) return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        if (!$this->accessService->canViewDetailedReports($userId)) {
            $this->auditService->log($userId, 'REPORT_VIEW', 'report', null, null, 'denied', ['reason' => 'detailed_report_access']);
            return new JSONResponse(['message' => 'You do not have permission to view detailed submission reports.'], Http::STATUS_FORBIDDEN);
        }

        $items = [];
        foreach ($this->mapper->findAllReportable() as $submission) {
            $patient = null;
            if ($submission->getPatientId() !== null) {
                try {
                    $patient = $this->patients->find($submission->getPatientId())->jsonSerialize();
                } catch (DoesNotExistException | MultipleObjectsReturnedException) {
                    $patient = null;
                }
            }

            $serialized = $submission->jsonSerialize();
            $serialized['userId'] = $submission->getUserId();
            $serialized['patient'] = $patient;
            $items[] = $serialized;
        }

        $this->auditService->log($userId, 'REPORT_VIEW', 'report', null, null, 'success', ['report' => 'submission_detail']);
        return new JSONResponse(['generatedAt' => time(), 'items' => $items]);
    }

    private function requireReportAccess(): string|JSONResponse
    {
        $userId = $this->accessService->currentUserId();
        if ($userId === null) return new JSONResponse(['message' => 'Authentication required.'], Http::STATUS_UNAUTHORIZED);
        if (!$this->accessService->canViewReports($userId)) {
            $this->auditService->log($userId, 'REPORT_VIEW', 'report', null, null, 'denied', ['reason' => 'report_access']);
            return new JSONResponse(['message' => 'You do not have permission to view reports.'], Http::STATUS_FORBIDDEN);
        }
        return $userId;
    }
}
