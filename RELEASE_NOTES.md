# SitePilot AI 1.0.0 Release Notes

## 🎉 Initial Release

SitePilot AI 1.0.0 is a comprehensive AI copilot plugin for WordPress with full WooCommerce integration.

## ✨ Features

### 🛍️ WooCommerce Connector

#### Phase 1: Product AI
- Create products with AI assistance
- Edit existing products
- Search products
- Product categories management
- Plan validation and sanitization

#### Phase 2: Order Intelligence
- Search orders by number/customer/email
- View recent orders
- Update order status
- Create refunds
- Add order notes
- Order statistics

#### Phase 3: Analytics
- Sales reports (day/week/month/year)
- Top selling products
- Low stock alerts
- Customer purchase history
- Revenue by category
- Dashboard summary

#### Phase 4: Content + SEO
- Generate product descriptions with AI
- Generate SEO titles and meta descriptions
- Generate promotional text
- Bulk content generation
- Apply content to products
- Content templates

### 💬 Chat Widget
- AI-powered chat for visitors
- Customizable appearance (position, theme, colors)
- Voice input and output
- Human handoff button
- RTL support

### 🎤 Voice Support
- Speech-to-text (STT) via Groq Whisper API
- Text-to-speech (TTS) with browser SpeechSynthesis fallback
- Voice auto-play option
- Voice name selection

### 📊 Dashboards
- WooCommerce Dashboard with:
  - Sales statistics cards
  - Sales chart (last 7 days)
  - Top products list
  - Low stock alerts
  - Recent orders
  - Quick actions
- Usage Dashboard with:
  - Token usage tracking
  - Credit monitoring
  - Threshold warnings (50%, 25%, 15%, 5%)

### 👥 Human Handoff
- Email handoff with HTML template
- Telegram Bot integration
- Twilio WhatsApp integration
- Custom Webhook support
- PDF export of chat history

### 🌐 Internationalization
- Full Persian (Farsi) translation (150+ strings)
- RTL support
- Translation template (.pot file)

### ✅ Quality Assurance
- PHPUnit unit tests (25+ tests)
- GitHub Actions CI/CD
- PHP 8.1, 8.2, 8.3, 8.4 support
- WordPress 6.2+ compatibility

## 🔧 Technical Details

### Supported AI Providers
- DeepSeek (default)
- OpenAI
- Qwen (Alibaba)
- Clusy.io
- Groq
- Any OpenAI-compatible API

### REST API Endpoints (30+)
- `/woocommerce/check` - WooCommerce status
- `/woocommerce/plan` - Create product plan
- `/woocommerce/execute` - Execute product plan
- `/woocommerce/search` - Search products
- `/woocommerce/categories` - Get categories
- `/woocommerce/orders/search` - Search orders
- `/woocommerce/orders/recent` - Recent orders
- `/woocommerce/orders/{id}` - Get order
- `/woocommerce/orders/update-status` - Update status
- `/woocommerce/orders/refund` - Create refund
- `/woocommerce/orders/stats` - Order stats
- `/woocommerce/orders/statuses` - Get statuses
- `/woocommerce/analytics/sales` - Sales report
- `/woocommerce/analytics/top-products` - Top products
- `/woocommerce/analytics/low-stock` - Low stock
- `/woocommerce/analytics/customers/{id}` - Customer history
- `/woocommerce/analytics/categories` - Category breakdown
- `/woocommerce/analytics/dashboard` - Dashboard
- `/woocommerce/content/description` - Generate description
- `/woocommerce/content/seo` - Generate SEO
- `/woocommerce/content/promo` - Generate promo
- `/woocommerce/content/bulk` - Bulk generate
- `/woocommerce/content/apply` - Apply content
- `/woocommerce/content/templates` - Get templates
- `/voice/transcribe` - STT
- `/voice/speak` - TTS
- `/widget/settings` - Widget settings
- `/widget/ask` - Chat ask
- `/widget/conversation` - Conversation
- `/widget/handoff` - Handoff
- `/widget/handoff/status` - Handoff status

### Security
- API keys encrypted with XSalsa20-Poly1305 or AES-256-GCM
- Input sanitization and validation
- Secure handoff channels
- Nonce verification
- Capability checks

## 📦 Installation

1. Upload the plugin files to `/wp-content/plugins/sitepilot-ai/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to SitePilot AI → Settings
4. Add your AI provider API key
5. Configure the chat widget and handoff channels

## 🐛 Known Issues

None at this time.

## 🙏 Credits

Developed by SitePilot AI Team.
