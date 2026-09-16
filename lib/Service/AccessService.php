<?php

declare(strict_types=1);

namespace OCA\CareForms\Service;

use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserSession;

class AccessService
{
    public const GROUP_AIDES = 'CareForms Aides';
    public const GROUP_NURSES = 'CareForms Nurses';
    public const GROUP_SUPERVISORS = 'CareForms Supervisors';
    public const GROUP_REPORT_VIEWERS = 'CareForms Report Viewers';
    public const GROUP_ADMINISTRATORS = 'CareForms Administrators';

    public const FORM_HOME_HEALTH_AIDE = 'home-health-aide-note';
    public const FORM_NURSES_PROGRESS_NOTE = 'nurses-progress-note';

    public const FORM_PERMISSION_FILL = 'fill';
    public const FORM_PERMISSION_REVIEW = 'review';
    public const FORM_PERMISSION_REPORT = 'report';
    private const FORM_PERMISSION_TYPES = [
        self::FORM_PERMISSION_FILL,
        self::FORM_PERMISSION_REVIEW,
        self::FORM_PERMISSION_REPORT,
    ];

    public function __construct(
        private IGroupManager $groupManager,
        private IUserSession $userSession,
        private IConfig $config,
        private FormDefinitionService $definitions,
    ) {}

    public function currentUserId(): ?string
    {
        return $this->userSession->getUser()?->getUID();
    }

    public function isCareFormsAdministrator(?string $userId = null): bool
    {
        $userId ??= $this->currentUserId();
        return $userId !== null && ($this->groupManager->isAdmin($userId) || $this->groupManager->isInGroup($userId, self::GROUP_ADMINISTRATORS));
    }

    public function roles(?string $userId = null): array
    {
        $userId ??= $this->currentUserId();
        if ($userId === null) return [];

        $roles = [];
        if ($this->groupManager->isAdmin($userId)) $roles[] = 'nextcloud_admin';
        foreach ([
            self::GROUP_AIDES => 'aide',
            self::GROUP_NURSES => 'nurse',
            self::GROUP_SUPERVISORS => 'supervisor',
            self::GROUP_REPORT_VIEWERS => 'report_viewer',
            self::GROUP_ADMINISTRATORS => 'careforms_administrator',
        ] as $group => $role) {
            if ($this->groupManager->isInGroup($userId, $group)) $roles[] = $role;
        }
        return $roles;
    }

    public function capabilities(?string $userId = null): array
    {
        $userId ??= $this->currentUserId();
        if ($userId === null) return [];

        if ($this->isCareFormsAdministrator($userId)) {
            return [
                'form.view', 'form.submit', 'submission.view_own', 'submission.review',
                'patient.select', 'patient.view', 'patient.manage',
                'report.view', 'report.detail', 'report.export',
                'form.manage', 'permissions.manage', 'settings.manage',
            ];
        }

        $caps = [];
        if ($this->groupManager->isInGroup($userId, self::GROUP_AIDES)
            || $this->groupManager->isInGroup($userId, self::GROUP_NURSES)
            || $this->groupManager->isInGroup($userId, self::GROUP_SUPERVISORS)) {
            $caps = array_merge($caps, ['form.view', 'form.submit', 'submission.view_own', 'patient.select']);
        }
        if ($this->groupManager->isInGroup($userId, self::GROUP_SUPERVISORS)) {
            $caps = array_merge($caps, ['submission.review', 'patient.view', 'patient.manage']);
        }
        if ($this->groupManager->isInGroup($userId, self::GROUP_REPORT_VIEWERS)) {
            $caps = array_merge($caps, ['report.view', 'report.detail']);
        }

        return array_values(array_unique($caps));
    }

    public function isFormEnabled(string $formId): bool
    {
        return $this->config->getAppValue('careforms', 'form_enabled_' . $formId, '1') === '1';
    }

    public function setFormEnabled(string $formId, bool $enabled): void
    {
        $this->config->setAppValue('careforms', 'form_enabled_' . $formId, $enabled ? '1' : '0');
    }


