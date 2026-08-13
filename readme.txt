=== SitePilot AI ===
Contributors: sitepilot
Tags: ai, forms, form-builder, voice, email
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.3
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safe AI form copilot for WordPress: describe a simple form by text or voice,
review it, approve it, and deliver submissions by email without storing visitor
field values.

== Description ==

SitePilot AI creates simple, single-step SitePilot Forms through a safe admin
flow: request, AI plan, preview, explicit approval, and create.

The native SitePilot Forms product supports text, email, telephone, textarea,
select, radio and checkbox fields (up to 20 fields). Public submissions are
validated server-side and sent with wp_mail(). SitePilot does not save form
values in posts, post meta, options or activity logs.

Easy Form Builder is a separate, optional advanced-form path. Use it for
uploads, payments, conditional logic, calculations, multi-step flows or its
own advanced integrations.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install its zip.
2. Activate SitePilot AI.
3. Go to SitePilot AI > Settings and configure an AI provider plus the
   SitePilot Forms notification email.
4. Go to SitePilot AI > Create with AI and type or record a form request.
5. Review the fields and delivery destination, approve it, and place the
   generated shortcode in a page or post.

== Frequently Asked Questions ==

= Are form submissions stored in WordPress? =

No. SitePilot validates each submission in memory, sends an email, and
discards the visitor values.

= Can I make a payment, upload, login or multi-step form? =

No. Install and use Easy Form Builder for advanced form features.

= Can AI choose the email recipient? =

No. The recipient is configured by an administrator in SitePilot Settings and
is shown masked in the preview.

= Why is voice input unavailable? =

Voice requires browser microphone support and an AI provider compatible with
the `/audio/transcriptions` endpoint.

== Changelog ==

= 1.0.3 =
* Security hardening: scoped administrator conversations, encrypted handoff credentials, rate-limited public and voice endpoints, and safe outbound HTTP handling.

= 1.0.2 =
* Finalized the Phase 1 release contract and cross-page admin UI.
* Added a protected text/voice form creation flow with immutable preview,
  explicit approval, one-time execution, and redacted audit events.
* Added the independent, email-only SitePilot Forms product and its safe
  delivery controls.
* Added the optional Easy Form Builder handoff and safe WooCommerce simple
  product draft workflow.

= 1.0.1 =
* Added the P1 safe, text/voice SitePilot Forms flow.
* Added email-only delivery, no visitor-value storage, validation and abuse
  controls.
* Added one-time pinned action execution and redacted audit events.
* Separated the Easy Form Builder advanced-form path.
