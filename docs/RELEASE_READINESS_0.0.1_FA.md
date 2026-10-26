# گزارش آمادگی انتشار — `0.0.1`

> **هشدار ۲۰۲۶-۱۰-۲۶:** ZIP منجمد زیر دیگر با کد یکی نیست: نویسنده و Contributors عوض شدند، و T74 (فرم‌ساز هوشمند EFB) و T75 (فهرست مدل‌ها) کد را تغییر دادند. پیش از انتشار، ZIP دوباره ساخته شود و T22 تا T24 دوباره اجرا شوند؛ با این حجم تغییر، شمارهٔ نسخه هم تصمیم مالک است. جزئیات: `docs/EFB_AI_FORM_BUILDER_T74_T75_FA.md`.

تاریخ: ۲۰۲۶-۰۹-۲۱
افزونه: **AI Site Assistant AutoPilot**، نسخهٔ `0.0.1`، `Tested up to: 7.1`
وضعیت: **آمادهٔ امضای مالک.** هر ۱۰ task فاز ۴ و انتشار (T15 تا T24) با گیت و شواهد بسته شده‌اند. تنها کار باقی‌مانده، checklist پذیرش محیطی بخش ۵ است که فقط مالک می‌تواند انجام دهد.

---

## ۱. بستهٔ انتشار

| مورد | مقدار |
|---|---|
| فایل | `ai-site-assistant-autopilot-0.0.1.zip` |
| اندازه | **۷۶۰٬۴۳۲ بایت** |
| SHA-256 | **`68FA68A56CFDBB7C11608B7794B42D375DE3F1596F14954AD29C45EB527F3B47`** |
| تعداد entry | ۱۷۰ (۱۳۴ PHP، ۲۴ JS، ۵ CSS) |
| ساخته‌شده در | بازسازی ۲۰۲۶-۰۹-۲۱، پس از بازگشت تصمیم **D-34** |

**این ZIP جایگزین ساخت ۲۰۲۶-۰۹-۲۰ شد.** ساخت اول (SHA-256 `90E5AE16…`، ۷۵۹٬۳۸۸ بایت) با یافته‌های Plugin Check عرضه می‌شد؛ مالک تصمیم D-34 را به گزینهٔ (ج) برگرداند («خودت پلاگین چکر را انجام بده و درستش کن»)، هر ۲۴ یافته رفع شد و ZIP دوباره ساخته شد. اختلاف **۱٬۰۴۴ بایت** است و فقط کامنت، `phpcs:ignore` و `esc_attr()` — فایل ترجمهٔ کامپایل‌شده (MO) دست نخورد و همان `03FAF4AA…` ماند. **شمارهٔ نسخه `0.0.1` ماند**، چون هرگز منتشر نشد و هیچ سایتی آن را ندارد.

**این ZIP اکنون منجمد است.** تا انتشار `0.0.1`، هر تغییر کد یعنی ZIP تازه با SHA تازه و اجرای دوبارهٔ گیت‌های T22 تا T24. تنها بدهی باقی‌مانده برای `0.0.2` ردیف **B-10** (پنج فایل بی‌hook، طبق D-33) است؛ ردیف **B-11** بسته شد چون یافته‌های Plugin Check در همین نسخه رفع شدند.

### سه مسیر نگهداری ZIP

| مسیر | نقش |
|---|---|
| `apps/wordpress-plugin/ai-site-assistant-autopilot/dist/ai-site-assistant-autopilot-0.0.1.zip` | **خروجی معتبر فعلی** |
| `test-artifacts/release-zip/20260921-t22-rebuild/ai-site-assistant-autopilot-0.0.1.zip` | **کپی پشتیبان بایت‌به‌بایت** (تأییدشده `68FA68A5…`)، با `FROZEN-ARTIFACT-README.md` و `zip-verification.txt` |
| `test-artifacts/release-zip/20260920-t22/SUPERSEDED-ai-site-assistant-autopilot-0.0.1.zip` | ساخت اول، **منسوخ**، فقط برای سابقه — عرضه نمی‌شود |

