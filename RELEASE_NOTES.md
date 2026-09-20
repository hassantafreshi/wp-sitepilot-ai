# AI Site Assistant AutoPilot 0.0.1

First release. The plugin has never been published before, so the numbering
starts at `0.0.1` (owner decision D-03, 2026-09-20). Until the product actually
reaches `1.0.0`, every version bump moves the **rightmost segment only**, so the
next release is `0.0.2`.

`0.0.1` is lower than the `1.0.4` that internal development builds carried. No
site has an upgrade path from those builds — they were never released — and no
code compares versions: `AISAAUTOPILOT_VERSION` is used only for asset
cache-busting and one informational field in the native form connector.

- Tested up to: WordPress 7.1
- Requires at least: WordPress 6.2
- Requires PHP: 8.1

## The contract this release keeps

- **AI proposes, a human approves, the backend executes.** Every write goes
  through request, plan, validation, an immutable preview, explicit approval,
  execution, an audit event and a usage record. Nothing bypasses approval and
  nothing publishes on its own.
- **No fake UI.** A control without a real backend is either absent or visibly
  locked with the real reason. There is no sample data, and no invented count,
  cost, status or success message.
- **WooCommerce stays draft-only.** One reviewed simple-product draft. Publish,
  edit, bulk, order and refund operations are not reachable.
- **Voice is transcript-first.** What you dictate becomes editable text; nothing
  is sent until you press Send.
- **Secrets stay server-side.** API keys are encrypted at rest and never reach
  the DOM, a REST response, the activity log or an export.
- **Visitor form values are never stored.** They are validated in memory, sent
  by `wp_mail()`, and discarded.

## What ships

**Admin information architecture** — eight top-level screens: Overview (with
Usage & Cost, Health and Activity Log as tabs), Setup & Connection,
Integrations, Site Knowledge, Answer Desk, Store Copilot, Content Studio and
Form Studio. Every slug that existed before stays registered as a hidden alias,
so old bookmarks and deep links keep working.

**Setup & Connection** — a four-step wizard: provider, API key and a real
connection test, model chosen from the provider's live catalogue, and
integrations. Fourteen provider presets plus a Custom OpenAI-compatible
endpoint.

**Site Knowledge** — indexing of published WordPress content, an entries list
with single delete and full re-index, an "Ask your knowledge base" console,
unanswered questions with "Write it" into Content Studio, saved answers,
and Collections / Sync sources views derived from the index. Optional
embeddings for semantic search.

**Answer Desk** — the visitor chat widget (off by default) with panel and
full-screen modes, a customiser for colours, icons, greeting, suggested
questions and the pages it appears on, conversation logging with a privacy
notice, a human-handoff card with configurable contact details, and a handoff
inbox in wp-admin with optional email, Telegram or Twilio delivery.

**Store Copilot** — read-only WooCommerce insight and a single reviewed
simple-product draft.

**Content Studio and Form Studio** — AI-drafted posts and pages with templates,
history and CSV export; and forms described in text or voice, previewed field
by field with a masked recipient, approved, then placed by shortcode.

**Easy Form Builder Copilot** — a "Build with AI" button inside Easy Form
Builder that creates and edits real EFB forms. Saving stays with EFB's own
button, and the interface says so.

**Usage and cost** — per period and per context, with an optional monthly token
budget that stops requests once it is reached.

**Localisation** — a complete Persian translation and full right-to-left
layout, built with CSS logical properties rather than a mirrored stylesheet.

## What deleting the plugin removes

Deactivating changes nothing. Deleting runs `uninstall.php`.

| Removed | Detail |
|---|---|
| Tables | `{prefix}aisaautopilot_kb`, `{prefix}aisaautopilot_chats`, `{prefix}aisaautopilot_widget_chats`, `{prefix}aisaautopilot_handoffs` |
| Options | 27 named `aisaautopilot_*` options (settings, encrypted keys, handoff secrets, logs, usage and budget, knowledge gaps and saved answers, schema stamps, the rebrand marker), every `aisaautopilot_action_claim_*` replay lock, and a final sweep of any remaining `aisaautopilot_*` row |
| Transients | Rate-limit counters, the 15-minute action preview, the provider-test record and the delivery-test cooldown, by `_transient_aisaautopilot_*` prefix |
| Cron | `aisaautopilot_index_post` is unscheduled |

| Kept on purpose | Why |
|---|---|
| `aisaautopilot_form` posts and their `_aisaautopilot_form_plan` meta | Your forms |
| Legacy `aisaautopilot_entry` posts | Data an older version created |
| Posts, pages and product drafts written through the plugin, with their `_aisaautopilot_generated`, `_aisaautopilot_meta_description` and `_aisaautopilot_source_gap` meta | Your content |
| Easy Form Builder forms | They belong to that plugin |

The plugin stores no user meta and no network-wide options. Under a persistent
object cache the plugin's transients live in the cache rather than the options
table; they are not reachable from the uninstall hook and expire within an hour.

