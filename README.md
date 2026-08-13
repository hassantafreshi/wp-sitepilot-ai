# SitePilot AI — P1 form release contract

SitePilot AI is a local, BYOK WordPress copilot for creating **simple,
single-step SitePilot Forms**. An administrator describes a form by text or
voice, reviews an immutable preview, and explicitly approves its creation.

## What this release contract includes

- Text and protected voice commands in **SitePilot AI → Create with AI**.
- A preview/approval flow with a 15-minute, owner-bound, one-time action.
- A native form contract with at most 20 fields: `text`, `email`, `tel`,
  `textarea`, `select`, `radio`, and `checkbox`.
- A separate **Easy Form Builder** entry point for advanced forms when that
  plugin is installed. SitePilot Forms never silently switches to EFB.
- Public form submissions are validated in memory and delivered only with
  `wp_mail()`. SitePilot does not create a submission post, post meta, log
  entry, or option containing visitor field values.
- Nonce checks, honeypot, per-form/IP hashed transient rate limiting, safe
  Reply-To, and redacted audit events.

## Explicitly outside this release contract

- File uploads, payments, passwords, account registration/login, conditional
  logic, calculations, multi-step forms, and arbitrary SMTP recipients.
- Storage, reporting, export, or analytics of SitePilot Form submissions.
- A separately approved WooCommerce **simple product draft**. It can never
  publish a product; order, refund, price, cart, checkout and discount writes
  are out.

For the advanced form features above, install and use Easy Form Builder in its
own editor.

## Compatibility and release gates

| Area | Contract |
|---|---|
| WordPress | Requires 6.2 or later |
| PHP | Requires 8.1 or later |
| Native SitePilot Forms | Always available after plugin activation |
| Voice input | Browser `MediaRecorder` plus an OpenAI-compatible `/audio/transcriptions` endpoint |
| Easy Form Builder | Optional; detected independently and opened in its own UI |
| WooCommerce | Optional; not required for the native forms release |

Before a production launch, configure an AI provider and notification inbox,
then complete the environment acceptance checks in
`PHASE_1_RELEASE_READINESS_FA.md`.

## Install and use

1. Copy `sitepilot-ai` into `wp-content/plugins/` and activate it.
2. In **SitePilot AI → Settings**, configure an OpenAI-compatible provider and
   set the SitePilot Forms notification email (or keep the site administration
   email as fallback).
3. Open **SitePilot AI → Create with AI**. Type or record the request, review
   the exact fields and masked delivery address, then select **Approve & create
   form**.
4. Put the generated `[sitepilot_form id="123"]` shortcode in a page or post.

## Test commands

```powershell
C:\xampp\php\php.exe -l includes\Frontend\FormShortcode.php
C:\xampp\php\php.exe tests\phase1_smoke.php
```

The complete suite requires a supported PHPUnit 9.6+ or 10.5+ installation:

```bash
composer install
composer test
composer lint
composer analyze
```

For local XAMPP acceptance tests, also run `tests/wordpress_phase1_integration.php`,
`tests/phase1_smoke.php`, and `tests/woo_draft_smoke.php` with the XAMPP PHP
binary. `tests/ai_runtime_check.php` verifies a configured provider without
printing its secret.
