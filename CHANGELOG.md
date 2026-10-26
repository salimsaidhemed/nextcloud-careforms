# CareForms Changelog

## [0.4.0] - 2026-10-26

### Added
- Conditional visibility and conditional required fields across schema, designer, CareFormsML, Preview, runtime, and server validation.
- Advanced field constraints, cross-field comparisons, calculated fields, and choice-dependent options.
- Compact dropdown presentation for single-choice fields.
- Designer-side logic linting with field-level remediation and cycle detection.
- Versioned submission integrity that binds signed data and signature content to the exact historical form definition.

### Changed
- Preview pauses when blocking form-logic errors are present.
- Publishing revalidates the exact draft definition.
- Historical submission views load exact definition snapshots instead of substituting the currently published definition.
- Signed submissions expose verified, legacy-valid, invalid, unavailable, or unsigned integrity status.

### Compatibility
- Nextcloud 31
- Existing integrity hashes remain readable through the legacy verification path.

## [0.3.0] - 2026-09-17

### Added
- Data-driven form definitions and responsive dynamic rendering.
- Visual Form Designer with Design, CareFormsML, and Preview workflows.
- Draft, published, and archived form-version lifecycle.
- JSON form-definition validation, import, and export.
- Per-form Nextcloud group permissions for Fill, Review, and Reports.
- Patient-linked form workflows, review/report authorization, printing/reporting, and digital-signature workflow improvements.

### Changed
- Redesigned Form Admin interface and permission controls.
- Improved JSON import/file-selection experience.
- Capability-driven CareForms navigation and protected server-side operations.
- Improved form-filler and audit identity handling.
- Improved responsive administration and designer layouts.

### Deferred
- Advanced CareFormsML syntax highlighting, line numbers, and code folding. v0.3.0 prioritizes reliable native text editing.

### Compatibility
- Nextcloud 31

### Status
Customer-preview milestone. HIPAA compliance depends on the complete deployment, infrastructure, configuration, organizational controls, and operational procedures.