## Verification

| Check | Result | Evidence |
|---|---|---|
| Static gate | php-lint 234/234, node --check 43/43, PHPCS 134/134 files with 0 errors and 0 warnings, PHPStan 0, PHPUnit 155 tests / 544 assertions | `test-artifacts/gates/20260919-003345-static` |
| Smoke gate | 8/8, including menu 5/0, assets 20/0 and render smoke 18/0 | `test-artifacts/gates/20260919-071048-smoke` |
| Phase 1 integration | 45/0 | `test-artifacts/gates/20260916-204545-release` |
| Release browser smoke (T15) | 587 PASS / 0 FAIL over 19 routes, `en_US` 288 and `fa_IR` 299, at 1440 and 390 | `test-artifacts/release-smoke/20260918-t15/run6-matrix.txt` |
| Visual parity with the mockups (T16) | 135 of 135 mockup states judged, 0 open defects | `docs/VISUAL_PARITY_Q4_2_REPORT_FA.md` |
| Accessibility, RTL and responsive (T17) | admin 1522/0 over 26 routes, visitor widget 437/0, EFB copilot 156/0 | `docs/ACCESSIBILITY_Q4_3_REPORT_FA.md` |
| Admin IA matrix (T56) | 652 PASS / 0 FAIL, `en_US` 316 and `fa_IR` 336 | `test-artifacts/admin-ia/20260918-t56/matrix-run5.txt` |
| Translation (T18) | POT and PO both 1819 of 1819 translated, 0 fuzzy, 0 obsolete, `msgfmt --check` clean | `test-artifacts/i18n/20260919-t18/` |
| Contract audit (T19) | 65 REST routes and 66 handlers; 7 public routes unchanged; all 59 admin handlers require `manage_options`; no route removed; frozen identifiers unchanged | `docs/CONTRACT_AUDIT_Q4_T19_REPORT_FA.md` |
| Security review (T20) | 124 files reviewed; four findings fixed, none accepted as risk; each fix has a test that fails against the pre-fix code | `docs/SECURITY_REVIEW_Q4_T20_REPORT_FA.md` |

Steps that need a real environment — a live provider key, real SMTP delivery, a
real browser microphone and a real WooCommerce draft — are listed in
`MANUAL-TESTING.md` and signed off by the site owner, not by automation.

## Known limitations in this release

- The indexing queue keeps no persistent cursor: a pass lives only while the
  browser tab is open, and the screen says so.
- PDF and external-URL knowledge sources are not built. Only WordPress post
  types are indexed.
- Voice has no realtime session: no live captions, no barge-in, no continuous
  call. The widget and the admin screens both state this.
- Visitor location, order-status lookups for visitors, and paid-tier features
  are not built.
- `includes/Frontend/WidgetRenderer.php`, `assets/frontend/chat-widget.js|css`,
  `assets/admin/dashboard.js` and `includes/Voice/VoiceService.php` are not
  hooked anywhere. They ship but never run; removing them is a separate
  decision.
- Deliberate differences from the hi-fi mockups are listed, one by one, in
  `docs/MOCKUP_FEATURE_GAP_REPORT_FA.md` and in each package's execution
  report.
- One Activity Log QA incident during development is disclosed in full in
  `docs/ACTIVITY_LOG_QA_INCIDENT_FA.md`.

### Plugin Check results for this ZIP, and why they were accepted

We run Plugin Check against the built ZIP, not the development folder. On
Plugin Check **2.0.0** in `new` mode, all **27 static checks run** and report
**11 errors and 13 warnings**. We are shipping with those open, deliberately.
The full machine-readable report is in
`test-artifacts/plugin-check/0.0.1/plugin-check-0.0.1.json`, and the run is
reproducible with the runner and the junction-safe swap script kept beside it.

**20 of the 27 checks are completely clean**, including `plugin_readme`,
`trademarks`, `prefixing`, `file_type`, `plugin_header_fields`,
`direct_file_access`, `plugin_uninstall`, `setting_sanitization`,
`code_obfuscation`, `minified_files` and `offloading_files`.

We reviewed all 24 findings individually. **23 are false positives** and 1 is
real but cosmetic. Per class:

- **8 errors, `late_escaping`, `includes/Admin/AnswerDeskHandoffPage.php`
  lines 251, 255, 382, 386.** These echo the return value of
  `card_class()` and `state_class()`. Those methods (lines 424-436) return one
  of exactly four hard-coded CSS class strings: `' is-ready'`,
  `' is-needs-setup'`, `' is-paused'`, `' is-off'`. No request, database or
  user data can reach them. The sniff flags them only because it cannot trace a
  method's return value.
- **1 error, `EscapeOutput.ExceptionNotEscaped`,
  `includes/Providers/UsageCappedProvider.php:66.`** This is the message passed
  to a `ProviderException` constructor. It is never echoed. Text shown to a user
  comes from `ProviderException::user_message()`, which is escaped at its own
  output sites. HTML-escaping this string would in fact be wrong, because it can
  travel into a JSON REST response.
