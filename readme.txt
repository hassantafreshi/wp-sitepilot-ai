=== AI Site Assistant AutoPilot ===
Contributors: aisaautopilot
Tags: ai, forms, form-builder, voice, email
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Describe a form by text or voice, review it, approve it, and deliver submissions by email without storing visitor field values.

== Description ==

AI Site Assistant AutoPilot creates simple, single-step AISAAutoPilot Forms through a safe admin
flow: request, AI plan, preview, explicit approval, and create.

The native AISAAutoPilot Forms product supports text, email, telephone, textarea,
select, radio and checkbox fields (up to 20 fields). Public submissions are
validated server-side and sent with wp_mail(). AISAAutoPilot does not save form
values in posts, post meta, options or activity logs.

Easy Form Builder is a separate, optional advanced-form path. Use it for
uploads, payments, conditional logic, calculations, multi-step flows or its
own advanced integrations.

The interface is fully right-to-left aware. It is laid out with CSS logical
properties rather than a mirrored stylesheet, and the visitor chat panel and
public forms declare their own text direction, so they read correctly even in a
theme that never sets one.

== External services ==

AI Site Assistant AutoPilot does not call any AISAAutoPilot server, and it does not phone home.
Every outbound request goes to a service you choose and configure yourself. No
request is made until you save a provider and an API key.

**AI provider (required for any AI feature)**

You pick one provider in AI Site Assistant AutoPilot > Setup & Settings and supply your own API
key. Requests are sent only when you generate a form, chat with the assistant,
index knowledge, or press "Test connection" / "Refresh from provider".

What is sent: the instruction you typed or dictated, the plugin's own system
prompt, and — for the assistant and knowledge base — excerpts of the published,
public content of your site. Your API key is sent as the request credential. No
visitor form submissions are ever sent to an AI provider.

Supported providers, each with its own terms and privacy policy:
OpenAI (https://openai.com/policies/terms-of-use,
https://openai.com/policies/privacy-policy),
Anthropic (https://www.anthropic.com/legal/consumer-terms,
https://www.anthropic.com/legal/privacy),
Google Gemini (https://ai.google.dev/gemini-api/terms,
https://policies.google.com/privacy),
DeepSeek (https://platform.deepseek.com/downloads/DeepSeek%20Terms%20of%20Service.html,
https://platform.deepseek.com/downloads/DeepSeek%20Privacy%20Policy.html),
Alibaba Cloud Model Studio / Qwen (https://www.alibabacloud.com/help/en/legal,
https://www.alibabacloud.com/help/en/legal/latest/alibaba-cloud-international-website-privacy-policy),
Mistral AI (https://mistral.ai/terms, https://mistral.ai/terms#privacy-policy),
Groq (https://groq.com/terms-of-sale/, https://groq.com/privacy-policy/),
Perplexity (https://www.perplexity.ai/hub/legal/terms-of-service,
https://www.perplexity.ai/hub/legal/privacy-policy),
Azure OpenAI (https://azure.microsoft.com/support/legal/,
https://privacy.microsoft.com/privacystatement),
OpenRouter (https://openrouter.ai/terms, https://openrouter.ai/privacy),
AI/ML API (https://aimlapi.com/terms-and-conditions,
https://aimlapi.com/privacy-policy),
Novita AI (https://novita.ai/legal/terms-of-service,
https://novita.ai/legal/privacy-policy),
Eden AI (https://www.edenai.co/terms-and-conditions,
https://www.edenai.co/privacy-policy),
Clusy.io (https://clusy.io/terms, https://clusy.io/privacy).
You may also point the "Custom" preset at any other OpenAI-compatible endpoint,
in which case that endpoint's terms apply.

**Telegram (optional)**

Only if you enable Telegram handoff and enter a bot token and chat ID. The chat
transcript the visitor asked to escalate, and an optional PDF of it, are sent to
api.telegram.org. Terms: https://telegram.org/tos, privacy:
https://telegram.org/privacy

**Twilio (optional)**

Only if you enable WhatsApp/SMS handoff and enter your Twilio credentials. The
handoff message and the numbers you configured are sent to api.twilio.com.
Terms: https://www.twilio.com/legal/tos, privacy:
https://www.twilio.com/legal/privacy

Provider logos shown in the settings screen use path data from Simple Icons
(https://simpleicons.org), released under CC0-1.0. The marks remain the
trademarks of their owners and are shown only to identify each service.
AI Site Assistant AutoPilot is not affiliated with, or endorsed by, any of them.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install its zip.
2. Activate AI Site Assistant AutoPilot.
3. Go to AI Site Assistant AutoPilot > Settings and configure an AI provider plus the
   AISAAutoPilot Forms notification email.
4. Go to AI Site Assistant AutoPilot > Create with AI and type or record a form request.
5. Review the fields and delivery destination, approve it, and place the
   generated shortcode in a page or post.

== Frequently Asked Questions ==

= Are form submissions stored in WordPress? =

No. AISAAutoPilot validates each submission in memory, sends an email, and
discards the visitor values.

= Can I make a payment, upload, login or multi-step form? =

No. Install and use Easy Form Builder for advanced form features.

= Can AI choose the email recipient? =

No. The recipient is configured by an administrator in AISAAutoPilot Settings and
is shown masked in the preview.

= Why is voice input unavailable? =

Voice requires browser microphone support and an AI provider compatible with
the `/audio/transcriptions` endpoint.

== Changelog ==

= 1.0.4 =
* Merged the first-run setup wizard into a single Setup & Settings screen; the
  progress strip is now derived from real state instead of a stored step.
* Expanded the AI provider catalogue to fifteen entries, including Anthropic
  (Claude) over its native Messages API, Google Gemini, Mistral, Perplexity,
  Azure OpenAI and several gateways.
* Models are chosen from a dropdown, with a "Refresh from provider" button that
  reads the provider's live catalogue instead of a list frozen at release.
* Added provider marks to the picker and completed right-to-left support for the
  visitor chat panel, public forms and exported transcripts.
* Replaced a direct cURL upload with wp_remote_post(), removed a dead
  translation subsystem whose strings were unextractable, and corrected around
  sixty Persian entries that carried another string's translation.

= 1.0.3 =
* Security hardening: scoped administrator conversations, encrypted handoff credentials, rate-limited public and voice endpoints, and safe outbound HTTP handling.

= 1.0.2 =
* Finalized the Phase 1 release contract and cross-page admin UI.
* Added a protected text/voice form creation flow with immutable preview,
  explicit approval, one-time execution, and redacted audit events.
* Added the independent, email-only AISAAutoPilot Forms product and its safe
  delivery controls.
* Added the optional Easy Form Builder handoff and safe WooCommerce simple
  product draft workflow.

= 1.0.1 =
* Added the P1 safe, text/voice AISAAutoPilot Forms flow.
* Added email-only delivery, no visitor-value storage, validation and abuse
  controls.
* Added one-time pinned action execution and redacted audit events.
* Separated the Easy Form Builder advanced-form path.
