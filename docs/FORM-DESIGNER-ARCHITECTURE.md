# CareForms Visual Form Designer Architecture

## Purpose

The CareForms visual form designer is intended for administrators and operational users who may understand the business form they need, but should not be expected to understand HTML, CSS, coordinates, pixels, JSON syntax, or application code.

The designer must therefore be simple, constrained, predictable, and difficult to misuse.

Its job is to produce the same canonical CareForms JSON definitions already consumed by the schema validator and generic renderer.

## Core principle: structured flow, not absolute positioning

CareForms will **not** use an absolute canvas with X/Y coordinates, pixel dimensions, free-floating widgets, or arbitrary positioning.

Form authors should never need to enter values such as:

```text
x = 340
y = 180
width = 427px
height = 96px
```

Instead, the designer uses a responsive document flow:

```text
Form
 └── Sections
      └── Fields
```

Sections appear from top to bottom.

Fields appear in their section from top to bottom and may be arranged using only a small set of understandable width choices.

The runtime renderer remains responsible for adapting that structure to desktop, tablet, mobile, and print layouts.

## User-facing layout choices

The designer should expose friendly choices rather than technical CSS concepts.

Recommended field width choices:

- Full row
- Half row
- Third row

These map internally to the canonical schema values:

```text
Full row  -> full
Half row  -> half
Third row -> third
```

The default should be **Automatic** in the designer. Automatic does not need to be persisted as a separate schema value; the renderer applies its sensible field-type defaults.

Recommended defaults:

- narrative/textarea fields: full row
- signatures: full row
- checkbox/choice groups: half or full depending on available space
- ordinary text/date/time/number fields: half row
- narrow screens: everything becomes full row automatically

Users should not be required to understand breakpoints or responsive design.

## Designer modes

The designer should eventually provide three views of the same form model:

```text
[ Design ]   [ Markup ]   [ Preview ]
```

### Design

Primary mode for non-technical administrators.

Users manipulate sections and fields through visual controls.

### Markup

Optional advanced mode using CareFormML.

Users can directly edit the declarative form definition without dealing with JSON punctuation.

### Preview

Shows the form using the actual CareForms generic renderer.

Preview is not a separate rendering implementation.

## Shared editable model

All designer modes operate on a shared in-memory representation equivalent to canonical schema v1.

Conceptually:

```text
DesignerState
  schemaVersion
  id
  name
  category
  version
  description
  tags
  sections[]
      id
      label
      description
      collapsible
      fields[]
          id
          type
          label
          required
          helpText
          placeholder
          unit
          width
          readOnly
          source
          options
          min
          max
          rows
          defaultValue
```

The browser designer must not invent another persistence model.

On save:

```text
Designer state
      ↓
canonical JSON
      ↓
FormSchemaValidator
      ↓
persisted draft definition
```

## Design-mode interaction model

The form is edited as a sequence of cards/blocks.

Example:

```text
┌──────────────────────────────────────────────┐
│ Fall Risk Assessment                        │
│ Nursing                                     │
├──────────────────────────────────────────────┤
│ Patient Information                    ⋮     │
│                                              │
│  Patient Name               Visit Date       │
│  [ Text ]                   [ Date ]          │
│                                              │
│                         + Add field           │
├──────────────────────────────────────────────┤
│ Assessment                             ⋮     │
│                                              │
│  History of Falls                           │
│  [ Choice ]                                  │
│                                              │
│  Fall Risk Score                            │
│  [ Number ]                                  │
│                                              │
│                         + Add field           │
├──────────────────────────────────────────────┤
│ + Add section                                │
└──────────────────────────────────────────────┘
```

## Section operations

A section should support:

- Add section
- Rename section
- Add optional description
- Reorder section
- Duplicate section
- Delete section
- Toggle collapsible behavior

Reordering should preferably use visible move controls in addition to drag-and-drop:

```text
↑ Move up
↓ Move down
```

This improves accessibility and makes the designer usable on tablets.

## Field palette

The first designer release should expose only schema-v1 field types:

- Short text
- Number
- Date
- Time
- Long text
- Yes/No checkbox
- Multiple choice
- Select one
- Signature

The labels presented to users should be friendlier than internal schema names.

Example mapping:

```text
Short text       -> text
Long text        -> textarea
Multiple choice  -> checkbox-group
Select one       -> choice-group
```

## Field insertion

Adding a field should be a simple flow:

```text
+ Add field
      ↓
Choose field type
      ↓
Enter label
      ↓
Field appears
```

The designer should create the field ID automatically from the label where possible.

Example:

```text
"Fall Risk Score"
       ↓
fall_risk_score
```

The generated ID must be visible in Advanced settings, but ordinary users should not normally need to edit it.

Once a field ID is used by a published version, changing it should generate a strong warning because field IDs are part of the historical data contract.

## Field property editor

Selecting a field opens a focused property panel.

Basic properties:

- Label
- Required
- Help text
- Placeholder
- Width
- Options, where applicable

Type-specific properties:

- Number: minimum, maximum, unit
- Long text: suggested height/rows
- Choice fields: list of options
- Trusted value fields: auto-fill source
- Signature: minimal signature-specific information

