# SitePilot AI 1.0.2 - Phase 1 release candidate

## Release status

This release completes the Phase 1 contract. It does not claim that the
roadmap's Phase 2 commerce automation, RAG, billing, or cloud capabilities are
ready. The precise production acceptance steps are tracked in
`PHASE_1_RELEASE_READINESS_FA.md`.

## Included changes

- Pinned action execution: the connector selected at preview is the only one
  allowed to run after approval. A connector that becomes unavailable causes a
  safe failure and a fresh preview is required.
- Owner/capability/one-time protection: proposals expire after 15 minutes,
  cannot be approved by a different user, and use an atomic claim lock to
  prevent replay.
- Redacted action and delivery audit events; no form values are logged.
- Native SitePilot Forms data contract: 20-field maximum and only text, email,
  tel, textarea, select, radio, and checkbox fields. `phone` normalizes to
  `tel`; uploads and high-risk field types are rejected.
- **Create with AI** accepts text and a protected, maximum-45-second voice
  command, then displays the exact preview and masked delivery recipient.
- Public submission delivery uses `wp_mail()` with server-side validation,
  nonce, honeypot, hashed per-form/IP transient rate limit, and safe Reply-To.
  Visitor values are not stored by SitePilot.
- Easy Form Builder is visibly separated as the advanced-form route.
- WooCommerce can create only a separately approved **simple product draft**;
  publish, order, refund, cart, checkout, discount, and price writes remain
  unavailable.
- Every SitePilot admin route uses the shared UI shell. The form builder uses
  one exclusive compose/review/result state at a time.

## Verification performed

- PHP syntax check: 93 production files passed.
- PHPUnit: 102 tests / 383 assertions passed.
- PHPStan level 5: passed with no errors.
- Local WordPress Phase 1 integration: 34/34 checks passed.
- Native form smoke test: 8/8 checks passed.
- WooCommerce draft smoke test: 3/3 checks passed.
- Browser E2E: all 14 SitePilot admin pages, the single form-builder flow,
  390px mobile layout, RTL layout, widget launcher, TTS, and the safe public
  microphone endpoint passed with no SitePilot JavaScript console errors.
- The delivery test uses `pre_wp_mail` and verifies recipient, body,
  Reply-To, rate limiting, and that visitor values are never stored.

## Environment acceptance before public launch

- Configure an AI provider and execute `tests/ai_runtime_check.php`; this local
  environment intentionally contains no provider secret.
- Send a real form submission to the production notification inbox (and test a
  browser microphone) after configuring the site's SMTP/mail transport.
- Repeat the browser acceptance once in the target hosting/browser environment
  as part of the deployment checklist.