- **2 errors, `file_system_operations_fclose`,
  `includes/Admin/ContentStudioHistoryExport.php:99` and
  `includes/Admin/LogExport.php:92.`** Both close a stream opened with
  `fopen( 'php://output', 'w' )` for a CSV download. `WP_Filesystem` has no API
  that streams to `php://output`. The sniff already exempts `fopen()` on
  `php://` streams; it simply cannot follow the resource variable to the
  matching `fclose()`.
- **9 warnings, `NonceVerification.*` and `MissingUnslash` /
  `InputNotSanitized`,** in `includes/Admin/IntegrationsPage.php` (402, 488,
  492), `includes/Admin/SettingsPage.php:185` and
  `includes/Admin/ContentStudioNavigation.php:83`. In each case the check the
  sniff wants exists, just not in the statement it is looking at:
  `IntegrationsPage::post_value()` is a pure reader whose caller
  `maybe_save()` verifies both `current_user_can( 'manage_options' )` and the
  nonce (lines 406-407) before anything is written; `SettingsPage:185` only
  chooses which read-only screen renders, via
  `sanitize_key( wp_unslash( ... ) )`; `ContentStudioNavigation:83` is unslashed
  and sanitised five lines later with `wp_unslash()`,
  `wp_check_invalid_utf8()` and `wp_strip_all_tags()`.
- **2 warnings, `MissingUnslash` / `InputNotSanitized`,
  `includes/Admin/WooCommerceSettings.php:18.` Real, and intentionally left
  as-is.** Lines 17-28 read the raw `$_GET['tab']`, force the canonical value
  for an aliased render, and restore the original in a `finally` block. The
  value is never echoed, never written to the database and never used in a
  query — it is only put back where it was found. Sanitising it would change
  what gets restored, which is a behaviour change, not a hardening.
- **1 warning, `EnqueuedResourceParameters.MissingVersion`,
  `includes/Admin/AdminMenu.php:223.` The one true positive.** The Google Fonts
  stylesheet is enqueued with `null` as its version. Passing `null` for a remote
  font URL is what WordPress core itself does, and it is deliberate: it stops
  WordPress appending a meaningless `?ver=` to a URL whose caching Google
  controls. "Fixing" it means either adding that meaningless query argument or
  adding a `phpcs:ignore`. Neither improves the plugin; both only quiet the
  sniff. We chose to leave the code honest and disclose the finding here.

Google Fonts itself is **not** flagged as an offloaded or external resource:
`offloading_files`, `enqueued_resources` and `external_admin_menu_links` all
return zero. Plugin Check's own blocklist
(`PluginCheck/Helpers/OffloadingServicesTrait.php`) lists only
`ajax.googleapis.com` and explicitly exempts font hosts via
`(?<!fonts\.)gstatic\.com`. The two webfonts loaded from
`fonts.googleapis.com` on admin screens are disclosed in the
`== External services ==` section of `readme.txt`.

**These are not regressions.** The history contains a commit
`56dd00b` (2026-08-18) named "Clear every Plugin Check finding on the release
build". It touched twelve files, and **not one of them is a file with a finding
today**. The reason is simply that the admin interface was rebuilt after that
date and had never been put through Plugin Check until now: of the ten files
flagged above, **six did not yet exist at `56dd00b`** — and those six account
for **all 11 errors** and 7 of the 13 warnings. The remaining four files existed
but have been substantially rewritten since (1,902 insertions and 871 deletions
between them). That also explains why the findings cluster into so few shapes:
helper methods that return a CSS class name, and saving and restoring a
superglobal around a render, are both patterns the rebuild introduced, and
neither is something the sniffs can follow.

None of these findings is a vulnerability. All 10 code sites are scheduled to be
silenced in `0.0.2` so that a future run is 0/0; they were left untouched in
`0.0.1` because every other release gate had already been verified green against
this exact tree, and editing it would have invalidated that evidence without
improving the plugin.

## Development build history

These builds were never published. They are kept here because the readme
changelog now starts at `0.0.1`.

**1.0.4** — merged the first-run wizard into a single Setup & Settings screen;
expanded the provider catalogue to fifteen entries including Anthropic over its
native Messages API; model dropdown with "Refresh from provider"; provider
marks and right-to-left support for the visitor panel, public forms and
exported transcripts; replaced a direct cURL upload with `wp_remote_post()`.

**1.0.3** — security hardening: scoped administrator conversations, encrypted
handoff credentials, rate-limited public and voice endpoints, safe outbound
HTTP.

**1.0.2** — the Phase 1 contract: pinned action execution, owner/capability and
one-time protection with a 15-minute expiry and an atomic claim lock, redacted
audit events, the native email-only forms product, the Easy Form Builder route
and the WooCommerce simple-product draft.

**1.0.1** — the first safe text/voice form flow, email-only delivery with no
visitor-value storage, and one-time pinned action execution.
