=== SitePilot AI ===
Contributors: sitepilot
Tags: ai, chatbot, woocommerce, voice, assistant
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safe AI copilot for WordPress with WooCommerce integration, voice support, and human handoff.

== Description ==

SitePilot AI is a comprehensive AI copilot plugin for WordPress that brings the power of artificial intelligence to your website management.

= Key Features =

**🛍️ WooCommerce Integration**
* Create products with AI assistance
* Manage orders intelligently
* View analytics and sales reports
* Generate product descriptions and SEO content
* Low stock alerts
* Customer purchase history

**💬 Chat Widget**
* AI-powered chat for your visitors
* Voice input and output support
* Human handoff when needed
* Customizable appearance
* RTL support

**🎤 Voice Support**
* Speech-to-text (STT) via Groq Whisper
* Text-to-speech (TTS) with browser fallback
* Voice commands for hands-free operation

**📊 Dashboards**
* WooCommerce dashboard with sales charts
* Usage tracking and credit monitoring
* Real-time statistics

**🌐 Multilingual**
* Full Persian (Farsi) translation
* RTL support
* Easy to add more languages

**🔒 Security**
* API keys encrypted at rest
* Secure handoff channels (Email, Telegram, Twilio)
* Input sanitization and validation

= Supported AI Providers =

* DeepSeek (default)
* OpenAI
* Qwen (Alibaba)
* Clusy.io
* Groq
* Any OpenAI-compatible API

= Handoff Channels =

* Email
* Telegram Bot
* Twilio WhatsApp
* Custom Webhook

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/sitepilot-ai/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to SitePilot AI → Settings
4. Add your AI provider API key
5. Configure the chat widget and handoff channels

== Frequently Asked Questions ==

= Which AI providers are supported? =

Any OpenAI-compatible API is supported, including DeepSeek, OpenAI, Qwen, Clusy.io, and Groq.

= Is WooCommerce required? =

No, WooCommerce is optional. The chat widget and voice features work without WooCommerce. WooCommerce features are automatically enabled when WooCommerce is active.

= How does the human handoff work? =

When a visitor requests human assistance, the chat history is sent to your configured channels (Email, Telegram, Twilio WhatsApp, or Webhook).

= Is my API key secure? =

Yes, API keys are encrypted using XSalsa20-Poly1305 or AES-256-GCM before being stored in the database.

== Screenshots ==

1. WooCommerce Dashboard
2. Chat Widget
3. Settings Page
4. Product AI

== Changelog ==

= 1.0.0 =
* Initial release
* WooCommerce Connector (Product AI, Order Intelligence, Analytics, Content + SEO)
* Chat Widget with voice support
* Human handoff (Email, Telegram, Twilio, Webhook)
* WooCommerce Dashboard
* Usage tracking
* Persian translation
* PHPUnit tests and CI/CD

== Upgrade Notice ==

= 1.0.0 =
Initial release with full WooCommerce integration, chat widget, voice support, and human handoff.
