# CareForms Field Type Contract v1

This document defines how each field type in the CareForms canonical form schema is rendered, validated, stored, printed, and interpreted by the application.

The contract applies to schemaVersion 1 definitions in `schemas/form-schema-v1.json`.

## General rules

Every field has:

- `id`: stable snake_case identifier used as the submission-data key.
- `type`: one of the supported v1 field types.
- `label`: human-readable label.
- `required`: optional boolean; defaults to false.
- `defaultValue`: optional initial value.

Field IDs are part of the historical data contract. Once a published form version has submissions, an existing field ID must not be repurposed to mean something different.

Unknown field types must fail validation before publication.

## text

### Purpose
Single-line free text.

### Rendering
HTML single-line text input.

### JSON definition
```json
{
  "id": "diagnosis",
  "type": "text",
  "label": "Diagnosis",
  "required": false
}
```

### Stored submission value
String.

```json
"diagnosis": "Hypertension"
```

### Validation
- required fields must contain a non-empty value
- no options/min/max/rows properties

### Printing
Rendered as the stored text value.

---

## number

### Purpose
Numeric measurements, scores, percentages, counts, and similar values.

### Rendering
HTML number input.

### Optional properties
- `min`
- `max`

### Stored submission value
Numeric values should be normalized to a number by the schema-driven submission layer. During compatibility migration, legacy string-encoded numeric values may remain readable.

### Validation
- value must be numeric when present
- `min <= max`
- enforce min/max when supplied

### Printing
Rendered as the normalized numeric value.

---

## date

### Purpose
Calendar dates without a time component.

### Rendering
HTML date input.

### Stored submission value
ISO date string:

```text
YYYY-MM-DD
```

Example:

```json
"visit_date": "2026-09-13"
```

### Validation
- valid calendar date
- required rule when applicable

### Printing
Displayed in a locale-friendly format while retaining the ISO value in stored data.

---

## time

### Purpose
Time-of-day values without a date.

### Rendering
HTML time input.

### Stored submission value
24-hour time string:

```text
HH:MM
```

### Validation
Valid time value.

### Printing
Displayed in a locale-friendly time format.

---

## textarea

### Purpose
Multi-line narrative or notes.

### Rendering
HTML textarea.

### Optional properties
- `rows`

### Stored submission value
String, preserving line breaks.

### Validation
- required fields must contain a non-empty value
- `rows` is presentation metadata and does not affect stored data

### Printing
Printed as multiline text with line breaks preserved.

---

## checkbox

### Purpose
Single true/false value.

### Rendering
Single checkbox.

### Stored submission value
Boolean.

```json
"bowel_movement": true
```

### Validation
For a required checkbox, checked/true is required.

### Printing
Displayed as Yes/No or Checked/Not checked in human-readable reports.

---

## checkbox-group

### Purpose
Select zero or more values from a predefined set.

### Rendering
Group of checkboxes.

### Required properties
- `options`

### Stored submission value
Array of strings.

```json
"symptoms": ["Pain", "Dizziness"]
```

### Validation
- every selected value must exist in `options`
- options must be unique and non-empty
- when required, at least one option must be selected

### Printing
Selected options are listed as human-readable values.

---

## choice-group

### Purpose
Select exactly one value from a predefined set.

### Rendering
Radio-button group in schema v1.

A future visual designer may offer display alternatives such as a dropdown without changing the logical field type.

### Required properties
- `options`

### Stored submission value
String.

```json
"pain_present": "No"
```

### Validation
- selected value must exist in `options`
- options must be unique and non-empty
- when required, one value must be selected

### Printing
Rendered as the selected option.

---

## signature

### Purpose
Authenticated electronic signature associated with the submitted record.

### Rendering
Signature canvas for editable submissions and signature image/metadata for read-only submissions.

### Stored submission value
The field itself is not stored as an ordinary answer inside the form data object. Signature content and metadata remain in the dedicated CareForms submission-signature fields.

The canonical field definition indicates where the signature control appears in the form.

### Validation
A signature is required before final submission when a signature field is present.

### Identity
The signature is bound to:
- authenticated Nextcloud user ID
- current display name
- signing timestamp
- submission integrity hash

### Printing
Print the signature plus signer display name and timestamp.

### Compatibility
The legacy hard-coded field type `signature-placeholder` maps to canonical `signature` during migration.

---

## Auto-populated identity fields

Some ordinary fields can be populated from trusted application context rather than typed by the user.

Current examples include:
- `patient_name` from the selected patient
- `mr_number` / `medical_record_number` from the patient registry
- `aide_name` from the authenticated user's display name
- `nurse_name` from the authenticated user's display name

These fields remain ordinary schema fields for historical compatibility, but the renderer may mark them read-only when their value is supplied by trusted context.

A later schema revision may formalize this behavior using an explicit source/binding property.

## Required-field behavior

The renderer must visually indicate required fields.

Before final submission:
- required text/date/time/number/textarea/choice fields must contain a valid value
- required checkbox must be true
- required checkbox-group must contain at least one selected option
- a signature field must contain a captured signature

Draft saving may permit incomplete required fields.

## Read-only behavior

Submitted and approved records are rendered read-only.

Returned records become editable according to the current review workflow.

Read-only rendering must preserve the same logical values displayed during data entry.

## Data compatibility rules

1. Field IDs are immutable once used by published submissions.
2. Removing a field from a future form version must not remove its historical answer from old submissions.
3. Changing a label is allowed in a new form version while old submissions remain tied to their historical version.
4. Changing the semantic meaning of an existing field ID is not allowed; create a new field ID instead.
5. Historical values that predate strict type normalization must remain readable.

## Validation responsibility

Validation is split between:

### Schema validation
Checks structure:
- known field type
- required definition properties
- valid property shapes

### Semantic validation
Checks business consistency:
- unique field IDs
- valid min/max relationship
- defaultValue compatible with type
- selected values belong to options
- signature presence before submission

## Future field types

The following are intentionally deferred:
- select/dropdown as a distinct logical type
- file/photo attachment
- repeating group/table
- calculated field
- rich text
- address
- phone/email specialized fields
- medication lookup
- external terminology/code lookup

These should be added through a new compatible contract extension rather than ad hoc renderer logic.
