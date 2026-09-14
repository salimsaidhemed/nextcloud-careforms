# CareFormML Design Proposal

CareFormML is a proposed XML-like authoring language for CareForms.

Its purpose is to give non-technical and semi-technical users a readable alternative to JSON while preserving the canonical JSON schema as the internal contract used by validation, storage, rendering, import/export, and the future visual form designer.

## Architecture

```text
Visual Form Designer
        |
        v
CareForms in-memory model
        |
        +---- serialize ----> CareFormML
        |
        +---- serialize ----> Canonical JSON
        |
        v
Schema Validator
        |
        v
Generic Renderer
```

CareFormML is an authoring format, not a second runtime form model.

The canonical JSON definition remains the source of truth for CareForms runtime behavior.

## Example

```xml
<form
  id="fall-risk-assessment"
  name="Fall Risk Assessment"
  category="Nursing"
  version="1">

  <section id="patient-information" label="Patient Information">
    <text
      id="patient_name"
      label="Patient Name"
      required="true"
      readonly="true"
      source="patient.name" />

    <date
      id="visit_date"
      label="Visit Date"
      required="true"
      source="system.current_date" />
  </section>

  <section id="assessment" label="Assessment">
    <choice id="fall_history" label="History of falls" required="true">
      <option>Yes</option>
      <option>No</option>
    </choice>

    <number
      id="fall_risk_score"
      label="Fall risk score"
      min="0"
      max="10" />

    <textarea
      id="notes"
      label="Notes"
      rows="4" />
  </section>

  <section id="signature" label="Signature">
    <text
      id="nurse_name"
      label="RN/LPN Name"
      readonly="true"
      source="current_user.display_name" />

    <signature id="signature" label="Signature" />
  </section>
</form>
```

The parser converts that markup into the same canonical JSON used by CareForms today.

## Proposed tag mapping

- `<form>` -> form definition
- `<section>` -> section
- `<text>` -> text
- `<number>` -> number
- `<date>` -> date
- `<time>` -> time
- `<textarea>` -> textarea
- `<checkbox>` -> checkbox
- `<checkbox-group>` -> checkbox-group
- `<choice>` -> choice-group
- `<signature>` -> signature
- `<option>` -> value inside an option-based field

## Design principles

### One-to-one mapping

Every CareFormML construct must map predictably to canonical JSON.

The markup language must not introduce behavior that cannot be represented in the canonical schema.

### Round-trip safety

The following operation should preserve meaning:

```text
GUI -> JSON -> CareFormML -> JSON -> GUI
```

Formatting and attribute order do not need to be preserved, but form semantics must be preserved.

### Declarative only

CareFormML must not support arbitrary JavaScript, Python, template execution, external entities, or embedded executable code.

Advanced logic should later use explicitly supported declarative elements rather than scripts.

### Strict parser

Unknown tags or attributes should produce validation errors instead of being silently ignored.

### Safe XML parsing

If XML syntax is used, external entity resolution and DTD processing must be disabled.

The markup must never be inserted into the browser DOM as trusted HTML.

### Canonical JSON remains authoritative

The parser flow is:

```text
CareFormML
   |
   v
Parser
   |
   v
Canonical JSON
   |
   v
FormSchemaValidator
   |
   v
Preview / Save / Publish
```

The renderer never needs to understand CareFormML directly.

## Visual designer integration

The future Form Designer should offer two editing modes:

```text
[ Design ] [ Markup ]
```

Both views operate on the same form model.

### Design mode

Users add sections and fields through controls.

### Markup mode

Users edit CareFormML in a code editor with:

- syntax highlighting
- line numbers
- auto indentation
- validation markers
- autocomplete for tags, attributes, field types, and trusted sources
- live preview
- format document

Switching back to Design mode first parses and validates the markup. Invalid markup must remain in Markup mode until corrected.

## Why not make JSON the only textual editor?

JSON is excellent as an interchange and machine representation, but verbose punctuation makes it less approachable for form authors.

CareFormML can make hierarchy visually obvious and can be easier to type for users familiar with HTML/XML.

JSON should still remain available for import/export and advanced troubleshooting.

## Why not YAML?

YAML is concise but indentation-sensitive and has a more complex parser surface. An XML-like grammar gives CareForms a deliberately small and strongly constrained language with clear opening/closing structure.

YAML could still be supported later as an additional interchange format, but should not be required for the first designer.

## Implementation recommendation

Do not build CareFormML as a separate Java or Python desktop application.

Implement the parser/serializer in the CareForms web application, preferably in TypeScript/JavaScript for the designer, with server-side validation of the resulting canonical JSON before saving or publishing.

A server-side PHP parser may also be added so imported `.cform` files can be validated independently of the browser.

## Suggested file extension

Working proposal:

```text
.cform
```

Example:

```text
fall-risk-assessment.cform
```

This avoids confusion with generic XML files while still allowing XML tooling internally.

## Future advanced logic

Later schema versions could add declarative constructs such as:

```xml
<field-visible when="pain_present == 'Yes'">
    ...
</field-visible>
```

However, expressions should not be added until the Advanced Form Logic epic defines a safe expression grammar.

The first CareFormML version should map only to schemaVersion 1.