**نکتهٔ مهم برای commit انتشار:** پوشهٔ `dist/` در `.gitignore` افزونه است (خط ۶، `/dist/`). پس ZIP **در commit انتشار روی `master` نخواهد بود** و در commit پشتیبان `snapshot/pre-release-0.0.1-20260921` (`39c2ae3`) هم نبود. مرجع دائمی‌اش کپی پشتیبان بالاست. برابری دو نسخه در ۲۰۲۶-۰۹-۲۱ با SHA-256 و اندازه تأیید شد.

---

## ۲. خلاصهٔ گیت‌ها، T15 تا T24

| task | موضوع | نتیجه با شمار | artifact |
|---|---|---|---|
| **T15** | browser smoke انتشار | **۵۸۷ PASS / ۰ FAIL** (`en_US` ۲۸۸، `fa_IR` ۲۹۹)؛ هر ۱۹ route با HTTP 200، پوستهٔ `.sp-app` و `ROUTE_390 overflow=0`؛ صفر console error و pageerror | `test-artifacts/release-smoke/20260918-t15/run6-matrix.txt` |
| **T16** | هم‌ترازی بصری با چهار موکاپ | هر **۱۳۵** حالت موکاپ حکم دارد، نقص باز **صفر**؛ ۸ نقص V16 رفع، ۸ اختلاف عمدی تازه (X-06 تا X-13) ثبت شد | `docs/VISUAL_PARITY_Q4_2_REPORT_FA.md`، `test-artifacts/visual-parity/20260919-t16/` |
| **T17** | accessibility، RTL و responsive | a11y **۱۵۲۲ / ۰**، ویجت **۴۳۷ / ۰**، EFB **۱۵۶ / ۰**؛ ۱۰ نقص A17 رفع؛ آزمون دائمی تازه روی ۲۶ route | `docs/ACCESSIBILITY_Q4_3_REPORT_FA.md`، `tests/admin_a11y_browser_matrix.cjs` |
| **T18** | همگام‌سازی POT، PO و MO | PO و POT هر دو **۱۸۱۹ از ۱۸۱۹**؛ ۵۱۰ ورودی کهنه حذف، ۲ fuzzy و ۸ ترجمهٔ فقط‌لاتین اصلاح شد | `test-artifacts/i18n/20260919-t18/` |
| **T19** | ممیزی قرارداد و preserve-list | **۶۵ route، صفر حذف‌شده**؛ ۵۹ handler مدیریتی `manage_options`؛ ۱۴ ردیف preserve-list همه «حفظ»؛ `KeyVault` و `RebrandMigrator` صفر خط diff | `docs/CONTRACT_AUDIT_Q4_T19_REPORT_FA.md`، `test-artifacts/contract-audit/20260918-t19/` |
| **T20** | بازبینی امنیتی بازطراحی | دامنهٔ **۱۲۴ فایل**؛ **۴ نقص رفع، صفر ریسک پذیرفته‌شده** (S20-1 XSS ذخیره‌شده، S20-2 حذف ناقص، S20-3 نشت توکن، S20-4 نوع فایل صدا)؛ هر رفع با آزمون و کنترل منفی | `docs/SECURITY_REVIEW_Q4_T20_REPORT_FA.md`، `test-artifacts/security-review/20260918-t20/` |
| **T21** | متادیتای انتشار | نسخه در هر سه جا `0.0.1`؛ inventory کامل: **۲۷ option، ۴ جدول، ۵ خانوادهٔ transient، ۱ cron، ۴ post meta، ۲ post type**؛ صفر user meta / site option / role / cap؛ ۲ نقص واقعی `uninstall.php` رفع شد | `test-artifacts/release-metadata/20260920-t21/verification.txt` |
| **T22** | ساخت و وارسی ZIP | build **exit 0**؛ وارسی مستقل **۳۱ PASS / ۰ FAIL** روی ZIP بازسازی‌شده؛ کنترل منفی: اسکریپت HEAD با exit 1 و ZIP ۱۸۴ entry که ۱۳ تای آن `test-artifacts/` بود | `test-artifacts/release-zip/20260921-t22-rebuild/zip-verification.txt`؛ ساخت اول: `…/20260920-t22/` |
| **T23** | Plugin Check روی ZIP | **۲۷ static check موجود / ۲۷ اجراشده / ۰ اجرانشده؛ ۰ error و ۰ warning — `RESULT PASS`**؛ junction سالم با **SWAP_FAILURES 0** | `test-artifacts/plugin-check/0.0.1-rebuild-20260921/`؛ اجرای اول: `…/plugin-check/0.0.1/` |
| **T24** | پذیرش نصب تمیز | **۵۹ PASS / ۰ FAIL** روی WordPress **7.1.1** منتشرشده؛ فعال‌سازی با صفر notice و صفر بایت خروجی؛ هر شش ردپا پس از حذف به پایه برگشت. **پذیرش دوباره روی ZIP بازسازی‌شده: ۲۳ PASS / ۰ FAIL و `debug.log` صفر بایت رشد کرد** | `test-artifacts/clean-install/20260921-t24/`، `…/20260921-t24-rebuild/reacceptance.txt` |

