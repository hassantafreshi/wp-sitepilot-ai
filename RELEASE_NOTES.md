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
- **Visitor form values are never stored by default.** They are validated in
  memory, sent by `wp_mail()`, and discarded. Only if the administrator turns
  on "Keep a submission when its email cannot be sent" is a submission whose
  email failed kept, retried, and listed under Your forms to resend or delete,
  until it is sent or its retention period (30 days) ends.

## What ships

**Admin information architecture** — eight top-level screens: Overview (with
Usage & Cost, Health and Activity Log as tabs), Setup & Connection,
Integrations, Site Knowledge, Answer Desk, Store Copilot, Content Studio and
Form Studio. Every slug that existed before stays registered as a hidden alias,
so old bookmarks and deep links keep working.

**Setup & Connection** — a four-step wizard: provider, API key and a real
connection test, model chosen from the provider's live catalogue, and
integrations. Twenty-three provider presets plus a Custom OpenAI-compatible
endpoint: OpenAI, Anthropic (native Messages API), Google Gemini, xAI Grok,
DeepSeek, Qwen, Mistral AI, Cohere, Z.ai GLM, Moonshot AI (Kimi), MiniMax,
Groq, Cerebras, SambaNova Cloud, Azure OpenAI, and the gateways OpenRouter,
Together AI, Fireworks AI, Hugging Face Inference Providers, NVIDIA NIM, AI/ML
API, Novita AI and Eden AI. Perplexity (its Chat Completions API ended on
2026-09-27) and Clusy.io (no public API could be confirmed) were removed at the
owner's request. Model lists were refreshed on 2026-10-26 from vendor
SDKs and official documentation (T75); anything that could not be confirmed is
listed in `docs/research/T75_MODEL_CATALOG_RESEARCH.md`. A reasoning model that rejects a
custom `temperature` is retried once without it instead of failing (this also
covers Kimi's fixed temperature), and `<think>…</think>` reasoning that MiniMax
and some open models write into the reply is stripped before it is used.

**Site Knowledge** — indexing of published WordPress content, an entries list
with single delete and full re-index, an "Ask your knowledge base" console,
unanswered questions with "Write it" into Content Studio, saved answers,
and Collections / Sync sources views derived from the index. Optional
embeddings for semantic search.

Indexing runs on the site's own server and makes no AI request: each page is
split by its headings and paragraphs into passages of about 900 characters,
WooCommerce price, stock, SKU and attributes become a passage of their own,
and every passage's words go into a search index (BM25) in which Persian,
Arabic and English spellings, digits and the zero-width non-joiner are folded
together. Password-protected and private content is never indexed, and is
checked again before anything is quoted. The assistant is given the passages
that match the question, not post summaries. A question that nothing on the
site relates to is answered "not found" without an AI request; the site owner
chooses whether visitors then see a message (their own or the built-in one)
or an offer to connect to a person. The widget sends its last few turns, so
a follow-up such as "and how much is it?" keeps its subject. Sites upgraded
from a development build are rebuilt in the background.

Measured on a 158-page test site (coffee shop, English and Persian, with
products, a long guide, a password-protected page and 120 filler posts): the
fact a question needs reached the model for 25 of 25 questions, against 0 of
25 with the previous summary-based index; indexing took 1.8 seconds instead of
about eight minutes of AI requests; end to end with a real model, 8 of 8
answers were right, against 2 of 8. Details are in
`docs/KB_RETRIEVAL_BENCHMARK_FA.md`.

**Privacy** — a personal-data exporter and eraser for WordPress's privacy
tools, matching requests to talk to a person, console conversations and
recorded visitor conversations by email address, and suggested privacy-policy
text under Settings > Privacy > Policy Guide. **Data retention** in Advanced
settings deletes visitor data once a day after a period the administrator sets:
recorded conversations 30 days, requests to talk to a person 180 days and
unanswered questions (with cached answers) 90 days by default, 0 to keep. The
card shows the last and next run, has "Delete expired data now", and explains
how to add a server cron job by hand for sites with little traffic or
`DISABLE_WP_CRON`. The suggested privacy-policy text states the periods.

**Multisite** — each site of a network keeps its own tables, settings and keys.
Network activation sets up every site; a site added later, or every site of a
network WordPress counts as large (where one request could not set them all
up), sets itself up on its first request. Deleting a site drops the plugin's
tables with it, and uninstall cleans every site of the network.

**Answer Desk** — the visitor chat widget (off by default) with panel and
full-screen modes, a customiser for colours, icons, greeting, suggested
questions and the pages it appears on, conversation logging with a privacy
notice, a human-handoff card with configurable contact details, and a handoff
inbox in wp-admin with optional email, Telegram, WhatsApp Business (Meta's
Cloud API, with an approved message template so a request can always start the
chat), SMS or WhatsApp through Twilio, Slack (incoming webhook) or Signal
(through a signal-cli REST API server the site owner runs) delivery. Every
credential is stored encrypted, and each channel has a "Check connection"
button; only Slack's posts a line, because a webhook cannot be read.

**Store Copilot** — read-only WooCommerce insight and a single reviewed
simple-product draft.

**Content Studio and Form Studio** — AI-drafted posts and pages with templates,
history and CSV export; and forms described in text or voice, previewed field
by field with a masked recipient, approved, then placed by shortcode. Form
Studio is one full-height workspace: a chat beside a canvas that shows six
example forms, then the plan's fields with a desktop and phone preview, then
the created form. A follow-up message changes the current plan instead of
starting over, and a checklist names what is missing (the Integrations switch,
an AI service, the delivery email) before the first request.

**Form text follows the site language.** Visitors read the form in the site
language, so every text the AI writes on a form (title, labels, placeholders,
help, options, button and messages) is in the site language, and on a Persian or
Arabic site it reads right to left, whatever language the admin writes the
request in or uses for the dashboard. The few texts the plugin writes itself (the
Submit, Next and Previous buttons, the thank-you lines, a page title, the consent
sentence) follow the site language too, even on a request that carries the
admin's own language. Only an explicit ask such as "in English" changes the
form's language.

**Easy Form Builder Copilot** — an assistant inside Easy Form Builder that
creates and edits real EFB forms. It sits in a column beside the editor (a
sheet on phones) and opens with Ctrl+/ (⌘+/ on a Mac), the Ask AI button, or
"Ask AI about" next to a field picked on the canvas. Each change arrives as a
card with its own Undo, and the button counts the AI changes not yet saved.
Saving stays with EFB's own button, and the interface says so. Without an AI
service the assistant shows the setup steps instead of failing. Checked against EFB 4.3.1 and its add-ons
(T74):

- *New form:* a preview before anything is written, with help text, a submit
  button label, steps for long forms, and the exact notification recipients.
  A consent checkbox links only to the site's own Privacy Policy page; any
  address the AI writes is stripped. `notify_admin` fills in the site's admin
  email and `confirm_to_respondent` binds the confirmation to the form's own
  email field, exactly like EFB's contact template; the AI never sees or picks
  an address.
- *Field types:* eleven more types can be generated — mobile, rating, 5- and
  10-point scales, yes/no, switch, terms checkbox, colour, drag-and-drop
  upload, checklist with quantities and rating matrix — plus 31 aliases. EFB
  reserves all eleven for its Free Plus and Pro plans, so on Free they are left
  out with an upgrade note, as before; that includes the consent checkbox and
  the review's one-click consent fix.
- *Open form:* `rename_option` and `add_logic_rule` join the existing
  operations. Every AI change gets its own Undo button that restores the canvas
  to exactly how it was before that change.
- *Conditional logic* (only when the Conditional Logic add-on is active):
  show/hide a field or step, required/optional, jump to step. References are
  resolved on the server against the open form, choices are stored by their
  option id as EFB's own builder does, self-references and show-and-hide on
  one target are refused, conflicts with existing rules are reported, and the
  plan limits (Free Plus: 3 rules × 2 conditions) are enforced.
- *Explain logic:* existing rules are explained in plain language on the
  server, without an AI call.
- *Review:* deterministic checks for missing or duplicate labels, choice
  fields with fewer than two options, personal data without consent (with an
  Apply button), too many required fields, long single-page forms and missing
  spam protection (silent when reCAPTCHA or EFB's Form Security & Spam
  Protection add-on is on).

**Usage and cost** — per period and per context, with an optional monthly token
budget that stops requests once it is reached.

**Localisation** — a complete Persian translation and full right-to-left
layout, built with CSS logical properties rather than a mirrored stylesheet.

## What deleting the plugin removes

Deactivating changes nothing. Deleting runs `uninstall.php`.

| Removed | Detail |
|---|---|
| Tables | `{prefix}aisaautopilot_kb`, `{prefix}aisaautopilot_kb_chunks`, `{prefix}aisaautopilot_kb_terms`, `{prefix}aisaautopilot_chats`, `{prefix}aisaautopilot_widget_chats`, `{prefix}aisaautopilot_handoffs` |
| Options | 32 named `aisaautopilot_*` options (settings, encrypted keys, handoff secrets, logs, usage and budget, knowledge gaps and saved answers, the passage index format and its rebuild cursor, schema stamps, the site-setup marker, the last retention run, the rebrand marker), every `aisaautopilot_action_claim_*` replay lock, and a final sweep of any remaining `aisaautopilot_*` row |
| Transients | Rate-limit counters, the 15-minute action preview, the provider-test record and the delivery-test cooldown, by `_transient_aisaautopilot_*` prefix |
| Cron | `aisaautopilot_index_post` (every queued page), `aisaautopilot_kb_rebuild` and the daily `aisaautopilot_retention_cleanup` are unscheduled |

| Kept on purpose | Why |
|---|---|
| `aisaautopilot_form` posts and their `_aisaautopilot_form_plan` meta | Your forms |
| Legacy `aisaautopilot_entry` posts | Data an older version created |
| Posts, pages and product drafts written through the plugin, with their `_aisaautopilot_generated`, `_aisaautopilot_meta_description` and `_aisaautopilot_source_gap` meta | Your content |
| Easy Form Builder forms | They belong to that plugin |

On a multisite network all of this is removed from every site. The plugin
stores no user meta and no network-wide options. Under a persistent
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

**Changed after the 21 September freeze.** The table above was measured on the
frozen tree. T74 and T75 changed the code afterwards, so the ZIP, Plugin Check,
clean-install and browser rows must be measured again before release (T22 to
T25). Measured on the new tree on 2026-10-26, outside WordPress: PHPUnit 191
tests / 1011 assertions, PHPStan 0, PHPCS 136 files with 0 errors and 0
warnings, php-lint 241/241, `node --check` 47/47, and the offline T74 copilot
browser check 65 PASS / 0 FAIL (`tests/efb_copilot_t74_offline_check.cjs`: the
real `efb-copilot.js` against replies built by the real controller). The same
T74 steps run inside `efb_copilot_browser_matrix.cjs` on a real Easy Form
Builder install.

On the same day the whole suite ran on a real site built in the cloud
container: WordPress 7.1.3, MariaDB 10.11, PHP 8.3 and Easy Form Builder 4.3.1
with its Conditional Logic and Human Shield add-ons. The EFB copilot matrix
passed 184/0 on the Free plan and 204/0 on Free Plus with Conditional Logic, in
`en_US` and `fa_IR`; the new end-to-end check (`efb_copilot_logic_e2e_check.cjs`:
EFB's own Save, then the rule on the published form) passed 8/0; and all 75
suites together gave 4444 PASS with one FAIL, the WooCommerce prerequisite, and
no PHP notice in `debug.log`. That site differs from the owner's in four stated
ways: a stub stands in for WordPress's Persian core language pack, three
blocked third-party hosts get an empty reply from a shim outside the
repository, the provider is a placeholder on a reserved `.invalid` host, and
WooCommerce is not installed. The run found and fixed two defects: unlabelled
rows on the Easy Form Builder details page, and eleven hidden admin pages
without a title.

A third pass used a real Claude key (Sonnet 5.5, about $0.70 in all) and a real
WooCommerce 11.2.1. The provider test and live model list worked; fifteen real
form-builder requests in English and Persian passed 19/19
(`tests/efb_copilot_live_ai_check.php`); the copilot driven by a real model in
the browser passed 8/8 (`tests/efb_copilot_live_ai_browser_check.cjs`); and a
real Store Copilot approval created exactly one draft and nothing else. With
WooCommerce and the real Persian language pack installed, all 75 suites gave
4399 PASS and 0 FAIL. This pass found three more defects, all fixed: new Easy
Form Builder forms with a consent checkbox were rejected as an unsupported type
(and the reply still said the form was built); Test connection failed on
models that always think (Claude Opus 5, Fable 5) and could fail on reasoning
models behind OpenAI-compatible endpoints; and a timing race in the release
browser check. Plugin Check 2.1.0 on a trial build first reported 0 errors and
13 warnings from its new `AIProvider.DirectIntegration` check. Under owner
decision D-35 the five files that were never hooked
(`includes/Frontend/WidgetRenderer.php`, `assets/frontend/chat-widget.js|css`,
`assets/admin/dashboard.js` and `includes/Voice/VoiceService.php`) were
deleted, and the provider presets carry a documented suppression: they are
bring-your-own-key defaults the administrator chooses, and the WordPress AI
Client exists only from WordPress 7.0 while this plugin supports 6.2. Plugin
Check 2.1.0 then reports no errors and no warnings.

**Measured again on 2026-10-28**, after data retention, multisite and the
redesigned copilot and Form Studio. Static: PHPUnit 216 tests / 1151
assertions, PHPStan 0, PHPCS 0 errors and 0 warnings. Browser, on the same
container site: EFB copilot matrix 107/0 in `en_US` and 107/0 in `fa_IR`,
offline T74 check 65/0, logic end-to-end 8/0, Form Studio 43/0 (both
languages), Advanced settings 18/0 and 20/0. The Persian translation covers
all 2118 strings. Plugin Check 2.1.0 on the built ZIP, with **every check in
all five categories** (General, Plugin Repo, Security, Performance,
Accessibility), the experimental checks, low-severity errors and warnings, and
the runtime checks (script and style size, scope, blocking and footer
placement, run with the release copy active), reports **0 errors and 0
warnings** in both `new` and default mode. No AI request was made for this
pass.

**Final QA, 2026-11-03.** A strict pass over the whole product found and
fixed five defects (details in `docs/CTO_REVIEW_AND_FINAL_QA_FA.md`):

- **Critical.** Form Studio's own menu page loaded the old script after the
  redesign, so its chat did not work there.
- **Plain permalinks.** Several admin requests returned 404, leaving the Site
  Knowledge list and the unanswered questions empty.
- **Behind Cloudflare.** All visitors shared one rate limit.
- **Unsaved AI changes.** Leaving the Easy Form Builder editor gave no warning.
- **Stalled scheduler.** The retention card showed a past date as "next
  cleanup" when WP-Cron was not running.

Each fix has its own test. Results on the final commit:

- the full regression on the final commit gave 4662 PASS and 0 FAIL over 86 suites, including multisite 7/0 and Plain permalinks 6/0, with no PHP notice in debug.log (the fa_IR halves of the a11y and Form Studio matrices were re-run after a slow fa_IR dashboard login in this container: 764/0 and 28/0)
- PHPUnit 225 tests / 1178 assertions.
- PHPStan 0, including a run targeting PHP 8.1.
- PHPCS 0/0.
- Plugin Check 2.1.0 on the ZIP, all categories plus runtime: 0/0.
- Clean install, uninstall (nothing left behind) and reinstall from the ZIP.
- Stored-XSS probing of every admin screen: nothing executed.
- REST access control: every admin route returns 401 without a login.

The provider key used earlier is now rejected by the provider, so no live
model call was made.

## Known limitations in this release

- The indexing queue keeps no persistent cursor: a pass lives only while the
  browser tab is open, and the screen says so.
- PDF and external-URL knowledge sources are not built. Only WordPress post
  types are indexed.
- Voice has no realtime session: no live captions, no barge-in, no continuous
  call. The widget and the admin screens both state this.
- Visitor location, order-status lookups for visitors, and paid-tier features
  are not built.
- Deliberate differences from the hi-fi mockups are listed, one by one, in
  `docs/MOCKUP_FEATURE_GAP_REPORT_FA.md` and in each package's execution
  report.
- One Activity Log QA incident during development is disclosed in full in
  `docs/ACTIVITY_LOG_QA_INCIDENT_FA.md`.
- Easy Form Builder copilot: payments, prices, login and registration forms,
  add-on activation, reCAPTCHA keys and EFB's global settings are never set by
  AI. Logic rules that calculate, write values, or drive notifications,
  confirmations and webhooks are left to EFB's own screens. Notifications can
  be switched on only when a new form is created. Fields cannot yet be moved,
  Undo does not survive a page reload, and PDFs or images cannot be imported.
  The full list, with the reason for each item, is in
  `docs/EFB_AI_FORM_BUILDER_T74_T75_FA.md`.

### Plugin Check

**Latest run, 2026-10-28:** Plugin Check **2.1.0** on the built ZIP, with all
checks in all five categories, the experimental checks, low-severity findings
and the runtime checks, reports **0 errors and 0 warnings**. The runtime checks
register only when `plugin check` comes first on the WP-CLI command line, so
the run used `wp plugin check ai-site-assistant-autopilot --require=…/plugin-check/cli.php
--include-experimental --include-low-severity-errors --include-low-severity-warnings`
with the release copy installed and active.

The history below is kept for the record. We run Plugin Check against the built
ZIP, not the development folder. On Plugin Check **2.0.0** in `new` mode, all **27 static checks run** and the
result is **0 errors and 0 warnings**, with no check failing to run. The
machine-readable report is in
`test-artifacts/plugin-check/0.0.1-rebuild-20260921/plugin-check-0.0.1.json`,
and the run is reproducible with the runner and the junction-safe swap script
kept beside it.

An earlier build of this release reported 11 errors and 13 warnings. Every one
of those 24 findings was reviewed individually and then **fixed, not accepted**,
across 10 files. Where a finding was a genuine defect it was corrected in code;
where the sniff could not follow the data flow, the code now carries a
`phpcs:ignore` with the reason stated inline, so the next reader sees the
argument instead of having to rebuild it. Two of those judgements are worth
recording, because the obvious fix would have been wrong:

- In `includes/Providers/UsageCappedProvider.php` the flagged string is the
  message given to a `ProviderException` constructor. It is never echoed; it
  reaches only the logger and a `wp_json_encode()` body. Wrapping it in
  `esc_html__()` would have put HTML entities inside a JSON payload, so the
  finding is silenced with a documented reason instead.
- In `includes/Admin/WooCommerceSettings.php` the raw `$_GET['tab']` is stored
  and put back in a `finally` block around an aliased render. Sanitising it
  would have silently rewritten the caller's superglobal, so it too is
  documented rather than changed.

The rebuild changed 1,044 bytes: comments, `phpcs:ignore` annotations and
`esc_attr()` calls. The compiled translation file was not touched. The full
gate suite was re-run against the changed tree and stayed green (php-lint
234/234, PHPCS 134/134 with 0/0, PHPStan 0, PHPUnit 156 tests / 547
assertions, and the admin smokes at 5/0, 20/0 and 18/0), the ZIP passed
independent structural verification at 31 PASS / 0 FAIL, and the clean-install
acceptance was repeated end to end at 23 PASS / 0 FAIL with the WordPress
debug log not growing by a single byte.

Google Fonts is not flagged as an offloaded or external resource:
`offloading_files`, `enqueued_resources` and `external_admin_menu_links` all
return zero. The two webfonts loaded from `fonts.googleapis.com` on admin
screens are disclosed in the `== External services ==` section of `readme.txt`.

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
