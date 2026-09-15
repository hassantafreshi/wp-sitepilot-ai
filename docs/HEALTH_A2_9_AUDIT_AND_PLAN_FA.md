# ممیزی و برنامهٔ اجرای A2.9 — Health

تاریخ: 2026-09-05  
مرجع بصری: `mockups/design_handoff_admin_redesign/AutoPilot Admin.dc.html`، کارت Health در خطوط 1332 تا 1345 و قرارداد stateها در 2572 تا 2592.

## حکم طراحی

صفحهٔ canonical Health دقیقاً یک کارت سفید با حداکثر عرض 840px و هشت ردیفِ به‌ترتیب موکاپ است. هر ردیف فقط dot، عنوان، detail و status pill دارد. summary، گروه‌بندی‌های قبلی، sidebar محدودیت بازدیدکننده، connectorها، handoffها و activity-log CTA از این surface حذف می‌شوند؛ برای آن‌ها در موکاپ UI مستقلی وجود ندارد.

1. Provider reachable
2. API key
3. Key storage
4. Embeddings
5. Background jobs (WP-Cron)
6. Index freshness
7. Rate limit headroom
8. PHP and WordPress

دکمهٔ `Run all checks` و state ترتیبی `Queued → Checking → result` نگه داشته می‌شود. تنها درخواست بیرونی همان health check کوتاهِ provider ذخیره‌شده است. باقی checkها local هستند.

## قرارداد حقیقت داده

| ردیف | منبع | state صادقانه |
|---|---|---|
| Provider reachable | `ProviderInterface::health_check()` روی config ذخیره‌شده | فقط نتیجهٔ تست همان config، همراه code/timestamp/latency امن؛ configured بودن به‌تنهایی Healthy نیست. |
| API key | key مؤثر و نتیجهٔ provider | بدون key: Failing؛ 401/unauthorized: Failing؛ خطای شبکه: Unknown؛ قبل از check: Not checked. |
| Key storage | `WpConnectors::get_active_mode()` و منبع دقیق key | فقط KeyVault را encrypted می‌نامیم؛ env، constant و WordPress Connectors صرفاً source/management واقعی خود را می‌گویند. |
| Embeddings | `ProviderManager`، model و شمارش vector در KB | Off/Limited/Good با مدل و تعداد واقعی؛ fallback keyword صریح است. |
| Background jobs | `DISABLE_WP_CRON` و event hook خود افزونه | disabled: Failing؛ event pending یا auto-index off: Limited؛ بدون ادعای ساختگیِ «آخرین اجرا». |
| Index freshness | `ContentIndexer::get_total_posts()` و `KnowledgeBase::get_stats()` | empty/behind/current با total، indexed، pending و last indexed واقعی. |
| Rate limit headroom | telemetry محلی وجود ندارد | Neutral / Not measured؛ درصد یا quota provider جعل نمی‌شود. |
| PHP and WordPress | `PHP_VERSION` و `$wp_version` | Good با نسخه‌های واقعی. |

Snapshot health فقط fingerprint HMAC از config ذخیره‌شده، timestamp، latency، code و outcome را نگه می‌دارد؛ key، URL خام یا پاسخ provider ذخیره/بازگردانده نمی‌شود. تغییر config باعث بی‌اعتباری snapshot می‌شود. تست Settings با key ذخیره‌نشده نیز حق تغییر Health ذخیره‌شده ندارد.

## قابلیت‌های حفظ‌شده اما بیرون از UI موکاپ

| قابلیت قبلی | تصمیم |
|---|---|
| Native Forms، Easy Form Builder، WooCommerce | در صفحات canonical خود می‌مانند؛ Health برای آن‌ها ردیف موکاپ ندارد. |
| Email، Telegram و Twilio handoff | در Answer Desk/Widget حفظ می‌شوند؛ از فهرست Health حذف می‌شوند. |
| Visitor message/voice limits و public assistant | در تنظیمات مرتبط باقی می‌مانند؛ Health مقدار ثابت یا meter ساختگی نمایش نمی‌دهد. |
| activity-log link و stamp متنی قبلی | snapshot برای data model نگه می‌ماند؛ موکاپ surface جداگانه‌ای برای آن ندارد. |

## برنامهٔ ریز و گیت پذیرش

| ID | کار کوچک | گیت پذیرش |
|---|---|---|
| A2.9.0 | ممیزی موکاپ و قرارداد داده | این سند، diff check و عدم تصمیم‌گیری براساس دادهٔ ساختگی |
| A2.9.1 | ساخت read model و endpoint امن `POST /health/diagnostics` | PHP lint/PHPCS/PHPStan و WordPress integration برای success، 401، unreachable و عدم نشت secret |
| A2.9.2 | بازسازی markup به کارت و هشت ردیف دقیق | render integration: دقیقاً ۸ row، ترتیب ثابت و نبود surface legacy |
| A2.9.3 | JS برای lock، aria-busy، state ترتیبی و نتیجهٔ REST | Node syntax و browser interaction با route interception |
| A2.9.4 | CSS scoped، RTL بومی و 390px | browser desktop/RTL/390px و screenshot review |
| A2.9.5 | regression نهایی و گزارش اجرا | PHPCS کامل، PHPStan، PHPUnit، WordPress local و visual QA |

## نتیجهٔ A2.9.1

read model و endpoint محافظت‌شدهٔ `POST /health/diagnostics` پیاده‌سازی شدند. مسیر فقط config ذخیره‌شده را با یک health request بررسی می‌کند و در snapshot فقط HMAC fingerprint، نتیجه، code، timestamp و latency نگه می‌دارد. تست draft در `/provider/test` دیگر snapshot Health را stamp نمی‌کند.

گیت این task: PHP lint، PHPCS و PHPStan فایل‌های تغییرکرده سبز؛ `wordpress_health_diagnostics_integration.php` با **7/0** روی WordPress محلی سبز شد (route، ترتیب هشت‌check، یک transport، عدم نشت credential/endpoint، 401، network failure، draft isolation و source fallback).

## نتیجهٔ A2.9.2 تا A2.9.5

markup به یک کارت canonical با دقیقاً هشت ردیف مرتب تبدیل شد و summary، connector، handoff، sidebar و CTAهای legacy حذف شدند. JS با request nonceدار، قفل دکمه، `aria-busy`، state ترتیبی موکاپ و `textContent` برای detailهای REST پیاده‌سازی شد. CSS scoped کارت 840px، dot/pill موکاپ، RTL بومی، mixed-direction fallback و 390px را پوشش می‌دهد.

گیت نهایی: Health render **3/0**، Health diagnostics **7/0**، Phase 1 **45/0**، PHPCS کامل **129/129**، PHPStan **0**، PHPUnit **133/439** و browser واقعی `fa_IR` با **0 failure**. گزارش اجرای قابل‌بازتولید در `HEALTH_A2_9_EXECUTION_REPORT_FA.md` قرار دارد.

## محدودیت آگاهانه

Rate-limit headroom و latency تاریخی provider منبع telemetry قابل‌اعتماد محلی ندارند. بنابراین Health درصد مصرف quota یا «آخرین استفاده» را وعده نمی‌دهد. Health request فقط provider ذخیره‌شده را یک‌بار امتحان می‌کند و نتیجهٔ آن را از test پیش‌نویس Settings جدا نگه می‌دارد.
