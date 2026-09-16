# CareForms Changelog

## [0.3.0] - 2026-09-17

CareForms 0.3.0 is the customer-preview milestone for data-driven form administration, visual form design, dynamic permissions, and end-to-end CareForms workflows.

### Added
- Data-driven form definitions and responsive dynamic form rendering.
- Visual Form Designer with Design, CareFormsML, and Preview workflows.
- Editable CareFormsML representation of the visual form model.
- Draft, published, and archived form-version lifecycle.
- JSON form-definition validation, import, and export.
- Per-form Nextcloud group permissions for Fill, Review, and View Reports.
- Capability-driven navigation and protected review/report workflows.
- Patient registry and patient-linked form workflows.
- Draft/submission, review, reporting, printing, and digital-signature workflow improvements.

### Changed
- Redesigned Form Admin interface and permission configuration.
- Improved JSON file-selection/import experience.
- Improved Form Designer workspace, metadata layout, preview, and responsive behavior.
- Form-filler identity is populated from the authenticated Nextcloud user.
- Audit activity uses more recognizable user identity information.

### Fixed
- Permission-gated navigation now respects dynamic capabilities.
- Review and Reports access enforcement improvements.
- Form-version and imported-draft issues discovered during the data-driven form rollout.
- CareFormsML editing stabilized around native text editing behavior.

### Deferred
- CareFormsML syntax-highlighting overlays, line numbers, and code folding, in favor of reliable cursor positioning and normal editing.

### Compatibility
- Nextcloud 31

### Status
Customer-preview milestone. CareForms is designed to support deployment in a HIPAA-compliant environment; compliance depends on the complete deployment and organizational controls.
