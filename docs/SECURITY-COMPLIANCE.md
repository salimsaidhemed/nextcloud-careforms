# CareForms Security and Compliance Guidance

CareForms is designed to support deployment in a HIPAA-compliant environment. The application itself is not a complete compliance solution. Compliance depends on the complete technical and organizational environment, including Nextcloud, database, storage, identity, backups, logging, networking, contracts, policies and operational procedures.

## Security principles implemented by CareForms

CareForms is designed around the following controls:

- authentication through the existing Nextcloud user session
- server-side authorization checks based on CareForms capabilities and Nextcloud groups
- role separation between data-entry, supervisory, reporting and administrative users
- patient records linked to submissions using stable internal patient IDs
- immutable/read-only finalized submissions during approval workflow
- authenticated electronic signatures tied to the logged-in user, submission, form version and signing time
- signature invalidation and mandatory re-signing when a submission is returned and changed
- audit events for sensitive CareForms actions without intentionally logging form-field/ePHI values in audit metadata
- versioned forms so historic submissions remain associated with the version used at submission time

## Production deployment requirements

### Transport security

Use HTTPS for all production access. TLS termination may occur on the Nextcloud host or an approved reverse proxy/load balancer. HTTP-only production use is not recommended for patient data.

### Encryption at rest

Protect all locations containing CareForms data, including:

- Nextcloud database
- Nextcloud data storage
- VM or container host volumes
- database storage volumes
- backup repositories
- snapshots and replicas

Encryption design is a deployment responsibility and should be consistent with the organization's security requirements and key-management procedures.

### Backups

Backups should include at least the Nextcloud database, `config/`, custom applications including CareForms where required for recovery, and the Nextcloud data directory. Backup repositories should be encrypted and access-controlled. Restore procedures should be tested periodically rather than assuming backup success implies recoverability.

### Authentication

Use the organization's approved identity provider. Where available, enable MFA and centralized account lifecycle management. For AD/Keycloak environments, deprovision or disable access promptly when employment or responsibilities change.

### Authorization and minimum necessary access

Assign users only to the CareForms groups required for their job function. `CareForms Report Viewers` and `CareForms Administrators` are particularly sensitive because they can access broad patient/reporting information.

Supervisory review rights and reporting rights are deliberately separate. Do not grant reporting access automatically to every supervisor unless their role requires it.

### Audit logging

CareForms records application audit events for sensitive actions. Production operations should define how long these records are retained and who can view them. If logs are forwarded to a SIEM such as Splunk, avoid adding form-field values or unnecessary patient information to forwarded metadata.

Audit logging should be protected against unauthorized modification and should be included in the organization's incident-response and review processes.

### Printing and PDF export

CareForms can render printable submissions and reports using the browser print/PDF workflow. Printed documents and locally saved PDFs containing patient data become copies outside the protected Nextcloud application. Organizations should define controls for printing, physical document handling, local downloads, endpoint protection and secure disposal.

### Database and infrastructure access

Restrict direct database, filesystem, container-host and VM administrative access to authorized technical personnel. Application-level CareForms permissions do not protect against administrators who have unrestricted infrastructure/database access.

## Electronic signatures

CareForms currently implements an authenticated electronic signature rather than a PKI/certificate-based qualified digital signature.

The application records the logged-in Nextcloud user identity, display name, signing time, signature representation, submission/form identifiers and an integrity hash. A returned submission invalidates the previous signature and requires a new signature after correction.

Organizations should confirm that this electronic-signature model satisfies their legal, clinical and policy requirements before relying on it as a regulated signature mechanism.

## HIPAA considerations

For deployments subject to HIPAA, the covered entity/business associate remains responsible for the complete Security Rule and Privacy Rule compliance program. Relevant deployment activities typically include:

- documented risk analysis and risk management
- workforce access authorization and termination procedures
- unique user identification
- authentication and MFA policy where appropriate
- audit controls and periodic log review
- transmission security
- backup and disaster-recovery planning
- incident-response procedures
- vendor/subprocessor review and Business Associate Agreements where required
- retention and secure disposal policies
- physical and endpoint safeguards

CareForms should therefore be described as **designed to support deployment in a HIPAA-compliant environment**, not as independently HIPAA compliant.

## GDPR and other jurisdictions

If patient data is processed in the EU/EEA or concerns EU/EEA data subjects, GDPR obligations may apply in addition to or instead of HIPAA. The deploying organization must determine applicable law, lawful basis, controller/processor responsibilities, retention rules, data-subject rights and cross-border data-transfer requirements.

## Recommended go-live checklist

Before entering real patient data, verify:

- production TLS is correctly configured
- database and storage are protected at rest
- backups are encrypted and a restore has been tested
- CareForms groups have been created and reviewed
- privileged reporting/admin membership is minimal
- account lifecycle and MFA policies are configured
- audit logging works and retention is defined
- printing/PDF handling policy is documented
- incident response contacts/processes are established
- applicable contracts/BAAs/DPAs are in place
- real users have completed role-appropriate training
- test/synthetic data has been removed where appropriate
- recovery and upgrade procedures have been tested

## Current scope limitations

The initial release does not provide offline/PWA patient-data entry, PKI certificate signing, a standalone compliance certification, or automatic compliance with an organization's retention and legal policies. Those controls must be addressed by deployment configuration, organizational processes or future application development as appropriate.
