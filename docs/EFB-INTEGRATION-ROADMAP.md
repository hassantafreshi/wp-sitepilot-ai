# SitePilot AI x Easy Form Builder - Integration Roadmap

> Reviewed against SitePilot AI and EFB 4.1.2 on 2026-07-22. This records verified
> implementation status separately from manual WordPress/XAMPP verification.
> `calculation`, `quiz`, and `ticketing` are explicitly out of scope.

## Ground truth

- EFB form table: `{prefix}emsfb_form` with `form_id`, `form_name`,
  `form_structer`, `form_email`, `form_type`, `form_created_by`,
  `form_access_by`, `form_create_date`, and `status`.
- EFB persists `form_structer` as JSON with every double quote escaped as `\"`.
  The SitePilot converter follows this exact representation.
- The native form structure is flat: `[formSettings, step, field, option, ...]`.
  Field options are individual `type: "option"` items pointing to their owner via
  `parent`; they are not nested inside the field.
- The editor state is the same structure in global `valj_efb`; index `0` is the
  form object. EFB saves the complete array, not individual field patches.
- EFB pages are `admin.php?page=Emsfb` and `admin.php?page=Emsfb_create`; the
  shortcode format is `[EMS_Form_Builder id=N]`.

## Design boundary

- `includes/Efb/efb-form-model.json` is the compact and deliberately conservative
  new-form-generation contract. Its templates expand AI plans into native EFB fields.
- `includes/Efb/efb-capability-catalog.json` is the full, versioned knowledge and
  execution catalog: field types, `valj_efb` keys, form settings, add-ons,
  storage, dependencies, exclusions, and AI-write policy.
- AI actions are proposals. Server validation and the browser executor only permit
  catalog entries marked `ai_writable`; EFB redraws the builder state and the normal
  EFB Save action persists it.

## Build checklist

- [x] Reverse-engineer EFB form structure, field types, options, and persistence.
- [x] Add compact model and native templates in `efb-form-model.json`.
- [x] Add `EfbFormModel` and `EfbFormConverter` for clean model to native
  `form_structer` conversion.
- [x] Configure `FormPlanValidator` with the safe EFB type set and option types.
- [x] Add `EfbFormSkill`, chat flow, REST plan/create endpoints, and approval store.
- [x] Add `EasyFormBuilderConnector::execute_action()` with EFB-compatible DB insert,
  edit URL, and shortcode response.
- [x] Add the EFB page chat button and assets.
- [x] Add safe in-editor customization for the live `valj_efb` structure: field
  add/remove, options, base settings, and catalog-driven form/field properties.
- [x] Add catalog-driven type/value validation on the server and type-aware operation
  execution in the browser.
- [x] Add correlated server/browser diagnostic logging for chat decisions, rejected
  operations, field insertion, renderer failures, and the final apply report.
- [x] Add smoke/unit coverage for the capability catalog and operation validator.
- [ ] Deploy/sync to XAMPP and perform a browser test with a real EFB form.

## Current AI capability

### Create a new form

The safe compact type set is: `text`, `textarea`, `email`, `tel`, `number`, `url`,
`date`, `file`, `select`, `radio`, `checkbox`, `multiselect`, `heading`, and the
EFB Pro electronic-signature field (`esign`).
Payment, authentication, password, and registration fields are never generated.

### Customize an open form

The full catalog lets the AI safely propose existing-field changes including labels,
required state, placeholder/help text, desktop/mobile layout, label position/alignment,
input height/corners/classes, visibility/disabled state, required-message text,
length/date limits, file limits, selection limits, choice columns, and recorder
settings. It can also change safe form settings such as confirmation text, button
labels, button/background/text colours, tracking-code visibility, background, loading
animation, and loading color.

### Diagnostics

Every EFB chat turn has a client-generated trace id. `SitePilot AI -> Logs` records
the submitted command, AI action/operation names, server validation drops, and the
browser's actual apply/render result. A failed field lookup, missing insert template,
unsupported target, or redraw error is displayed in the chat and stored with that
same trace id; it is never reported as a successful form change.

### Recorded but intentionally not AI-writable

The catalog grounds help answers for all implemented add-ons and settings, but does
not automatically change global EFB settings, add-on activation, external credentials,
SMTP recipients, payment/pricing configuration, raw HTML, URLs, conditional-logic
rules, or submission data. These require the native EFB UI and explicit user approval.

## Follow-ups

- Add a user-visible preview/diff and undo snapshot before applying editor changes.
- Manually test catalog-driven operations across desktop/mobile views and all supported
  EFB field families in XAMPP.
- Add multi-step creation only after native step/amount ordering has browser coverage.
- Do not start AI conditional-logic generation before the EFB execution plan's
  conditional-logic hardening prerequisite is complete.
