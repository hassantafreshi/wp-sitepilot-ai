# گزارش آمادگی انتشار — `0.0.1`

تاریخ: ۲۰۲۶-۰۹-۲۱
افزونه: **AI Site Assistant AutoPilot**، نسخهٔ `0.0.1`، `Tested up to: 7.1`
وضعیت: **آمادهٔ امضای مالک.** هر ۱۰ task فاز ۴ و انتشار (T15 تا T24) با گیت و شواهد بسته شده‌اند. تنها کار باقی‌مانده، checklist پذیرش محیطی بخش ۵ است که فقط مالک می‌تواند انجام دهد.

---

## ۱. بستهٔ انتشار

| مورد | مقدار |
|---|---|
| فایل | `ai-site-assistant-autopilot-0.0.1.zip` |
| اندازه | ۷۵۹٬۳۸۸ بایت |
| SHA-256 | `90E5AE16B321DBAF1D4C6EDC3415E3E9C8336E3301FD025052ED1496BC33193E` |
| تعداد entry | ۱۷۰ (۱۳۴ PHP، ۲۴ JS، ۵ CSS) |
| ساخته‌شده در | T22، ۲۰۲۶-۰۹-۲۰ |

**این ZIP منجمد است و بازسازی نمی‌شود.** طبق تصمیم‌های مالک **D-33** و **D-34**، درخت `0.0.1` بسته است و هر اصلاحی به `0.0.2` می‌رود (ردیف‌های **B-10** و **B-11** بک‌لاگ). ساخت دوباره لزوماً همان SHA را نمی‌دهد و آن‌وقت شواهد T22، T23 و T24 موضوعشان را از دست می‌دهند.

### دو مسیر نگهداری ZIP — هر دو لازم‌اند

| مسیر | نقش |
|---|---|
| `apps/wordpress-plugin/ai-site-assistant-autopilot/dist/ai-site-assistant-autopilot-0.0.1.zip` | خروجی اصلی build |
| `test-artifacts/release-zip/20260920-t22/ai-site-assistant-autopilot-0.0.1.zip` | **کپی پشتیبان بایت‌به‌بایت**، به‌همراه `FROZEN-ARTIFACT-README.md` |

**نکتهٔ مهم برای commit انتشار:** پوشهٔ `dist/` در `.gitignore` افزونه است (خط ۶، `/dist/`). پس ZIP **در commit انتشار روی `master` نخواهد بود** و در commit پشتیبان `snapshot/pre-release-0.0.1-20260921` (`39c2ae3`) هم نبود. تا پیش از ساخت کپی پشتیبان، این فایل **تنها یک نسخه** روی کل دیسک داشت. برابری دو نسخه در ۲۰۲۶-۰۹-۲۱ با SHA-256 و اندازه دوباره تأیید شد.

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
| **T22** | ساخت و وارسی ZIP | build **exit 0**؛ وارسی مستقل **۳۱ PASS / ۰ FAIL**؛ کنترل منفی: اسکریپت HEAD با exit 1 و ZIP ۱۸۴ entry که ۱۳ تای آن `test-artifacts/` بود؛ smoke **۸/۸** | `test-artifacts/release-zip/20260920-t22/`، `test-artifacts/gates/20260920-160315-smoke` |
| **T23** | Plugin Check روی ZIP | **۲۷ static check موجود / ۲۷ اجراشده / ۰ اجرانشده**؛ **۱۱ error و ۱۳ warning** — گیت ۰/۰ نخورد و با حکم مستند **D-34** پذیرفته شد (بخش ۴)؛ junction سالم با **SWAP_FAILURES 0** | `test-artifacts/plugin-check/0.0.1/` |
| **T24** | پذیرش نصب تمیز | **۵۹ PASS / ۰ FAIL** روی WordPress **7.1.1** منتشرشده؛ فعال‌سازی با صفر notice و صفر بایت خروجی؛ هر شش ردپا پس از حذف به پایه برگشت | `test-artifacts/clean-install/20260921-t24/` |

### گیت‌های مشترک، آخرین مقدار معتبر

| گیت | مقدار | artifact |
|---|---|---|
| php-lint | **۲۳۴ / ۲۳۴** | `test-artifacts/gates/20260920-160315-smoke` |
| node --check | **۴۵ / ۴۵** | همان |
| PHPCS | **۱۳۴ / ۱۳۴ فایل، ۰ error، ۰ warning** | همان |
| PHPStan | **۰ error** | همان |
| PHPUnit | **۱۵۶ test / ۵۴۷ assertion** | همان |
| menu smoke | **۵ / ۰** | همان، و دوباره روی نصب تمیز در T24 |
| assets smoke | **۲۰ / ۰** | همان، و دوباره روی نصب تمیز در T24 |
| render smoke | **۱۸ / ۰** | همان، و دوباره روی نصب تمیز در T24 |
| MO فارسی | **۱۸۱۹ از ۱۸۱۹**، `msgfmt --check` سالم، ۲۳۳٬۸۷۳ بایت، SHA-256 `03FAF4AA…` | `scripts/compile-mo.php` |

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

