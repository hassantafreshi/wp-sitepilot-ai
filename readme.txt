=== SitePilot AI ===
Contributors: sitepilot
Tags: ai, forms, copilot, assistant, deepseek
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safe AI copilot for WordPress. Describe a form in plain language, preview it, approve it, and SitePilot creates it. You always approve before anything is created.

== Description ==

SitePilot AI turns a plain-language request into a real WordPress form. It follows a strict safety loop:

request → AI plan → preview → your approval → create → activity log

This build runs in **temporary standalone mode**: it talks directly to your own AI provider (DeepSeek, OpenAI, OpenRouter, Groq, or any OpenAI-compatible endpoint such as cloud.ai). Your API key is stored **encrypted** on your site and is never sent anywhere except your chosen provider.

Forms are created with the built-in form engine (shortcode `[sitepilot_form id="123"]`). If Easy Form Builder is installed and an integration is registered, SitePilot can use it instead — see the developer notes.

= Key points =

* You always preview and approve before a form is created.
* Payment, password, and user-registration fields are blocked for safety.
* Your provider API key is encrypted at rest (libsodium / AES-256-GCM).
* Nothing is created without an administrator's approval.

== Installation ==

1. Upload the `sitepilot-ai` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Go to **SitePilot AI → Settings**, choose your provider, paste your API key, and click **Test connection**.
4. Open **SitePilot AI → Copilot**, describe your form, preview it, and approve.

== Frequently Asked Questions ==

= Where is my API key stored? =
Encrypted in your WordPress database and only decrypted server-side when calling your provider. It is never shown again after saving and never sent to any third party.

= Does it work without Easy Form Builder? =
Yes. The built-in form engine always works. Easy Form Builder is used only if installed and an integration filter is registered.

== Changelog ==

= 0.1.0 =
* Initial standalone MVP: Admin Copilot, direct provider mode (BYOK), form preview + approval, built-in form connector, activity log.
