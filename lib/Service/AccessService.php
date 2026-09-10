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

    public function __construct(
        private IGroupManager $groupManager,
        private IUserSession $userSession,
        private IConfig $config,
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

    public function allowedForms(?string $userId = null): array
    {
        $userId ??= $this->currentUserId();
        if ($userId === null) return [];

        if ($this->isCareFormsAdministrator($userId) || $this->groupManager->isInGroup($userId, self::GROUP_SUPERVISORS)) {
            $forms = [self::FORM_HOME_HEALTH_AIDE, self::FORM_NURSES_PROGRESS_NOTE];
        } else {
            $forms = [];
            if ($this->groupManager->isInGroup($userId, self::GROUP_AIDES)) $forms[] = self::FORM_HOME_HEALTH_AIDE;
            if ($this->groupManager->isInGroup($userId, self::GROUP_NURSES)) $forms[] = self::FORM_NURSES_PROGRESS_NOTE;
        }

        return array_values(array_filter($forms, fn (string $formId): bool => $this->isFormEnabled($formId)));
    }

    public function canAccessForm(string $formId, ?string $userId = null): bool { return in_array($formId, $this->allowedForms($userId), true); }
    public function canViewReports(?string $userId = null): bool { return in_array('report.view', $this->capabilities($userId), true); }
    public function canViewDetailedReports(?string $userId = null): bool { return in_array('report.detail', $this->capabilities($userId), true); }
    public function canManageForms(?string $userId = null): bool { return in_array('form.manage', $this->capabilities($userId), true); }
    public function canSelectPatients(?string $userId = null): bool { return in_array('patient.select', $this->capabilities($userId), true); }
    public function canViewPatientDetails(?string $userId = null): bool { return in_array('patient.view', $this->capabilities($userId), true); }
    public function canManagePatients(?string $userId = null): bool { return in_array('patient.manage', $this->capabilities($userId), true); }
}
