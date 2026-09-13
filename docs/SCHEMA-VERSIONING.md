# CareForms Schema Versioning Strategy

This document defines how CareForms evolves the canonical form-definition contract without breaking published forms or historical submissions.

## Two independent version numbers

CareForms uses two separate version concepts:

- `schemaVersion`: the version of the CareForms JSON definition format.
- `version`: the business revision of an individual form.

Example:

```json
{
  "schemaVersion": 1,
  "id": "nurses-progress-note",
  "version": 4
}
```

This means version 4 of Nurses Progress Note still uses CareForms schema contract v1.

## Schema version rules

### Compatible changes

Changes that do not invalidate existing schema v1 documents may remain in schemaVersion 1.

Examples:

- adding a new optional metadata property
- documenting an existing behavior more precisely
- adding a new optional display hint that older renderers may safely ignore only when the application explicitly supports it

CareForms must not silently reinterpret an existing property with a different meaning.

### Incompatible changes

Any change that makes an existing valid definition invalid, changes the meaning or stored representation of existing fields, or requires materially different parsing behavior must introduce a new schemaVersion.

Examples:

- renaming a required property
- changing `sections` to another structural model
- changing the stored representation of `choice-group`
- removing a supported field type
- changing field ID semantics

## Reader compatibility

CareForms should explicitly declare which schema versions it supports.

The application must:

1. accept supported schema versions
2. reject unknown or unsupported versions before publication/use
3. return an actionable error identifying the unsupported version
4. preserve the ability to read historical definitions using still-supported older versions

A future CareForms release may support multiple schema versions simultaneously.

Example:

```text
Supported schema versions: 1, 2
Default schema version for new forms: 2
```

## Writer behavior

New forms created by CareForms use the current default schema version.

Editing an existing form draft should not automatically rewrite its schema version unless an explicit migration is performed.

Schema upgrades must be deliberate and auditable.

## Form business version lifecycle

The existing CareForms lifecycle remains:

```text
Draft -> Published -> Archived
```

Publishing a changed form definition increments the form's business `version`, not necessarily `schemaVersion`.

Example:

```text
Fall Risk Assessment v1, schemaVersion 1
Fall Risk Assessment v2, schemaVersion 1
Fall Risk Assessment v3, schemaVersion 2
```

## Historical submission integrity

Every submission must remain associated with the form ID and business form version used at the time of data entry.

CareForms must never render an old submission against a newer form definition merely because the newer version has the same form ID.

The long-term data-driven implementation should preserve or snapshot the exact published definition for each form version.

## Migration strategy

When a future schema version is introduced, CareForms may provide a migration path:

```text
schema v1 definition
        |
        v
schema migrator
        |
        v
schema v2 draft
        |
        v
validation + preview
        |
        v
publish as new form business version
```

Migration rules must:

- never modify a published historical definition in place
- produce a new draft definition
- preserve stable form and field IDs where semantics are unchanged
- require validation before publication
- record the migration action in the audit log

## Import behavior

JSON import must inspect `schemaVersion` before accepting a definition.

If the version is supported, normal validation continues.

If unsupported, import must fail with a clear message such as:

```text
Unsupported CareForms schemaVersion 3.
This CareForms installation supports schemaVersion 1 and 2.
```

The importer must not guess how to interpret an unknown schema.

## Visual form designer behavior

The designer should:

- create new definitions using the current default schema version
- display the schema version in form administration details
- prevent unsupported definitions from being edited
- offer explicit migration when a newer supported schema is available
- avoid exposing schemaVersion as an arbitrary editable number to ordinary users

## Deprecation policy

A schema version should only be deprecated after:

1. a newer version is available
2. a migration path exists where practical
3. published/historical forms remain readable
4. administrators receive advance notice

Removing read support for a schema version is a breaking platform change and should only occur in a major CareForms release with explicit migration guidance.

## Runtime constants

The implementation should eventually centralize values equivalent to:

```text
SUPPORTED_SCHEMA_VERSIONS = [1]
DEFAULT_SCHEMA_VERSION = 1
```

Validation, import/export, form administration and the visual designer should rely on the same centralized values.

## Definition of done for schema version handling

The schema-versioning foundation is complete when:

- schemaVersion and form version are consistently treated as independent values
- unsupported schema versions are rejected safely
- published historical definitions are never mutated in place
- migration creates a new draft rather than rewriting history
- import/export preserves schemaVersion
- the form designer uses the application's default schema version
- compatibility expectations are documented and testable