Advanced properties should be hidden behind an **Advanced** section.

The initial UI should avoid exposing internal implementation details unless needed.

## Auto-fill sources

The current trusted-source contract should be presented with friendly names:

```text
Manual entry                  -> manual
Patient name                  -> patient.name
Patient medical record number -> patient.mr_number
Patient date of birth         -> patient.date_of_birth
Logged-in user's name         -> current_user.display_name
Today's date                  -> system.current_date
```

Choosing an auto-fill source should automatically enable read-only when appropriate.

## Ordering model

Field and section order is represented by array order in canonical JSON.

There is no separate X/Y coordinate system.

Reordering therefore means moving items in their arrays.

Example:

```text
sections[0]
sections[1]
sections[2]
```

and:

```text
sections[0].fields[0]
sections[0].fields[1]
sections[0].fields[2]
```

This makes ordering deterministic and portable between screen sizes.

## Responsive behavior

The designer does not decide exact desktop/tablet/mobile coordinates.

The generic renderer handles presentation.

The same form definition should work on:

- desktop browser
- tablet
- phone
- print/PDF

A definition should therefore describe **semantic structure and relative layout hints**, not fixed visual coordinates.

## CareFormML integration

Markup mode serializes the shared designer state to CareFormML.

Example:

```xml
<form id="fall-risk-assessment"
      name="Fall Risk Assessment"
      category="Nursing"
      version="1">

  <section id="assessment" label="Assessment">
    <choice id="fall_history"
            label="History of Falls"
            required="true"
            width="half">
      <option>Yes</option>
      <option>No</option>
    </choice>

    <textarea id="notes"
              label="Notes"
              width="full"
              rows="4" />
  </section>
</form>
```

The expected synchronization flow is:

```text
Design mode
    ↓
shared model
    ↓
CareFormML serializer
    ↓
Markup mode
```

and:

```text
Markup mode
    ↓
CareFormML parser
    ↓
canonical model
    ↓
schema validation
    ↓
Design mode
```

The user cannot switch from invalid markup back to Design mode until parse/validation errors are corrected.

## Preview architecture

Preview should render directly from the current unsaved designer model using the existing generic FormRenderer.

```text
Designer model
      ↓
validation
      ↓
FormRenderer
      ↓
Preview
```

This guarantees that what the author previews closely matches what users will eventually fill.

## Draft integration

The designer edits drafts, never published definitions directly.

Recommended lifecycle:

```text
Published v3
     ↓
Create new draft
     ↓
clone published definition
     ↓
v4 Draft
     ↓
Open Designer
     ↓
Design / Markup / Preview
     ↓
Save Draft
     ↓
Publish
     ↓
v3 Archived
v4 Published
```

JSON import should enter the same lifecycle:

```text
Import JSON
     ↓
validate
     ↓
create/update next draft
     ↓
Open Designer if desired
     ↓
Publish
```

## Simplicity rules

The first designer should deliberately avoid becoming a general page builder.

Do not include:

- absolute X/Y positioning
- pixel dimensions
- arbitrary CSS
- arbitrary HTML
- free-floating elements
- overlapping controls
- custom JavaScript
- custom fonts per field
- complex visual themes per form
- arbitrary grid coordinates

These features would make forms harder to maintain, less responsive, harder to print, and more difficult for non-technical administrators.

## Progressive disclosure

The designer should show the minimum necessary controls first.

Example:

```text
Field
  Label
  Required
  Width
  Help text

  ▸ Advanced
      Field ID
      Auto-fill source
      Placeholder
      Min / Max
      Unit
```

A non-technical administrator should be able to build a normal form without opening Advanced settings.

## Undo and destructive actions

Later implementation should support at minimum:

- confirm before deleting a section containing fields
- confirm before deleting a field
- warn before changing a field ID used by a published form
- dirty-state warning when navigating away with unsaved changes

Undo/redo is desirable but can follow after the first usable designer.

## Accessibility

The designer must not rely solely on drag-and-drop.

Every reorderable element should also have keyboard/button controls.

Fields and sections should be identifiable by text labels, not position alone.

## Implementation approach

Recommended implementation:

- JavaScript for the initial designer UI to match the existing CareForms frontend
- reuse canonical JSON structures directly
- reuse FormSchemaValidator server-side before save/publish
- reuse FormRenderer for preview
- add CareFormML parser/serializer as a separate module
- persist designer changes only as versioned draft definitions

A framework migration is not required for the first designer milestone.

## Initial milestone boundaries

The first implementation milestone should provide:

1. Designer shell
2. Shared editable model
3. Create/edit form metadata
4. Add/remove/reorder sections
5. Add/remove/reorder fields
6. Basic field property editing
7. Live preview
8. Save canonical JSON as a draft

CareFormML synchronization can then build on the same shared model without redesigning the visual editor.

## Definition of done

The architecture is successful when:

- a non-technical administrator can understand the layout model without knowing coordinates or CSS
- the same form definition works responsively across devices
- Design, Markup, JSON import, and Preview can converge on one canonical form model
- the designer produces schema-valid canonical JSON
- no form-specific frontend code is required
- published definitions remain immutable
- edits occur through versioned drafts
