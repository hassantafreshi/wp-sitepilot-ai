=== AI Site Assistant AutoPilot ===
Contributors: aisaautopilot
Tags: ai, forms, form-builder, voice, email
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.0.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An AI copilot for your site that proposes, never publishes: every form, draft and product is previewed and approved by a human first.

== Description ==

AI Site Assistant AutoPilot adds an AI copilot to wp-admin. The AI proposes,
you review an exact preview, and only your approval lets the plugin write
anything. It never publishes on its own.

Everything in this first version:

* Overview screen with four tabs: Overview, Usage & Cost, Health and Activity Log.
* Setup & Connection wizard: pick one of fourteen AI provider presets, or point
  the Custom preset at any OpenAI-compatible endpoint. Save your own API key
  encrypted, test the connection, and choose a model from the provider's live
  catalogue.
* Integrations screen: one place to turn each capability on or off and see
  which ones this site can actually run.
* Site Knowledge: index your published content, browse and re-index entries,
  an "Ask your knowledge base" console, unanswered questions and saved answers,
  plus Collections and Sync sources views of what is read. WordPress post types
  are the only sources in this version. Optional embeddings for semantic search.
* Answer Desk: a visitor chat widget (off until you enable it) with panel and
  full-screen modes, conversation history, a customiser for colours, icons,
  greeting and suggested questions, and a rule for which pages it appears on.
* Human handoff: a visitor can ask for a person. The request reaches an inbox
  in wp-admin, and optionally email, Telegram or WhatsApp/SMS through Twilio.
* Voice input that is transcript-first: speech becomes editable text in the
  message box and is never sent by itself.
* Store Copilot for WooCommerce: read-only store insight, plus creation of a
  single reviewed simple-product draft. Publishing, editing, bulk changes,
  orders and refunds stay unavailable.
* Content Studio: AI-drafted posts and pages with templates, an immutable
  preview, explicit approval, a history screen and CSV export. Drafts only.
* Form Studio: describe a form in text or by voice, review the exact fields
  and the masked delivery recipient, approve, and place the shortcode.
* Native AISAAutoPilot Forms: text, email, tel, textarea, select, radio and
  checkbox, up to 20 fields, validated server-side and delivered by
  wp_mail(). Visitor values are never stored in posts, meta, options or logs.
* Easy Form Builder Copilot: a "Build with AI" button inside Easy Form Builder
  that creates and edits real EFB forms. Saving stays with EFB's own button.
* Usage & cost tracking per period and per context, with an optional monthly
  token budget that stops requests once it is reached.
* Activity log of plans, approvals and executions, with API keys and tokens
  redacted.
* Full right-to-left support and a complete Persian translation. The interface
  uses CSS logical properties rather than a mirrored stylesheet, and the
  visitor panel and public forms declare their own text direction.

Easy Form Builder is a separate, optional plugin. Use it for uploads,
payments, conditional logic, calculations or multi-step flows.

== What deleting the plugin removes ==

Deactivating changes nothing. Deleting the plugin from the Plugins screen runs
uninstall.php, which removes exactly this:

Database tables (dropped):

* {prefix}aisaautopilot_kb - the knowledge index.
* {prefix}aisaautopilot_chats - administrator conversations.
* {prefix}aisaautopilot_widget_chats - visitor conversations.
* {prefix}aisaautopilot_handoffs - handoff requests, including the contact
  details a visitor typed.

Options (deleted): aisaautopilot_settings, aisaautopilot_provider_key,
aisaautopilot_embedding_key, aisaautopilot_handoff_secrets,
aisaautopilot_wp_connector_key, aisaautopilot_provider_verified,
aisaautopilot_setup_step, aisaautopilot_logs, aisaautopilot_usage_log,
aisaautopilot_usage_totals, aisaautopilot_monthly_budget,
aisaautopilot_kb_gaps, aisaautopilot_kb_saved_answers,
aisaautopilot_kb_answer_cache, aisaautopilot_kb_version,
aisaautopilot_usage_period, aisaautopilot_usage_period_contexts,
aisaautopilot_usage_daily, aisaautopilot_usage_historical_totals,
aisaautopilot_usage_historical_log, aisaautopilot_usage_period_archive,
aisaautopilot_voice_usage_log, aisaautopilot_health_diagnostics,
aisaautopilot_last_diagnostics, aisaautopilot_handoff_inbox_schema,
aisaautopilot_widget_chat_schema, aisaautopilot_rebrand_migrated, and every
aisaautopilot_action_claim_* replay lock.

