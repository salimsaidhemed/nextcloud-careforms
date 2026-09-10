# CareForms Installation Guide

CareForms is a Nextcloud 31 application. The technical app ID is `careforms`.

> CareForms is designed to support deployment in a HIPAA-compliant environment. Installing the application does not by itself make a deployment HIPAA compliant; the complete Nextcloud, database, storage, backup, identity, network, logging, policy and operational environment must be assessed.

## Prerequisites

- Nextcloud 31
- PHP version supported by the installed Nextcloud 31 release
- MariaDB/MySQL or another database supported by Nextcloud
- Administrative access to Nextcloud
- HTTPS for production deployments
- A tested backup of the Nextcloud database, configuration, application directories and data before upgrades

## Option A: Dockerized Nextcloud

This method is intended for deployments where Nextcloud runs in containers.

### 1. Obtain CareForms

Clone or download the desired release into the host directory that is mounted as Nextcloud custom applications.

Example development layout:

```text
nextcloud-dev/
├── docker-compose.yml
└── nextcloud-careforms/
```

Example Compose mount:

```yaml
services:
  nextcloud:
    volumes:
      - ./nextcloud-careforms:/var/www/html/custom_apps/careforms
```

For production, pin deployments to a tested CareForms tag/release rather than tracking a feature branch.

### 2. Verify the application is visible inside the container

```bash
docker compose exec nextcloud ls -la /var/www/html/custom_apps/careforms
```

The directory must contain `appinfo/info.xml`.

### 3. Enable CareForms

```bash
docker compose exec -u www-data nextcloud php occ app:enable careforms
```

If CareForms was already enabled and the release contains database migrations, run:

```bash
docker compose exec -u www-data nextcloud php occ upgrade
```

### 4. Verify status

```bash
docker compose exec -u www-data nextcloud php occ app:list | grep -A2 careforms
docker compose exec -u www-data nextcloud php occ status
```

### 5. Create the CareForms groups

```bash
docker compose exec -u www-data nextcloud php occ group:add "CareForms Aides"
docker compose exec -u www-data nextcloud php occ group:add "CareForms Nurses"
docker compose exec -u www-data nextcloud php occ group:add "CareForms Supervisors"
docker compose exec -u www-data nextcloud php occ group:add "CareForms Report Viewers"
docker compose exec -u www-data nextcloud php occ group:add "CareForms Administrators"
```

Existing groups do not need to be recreated.

## Option B: Direct installation on a VM

These examples assume Nextcloud is installed at `/var/www/nextcloud` and the web-server account is `www-data`.

### 1. Install the application files

```bash
cd /var/www/nextcloud/custom_apps
sudo git clone https://github.com/salimsaidhemed/nextcloud-careforms.git careforms
cd careforms
sudo git checkout <release-tag>
```

Alternatively, extract a release archive so that the final path is exactly:

```text
/var/www/nextcloud/custom_apps/careforms
```

Do not create an extra nested directory such as `careforms/nextcloud-careforms`.

### 2. Set ownership and permissions

Use the same ownership model as the rest of the Nextcloud installation. A common Apache/Nginx + PHP-FPM deployment uses:

```bash
sudo chown -R www-data:www-data /var/www/nextcloud/custom_apps/careforms
sudo find /var/www/nextcloud/custom_apps/careforms -type d -exec chmod 750 {} \;
sudo find /var/www/nextcloud/custom_apps/careforms -type f -exec chmod 640 {} \;
```

If your Nextcloud installation uses a different owner/group or deployment mechanism, follow that existing convention instead.

### 3. Enable CareForms

```bash
cd /var/www/nextcloud
sudo -u www-data php occ app:enable careforms
```

For upgrades or releases containing migrations:

```bash
sudo -u www-data php occ upgrade
```

### 4. Verify status

```bash
sudo -u www-data php occ app:list | grep -A2 careforms
sudo -u www-data php occ status
```

### 5. Create the CareForms groups

```bash
sudo -u www-data php occ group:add "CareForms Aides"
sudo -u www-data php occ group:add "CareForms Nurses"
sudo -u www-data php occ group:add "CareForms Supervisors"
sudo -u www-data php occ group:add "CareForms Report Viewers"
sudo -u www-data php occ group:add "CareForms Administrators"
```

## Assigning users to groups

Example Docker deployment:

```bash
docker compose exec -u www-data nextcloud php occ group:adduser "CareForms Aides" jsmith
```

Example VM deployment:

```bash
cd /var/www/nextcloud
sudo -u www-data php occ group:adduser "CareForms Aides" jsmith
```

Users can belong to multiple CareForms groups when their duties require combined capabilities.

## Upgrade procedure

1. Back up the Nextcloud database, configuration, data and custom application files.
2. Put Nextcloud into maintenance mode if required by your change process.
3. Update CareForms to the desired release tag.
4. Restore correct file ownership/permissions.
5. Run `php occ upgrade` as the web-server user.
6. Verify `occ status` and `occ app:list`.
7. Test login, form entry, patient selection, signature submission, supervisor review, detailed reporting and printing.
8. Exit maintenance mode.

Docker example:

```bash
docker compose exec -u www-data nextcloud php occ maintenance:mode --on
# update the mounted CareForms code
docker compose exec -u www-data nextcloud php occ upgrade
docker compose exec -u www-data nextcloud php occ maintenance:mode --off
```

VM example:

```bash
cd /var/www/nextcloud
sudo -u www-data php occ maintenance:mode --on
cd /var/www/nextcloud/custom_apps/careforms
sudo -u www-data git fetch --tags
sudo -u www-data git checkout <release-tag>
cd /var/www/nextcloud
sudo -u www-data php occ upgrade
sudo -u www-data php occ maintenance:mode --off
```

## Post-install validation

Confirm at minimum that:

- CareForms appears in the Nextcloud application navigation.
- An Aide can access only the Home Health Aide form.
- A Nurse can access only the Nurses Progress Note.
- A Supervisor can review submitted forms and access patient details.
- A Report Viewer can access aggregate and detailed data-entry reports.
- An Administrator can manage CareForms features and permissions intended for administrators.
- A signed submission becomes read-only while pending approval.
- A returned submission requires correction and re-signing.
- Approved submissions remain available in reports.
- Submission and report printing produces complete multi-page output.
- Audit events are being written.

## Troubleshooting

### App is not visible

```bash
php occ app:list
php occ app:enable careforms
```

Check that `custom_apps/careforms/appinfo/info.xml` exists and that Nextcloud can read the directory.

### Database migration problem

Run:

```bash
php occ upgrade
```

Then inspect the Nextcloud log while reproducing the issue:

```bash
php occ log:watch
```

### Permissions problem on a VM

Confirm the PHP/web-server account can read the application tree and that ownership matches the surrounding Nextcloud custom applications.