### گیت‌های مشترک، آخرین مقدار معتبر

| گیت | مقدار | artifact |
|---|---|---|
آخرین اجرا پس از رفع یافته‌های Plugin Check است، یعنی روی همان درختی که ZIP معتبر از آن ساخته شد: تیر **`-Tier full`، ۹ از ۹ گیت سبز**.

| گیت | مقدار | artifact |
|---|---|---|
| php-lint | **۲۳۴ / ۲۳۴** | `test-artifacts/gates/20260921-013502-full` |
| node --check | **۴۵ / ۴۵** | همان |
| PHPCS | **۱۳۴ / ۱۳۴ فایل، ۰ error، ۰ warning** | همان |
| PHPStan | **۰ error** | همان |
| PHPUnit | **۱۵۶ test / ۵۴۷ assertion** | همان |
| menu smoke | **۵ / ۰** | همان، و دوباره روی نصب تمیز در T24 |
| assets smoke | **۲۰ / ۰** | همان، و دوباره روی نصب تمیز در T24 |
| render smoke | **۱۸ / ۰** | همان، و دوباره روی نصب تمیز در T24 |
| Phase 1 integration | **۴۵ / ۰** | همان |
| Plugin Check | **۲۷ / ۲۷ check، ۰ error، ۰ warning** | `test-artifacts/plugin-check/0.0.1-rebuild-20260921/` |
| MO فارسی | **۱۸۱۹ از ۱۸۱۹**، `msgfmt --check` سالم، ۲۳۳٬۸۷۳ بایت، SHA-256 `03FAF4AA…` — **بازسازی ZIP آن را تغییر نداد** | `scripts/compile-mo.php` |

---

## ۳. جزئیات پذیرش نصب تمیز (T24)

محیط: `C:\xampp\htdocs\wp-clean`، WordPress **7.1.1** منتشرشده، دیتابیس جدای `wp_clean`، **صفر افزونهٔ فعال**، **صفر mu-plugin**، locale `en_US`، cron روشن، `wp-config.php` مینیمال، **بدون هیچ junction** — افزونه واقعاً از ZIP نصب شد.

| بررسی | نتیجه |
|---|---|
| پایه پیش از نصب | صفر option، جدول، transient، cron و post meta؛ **`debug.log` اصلاً وجود نداشت** |
| نصب | پوشهٔ واقعی (`LinkType` خالی)، **۱۷۰ فایل**، بدون `tests/`، `docs/` و `.git/` |
| فعال‌سازی | `activate_plugin()` برابر `NULL` (بدون `WP_Error`)، **صفر PHP notice، صفر بایت خروجی**، و `debug.log` همچنان ساخته نشد |
| ویجت عمومی | **پیش‌فرض خاموش**: `public_assistant_enabled` falsy، `should_render_widget()` برابر false، و `do_action( 'wp_footer' )` **صفر بایت** markup ویجت داد |
| منوی مدیر | **۸ آیتم D-23** با ترتیب و slug دقیق؛ Content Studio روی slug قدیمی `aisaautopilot-form-builder` |
| aliasهای قدیمی | هر **۱۱** ثبت‌شده ولی نامرئی |
| REST | **۶۵ route، ۱۳۲ handler، همگی با `permission_callback`** — برابر inventory T19 |
| جدول‌ها | هر ۴ جدول ساخته شد |
| حذف | `deactivate_plugins()` سپس `delete_plugins()` (همان مسیر `wp-admin/plugins.php`، پس `uninstall.php` واقعاً اجرا شد)؛ نتیجه `true`، صفر notice، پوشه حذف شد |
| وارسی حذف | option `0→4→0`، جدول `0→4→0`؛ transient، cron، legacy option و post meta همه `0→0→0` |

