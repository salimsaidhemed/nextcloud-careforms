# What's New in CareForms 0.3.0

**Release date:** 17 September 2026  
**Release theme:** Form Designer, Dynamic Permissions & Administration UX

CareForms 0.3.0 is a customer-preview milestone that moves CareForms toward a data-driven form platform where administrators can manage form definitions, versions, access, and publication from within CareForms.

## Visual Form Designer

Administrators can build structured forms using sections and fields without pixel coordinates or absolute X/Y positioning.

The designer provides three complementary views:

- **Design** — visually build and organize the form.
- **CareFormsML** — edit the declarative form definition directly.
- **Preview** — see the form from the end user's perspective without editing controls.

All three views operate on the same form definition.

## Form version lifecycle

Forms support **Draft**, **Published**, and **Archived** versions. Administrators can prepare a new version without modifying the currently published form or historical records, then publish the draft when it is ready.

## Dynamic permissions

Each form can assign Nextcloud groups to **Fill form**, **Review submissions**, and **View reports**.

CareForms derives application capabilities and navigation from these assignments. Protected operations are also enforced server-side rather than relying only on hidden UI controls.

## Improved Form Administration

Form Admin has been reorganized to make form/version state, actions, permissions, and imports easier to understand. Permission responsibilities are separated into Fill, Review, and Reports areas, and JSON import has a clearer file-selection experience.

## Data-driven definitions

CareForms can validate and import JSON form definitions and export form definitions for administration and portability. Dynamically defined forms use the same rendering pipeline as forms created through the Visual Form Designer.

## Workflow improvements

The 0.3.0 cycle also includes patient-linked forms, drafts and submissions, review/report access controls, authenticated form-filler identity, improved audit identities, printing/reporting workflows, and digital-signature support.

## CareFormsML editor

CareFormsML provides a human-readable text representation of the same form model used by the Visual Designer.

For 0.3.0, reliable editing is deliberately prioritized over advanced editor effects. Syntax-highlighting overlays, line numbers, and code folding are deferred so cursor movement, selection, Backspace/Delete, copy/paste, and undo/redo remain predictable.

## Suggested customer demonstration

1. Open **Form Admin** and inspect an existing published form.
2. Create or open a draft in the **Visual Form Designer**.
3. Move between **Design**, **CareFormsML**, and **Preview**.
4. Configure Fill, Review, and Report permissions for Nextcloud groups.
5. Sign in as a permitted caregiver or nurse and complete or save a draft.
6. Submit the form.
7. Sign in with reviewer permissions and demonstrate the Review Queue.
8. Show authorized reporting, patient history, and audit functionality.

## Compatibility

CareForms 0.3.0 targets **Nextcloud 31**.

## Deployment note

CareForms is designed to support deployment in a HIPAA-compliant environment, but application installation alone does not establish HIPAA compliance. Compliance depends on the complete deployment, infrastructure, access controls, configuration, policies, and organizational procedures.
