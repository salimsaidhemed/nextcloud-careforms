# CareForms Migration Compatibility

CareForms preserves compatibility while moving from hard-coded form definitions to the canonical data-driven schema.

## Legacy definition compatibility

Older CareForms definitions used:

- a top-level `fields` array
- section objects with `type: "section"`
- `signature-placeholder` instead of canonical `signature`
- no explicit `schemaVersion`

The compatibility service converts those definitions to schema v1 before validation.

The conversion is intentionally structural only:

```text
legacy definition
     |
     v
compatibility adapter
     |
     v
canonical schema v1
     |
     v
schema validator
     |
     v
generic renderer
```

Field IDs are never renamed by the compatibility layer.

## Historical submission compatibility

Historical submissions remain keyed by their original field IDs. Unknown stored keys are preserved rather than discarded.

This is important because a newer form version may remove a field while old submissions still contain its answer.

Legacy numeric answers may have been stored as strings. When a canonical definition clearly identifies the field as numeric, the compatibility service may normalize values such as:

```text
"10" -> 10
"3.5" -> 3.5
```

Legacy checkbox values represented as `0`, `1`, `"0"`, or `"1"` may be normalized to booleans.

No destructive data migration is performed.

## Signature compatibility

The old field type:

```text
signature-placeholder
```

is treated as:

```text
signature
```

Existing electronic signature data continues to live in the dedicated submission signature fields.

Historical submissions without electronic signature metadata remain readable and display the existing legacy/no-signature message.

## Compatibility guarantees

The migration layer must preserve:

- form IDs
- field IDs
- stored submission keys
- patient relationships
- submission status
- signature metadata
- audit history
- review history
- form version references

The compatibility layer must never silently rename or delete historical data.

## Removal policy

Legacy compatibility code should only be removed after all supported production data and imported definitions are guaranteed to use the canonical schema and an explicit migration path has been provided.

Until then, compatibility is treated as part of the public CareForms data contract.
