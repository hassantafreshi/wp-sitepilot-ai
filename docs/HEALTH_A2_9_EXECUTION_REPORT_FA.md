# گزارش اجرای A2.9 — Health

تاریخ: 2026-09-05  
مرجع بصری: `mockups/design_handoff_admin_redesign/AutoPilot Admin.dc.html`، Health در خطوط 1332 تا 1345 و stateها در 2572 تا 2592.

## نتیجه

Health اکنون دقیقاً surface موکاپ را دارد: یک کارت حداکثر 840px با هشت ردیف ثابت، dot، detail و status pill. دکمهٔ `Run all checks` همان state ترتیبی `Queued → Checking → result` موکاپ را اجرا می‌کند، اما نتیجه‌ها از data model واقعی می‌آیند و اعداد ساختگی موکاپ نمایش داده نمی‌شوند.

## هشت بررسی canonical

1. Provider reachable
2. API key
3. Key storage
4. Embeddings
5. Background jobs (WP-Cron)
6. Index freshness
7. Rate limit headroom
8. PHP and WordPress

## قرارداد فنی

- endpoint جدید `POST /health/diagnostics` فقط config ذخیره‌شده را می‌خواند و حداکثر یک health request کوتاه به provider می‌فرستد. هیچ پارامتر draft از browser نمی‌پذیرد.
- snapshot نتیجه فقط HMAC fingerprint config، outcome/code، timestamp و latency را نگه می‌دارد؛ API key، base URL، model و raw provider response نه ذخیره و نه در REST/UI برگردانده می‌شوند.
- تغییر config fingerprint را عوض می‌کند و snapshot قبلی را نامعتبر می‌سازد. تست `/provider/test` برای key ذخیره‌نشده نیز دیگر snapshot Health را تغییر نمی‌دهد.
- Provider configured بودن به‌تنهایی Healthy نیست. پیش از check، state صادقانه `Not checked` است؛ 401 فقط API key را Failing می‌کند و خطای شبکه آن را Unknown نگه می‌دارد.
- Key storage از منبع مؤثر واقعی تشخیص داده می‌شود: فقط KeyVault سالم «Encrypted» نام دارد؛ environment variable، PHP constant و WordPress Connectors هرگز این ادعا را ندارند.
- Embeddings، WP-Cron، coverage/index timestamp و runtime همه محلی هستند. rate-limit headroom چون telemetry قابل‌اعتماد ندارد، `Not measured` است.

## تفاوت‌های عمدی با دادهٔ نمایشی موکاپ

| دادهٔ موکاپ | تصمیم واقعی |
|---|---|
| پاسخ 340ms، آخرین استفادهٔ key و سه بار retry | فقط latency اندازه‌گیری‌شدهٔ همان Health check ذخیره می‌شود؛ history و retry جعلی نداریم. |
| «38 product queued» | total/indexed/pending واقعی Knowledge Base نمایش داده می‌شود؛ plugin import queue محصولات ندارد. |
| 18٪ quota provider | حذف؛ state صادقانه `Not measured`. |
| مدل «Encrypted vault» در همهٔ حالت‌ها | فقط vault سالم؛ sourceهای env/constant/Connectors دقیقاً نام خود را می‌گویند. |

## قابلیت‌های حفظ‌شده اما خارج از surface موکاپ

| قابلیت | وضعیت |
|---|---|
| Native Forms، EFB و WooCommerce | در صفحات canonical خود باقی‌اند؛ ردیف Health موکاپ ندارند. |
| Email، Telegram و Twilio handoff | در Answer Desk/Widget باقی‌اند؛ از Health حذف شده‌اند. |
| visitor limits و Public assistant | در تنظیمات مربوط باقی‌اند؛ Health meter یا مقدار ثابت ساختگی ندارد. |
| stamp قدیمی و link مستقیم Activity Log | snapshot امن data model جایگزین stamp شده؛ موکاپ برای panel جداگانه UI ندارد. |

## شواهد تست

| گیت | نتیجه |
|---|---|
| `wordpress_health_diagnostics_integration.php` | 7/0؛ route، یک transport، ترتیب هشت check، عدم نشت secret/endpoint، 401، network failure، draft isolation و fallback source |
| `wordpress_health_page_integration.php` | 3/0؛ یک کارت، هشت ردیف مرتب، حذف surface legacy و state rate-limit صادقانه |
| `wordpress_phase1_integration.php` | 45/0؛ رگرسیون routeها و صفحه‌های canonical |
| PHP lint / Node syntax / diff check | سبز |
| PHPCS کامل | 129/129 بدون error/warning |
| PHPStan کامل | 0 error |
| PHPUnit | 133 test / 439 assertion |
| browser QA واقعی `fa_IR` | 0 failure؛ state checking، lock/nonce، safe text، RTL بومی، 390px، console/pageerror |

بازبینی تصویری desktop، RTL و 390px انجام شد. screenshotهای نهایی در `test-artifacts/aisaautopilot-health-{desktop,rtl,mobile}.png` هستند. حساب administrator موقت browser QA پس از اجرا حذف شد.
