# CareForms Metadata Contract v1

This document defines optional metadata that controls presentation, guidance, layout, and trusted value population for schemaVersion 1 form definitions.

Metadata must not change the clinical meaning of historical data. Form lifecycle state, permissions, workflow status, and audit policy remain outside this contract.

## Form metadata

Supported optional properties:

- `tags`: searchable classification labels, for example `["nursing", "assessment"]`.
- `display.icon`: symbolic icon name used by the CareForms UI.
- `display.accent`: optional visual accent token.

Example:

```json
{
  "tags": ["nursing", "assessment"],
  "display": {
    "icon": "clipboard-pulse",
    "accent": "clinical"
  }
}
```

These values are presentation metadata only and must not be used as authorization controls.

## Section metadata

Supported optional properties:

- `description`: helper text shown below the section heading.
- `collapsible`: whether the UI may present the section as collapsible.

Example:

```json
{
  "id": "vital-signs",
  "label": "Vital Signs",
  "description": "Record measurements taken during this visit.",
  "collapsible": false,
  "fields": []
}
```

Section order remains the order in the `sections` array.

## Field metadata

Supported optional properties:

- `helpText`: guidance shown near the field.
- `placeholder`: short input hint.
- `unit`: display unit such as `cm`, `%`, or `mmHg`.
- `width`: preferred layout width: `full`, `half`, or `third`.
- `readOnly`: prevents direct editing.
- `source`: trusted source used to populate the value.

## Trusted field sources

Schema v1 defines these sources:

- `manual`
- `patient.name`
- `patient.mr_number`
- `patient.date_of_birth`
- `current_user.display_name`
- `system.current_date`

Example:

```json
{
  "id": "nurse_name",
  "type": "text",
  "label": "RN/LPN Name",
  "required": true,
  "readOnly": true,
  "source": "current_user.display_name"
}
```

A source value is resolved by CareForms from trusted application context. The client must not be allowed to override a trusted server-side identity binding during final submission.

The current hard-coded patient and user auto-population behavior will be migrated to this contract in a later implementation task.

## Layout metadata

`width` is a presentation hint, not a guarantee. Responsive clients may override it.

- `full`: use the available row width.
- `half`: target approximately half of the row.
- `third`: target approximately one third.

Printed output may ignore screen layout hints when necessary for readability.

## Metadata compatibility

Changing presentation-only metadata in a new form version is allowed. Historical submissions remain associated with the form version used when they were created.

Do not use metadata to silently change:
- the semantic meaning of a field
- authorization
- review requirements
- signature requirements
- workflow state

Those concerns belong to dedicated CareForms subsystems or future schema contracts.

## Visual designer implications

The visual form designer can expose these properties as simple controls:

- Form tags
- Icon
- Section helper text
- Collapsible section toggle
- Field help text
- Placeholder
- Unit
- Width
- Read-only toggle
- Auto-fill source

This lets non-technical administrators configure useful presentation behavior without editing JSON directly.
