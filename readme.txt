=== AI Site Assistant AutoPilot ===
Contributors: hassantafreshi
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
* Setup & Connection wizard: pick one of twenty-three AI provider presets,
  including OpenAI, Anthropic Claude, Google Gemini, xAI Grok, DeepSeek, Qwen,
  Mistral, Cohere, Z.ai GLM, Moonshot Kimi and MiniMax, or point the Custom preset at any
  OpenAI-compatible endpoint. Save your own API key encrypted, test the
  connection, and choose a model from the provider's live catalogue.
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
  in wp-admin, and optionally email, Telegram, WhatsApp Business (Meta's Cloud
  API), SMS or WhatsApp through Twilio, Slack, or Signal (through a
  signal-cli REST API server you run).
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
* Easy Form Builder Copilot: an "Ask AI Site Assistant" chat button inside
  Easy Form Builder that creates and edits real EFB forms. Saving stays with EFB's own button.
  Describe a form and review a preview first: fields, help text, steps for
  long forms, a consent checkbox linked to your own privacy page, and optional
  email notifications to the site admin and a confirmation to the respondent.
  Field types that Easy Form Builder reserves for its Free Plus or Pro plan,
  such as the consent checkbox, are only added on those plans; on Free the
  copilot names what it left out.
  On an open form, ask it to add, rename or remove fields and choices, add
  conditional-logic rules (with EFB's Conditional Logic add-on), explain the
  rules already there, or review the form for missing labels, consent, spam
  protection and length. Every change can be undone before you save.
* Usage & cost tracking per period and per context, with an optional monthly
  token budget that stops requests once it is reached.
* Activity log of plans, approvals and executions, with API keys and tokens
  redacted.
* Full right-to-left support and a complete Persian translation. The interface
  uses CSS logical properties rather than a mirrored stylesheet, and the
  visitor panel and public forms declare their own text direction.

Easy Form Builder is a separate, optional plugin. Use it for uploads,
payments, conditional logic, calculations or multi-step flows. The copilot
never configures payments, prices, login or registration forms, add-on
activation or EFB's global settings; those stay in Easy Form Builder's own
screens.

== What deleting the plugin removes ==

Deactivating changes nothing. Deleting the plugin from the Plugins screen runs
uninstall.php, which removes exactly this:

Database tables (dropped):

* {prefix}aisaautopilot_kb - the knowledge index (one row per page).
* {prefix}aisaautopilot_kb_chunks - the passages each page is split into.
* {prefix}aisaautopilot_kb_terms - the search terms of those passages.
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
aisaautopilot_kb_index_format, aisaautopilot_kb_rebuild,
aisaautopilot_usage_period, aisaautopilot_usage_period_contexts,
aisaautopilot_usage_daily, aisaautopilot_usage_historical_totals,
aisaautopilot_usage_historical_log, aisaautopilot_usage_period_archive,
aisaautopilot_voice_usage_log, aisaautopilot_health_diagnostics,
aisaautopilot_last_diagnostics, aisaautopilot_kb_schema, aisaautopilot_handoff_inbox_schema,
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

The scheduled indexing jobs (aisaautopilot_index_post and
aisaautopilot_kb_rebuild) are unscheduled.

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
the assistant or the Easy Form Builder copilot, transcribe a voice message, or
press "Test connection" / "Refresh from provider". Indexing your content is
done on your own server and sends nothing, unless you switch on semantic search:
then each page's title and opening lines are sent once to your embedding
provider. A question nothing on your site relates to is answered "not found"
without any request.

What is sent: the instruction you typed or dictated, the plugin's own system
prompt, and — for the assistant and knowledge base — the passages of your
published, public content that match the question. Password-protected and
private content is never indexed, so it is never sent. Once you enable the visitor chat widget, a
visitor's question, the previous few turns of that conversation and, if the
visitor records one, the voice message to be transcribed are sent as well;
the contact details a visitor types into a handoff request are not. When you use the Easy Form Builder copilot on an
open form, the form's structure is sent as well: field ids, labels, types,
choice texts, step titles, a plain-text summary of its conditional-logic
rules, your Easy Form Builder plan, whether the Conditional Logic add-on is
active, and the last few turns of that copilot conversation. Your API key is
sent as the request credential. No visitor form submissions, no
administrator email address and no privacy-page address are ever sent to an
AI provider; the plugin adds those to an approved form itself.

Supported providers, each with its own terms and privacy policy:
OpenAI (https://openai.com/policies/terms-of-use,
https://openai.com/policies/privacy-policy),
Anthropic (https://www.anthropic.com/legal/consumer-terms,
https://www.anthropic.com/legal/privacy),
Google Gemini (https://ai.google.dev/gemini-api/terms,
https://policies.google.com/privacy),
xAI Grok (https://x.ai/legal/terms-of-service-enterprise,
https://x.ai/legal/privacy-policy),
DeepSeek (https://platform.deepseek.com/downloads/DeepSeek%20Terms%20of%20Service.html,
https://platform.deepseek.com/downloads/DeepSeek%20Privacy%20Policy.html),
Alibaba Cloud Model Studio / Qwen (https://www.alibabacloud.com/help/en/legal,
https://www.alibabacloud.com/help/en/legal/latest/alibaba-cloud-international-website-privacy-policy),
Mistral AI (https://mistral.ai/terms, https://mistral.ai/terms#privacy-policy),
Cohere (https://cohere.com/terms-of-use, https://cohere.com/privacy),
Z.ai GLM (https://docs.z.ai/legal-agreement/terms-of-use,
https://docs.z.ai/legal-agreement/privacy-policy),
Moonshot AI Kimi (https://platform.kimi.ai/docs/agreement/modeluse,
https://platform.kimi.ai/docs/agreement/userprivacy),
MiniMax (https://platform.minimax.io/protocol/terms-of-service,
https://platform.minimax.io/protocol/privacy-policy),
Groq (https://groq.com/terms-of-sale/, https://groq.com/privacy-policy/),
Cerebras (https://cloud.cerebras.ai/terms,
https://www.cerebras.ai/privacy-policy),
SambaNova Cloud (https://sambanova.ai/cloud-end-user-license-agreement,
https://sambanova.ai/privacy-policy),
Azure OpenAI (https://azure.microsoft.com/support/legal/,
https://privacy.microsoft.com/privacystatement),
OpenRouter (https://openrouter.ai/terms, https://openrouter.ai/privacy),
Together AI (https://www.together.ai/terms-of-service,
https://www.together.ai/privacy),
Fireworks AI (https://fireworks.ai/terms-of-service,
https://fireworks.ai/privacy-policy),
Hugging Face Inference Providers (https://huggingface.co/terms-of-service,
https://huggingface.co/privacy),
NVIDIA NIM (https://assets.ngc.nvidia.com/products/api-catalog/legal/NVIDIA%20API%20Trial%20Terms%20of%20Service.pdf,
https://www.nvidia.com/en-us/about-nvidia/privacy-policy/),
AI/ML API (https://aimlapi.com/terms-and-conditions,
https://aimlapi.com/privacy-policy),
Novita AI (https://novita.ai/legal/terms-of-service,
https://novita.ai/legal/privacy-policy),
Eden AI (https://www.edenai.co/terms-and-conditions,
https://www.edenai.co/privacy-policy).
Gateways (OpenRouter, Together AI, Fireworks AI, Hugging Face, AI/ML API,
Novita AI and Eden AI) may pass a request on to the vendor of the
model you picked, whose own terms then apply as well. Some providers also offer
regional endpoints (for example xAI's US-only or Eden AI's EU-only address);
the request goes only to the base URL you saved.
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

**WhatsApp Business through Meta's Cloud API (optional)**

Only if you enable WhatsApp Business handoff and enter an access token, a phone
number ID and a recipient. The handoff message (the visitor's request and the
details they left), the recipient number and, if you set one, your message
template's name are sent to graph.facebook.com. "Check connection" reads your
business number's display name and sends nothing. Terms:
https://www.whatsapp.com/legal/business-terms, privacy:
https://www.whatsapp.com/legal/privacy-policy

**Slack (optional)**

Only if you enable Slack handoff and enter an incoming webhook URL. The handoff
message is posted to hooks.slack.com, into the channel the webhook belongs to;
"Check connection" posts one short line there. Terms:
https://slack.com/terms-of-service, privacy:
https://slack.com/trust/privacy/privacy-policy

**Signal (optional, through your own server)**

Only if you enable Signal handoff. The handoff message, the sending number and
the recipient are sent to the signal-cli REST API address you enter, which is a
server you run; that server delivers it through Signal. "Check connection" asks
the server which numbers it can send from. Signal's terms and privacy policy:
https://signal.org/legal/

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

Not with the plugin's own native forms. Install Easy Form Builder for these.
With it, the copilot can build multi-step forms and file uploads on any EFB
plan, and drag-and-drop uploads, signatures, ratings, matrix questions and
consent checkboxes on EFB's Free Plus or Pro plan. With EFB's Conditional Logic
add-on it can also add show/hide rules. Payments, prices, login and registration forms are
never set up by AI; configure them in Easy Form Builder yourself.

= Can AI choose the email recipient? =

No. For native forms, the recipient is configured by an administrator in
Advanced settings and is shown masked in the preview. For Easy Form Builder
forms, you can only switch notifications on: the plugin then fills in the
site's admin email address itself, and a confirmation goes to the address the
visitor types into the form's own email field. The AI never sees or picks an
address.

= Does the Easy Form Builder copilot save my form? =

No. Each AI change is applied to the open builder so you can see it, has its
own Undo button, and is saved only when you press Easy Form Builder's own Save
button. A new form is created only after you approve its preview.

= Which conditional-logic rules can the copilot add? =

With EFB's Conditional Logic add-on active, it can show or hide a field or a
step, make a field required or optional, or jump to a step, depending on
other fields' answers. It refuses a rule that makes a field depend on itself or
shows and hides the same target at once, warns you when a new rule fights an
existing one, and respects your EFB plan's rule limits. Rules that calculate,
write values or change notifications are left to you.

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
  email, Telegram, WhatsApp Business, Twilio, Slack or Signal, and
  transcript-first voice input.
* Site Knowledge with indexing, an ask console, unanswered questions and saved
  answers. Pages are split into passages and searched by their words on your
  own server, with Persian, Arabic and English spellings folded together, so
  indexing needs no AI request and the assistant reads the exact lines that
  answer a question, including WooCommerce prices and stock. A question with
  nothing related on the site is answered "not found" without an AI request,
  and you choose what visitors then see: a message (yours or the built-in one)
  or an offer to connect to a person. Follow-up questions keep their subject.
* Content Studio drafts, Form Studio forms, native email-only forms, and the
  Easy Form Builder copilot.
* Easy Form Builder copilot: help text, steps for long forms, a consent
  checkbox linked to the site's privacy page, optional admin and respondent
  email notifications, eleven more field types on EFB's Free Plus or Pro plan
  (mobile, rating, 5- and 10-point scales, yes/no, switch, terms checkbox,
  colour, drag-and-drop upload, checklist and matrix), renaming choices, conditional-logic rules
  with the Conditional Logic add-on, plain-language explanations of existing
  rules, a form review that can add a missing consent checkbox in one click,
  and Undo for every AI change.
* Twenty-three provider presets, including new xAI Grok, Cohere, Z.ai GLM,
  Moonshot Kimi, MiniMax, Cerebras, SambaNova, Together AI, Fireworks AI,
  Hugging Face and NVIDIA NIM presets, and refreshed model lists for every
  provider.
* Reasoning models that reject a custom temperature are retried once without
  it instead of failing, and the "think" block some models write inside their
  reply is removed before the reply is used.
* Usage and cost tracking with an optional monthly token budget.
* Complete Persian translation and right-to-left layout.
* Uninstall removes the plugin's tables, options and transients and leaves your
  posts, forms and drafts in place.

Development builds before this release were numbered 1.0.1 to 1.0.4 and were
never published. Their notes are kept in RELEASE_NOTES.md.