Anything else stored under the plugin's own aisaautopilot_ prefix is swept by
prefix, so an install that was migrated from the plugin's former name does not
leave rows behind.

Temporary entries (deleted): the plugin's transients - rate-limit counters,
the fifteen-minute action preview, the provider-test record and the delivery
test cooldown. They hold no personal data (callers are stored as a keyed hash,
never a raw IP address) and none of them lives longer than an hour. If your
site uses a persistent object cache, transients are held there instead of in
the options table; they are not reachable from the uninstall hook and expire on
their own.

The scheduled indexing job (aisaautopilot_index_post) is unscheduled.

Kept on purpose, because it is your content:

* Forms you created, as aisaautopilot_form posts, and their stored plan in the
  _aisaautopilot_form_plan post meta.
* Legacy aisaautopilot_entry posts, if an older version created any.
* Posts, pages and WooCommerce product drafts written through Content Studio
  or Store Copilot, and their _aisaautopilot_generated,
  _aisaautopilot_meta_description and _aisaautopilot_source_gap meta.
* Forms created in Easy Form Builder. They belong to that plugin.

The plugin stores no user meta and no site-wide (network) options.

== External services ==

AI Site Assistant AutoPilot does not call any AISAAutoPilot server, and it does not phone home.
Every outbound request goes to a service you choose and configure yourself. No
request is made until you save a provider and an API key.

**AI provider (required for any AI feature)**

You pick one provider in AI Site Assistant AutoPilot > Setup & Connection and supply your own
API key. Requests are sent only when you generate a form or a draft, chat with
the assistant, index knowledge, transcribe a voice message, or press "Test
connection" / "Refresh from provider".

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

**Google Fonts (admin screens only)**

The plugin's admin screens load the Plus Jakarta Sans and JetBrains Mono
webfonts from fonts.googleapis.com. This request is made by the browser of a
logged-in administrator viewing one of the plugin's own pages; no visitor page
and no site content is involved, and nothing is sent about the site itself.
Terms: https://policies.google.com/terms, privacy:
https://policies.google.com/privacy

Provider logos shown in the settings screen use path data from Simple Icons
(https://simpleicons.org), released under CC0-1.0. The path data is bundled
with the plugin, so no request is made to that service. The marks remain the
trademarks of their owners and are shown only to identify each service.
AI Site Assistant AutoPilot is not affiliated with, or endorsed by, any of them.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install its zip.
2. Activate AI Site Assistant AutoPilot.
3. Go to AI Site Assistant AutoPilot > Setup & Connection and complete the four
   steps: provider, API key and connection test, model, and integrations.
4. Go to AI Site Assistant AutoPilot > Form Studio and type or record a form
   request.
5. Review the fields and the delivery destination, approve it, and place the
   generated shortcode in a page or post.

The visitor chat widget is off until you enable it in Answer Desk.

== Frequently Asked Questions ==

= Are form submissions stored in WordPress? =

No. AISAAutoPilot validates each submission in memory, sends an email, and
discards the visitor values.

= Can I make a payment, upload, login or multi-step form? =

No. Install and use Easy Form Builder for advanced form features.

= Can AI choose the email recipient? =

No. The recipient is configured by an administrator in Advanced settings and is
shown masked in the preview.

= Can the plugin publish a post or a product by itself? =

No. Content Studio creates drafts, and WooCommerce work is limited to one
reviewed simple-product draft. Publishing is always a human action in
WordPress' own editor.

= Why is voice input unavailable? =

Voice requires browser microphone support and an AI provider compatible with
the `/audio/transcriptions` endpoint. What you dictate always arrives as
editable text; nothing is sent until you press Send.

== Changelog ==

= 0.0.1 =
* First release.
* Admin rebuilt around eight screens: Overview (with Usage & Cost, Health and
  Activity Log as tabs), Setup & Connection, Integrations, Site Knowledge,
  Answer Desk, Store Copilot, Content Studio and Form Studio.
* Visitor chat widget with panel and full-screen modes, human handoff by inbox,
  email, Telegram or Twilio, and transcript-first voice input.
* Site Knowledge with indexing, an ask console, unanswered questions and saved
  answers.
* Content Studio drafts, Form Studio forms, native email-only forms, and the
  Easy Form Builder copilot.
* Usage and cost tracking with an optional monthly token budget.
* Complete Persian translation and right-to-left layout.
* Uninstall removes the plugin's tables, options and transients and leaves your
  posts, forms and drafts in place.

Development builds before this release were numbered 1.0.1 to 1.0.4 and were
never published. Their notes are kept in RELEASE_NOTES.md.
