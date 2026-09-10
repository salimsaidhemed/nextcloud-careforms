# CareForms Access Control

CareForms authorization is based on Nextcloud groups and server-side capability checks. Hiding a tab in the browser is not treated as a security boundary; API/controller access is also checked on the server.

## Standard groups

| Group | Intended users | Core access |
|---|---|---|
| `CareForms Aides` | Home health aides / caregivers | Home Health Aide form, own drafts/submissions, patient selection |
| `CareForms Nurses` | Nurses | Nurses Progress Note, own drafts/submissions, patient selection |
| `CareForms Supervisors` | Supervisors / clinical reviewers | Both forms, own submissions, patient registry/detail, review queue, approve/return submissions |
| `CareForms Report Viewers` | Reporting/quality staff | Aggregate reports and identifiable detailed data-entry reports |
| `CareForms Administrators` | CareForms application administrators | Full CareForms administration, forms, patients, reporting, review and permission/settings capabilities |

A Nextcloud system administrator is also treated as a CareForms administrator.

## Capability model

Current CareForms capabilities include:

- `form.view`
- `form.submit`
- `submission.view_own`
- `submission.review`
- `patient.select`
- `patient.view`
- `patient.manage`
- `report.view`
- `report.detail`
- `report.export`
- `form.manage`
- `permissions.manage`
- `settings.manage`

`CareForms Report Viewers` is a privileged group because detailed reports can expose identifiable patient and clinical information. Membership should follow the principle of minimum necessary access and should be reviewed periodically.

Supervisors do not automatically receive reporting access solely because they can review submissions. Assign `CareForms Report Viewers` as an additional group when a supervisor genuinely requires reporting access.

## Example group assignment

Docker:

```bash
docker compose exec -u www-data nextcloud php occ group:adduser "CareForms Nurses" nurse1
docker compose exec -u www-data nextcloud php occ group:adduser "CareForms Supervisors" supervisor1
docker compose exec -u www-data nextcloud php occ group:adduser "CareForms Report Viewers" quality1
```

Direct VM installation:

```bash
cd /var/www/nextcloud
sudo -u www-data php occ group:adduser "CareForms Nurses" nurse1
sudo -u www-data php occ group:adduser "CareForms Supervisors" supervisor1
sudo -u www-data php occ group:adduser "CareForms Report Viewers" quality1
```

## Keycloak / Active Directory

When Nextcloud authentication is federated through Keycloak/AD, keep CareForms authorization based on Nextcloud-visible groups.

Recommended flow:

```text
Active Directory group
        ↓
Keycloak group / role mapping
        ↓
OIDC groups claim
        ↓
Nextcloud group provisioning
        ↓
CareForms AccessService
```

The exact OIDC/Keycloak configuration depends on the customer's identity environment. Verify the actual groups claim and confirm that the resulting Nextcloud group names match the CareForms group names exactly, unless the application is later extended to make group names configurable.

## Access review

Administrators should periodically review:

- users with `CareForms Administrators`
- users with `CareForms Report Viewers`
- users with supervisor review access
- users who changed job function or left the organization
- dormant Nextcloud accounts

Remove unnecessary group membership promptly and retain evidence of access reviews according to the organization's policy.