**افشای صادقانه:** پس از اجرا `debug.log` با ۵۰۶ بایت ساخته شد، ولی **هیچ خطی از افزونهٔ ما نیست**. هر دو خط `PHP Deprecated: Function the_block_template_skip_link is deprecated since version 6.4.0` است؛ خود هسته در `wp-includes/default-filters.php` آن تابع را با یادداشت «Retained for backwards-compatibility» روی `wp_footer` می‌بندد و probe خودِ ایجنت آن را در بستر CLI شلیک کرد. grep کل افزونه **صفر** ارجاع به آن تابع دارد. دو پوشهٔ **خالی** `wp-content/uploads/2026/09` هم ساخته شد که ساختار استاندارد وردپرس است — افزونه **صفر** فراخوانی `wp_upload_dir()` دارد.

---

## ۴. محدودیت‌های شناخته‌شده در این نسخه

### ۴.۱ Plugin Check — **دیگر محدودیت نیست**

Plugin Check **2.0.0** در حالت `new` روی ZIP معتبر: **۲۷ از ۲۷ check اجرا شد و نتیجه ۰ error و ۰ warning است** (`RESULT PASS`). این ردیف فقط برای سابقه اینجاست.

ساخت اول (۲۰۲۶-۰۹-۲۰) **۱۱ error و ۱۳ warning** داشت و قرار بود با حکم مستند پذیرفته شود (D-34 گزینهٔ الف). مالک در ۲۰۲۶-۰۹-۲۱ تصمیم را به **گزینهٔ (ج)** برگرداند — «خودت پلاگین چکر را انجام بده و درستش کن» — و هر ۲۴ یافته در **۱۰ فایل** رفع شد، نه پذیرفته. جایی که یافته نقص واقعی بود کد اصلاح شد؛ جایی که sniff نمی‌توانست جریان داده را دنبال کند، `phpcs:ignore` با دلیل **درون خود کد** نوشته شد تا خوانندهٔ بعدی استدلال را ببیند.

دو قضاوت ارزش ثبت دارند، چون رفع بدیهی در آن‌ها **غلط** می‌بود:

- **`Providers/UsageCappedProvider.php`** — رشتهٔ علامت‌خورده پیام سازندهٔ `ProviderException` است و هرگز echo نمی‌شود؛ فقط به Logger و بدنهٔ `wp_json_encode()` می‌رود. `esc_html__()` موجودیت HTML را داخل بدنهٔ JSON می‌گذاشت، پس ignore مستند گرفت.
- **`Admin/WooCommerceSettings.php`** — مقدار خام `$_GET['tab']` فقط ذخیره و در `finally` بازگردانده می‌شود. sanitize کردنش superglobal فراخواننده را بی‌صدا بازنویسی می‌کرد، پس آن هم مستند شد و تغییر نکرد. (این همان هشداری است که ارزیابی T23 صریحاً داده بود و رعایت شد.)

پس از تغییر، **کل تیر `-Tier full` دوباره اجرا شد و ۹ از ۹ گیت سبز ماند**، ZIP دوباره وارسی مستقل شد (**۳۱ PASS / ۰ FAIL**) و پذیرش نصب تمیز از ابتدا تا انتها تکرار شد (**۲۳ PASS / ۰ FAIL**، با `debug.log` که **صفر بایت** رشد کرد).