    /**
     * @return list<string>
     */
    public function formPermissionGroups(string $formId, string $permission): array
    {
        $this->assertFormPermissionType($permission);
        $raw = $this->config->getAppValue(
            'careforms',
            'form_permission_' . $permission . '_' . $formId,
            '',
        );
        if ($raw === '') return [];

        try {
            $groups = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        if (!is_array($groups)) return [];

        return array_values(array_unique(array_filter(
            array_map(static fn ($group): string => trim((string)$group), $groups),
            static fn (string $group): bool => $group !== '',
        )));
    }

    /**
     * @param list<string> $groups
     */
    public function setFormPermissionGroups(string $formId, string $permission, array $groups): void
    {
        $this->assertFormPermissionType($permission);
        if (!$this->definitions->exists($formId)) {
            throw new \InvalidArgumentException(sprintf('Unknown CareForms form "%s".', $formId));
        }

        $groups = array_values(array_unique(array_filter(
            array_map(static fn ($group): string => trim((string)$group), $groups),
            static fn (string $group): bool => $group !== '',
        )));
        $this->config->setAppValue(
            'careforms',
            'form_permission_' . $permission . '_' . $formId,
            json_encode($groups, JSON_THROW_ON_ERROR),
        );
    }

    public function hasConfiguredFormPermission(string $formId, string $permission): bool
    {
        $this->assertFormPermissionType($permission);
        return $this->config->getAppValue(
            'careforms',
            'form_permission_' . $permission . '_' . $formId,
            '',
        ) !== '';
    }

    public function userHasFormPermission(string $formId, string $permission, ?string $userId = null): bool
    {
        $userId ??= $this->currentUserId();
        if ($userId === null || !$this->isFormEnabled($formId)) return false;

        // CareForms/Nextcloud administrators retain break-glass application access.
        if ($this->isCareFormsAdministrator($userId)) return true;

        if (!$this->hasConfiguredFormPermission($formId, $permission)) {
            return $this->legacyFormPermission($formId, $permission, $userId);
        }

        foreach ($this->formPermissionGroups($formId, $permission) as $group) {
            if ($this->groupManager->isInGroup($userId, $group)) return true;
        }
        return false;
    }

    public function canFillForm(string $formId, ?string $userId = null): bool
    {
        return $this->userHasFormPermission($formId, self::FORM_PERMISSION_FILL, $userId);
    }

    public function canReviewForm(string $formId, ?string $userId = null): bool
    {
        return $this->userHasFormPermission($formId, self::FORM_PERMISSION_REVIEW, $userId);
    }

    public function canReportOnForm(string $formId, ?string $userId = null): bool
    {
        return $this->userHasFormPermission($formId, self::FORM_PERMISSION_REPORT, $userId);
    }

    private function legacyFormPermission(string $formId, string $permission, string $userId): bool
    {
        if ($permission === self::FORM_PERMISSION_FILL) {
            if ($this->groupManager->isInGroup($userId, self::GROUP_SUPERVISORS)) return true;
            if ($formId === self::FORM_HOME_HEALTH_AIDE) return $this->groupManager->isInGroup($userId, self::GROUP_AIDES);
            if ($formId === self::FORM_NURSES_PROGRESS_NOTE) return $this->groupManager->isInGroup($userId, self::GROUP_NURSES);
            return false;
        }
        if ($permission === self::FORM_PERMISSION_REVIEW) {
            return $this->groupManager->isInGroup($userId, self::GROUP_SUPERVISORS);
        }
        if ($permission === self::FORM_PERMISSION_REPORT) {
            return $this->groupManager->isInGroup($userId, self::GROUP_REPORT_VIEWERS);
        }
        return false;
    }

    private function assertFormPermissionType(string $permission): void
    {
        if (!in_array($permission, self::FORM_PERMISSION_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported form permission "%s".', $permission));
        }
    }

    public function allowedForms(?string $userId = null): array
    {
        $userId ??= $this->currentUserId();
        if ($userId === null) return [];

        $forms = [];
        foreach ($this->definitions->all() as $definition) {
            $formId = (string)$definition['id'];
            if ($this->canFillForm($formId, $userId)) $forms[] = $formId;
        }
        return array_values(array_unique($forms));
    }

    public function canAccessForm(string $formId, ?string $userId = null): bool { return in_array($formId, $this->allowedForms($userId), true); }
    public function canViewReports(?string $userId = null): bool { return in_array('report.view', $this->capabilities($userId), true); }
    public function canViewDetailedReports(?string $userId = null): bool { return in_array('report.detail', $this->capabilities($userId), true); }
    public function canManageForms(?string $userId = null): bool { return in_array('form.manage', $this->capabilities($userId), true); }
    public function canSelectPatients(?string $userId = null): bool { return in_array('patient.select', $this->capabilities($userId), true); }
    public function canViewPatientDetails(?string $userId = null): bool { return in_array('patient.view', $this->capabilities($userId), true); }
    public function canManagePatients(?string $userId = null): bool { return in_array('patient.manage', $this->capabilities($userId), true); }
}
