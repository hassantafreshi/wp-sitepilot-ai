# AI Site Assistant AutoPilot x Easy Form Builder - Integration Roadmap

> Reviewed against AI Site Assistant AutoPilot and EFB 4.1.2 on 2026-07-22. This records verified
> implementation status separately from manual WordPress/XAMPP verification.
> 2026-10-26 (T74): re-checked against EFB 4.3.1 RC (`dev4`) and the add-ons in `vendores-efb`
> (`main`). Full requirement list and status: `docs/EFB_AI_FORM_BUILDER_T74_T75_FA.md`.
> `calculation`, `quiz`, and `ticketing` are explicitly out of scope.

## Ground truth

- EFB form table: `{prefix}emsfb_form` with `form_id`, `form_name`,
  `form_structer`, `form_email`, `form_type`, `form_created_by`,
  `form_access_by`, `form_create_date`, and `status`.
- EFB persists `form_structer` as JSON with every double quote escaped as `\"`.
  The AISAAutoPilot converter follows this exact representation.
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
EFB Pro electronic-signature field (`esign`). T74 added the Pro types `mobile`,
`rating`, `pointr5`, `pointr10`, `yesNo`, `switch`, `trmCheckbox` (consent, linked
only to the site's own privacy policy page), `color`, `dadfile`, `chlCheckBox` and
`table_matrix` (rows stored as `r_matrix` children).
Payment, authentication, password, and registration fields are never generated.

A designed form can also carry per-field help text, the submit-button text, and two
notification switches: `notify_admin` writes the site admin email into the form's
`email`, and `confirm_to_respondent` sets `sendEmail`/`email_to` to the form's own
email field — the same shape as EFB's contact template. The AI never writes an
address; the preview shows the recipient before approval.

### Customize an open form

The full catalog lets the AI safely propose existing-field changes including labels,
required state, placeholder/help text, desktop/mobile layout, label position/alignment,
input height/corners/classes, visibility/disabled state, required-message text,
length/date limits, file limits, selection limits, choice columns, and recorder
settings. It can also change safe form settings such as confirmation text, button
labels, button/background/text colours, tracking-code visibility, background, loading
animation, and loading color.

### Review, undo and conditional logic (T74)

- "Review this form" runs deterministic checks (consent for personal data, spam
  protection with Human Shield or reCAPTCHA, missing or duplicate labels, choice
  fields with fewer than two options, too many required fields, long single pages)
  and offers one-click fixes that go through the normal validation.
- Every applied AI change gets an "Undo this change" button that restores the canvas
  exactly as it was before that change.
- With the Conditional Logic add-on (AdnSMF) active, `add_logic_rule` proposes
  field-tab rules (show/hide field or step, required/optional, jump to step). Every
  reference is resolved against the open form, choice conditions store the option
  `id_`, self-referencing and contradictory rules are refused, conflicts with
  existing rules are reported, and the add-on's own limits apply (Free: none; Free
  Plus: 3 rules × 2 conditions; Pro: unlimited). Existing rules are explained in
  plain language without calling the AI.

### Diagnostics

Every EFB chat turn has a client-generated trace id. `AI Site Assistant AutoPilot -> Logs` records
the submitted command, AI action/operation names, server validation drops, and the
browser's actual apply/render result. A failed field lookup, missing insert template,
unsupported target, or redraw error is displayed in the chat and stored with that
same trace id; it is never reported as a successful form change.

### Recorded but intentionally not AI-writable

The catalog grounds help answers for all implemented add-ons and settings, but does
not automatically change global EFB settings, add-on activation, external credentials,
notification recipients on existing forms, captcha, payment/pricing configuration, raw
HTML, URLs, notification/confirmation/webhook/pricing conditional rules, value-writing
logic actions, or submission data. These require the native EFB UI and explicit user
approval.

**2026-09-15 update**: three of these — submission data (search/summarize/update/delete
entries), payment/pricing configuration (read-only), and conditional-logic rules
(read-only "explain" only, never write) — now have a concrete, planned path as
agent-callable WordPress Abilities API tools, gated to EFB Free Plus/Pro. See
`docs/EFB_AGENT_ABILITIES_S4_1_AUDIT_AND_PLAN_FA.md` (backlog item B-09 in
`PRODUCT_COMPLETION_ROADMAP_FA.md`, decisions D-10 to D-12). This is a direct response
to Gravity Forms bundling an equivalent agent-callable AI Form Builder into every
license tier (announced 2026-08-26). Global settings, add-on activation, external
credentials, SMTP recipients, raw HTML, URLs, and Mailchimp (which has no EFB-side
integration to wrap at all) remain out of scope — see that plan's "قابلیت‌هایی که این
بسته عمداً نمی‌سازد" section for why.

## Follow-ups

- ~~Add a user-visible preview/diff and undo snapshot before applying editor changes.~~
  Undo per AI change shipped in T74; a selectable per-change diff is still open.
- Manually test catalog-driven operations across desktop/mobile views and all supported
  EFB field families in XAMPP.
- Add multi-step creation only after native step/amount ordering has browser coverage.
- AI conditional logic is limited to field-tab `logic_rules` (T74). Notification,
  confirmation and webhook rules stay out until EFB resolves their gating debt.
- EFB Agent Abilities (S4.1, backlog B-09): entries, payment-settings-read, and
  logic-explain abilities exposed via WordPress Abilities API — see
  `docs/EFB_AGENT_ABILITIES_S4_1_AUDIT_AND_PLAN_FA.md`.