**گوگل‌فونت هیچ‌وقت علامت نخورد:** `offloading_files`، `enqueued_resources` و `external_admin_menu_links` هر سه صفر. فهرست دامنه‌های Plugin Check فقط `ajax.googleapis.com` را دارد و font hostها را با `(?<!fonts\.)gstatic\.com` استثنا می‌کند. دو webfont در `readme.txt` بخش `== External services ==` افشا شده‌اند.

متن کامل و قابل‌خواندن برای بازبین WordPress.org در `RELEASE_NOTES.md` است. ردیف بدهی **B-11 بسته شد**.

### ۴.۲ پنج فایل بی‌hook (تصمیم مالک D-33، گزینهٔ الف) — **تنها بدهی باز `0.0.2`**

`assets/admin/dashboard.js`، `assets/frontend/chat-widget.js`، `assets/frontend/chat-widget.css`، `includes/Frontend/WidgetRenderer.php` و `includes/Voice/VoiceService.php` — روی هم **۳۹٬۶۵۰ بایت نافشرده** — در ZIP هستند ولی هیچ hook یا فراخوان محصولی ندارند. سطح حمله نیستند چون هیچ‌کدام بارگذاری نمی‌شوند. حذف در `0.0.2`: ردیف **B-10**.

### ۴.۳ قابلیت‌های قفل یا ساخته‌نشده

- **صف ایندکس ناپایدار است:** یک پاس فقط تا وقتی زنده است که تب مرورگر باز باشد؛ خود صفحه این را می‌گوید. (صف پایدار: T44)
- **منبع دانش PDF و External URL ساخته نشده**؛ فقط post typeهای وردپرس ایندکس می‌شوند. (T45)
- **voice realtime وجود ندارد:** نه زیرنویس زنده، نه barge-in، نه تماس پیوسته. ویجت و صفحه‌های مدیر هر دو این را می‌گویند. (T47)
- **transportهای handoff برای Slack، Discord، WhatsApp و webhook امضاشده ساخته نشده‌اند.** (T46)
- **قابلیت‌های Phase 2 ووکامرس قفل‌اند.** افزونه draft-only است: هیچ publish، ویرایش، عملیات گروهی، سفارش یا refund. متدهای نوشتنی خفته‌اند و **هیچ REST route ندارند** (وارسی T19). (T48، T49)
- **مکان بازدیدکننده، وضعیت سفارش برای بازدیدکننده و price map دلاری ساخته نشده‌اند.** (T50، T51)
- **۱۳ اختلاف عمدی از موکاپ‌ها** (X-01 تا X-13) تک‌تک در `docs/MOCKUP_FEATURE_GAP_REPORT_FA.md` و در گزارش اجرای هر بسته ثبت شده‌اند.

### ۴.۴ رخداد کیفی افشا شده

یک رخداد QA در Activity Log حین توسعه، کامل و بدون پنهان‌کاری در [`docs/ACTIVITY_LOG_QA_INCIDENT_FA.md`](ACTIVITY_LOG_QA_INCIDENT_FA.md) افشا شده است.

---

## ۵. checklist پذیرش محیطی — **فقط مالک می‌تواند انجام دهد**

هیچ‌کدام از این چهار مورد در QA خودکار پوشش داده نشده، چون آزمون‌ها هرگز provider واقعی صدا نمی‌زنند، ایمیل یا پیامک واقعی نمی‌فرستند و اعتبارنامه نمی‌سازند. `provider_not_configured` یک حالت **مورد انتظار** QA است. این چهار مورد پیش از انتشار عمومی باید یک‌بار روی محیط واقعی تأیید شوند.

