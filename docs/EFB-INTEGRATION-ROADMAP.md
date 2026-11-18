# AI Site Assistant AutoPilot x Easy Form Builder - Integration Roadmap

> Reviewed against AI Site Assistant AutoPilot and EFB 4.1.2 on 2026-07-22. This records verified
> implementation status separately from manual WordPress/XAMPP verification.
> 2026-10-26 (T74): re-checked against EFB 4.3.1 RC (`dev4`) and the add-ons in `vendores-efb`
> (`main`). Full requirement list and status: `docs/EFB_AI_FORM_BUILDER_T74_T75_FA.md`.
> `calculation`, `quiz`, and `ticketing` are explicitly out of scope.

> 2026-11-18 (`dev4`): re-checked against EFB `dev4` after its SPLIT, F01–F21, PAY00 and F20-core work. The copilot
> now reads EFB's own registry for every plan, add-on and gateway decision, creates forms only through EFB's form
> package API (no table write), keeps EFB 4.3.2's visitor confirmation default and the forward-jump rule. Row-by-row
> coverage with the test of each row: `docs/EFB_FEATURE_COVERAGE_FA.md`. The "Ground truth" below about writing
> `form_structer` describes the format the converter still builds; it is stored by EFB's `Form_Package::import()`.

## 2026-11-18: what changed in the integration

- **Registry first.** `Efb/EfbCapabilities.php` reads `get_efbFunction()->capabilities_efb()`: plan, `features[key]`
  (`package`, `addon`, `available_now`, `reason`), `split` (moved features, add-on names, plans, states incl.
  `update`), `limit:steps` / `limit:logic`, `email_confirmation`, `portal_core`, `payment_gateways`, `payment_keys`,
  `spam.providers`, `name_address`. `EfbLicense`, `EfbFormModel` plan checks, `EfbAddons` offers, `EfbPayments` and
  the logic limits all answer from it; their bundled lists are only the fallback for an EFB without the registry.
- **SPLIT gate.** `Efb/EfbFeatureGate.php`: a planned field the site cannot render becomes the closest field it can
  (calc → Number, map → Text, typed signature → drawn, mobile → Phone on Free, …) or is left out; the preview lists
  each with the add-on and plan (`addon_required`), offers the install and EFB's upgrade dialog.
- **Create through EFB.** `EasyFormBuilderConnector` builds a form package (schema 1) and calls
  `Emsfb\Form_Package::validate()` for the preview and `::import()` on approval; `import_token` (contract 1.1) lets the
  same administrator undo the creation (`/efb/undo` → `Form_Package::delete()`). `/efb/save` (direct table write) is
  closed (410). `Efb/EfbSaveNotes.php` turns EFB's `dropped[]`, `notices[]` and payment readiness into sentences.
- **Owner rules.** First Email field confirms to the visitor (noti 1, `email_to`, `sendEmail`), later ones stay off, off
  only on request (`visitor_confirmation` in the editor). A `jump_to_step` rule says which steps it skips and that their
  fields become optional, are not checked and are not charged.
- **Gateways are add-ons.** Stripe / PayPal / Persia Pay through their add-ons (legacy bridge, `update` state after the
  move), Mollie, Payplug, Tap, Omise, PayTabs, Paymob, Xendit (PAY00) with EFB's own field template. No registry → no
  payment ("update Easy Form Builder").
- **F16 / F17.** New forms get `efb_form_defaults_efb()`; national address schemes come from EFB's templates
  (`needs_states` left to EFB's page).
- **Knowledge.** The prompt carries the registry facts; 23 new help entries (F01–F21, add-ons, gateways, portal, Data
  Tools) end with this site's live state.
- **Connector registry.** `ConnectorBase` + `aisaautopilot_register_connectors` + capability `portal.link` for the
  coming customer portal connector (not built).
- **Tests.** PHPUnit (EfbSplitRegistryTest, EfbFormPackageConnectorTest, EfbKnowledgeRegistryTest,
  ConnectorRegistryPortalTest, additions to EfbPaymentsTest and EfbLogicRulesTest) on the registry a real EFB `dev4`
  returned; `tests/efb_e2e/` on a real WordPress with EFB `dev4` and its add-ons (REST flow and Chromium, en_US 1280 px,
  fa_IR and ar 375 px).

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
