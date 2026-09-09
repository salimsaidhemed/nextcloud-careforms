<?php

declare(strict_types=1);

namespace OCA\CareForms\Service;

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

    public function __construct(
        private IGroupManager $groupManager,
        private IUserSession $userSession,
    ) {
    }

    public function currentUserId(): ?string
    {
        return $this->userSession->getUser()?->getUID();
    }

    public function isCareFormsAdministrator(?string $userId = null): bool
    {
        $userId ??= $this->currentUserId();
        if ($userId === null) {
            return false;
        }

        return $this->groupManager->isAdmin($userId)
            || $this->groupManager->isInGroup($userId, self::GROUP_ADMINISTRATORS);
    }

    public function roles(?string $userId = null): array
    {
        $userId ??= $this->currentUserId();
        if ($userId === null) {
            return [];
        }

        $roles = [];

        if ($this->groupManager->isAdmin($userId)) {
            $roles[] = 'nextcloud_admin';
        }

        $groupRoles = [
            self::GROUP_AIDES => 'aide',
            self::GROUP_NURSES => 'nurse',
            self::GROUP_SUPERVISORS => 'supervisor',
            self::GROUP_REPORT_VIEWERS => 'report_viewer',
            self::GROUP_ADMINISTRATORS => 'careforms_administrator',
        ];

        foreach ($groupRoles as $groupId => $role) {
            if ($this->groupManager->isInGroup($userId, $groupId)) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    public function capabilities(?string $userId = null): array
    {
        $userId ??= $this->currentUserId();
        if ($userId === null) {
            return [];
        }

        if ($this->isCareFormsAdministrator($userId)) {
            return [
                'form.view',
                'form.submit',
                'submission.view_own',
                'submission.review',
                'report.view',
                'report.export',
                'form.manage',
                'permissions.manage',
                'settings.manage',
            ];
        }

        $capabilities = [];

        if ($this->groupManager->isInGroup($userId, self::GROUP_AIDES)
            || $this->groupManager->isInGroup($userId, self::GROUP_NURSES)) {
            $capabilities[] = 'form.view';
            $capabilities[] = 'form.submit';
            $capabilities[] = 'submission.view_own';
        }

        if ($this->groupManager->isInGroup($userId, self::GROUP_SUPERVISORS)) {
            $capabilities[] = 'form.view';
            $capabilities[] = 'form.submit';
            $capabilities[] = 'submission.view_own';
            $capabilities[] = 'submission.review';
        }

        if ($this->groupManager->isInGroup($userId, self::GROUP_REPORT_VIEWERS)) {
            $capabilities[] = 'report.view';
        }

        return array_values(array_unique($capabilities));
    }

    public function allowedForms(?string $userId = null): array
    {
        $userId ??= $this->currentUserId();
        if ($userId === null) {
            return [];
        }

        if ($this->isCareFormsAdministrator($userId)
            || $this->groupManager->isInGroup($userId, self::GROUP_AIDES)
            || $this->groupManager->isInGroup($userId, self::GROUP_SUPERVISORS)) {
            return [self::FORM_HOME_HEALTH_AIDE];
        }

        return [];
    }

    public function canAccessForm(string $formId, ?string $userId = null): bool
    {
        return in_array($formId, $this->allowedForms($userId), true);
    }

    public function canViewReports(?string $userId = null): bool
    {
        return in_array('report.view', $this->capabilities($userId), true);
    }
}