| # | مورد | چگونه | معیار قبولی | وضعیت |
|---|---|---|---|---|
| **E-1** | کلید واقعی provider | کلید واقعی را در Setup & Connection ذخیره کنید و «Test connection» بزنید؛ سپس `AISAAUTOPILOT_WP_ROOT=<wp-root> php tests/ai_runtime_check.php` | اتصال سبز؛ اسکریپت فقط **یک پیشنهاد** می‌سازد و هیچ فرمی تأیید یا ایجاد نمی‌کند، و action و log موقت را پیش از خروج پاک می‌کند | ☐ |
| **E-2** | تحویل واقعی SMTP | handoff ایمیلی را روی یک آدرس واقعی تنظیم کنید و یک انتقال واقعی بسازید | ایمیل **واقعاً** می‌رسد؛ محتوا بدون داده یا اسرار بازدیدکننده | ☐ |
| **E-3** | میکروفون مرورگر واقعی | در ویجت عمومی و در کنسول مدیر یک پیام صوتی ضبط کنید | متن پیاده‌شده **فقط composer را پر می‌کند** و چیزی به‌طور خودکار ارسال نمی‌شود | ☐ |
| **E-4** | draft واقعی ووکامرس | از Store Copilot یک پیشنهاد محصول بسازید و تأیید کنید | یک **draft** ساخته می‌شود؛ هیچ محصولی منتشر نمی‌شود و هیچ سفارش یا قیمتی تغییر نمی‌کند | ☐ |

مورد E-1 تنها موردی است که هزینهٔ توکن واقعی دارد.

---

## ۶. وضعیت git و برنامهٔ commit (تصمیم مالک D-05)

| مورد | وضعیت |
|---|---|
| commit پشتیبان | `snapshot/pre-release-0.0.1-20260921` روی **`39c2ae3`** — ۱۰۷ فایل تغییرکرده و ۸۴ فایل تازه |
| پشتیبان روی remote | `origin/snapshot/pre-release-0.0.1-20260921` روی `39c2ae3`. مخزن `hassantafreshi/wp-sitepilot-ai` **خصوصی** است و این پیش از push وارسی شد |
| `master` | در ۲۰۲۶-۰۹-۲۱ یک commit انتشار و tag `v0.0.1` روی `3a0e309` ساخته شد، ولی **هر دو درخت پیش از رفع Plugin Check را دارند و منسوخ‌اند**؛ پس از نشستن این اسناد هر دو دوباره ساخته می‌شوند |
| **تصمیم D-05** | **یک commit انتشار روی `master` به‌علاوهٔ tag `v0.0.1`.** گزینهٔ «چند commit منطقی به تفکیک فاز» رد شد، چون ژورنال `PRODUCT_COMPLETION_ROADMAP_FA.md` تاریخچهٔ فازبه‌فاز را با جزئیاتی بسیار بیشتر از هر پیام commit ثبت کرده است |
| شاخهٔ snapshot | به‌عنوان تور ایمنی باقی می‌ماند |

**یادآوری عملیاتی:** `/dist/` در `.gitignore` است، پس ZIP انتشار در هیچ commit نمی‌آید. مرجع دائمی آن، کپی پشتیبان و `FROZEN-ARTIFACT-README.md` در `test-artifacts/release-zip/20260921-t22-rebuild/` است.

**هشدار:** commit انتشار و tag `v0.0.1` موجود روی `3a0e309` درختِ **پیش از** رفع یافته‌های Plugin Check را دارند. هر دو باید روی درخت فعلی دوباره ساخته شوند، وگرنه چیزی که tag می‌خورد با ZIPیی که SHA-256 `68FA68A5…` دارد یکی نیست.

---

## ۷. امضای مالک

با امضای زیر تأیید می‌شود که بخش ۲ (گیت‌ها)، بخش ۴ (محدودیت‌های شناخته‌شده، از جمله تصمیم‌های D-33 و D-34) و بخش ۵ (پذیرش محیطی) خوانده و پذیرفته شده‌اند.

| مورد | مقدار |
|---|---|
| نسخه | `0.0.1` |
| SHA-256 بستهٔ انتشار | `68FA68A56CFDBB7C11608B7794B42D375DE3F1596F14954AD29C45EB527F3B47` |
| تاریخ امضا | ☐ |
| امضای مالک | ☐ |

پس از امضا: ردیف‌های Q4 در `MOCKUP_REDESIGN_TASK_TRACKER_FA.md` به `تکمیل` می‌روند و تاریخ امضا در ژورنال رودمپ ثبت می‌شود.
