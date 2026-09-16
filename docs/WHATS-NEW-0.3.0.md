# What's New in CareForms 0.3.0

**Release date:** 17 September 2026  
**Release theme:** Form Designer, Dynamic Permissions & Administration UX

CareForms 0.3.0 is a major customer-preview milestone. It moves CareForms toward a data-driven form platform where administrators can manage form definitions, versions, permissions, and publication from within CareForms.

## Visual Form Designer
Administrators can build forms using sections and fields without pixel coordinates or absolute X/Y positioning. **Design**, **CareFormsML**, and **Preview** operate on the same form model.

## Form version lifecycle
Forms support **Draft**, **Published**, and **Archived** versions, allowing a new draft to be prepared without modifying the published form or historical versions.

## Dynamic permissions
Each form can assign Nextcloud groups to **Fill form**, **Review submissions**, and **View reports**. CareForms derives application capabilities from these assignments and enforces protected operations server-side.

## Improved Form Administration
Form Admin has been reorganized to make forms, versions, actions, permissions, and imports easier to understand. The JSON form-definition workflow also has an improved file-selection/import experience.

## Data-driven forms
CareForms can validate and import JSON form definitions and render them through the same dynamic form pipeline used by the Visual Designer. Definitions can also be exported for administration and portability.

## Workflow improvements
The milestone includes patient-linked forms, drafts and submissions, review/report authorization, authenticated form-filler identity, audit improvements, printing/reporting workflows, and digital-signature support.

## CareFormsML
CareFormsML provides a human-readable text representation of the form model. For 0.3.0, editing reliability is prioritized over advanced editor effects. Syntax-highlighting overlays, line numbers, and code folding are deferred.

## Recommended customer demonstration
1. Inspect a published form and its versions in **Form Admin**.
2. Open a draft in the **Visual Form Designer**.
3. Demonstrate **Design**, **CareFormsML**, and **Preview**.
4. Configure Fill, Review, and Report permissions.
5. Sign in as a permitted caregiver or nurse and fill/save a form.
6. Submit the form.
7. Demonstrate the Review Queue with a reviewer account.
8. Demonstrate authorized Reports, patient history, and Audit functionality.

## Compatibility
CareForms 0.3.0 targets **Nextcloud 31**.

## Deployment note
CareForms is designed to support deployment in a HIPAA-compliant environment. Application installation alone does not establish HIPAA compliance; compliance depends on the complete deployment, infrastructure, access controls, configuration, policies, and organizational procedures.