### ۴.۱ یافته‌های Plugin Check (تصمیم مالک D-34، گزینهٔ الف)

Plugin Check **2.0.0** در حالت `new` روی ZIP: **۱۱ error و ۱۳ warning**. **۲۳ از ۲۴ یافته مثبت کاذب است و یکی واقعی و کم‌اثر؛ هیچ‌کدام آسیب‌پذیری نیست.** **۲۰ از ۲۷ check کاملاً پاک بودند**، از جمله `plugin_readme`، `trademarks` و `prefixing`.

| کلاس | شمار | حکم |
|---|---|---|
| `late_escaping` روی `AnswerDeskHandoffPage` | ۸ error | خروجی `card_class()`/`state_class()` از مجموعهٔ بستهٔ چهار رشتهٔ ثابت CSS است |
| `ExceptionNotEscaped` روی `UsageCappedProvider:66` | ۱ error | پیام سازندهٔ `ProviderException`؛ به UI نمی‌رود |
| `file_system_operations_fclose` | ۲ error | stream برابر `php://output` است؛ `WP_Filesystem` جایگزینش نیست |
| nonce و unslash در چهار فایل Admin | ۹ warning | بررسی در همان تابع یا تابع فراخوان وجود دارد |
| `WooCommerceSettings:18` | ۲ warning | **واقعی ولی عمدی**: مقدار خام `$_GET['tab']` ذخیره و در `finally` بازگردانده می‌شود |
| `MissingVersion` روی `AdminMenu:223` | ۱ warning | **تنها مثبت واقعی**؛ نسخهٔ `null` برای گوگل‌فونت |

**اینها regression نیستند.** commit `56dd00b` (۲۰۲۶-۰۸-۱۸، «Clear every Plugin Check finding on the release build») ۱۲ فایل را لمس کرده و هیچ‌کدام از ۱۰ فایل یافته‌های امروز در آن نیست؛ **۶ فایل از ۱۰ تا در آن commit اصلاً وجود نداشتند** و همان شش **هر ۱۱ error** را می‌سازند. چهار فایل باقی از آن زمان بازنویسی شده‌اند (۱۹۰۲ افزوده / ۸۷۱ حذف).

**گوگل‌فونت علامت نمی‌خورد:** `offloading_files`، `enqueued_resources` و `external_admin_menu_links` هر سه صفر. فهرست دامنه‌های Plugin Check فقط `ajax.googleapis.com` را دارد و font hostها را با `(?<!fonts\.)gstatic\.com` استثنا می‌کند. دو webfont در `readme.txt` بخش `== External services ==` افشا شده‌اند.

متن کامل و قابل‌خواندن برای بازبین WordPress.org در `RELEASE_NOTES.md` است. بدهی رفع: **B-11**.

### ۴.۲ پنج فایل بی‌hook (تصمیم مالک D-33، گزینهٔ الف)

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
| `master` | دست‌نخورده روی `459bfd8`، هم محلی و هم روی remote |
| **تصمیم D-05** | **یک commit انتشار روی `master` به‌علاوهٔ tag `v0.0.1`.** گزینهٔ «چند commit منطقی به تفکیک فاز» رد شد، چون ژورنال `PRODUCT_COMPLETION_ROADMAP_FA.md` تاریخچهٔ فازبه‌فاز را با جزئیاتی بسیار بیشتر از هر پیام commit ثبت کرده است |
| شاخهٔ snapshot | به‌عنوان تور ایمنی باقی می‌ماند |

**یادآوری عملیاتی:** `/dist/` در `.gitignore` است، پس ZIP انتشار در هیچ commit نمی‌آید. مرجع دائمی آن، کپی پشتیبان و `FROZEN-ARTIFACT-README.md` در `test-artifacts/release-zip/20260920-t22/` است.

---

## ۷. امضای مالک

با امضای زیر تأیید می‌شود که بخش ۲ (گیت‌ها)، بخش ۴ (محدودیت‌های شناخته‌شده، از جمله تصمیم‌های D-33 و D-34) و بخش ۵ (پذیرش محیطی) خوانده و پذیرفته شده‌اند.

| مورد | مقدار |
|---|---|
| نسخه | `0.0.1` |
| SHA-256 بستهٔ انتشار | `90E5AE16B321DBAF1D4C6EDC3415E3E9C8336E3301FD025052ED1496BC33193E` |
| تاریخ امضا | ☐ |
| امضای مالک | ☐ |

پس از امضا: ردیف‌های Q4 در `MOCKUP_REDESIGN_TASK_TRACKER_FA.md` به `تکمیل` می‌روند و تاریخ امضا در ژورنال رودمپ ثبت می‌شود.
