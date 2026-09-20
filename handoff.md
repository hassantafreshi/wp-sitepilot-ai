# Handoff — ادامه بازطراحی افزونه براساس موکاپ

آخرین به‌روزرسانی: ۲۰۲۶-۰۹-۲۱
وضعیت جاری: **صف انتشار تمام شد — T15 تا T25 همه تکمیل‌اند و فاز ۴ بسته است. Plugin Check اکنون ۰ error و ۰ warning است.** مالک در ۲۰۲۶-۰۹-۲۱ تصمیم D-34 را به گزینهٔ (ج) برگرداند و هر ۲۴ یافته در ۱۰ فایل **رفع** شد، نه پذیرفته؛ ZIP دوباره ساخته شد. **بستهٔ معتبر فعلی: SHA-256 `68FA68A56CFDBB7C11608B7794B42D375DE3F1596F14954AD29C45EB527F3B47`، ۷۶۰٬۴۳۲ بایت، ۱۷۰ entry.** ساخت `90E5AE16…` منسوخ است. **هشدار:** `master` روی `3a0e309` و tag `v0.0.1` هر دو درخت پیش از رفع را دارند و باید دوباره ساخته شوند. گزارش آمادگی در [`docs/RELEASE_READINESS_0.0.1_FA.md`](docs/RELEASE_READINESS_0.0.1_FA.md) است. **دو مورد باز که هیچ‌کدام کار ایجنت نیست:** (۱) **امضای مالک** در بخش ۷ گزارش؛ (۲) **checklist پذیرش محیطی E-1 تا E-4** — کلید واقعی provider با `tests/ai_runtime_check.php`، تحویل واقعی SMTP، میکروفون مرورگر واقعی، و draft واقعی ووکامرس. آزمون‌ها هرگز provider واقعی صدا نمی‌زنند و ایمیل یا پیامک واقعی نمی‌فرستند، پس این چهار مورد ذاتاً بیرون از QA خودکارند. **مانده برای نشست هماهنگ‌کننده:** ساخت commit انتشار روی `master` و tag `v0.0.1` با `commit-tree` (تصمیم D-05). سابقهٔ پیشین: **T15 تا T24 تکمیل.** T24 در ۲۰۲۶-۰۹-۲۱ روی نصب تمیز `C:\xampp\htdocs\wp-clean` (WordPress 7.1.1) با **۵۹ PASS / ۰ FAIL** سبز شد و هر شش ردپای افزونه پس از حذف به پایه برگشت. **تغییر invariant مهم:** worktree اکنون **تمیز** است روی شاخهٔ `snapshot/pre-release-0.0.1-20260921` در commit `39c2ae3`؛ `master` دست‌نخورده روی `459bfd8`. معیار سلامت دیگر «modified=107» نیست، بلکه «هیچ تغییر غیرمنتظره‌ای نسبت به `39c2ae3`». سابقهٔ پیشین: **T15 تا T23 تکمیل.** T23 در ۲۰۲۶-۰۹-۲۱ با تصمیم مالک (D-34 الف) بسته شد — صادقانه با حکم مستند، نه با گیت ۰/۰: Plugin Check روی ZIP ۱۱ error و ۱۳ warning می‌دهد که ۲۳ از ۲۴ مثبت کاذب است و در `RELEASE_NOTES.md` برای بازبین WordPress.org افشا شده؛ بدهی رفع در **B-11**. **T24 شروع نشد و `نیازمند تصمیم` است:** مالک D-04 را روی `C:\xampp\htdocs\wp-test-ai` بست، ولی بازرسی فقط‌خواندنی نشان داد آن نصب تمیز نیست و بستر فعال کار موازی زبان‌بسته‌های EFB است (mu-plugin سراسری، تزریق ثابت سناریومحور، `DISABLE_WP_CRON`، locale غیرانگلیسی). هیچ چیزی در آن نصب لمس نشد. **قدم بعدی: تصمیم تکمیلی مالک دربارهٔ محیط پذیرش.** ادامهٔ وضعیت پیشین: **ZIP نسخهٔ `0.0.1` دست‌نخورده معتبر است (`dist/ai-site-assistant-autopilot-0.0.1.zip`، ۱۷۰ entry، ۷۵۹٬۳۸۸ بایت، SHA-256 `90E5AE16…`). T23 اجرا شد و junction سالم ماند، ولی گیت ۰/۰ سبز نشد: Plugin Check 2.0.0 روی ZIP **۱۱ error و ۱۳ warning** داد که **۲۳ تای آن‌ها از ۲۴ مثبت کاذب‌اند و هیچ‌کدام آسیب‌پذیری نیست**. پس T23 روی `نیازمند تصمیم` است و **تصمیم باز و مسدودکننده D-34** است: `0.0.1` با همین ZIP منتشر شود (پیشنهاد)، فقط تنها مثبت واقعی رفع شود، یا هر ۱۰ نقطه در ۸ فایل رفع شود. D-33 بسته است (گزینهٔ الف) و بدهی‌اش در B-10 ثبت شده. T24 پشت D-04 (وردپرس تمیز) و T25 پشت D-05 (commit) است. S5-A (T29 تا T43) طبق D-13 (ب) پس از انتشار اجرا می‌شود. S7 (T57 تا T72) منتظر D-26 و D-27 است.**

## به‌روزرسانی ۲۰۲۶-۰۹-۲۱ — D-34 برگشت: هر ۲۴ یافتهٔ Plugin Check **رفع** شد؛ ZIP بازسازی شد

- **تصمیم تازهٔ مالک:** «خودت پلاگین چکر را انجام بده و درستش کن» ← **D-34 گزینهٔ (ج)** به‌جای (الف). تصمیم قبلی در ردیف D-34 به‌عنوان سابقه با تاریخ ماند و پاک نشد.
- **چرا عوض شد:** (الف) بر این پایه بود که رفع، گیت‌های سبز را روی درختی که دیگر همان نیست بی‌اعتبار می‌کند. وقتی مالک پذیرفت **کل تیر `-Tier full` دوباره اجرا شود**، آن هزینه پرداخت شد و دلیل (الف) از بین رفت؛ بسته‌ای با ۰/۰ برای بازبین WordPress.org به‌روشنی بهتر از بسته‌ای با ۲۴ یافتهٔ توضیح‌داده‌شده است.
- **کد و گیت را نشست هماهنگ‌کننده انجام داد. این ایجنت فقط اسناد را تازه کرد و هیچ کد، گیت یا عملیات git را لمس نکرد.**
- **۱۰ فایل محصول تغییر کرد** (نه ۸ که ارزیابی T23 تخمین زده بود).
- **دو قضاوت که ارزیابی T23 از پیش درست پیش‌بینی کرده بود و رعایت شد:**
  - `Admin/WooCommerceSettings.php:18` **sanitize نشد** — مقدار خام `$_GET['tab']` فقط ذخیره و در `finally` بازگردانده می‌شود و sanitize کردنش superglobal فراخواننده را بی‌صدا بازنویسی می‌کرد؛ فقط کامنت و ignore مستند گرفت.
  - `Providers/UsageCappedProvider.php:66` به‌جای `esc_html__()` ignore مستند گرفت — پیام فقط به Logger و بدنهٔ `wp_json_encode()` می‌رود، پس escape کردنش موجودیت HTML را داخل JSON می‌گذاشت.
- **گیت‌ها، همه پس از تغییر — هر کدام را مستقل وارسی کردم:**

  | گیت | نتیجه | شاهد |
  |---|---|---|
  | **Plugin Check روی ZIP تازه** | **۲۷ موجود / ۲۷ اجراشده / ۰ اجرانشده؛ ۰ error، ۰ warning، `RESULT PASS`** | `test-artifacts/plugin-check/0.0.1-rebuild-20260921/` |
  | تیر `-Tier full` | **۹/۹** — php-lint ۲۳۴/۲۳۴، node-check ۴۵/۴۵، PHPCS ۱۳۴/۱۳۴ با ۰/۰، PHPStan ۰، PHPUnit ۱۵۶/۵۴۷، menu ۵/۰، assets ۲۰/۰، render ۱۸/۰، phase1-integration ۴۵/۰ | `test-artifacts/gates/20260921-013502-full` |
  | وارسی مستقل ZIP | **۳۱ PASS / ۰ FAIL** | `test-artifacts/release-zip/20260921-t22-rebuild/zip-verification.txt` |
  | پذیرش نصب تمیز دوباره | **۲۳ PASS / ۰ FAIL**، `debug.log` با `delta=0` | `test-artifacts/clean-install/20260921-t24-rebuild/reacceptance.txt` |

- **یک شاهد که جا افتاده بود و خودم ساختم:** ادعای «وارسی مستقل ZIP ۳۱ PASS / ۰ FAIL» **هیچ فایل خروجی** در پوشهٔ rebuild نداشت. چون قرار بود همین عدد را در گزارش آمادگی و `RELEASE_NOTES.md` بنویسم، `verify-zip.ps1` (فقط‌خواندنی و پارامتری) را روی ZIP تازه اجرا کردم و خروجی‌اش را ثبت کردم: `RESULT PASS=31 FAIL=0`، ۱۷۰ entry، ۷۶۰٬۴۳۲ بایت، و نسخهٔ `0.0.1` در هر سه جا **از داخل خود ZIP**.
- **ZIP معتبر تازه:** SHA-256 `68FA68A56CFDBB7C11608B7794B42D375DE3F1596F14954AD29C45EB527F3B47`، **۷۶۰٬۴۳۲ بایت**، همان ۱۷۰ entry. اختلاف ۱٬۰۴۴ بایت فقط کامنت، `phpcs:ignore` و `esc_attr()` است. **MO دست‌نخورده** ماند (`03FAF4AA…`، مستقل تأیید شد). **نسخه `0.0.1` ماند** چون هرگز منتشر نشد و هیچ سایتی آن را ندارد. کپی پشتیبان در `20260921-t22-rebuild/` و ساخت اول با پیشوند `SUPERSEDED-` در `20260920-t22/` فقط برای سابقه.
- **B-11 بسته شد** (یافته‌های Plugin Check دیگر بدهی `0.0.2` نیستند). **B-10 تنها بدهی باز `0.0.2` می‌ماند** (پنج فایل بی‌hook، طبق D-33).
- **اسناد تازه‌شده:** `RELEASE_NOTES.md` (بخش Plugin Check کامل بازنویسی شد — اکنون ۰/۰، با تأکید بر «رفع شدند نه پذیرفته» و ثبت دو قضاوتی که رفع بدیهی در آن‌ها غلط می‌بود)؛ `docs/RELEASE_READINESS_0.0.1_FA.md` (هویت بسته، **سه** مسیر نگهداری ZIP، جدول گیت‌ها با ردیف‌های تازهٔ Plugin Check و Phase 1، حذف Plugin Check از محدودیت‌ها و تبدیل بخش ۴.۱ به ردیف تاریخی، نشانه‌گذاری ۴.۲ به‌عنوان تنها بدهی باز، جدول امضا با SHA تازه، و هشدار commit/tag کهنه)؛ `docs/technical/QUALITY_AND_RELEASE_FA.md`؛ و ردیف‌های T22، T23، T24، T25، D-34 و B-11 رودمپ.
- **هشدار عملیاتی برای نشست هماهنگ‌کننده:** `master` روی `3a0e309` با tag `v0.0.1` **هر دو درخت پیش از رفع** را دارند. باید روی درخت فعلی دوباره ساخته شوند، وگرنه چیزی که tag می‌خورد با ZIPیی که SHA-256 `68FA68A5…` دارد یکی نیست.

## به‌روزرسانی ۲۰۲۶-۰۹-۲۱ — T25 تکمیل: گزارش آمادگی انتشار؛ **فاز ۴ بسته شد**

- **D-05 بسته شد.** تصمیم مالک: **یک commit انتشار روی `master` به‌علاوهٔ tag تازهٔ `v0.0.1`**؛ شاخهٔ snapshot تور ایمنی می‌ماند. گزینهٔ «چند commit منطقی به تفکیک فاز» رد شد، چون ژورنال رودمپ تاریخچهٔ فازبه‌فاز را با جزئیاتی بسیار بیشتر از هر پیام commit ثبت کرده است.
- **پشتیبان روی remote:** `origin/snapshot/pre-release-0.0.1-20260921` روی **`39c2ae3`**. پیش از push وارسی شد که مخزن `hassantafreshi/wp-sitepilot-ai` **خصوصی** است. `master` هم محلی و هم روی remote دست‌نخورده روی `459bfd8`.
- **سند تازه: [`docs/RELEASE_READINESS_0.0.1_FA.md`](docs/RELEASE_READINESS_0.0.1_FA.md)** در هفت بخش:
  1. **هویت بستهٔ انتشار** — SHA-256 `90E5AE16…`، ۷۵۹٬۳۸۸ بایت، ۱۷۰ entry، و **دو مسیر نگهداری**. هشدار صریح: `/dist/` در `.gitignore` است (خط ۶)، پس ZIP **در commit انتشار نخواهد بود**؛ مرجع دائمی‌اش کپی پشتیبان و `FROZEN-ARTIFACT-README.md` در `test-artifacts/release-zip/20260920-t22/` است. برابری بایتی دو نسخه امروز دوباره با SHA-256 و اندازه تأیید شد.
  2. **جدول گیت‌های T15 تا T24** با شمار و artifact، به‌علاوه جدول گیت‌های مشترک: php-lint ۲۳۴/۲۳۴، node-check ۴۵/۴۵، PHPCS ۱۳۴/۱۳۴ با ۰/۰، PHPStan ۰، PHPUnit **۱۵۶ / ۵۴۷**، menu ۵/۰، assets ۲۰/۰، render ۱۸/۰، MO **۱۸۱۹ از ۱۸۱۹**.
  3. **جزئیات پذیرش نصب تمیز** با افشای صادقانهٔ دو خط `debug.log` که هر دو deprecation خود هستهٔ وردپرس‌اند.
  4. **چهار دستهٔ محدودیت شناخته‌شده** — Plugin Check طبق D-34 با جدول کلاس‌به‌کلاس و بند «اینها regression نیستند»؛ پنج فایل بی‌hook طبق D-33؛ قابلیت‌های قفل یا ساخته‌نشده (صف ناپایدار ایندکس، نبود PDF/URL، نبود voice realtime، نبود transportهای Slack/Discord/WhatsApp/webhook، قفل Phase 2 ووکامرس با تأکید بر draft-only و نبود REST route برای متدهای نوشتنی، و ۱۳ اختلاف عمدی X-01 تا X-13)؛ و رخداد Activity Log.
  5. **checklist پذیرش محیطی E-1 تا E-4** با روش اجرا و معیار قبولی هرکدام.
  6. **وضعیت git و برنامهٔ commit** طبق D-05.
  7. **جدول امضای مالک.**
- **tracker:** هر چهار ردیف Q4 به `تکمیل` رفت با شمار گیت در ستون پذیرش (Q4.1 ← T15، Q4.2 ← T16، Q4.3 ← T17، Q4.4 ← T22 و T23) و یک سطر لاگ افزوده شد. **نکتهٔ اصلاحی:** عنوان Q4.1 «۱۸ slug/alias» می‌گفت ولی شمار واقعی route **۱۹** است، چون Form Studio در T54 اضافه شد؛ در ستون پذیرش تصریح شد.
- **هیچ عملیات git انجام نشد** — نه commit، نه tag، نه switch، نه push. طبق دستور، ساخت commit انتشار و tag به نشست هماهنگ‌کننده سپرده شد تا با `commit-tree` و بدون جابه‌جایی worktree انجام شود (switch به `master` با درخت فعلی ۱۹۱ فایل را عقب می‌برد).
- **گیت‌ها:** این task سندی است و گیت اجرایی ندارد. preflight آغازین و پایانی هر دو WARNINGS 0 و BLOCKERS 0. `git status` در پایان فقط اسناد همین task را نشان می‌دهد؛ junction نصب اصلی `wp` لمس نشد و ZIP دست‌نخورده است.
- **قدم بعدی:** (۱) مالک بخش ۵ (E-1 تا E-4) و بخش ۷ (امضا) گزارش آمادگی را کامل کند؛ (۲) نشست هماهنگ‌کننده commit انتشار روی `master` و tag `v0.0.1` را بسازد. پس از آن تاریخ امضا در ژورنال رودمپ ثبت می‌شود. صف بعدی محصول: **S5-A (T29 تا T43)** که طبق D-13 (ب) پس از انتشار اجرا می‌شود، و **S7 (T57 تا T72)** که منتظر D-26 و D-27 است.

## به‌روزرسانی ۲۰۲۶-۰۹-۲۱ — T24 تکمیل: پذیرش نصب تمیز سبز

- **D-04 با گزینهٔ (ج) بسته شد.** `wp-test-ai` کنار گذاشته شد (harness بود) و نصب تمیز تازه ساخته شد. **محیط پذیرش رسمی:** `C:\xampp\htdocs\wp-clean`، `http://127.0.0.1/wp-clean`، **WordPress 7.1.1 منتشرشده** (نه alpha — با `Tested up to: 7.1` جور است)، دیتابیس `wp_clean`، صفر افزونهٔ فعال، صفر mu-plugin، locale `en_US`، cron روشن، `wp-config.php` مینیمال، **بدون هیچ junction**.
- **گیت سبز: ۵۹ PASS / ۰ FAIL در پنج لایه، و هر شش ردپای افزونه پس از حذف دقیقاً به پایه برگشت.**

  | لایه | نتیجه | شاهد |
  |---|---|---|
  | پایه پیش از نصب | صفر option/جدول/transient/cron/meta؛ **`debug.log` وجود نداشت** | `01-baseline.json` |
  | نصب از ZIP | SHA-256 `90E5AE16…`؛ پوشهٔ واقعی با `LinkType` خالی؛ **۱۷۰ فایل**؛ بدون `tests/`، `docs/`، `.git/` | `02-install.txt` |
  | فعال‌سازی | `activate_plugin()` = `NULL` (بدون `WP_Error`)، **صفر notice، صفر بایت خروجی، `debug.log` همچنان ساخته نشد** | `03-activate.txt` |
  | پذیرش | **۳۲ PASS / ۰ FAIL** | `05-acceptance.txt` |
  | smokeهای admin | menu **۵/۰**، assets **۲۰/۰**، render **۱۸/۰** — برابر خط مبنا | `06-admin-smokes.txt` |
  | حذف و وارسی | option `0→4→0`، جدول `0→4→0`، بقیه `0→0→0` | `07`، `09`، `10-comparison.json` |
  | پاک‌سازی | preflight WARNINGS 0 و BLOCKERS 0؛ `git status` نسبت به `39c2ae3` کاملاً تمیز | `11-preflight-final.txt` |

- **پذیرش شامل چه بود:** ویجت عمومی پیش‌فرض **خاموش** (`public_assistant_enabled` falsy، `should_render_widget()` false، و `do_action('wp_footer')` **صفر بایت** markup ویجت)؛ **منوی ۸ آیتمی D-23** با ترتیب و slug دقیق (Content Studio روی slug قدیمی `aisaautopilot-form-builder`)؛ **هر ۱۱ alias قدیمی ثبت‌شده ولی نامرئی**؛ و **۶۵ route با ۱۳۲ handler که همگی `permission_callback` دارند** — برابر inventory T19؛ و هر ۴ جدول ساخته‌شده.
- **یک assertion اصلاح شد، نه سست.** بررسی اول روی `/aisaautopilot/v1` شکست. ریشه: آن مسیر **از آنِ افزونه نیست** — هستهٔ وردپرس برای هر namespace یک index کشف با callback `WP_REST_Server::get_namespace_index` و بدون `permission_callback` می‌سازد، و اثبات شد افزونه هرگز namespace خام را ثبت نمی‌کند. assertion اکنون هویت core بودن index را صریح می‌سنجد و شرط permission را روی ۶۵ مسیر زیرین اجرا می‌کند.
- **افشای صادقانه — `debug.log` با ۵۰۶ بایت ساخته شد، ولی هیچ خطی از افزونهٔ ما نیست.** هر دو خط `PHP Deprecated: Function the_block_template_skip_link is deprecated since version 6.4.0` است. خود هسته در `wp-includes/default-filters.php` آن تابع را با یادداشت «Retained for backwards-compatibility» روی `wp_footer` می‌بندد، و **probe خودِ من** (`do_action('wp_footer')` در بررسی ویجت) آن را در بستر CLI شلیک کرد. grep کل افزونه **صفر** ارجاع به آن دارد. پس **صفر تشخیص PHP قابل‌انتساب به افزونه**.
- **دو تلهٔ ابزاری که خوردم و رفع شد:** (۱) `Set-Content -Encoding utf8` در PowerShell 5.1 **BOM** می‌نویسد، پس `declare(strict_types=1)` دیگر اولین دستور نبود و هر سه smoke با fatal error ایستادند — با `[IO.File]::WriteAllText` و `UTF8Encoding($false)` حل شد. (۲) `local_admin_assets_smoke.php` وجود فایل را با `dirname( __DIR__ )` می‌سنجد که نسبت به **مسیر خود اسکریپت** حل می‌شود، پس کپی scratchpad `files=no` می‌داد؛ در کپی به `AISAAUTOPILOT_DIR` تغییر کرد که **سخت‌گیرانه‌تر** است چون نسخهٔ نصب‌شده از ZIP را می‌سنجد. هر ۱۶ فایل JS مستقل هم بررسی و موجود بودند.
- **چه چیزی در `wp-clean` تغییر کرد:** افزونه نصب و سپس کاملاً حذف شد (پوشه و همهٔ ردپای دیتابیس)؛ `debug.log` با دو خط deprecation هسته ساخته شد؛ و دو پوشهٔ **خالی** `wp-content/uploads/2026/09` ایجاد شد که ساختار استاندارد وردپرس است (افزونه **صفر** فراخوانی `wp_upload_dir()` دارد، پس از ما نیست). نصب بدون افزونهٔ ما و بدون هیچ ردپای دیتابیسی رها شد.
- **هیچ فایل مخزنی تغییر نکرد** — کپی‌های smoke در scratchpad ماندند. junction نصب اصلی `wp` لمس نشد و ZIP دست‌نخورده است.
- **اسناد:** رودمپ (ردیف T24 ← تکمیل، ردیف D-04 ← بسته با مشخصات کامل محیط، بخش جزئیات T24، دو سطر ژورنال)، `docs/technical/QUALITY_AND_RELEASE_FA.md` (بند تازهٔ «پذیرش نصب تمیز» با شرایط محیط، قاعدهٔ `debug.log`، مسیر حذف، تلهٔ BOM، تلهٔ `dirname(__DIR__)` و قاعدهٔ namespace index). راهنمای کاربر تغییری نمی‌خواهد. commit انجام نشد.
- **قدم بعدی:** **T25** — گزارش آمادگی انتشار `docs/RELEASE_READINESS_0.0.1_FA.md` و برنامهٔ commit، که پشت **D-05** است.

## به‌روزرسانی ۲۰۲۶-۰۹-۲۱ — D-34 بسته شد و T23 تکمیل؛ T24 تلاش اول شروع نشد

- **تصمیم مالک، D-34: گزینهٔ (الف).** `0.0.1` با همین ZIP منتشر می‌شود (SHA-256 `90E5AE16…`، ۱۷۰ entry — **دوباره ساخته نشد**) و هر ۱۰ نقطهٔ Plugin Check به `0.0.2` می‌رود. استدلال ثبت‌شده: ۲۳ از ۲۴ یافته مثبت کاذب است، و رفع **جزئی** بدترین حالت است چون گیت را سبز نمی‌کند ولی artifact وارسی‌شدهٔ T22 را باطل می‌کند.
- **T23 ← `تکمیل`، صادقانه با حکم مستند و نه با گیت ۰/۰.** ردیف صف و بخش جزئیات هر دو همین را می‌گویند.
- **ردیف تازهٔ B-11** کنار B-10: هر ۱۰ نقطه در `0.0.2` ساکت شود، با فهرست دقیق فایل و خط، و **هشدار صریح که `WooCommerceSettings.php:18` نباید sanitize شود** (مقدار خام عمداً ذخیره و در `finally` بازگردانده می‌شود).
- **`RELEASE_NOTES.md`** بخش تازهٔ «Plugin Check results for this ZIP, and why they were accepted» گرفت، نوشته‌شده برای **بازبین انسانی WordPress.org** چون او همین Plugin Check را اجرا می‌کند: شمارها، ۲۰ check کاملاً پاک، دلیل هر کلاس مثبت کاذب با شمارهٔ خط، و ارجاع به `test-artifacts/plugin-check/0.0.1/`. دربارهٔ تنها مثبت واقعی (W12) صادقانه نوشته شد که «رفع» آن یا چسباندن `?ver=` بی‌معنی به URL گوگل‌فونت است یا `phpcs:ignore`، و `null` دادن برای فونت راه دور **همان کاری است که خود هستهٔ وردپرس می‌کند**، پس رفع، بهبود کیفیت نیست و فقط ساکت کردن sniff است.
- **بند «اینها regression نیستند» هم به همان بخش افزوده شد.** commit `56dd00b` (۲۰۲۶-۰۸-۱۸) با پیام «Clear every Plugin Check finding on the release build» در تاریخچه هست و هر بازبینی می‌پرسد چرا امروز ۲۴ یافته داریم. مستقل وارسی شد: آن commit **۱۲ فایل** را لمس کرده و **هیچ‌کدام از ۱۰ فایل یافته‌های امروز در آن نیست**؛ و با `git cat-file -e 56dd00b:<path>` ثابت شد **۶ فایل از ۱۰ تا در آن commit اصلاً وجود نداشته‌اند** (`AnswerDeskHandoffPage`، `IntegrationsPage`، `ContentStudioNavigation`، `UsageCappedProvider`، `LogExport`، `ContentStudioHistoryExport`) و همین شش **هر ۱۱ error** و ۷ از ۱۳ warning را می‌سازند؛ چهار فایل باقی از آن زمان اساساً بازنویسی شده‌اند (**۱۹۰۲ افزوده / ۸۷۱ حذف**). یعنی یافته‌ها **کد تازه‌اند، نه بازگشت عیب رفع‌شده** — و همین توضیح می‌دهد چرا الگو فقط دو شکل دارد: متد کمکی که نام کلاس CSS برمی‌گرداند، و ذخیره/بازگردانی superglobal دور یک render.

### T24 شروع نشد — نصب پیشنهادی تمیز نیست (مهم)

مالک D-04 را روی `C:\xampp\htdocs\wp-test-ai` بست. طبق دستور «اول بررسی کن و اگر نشانهٔ کار فعال دیدی شروع نکن»، **بازرسی فقط‌خواندنی انجام شد و کار شروع نشد. هیچ فایلی، دیتابیسی یا `debug.log`ی در آن نصب لمس نشد.**

آنچه در توصیف نیامده بود:

1. **mu-plugin `wp-content/mu-plugins/wptai-tools.php`** روی هر درخواست بار می‌شود، با `pre_wp_mail` **هر** `wp_mail()` را می‌بلعد (هیچ ایمیلی ارسال نمی‌شود) و در option `wptai_mail_log` می‌ریزد، و ترافیک HTTP خروجی را در `wptai_http_log` ثبت می‌کند.
2. **`wp-config.php` سناریومحور است:** از `wptai-scenario.json` (اکنون `{"server":"https://demo.whitestudio.team"}`) می‌خواند و علاوه بر `EMSFB_LANGUAGE_PACKS_SERVER_URL` یک **تزریق ثابت دلخواه** دارد: `define( $wptai_name, $wptai_value )`. در لاگ ۲۰۲۶-۰۹-۲۰ اثرش هست (`not installing anything: file_mods_disallowed`) — یعنی سناریو می‌تواند `DISALLOW_FILE_MODS` را روشن کند و **نصب ZIP را از اساس ببندد**.
3. **`DISABLE_WP_CRON = true`** و `EFB_DEBUG = true`.
4. **بسته‌های زبان `ru_RU`، `hi_IN` و `nl_NL` نصب‌اند** و بخشی از هشدارهای `debug.log` **به سوئدی** است، پس locale مدیر `en_US` نیست.
5. **`debug.log` (۲۷٬۸۹۴ بایت) پر از اجراهای تکراری `emsfb_language_packs`** در سراسر ۲۰۲۶-۰۹-۲۰ است (۱۲:۰۴، ۱۳:۱۲، ۱۶:۴۳، ۱۷:۵۵، ۱۸:۱۵ UTC). **آخرین نوشتن ۲۱:۱۶ محلی بود، نه ۱۶:۱۴.** هیچ پروسهٔ زنده، `.maintenance` یا قفلی در لحظهٔ بررسی نبود، ولی الگوی اجراهای مکرر همان‌روز نشان می‌دهد این بستر کارِ جاری کسی است.

**چرا گیت T24 روی این نصب معنا نمی‌دهد:** «فعال‌سازی بدون PHP warning» با لاگ از پیش آلوده و دو فیلتر سراسری mu-plugin سنجیدنی نیست؛ رفتار cron ما با `DISABLE_WP_CRON` اصلاً اجرا نمی‌شود؛ و منوی مدیر ممکن است به زبان دیگری رندر شود. T24 قرار است رفتار روی **وردپرس دست‌نخورده** را اثبات کند، نه روی یک harness.

**نقص سندی که همین‌جا پیدا و اصلاح شد:** متن گیت T24 هنوز «منوی دقیق ۱۰تایی» می‌گفت، از پیش از **D-23** مانده بود. D-23 منو را روی **۸** آیتم قفل کرده و Usage & Cost و Health و Activity Log را به تب‌های Overview برده است. عدد درست ۸ است و در رودمپ اصلاح شد.

- **اسناد:** رودمپ (ردیف T23 ← تکمیل، ردیف T24 ← نیازمند تصمیم، ردیف D-34 ← بسته، ردیف D-04 با مشخصات محیط و سه گزینهٔ تکمیلی، ردیف تازهٔ B-11، اصلاح ۱۰ ← ۸ در گیت T24، و دو سطر ژورنال)، `RELEASE_NOTES.md`. راهنمای کاربر تغییری نمی‌خواهد. commit انجام نشد.
- **قدم بعدی:** مالک تکلیف محیط پذیرش را روشن کند — (الف) همین نصب با غیرفعال‌سازی موقت mu-plugin و سناریو، تثبیت locale روی `en_US` و روشن کردن cron؛ (ب) یک نصب واقعاً تمیز که خودش می‌سازد؛ (ج) اجازهٔ صریح به ایجنت برای ساخت نصب تمیز. **پیشنهاد ایجنت: (ب) یا (ج).**

## به‌روزرسانی ۲۰۲۶-۰۹-۲۰ — T23 اجرا شد (شواهد همین تاریخ)

- **درخواست مالک:** `/aisa-next T23`. وابستگی T22 تکمیل. **SHA-256 ZIP پیش از هر کار دوباره سنجیده شد و دقیقاً خواند:** `90E5AE16B321DBAF1D4C6EDC3415E3E9C8336E3301FD025052ED1496BC33193E`، ۷۵۹٬۳۸۸ بایت، ۱۷۰ entry. D-33 و ردیف B-10 و سطر ژورنالش در رودمپ بررسی شد و کامل بود؛ چیزی بازنویسی نشد.
- **preflight آغازین یک BLOCKER گذرا روی `wp-login.php` داد (timeout)** — همان الگوی T21 و T22. probe دوباره با ۶۰ ثانیه 200 گرفت، پس کار ادامه یافت. preflight پایانی `v0.0.1`، `settings_stored=False`، WARNINGS 0، BLOCKERS 0.
- **هیچ فایل محصولی تغییر نکرد و ZIP دوباره ساخته نشد.** `git status` در پایان همان ۱۰۷ modified و ۵۶ untracked آغاز کار است.

### جابه‌جایی junction — بدون حادثه

اسکریپت `try/finally` با سوئیچ `-DryRun`؛ اول dry-run اجرا شد و بعد اجرای واقعی. **SWAP_FAILURES 0.**

- ۱۴ assert پیش از swap: SHA و اندازه و شمار entry ZIP، پیشوند همهٔ entryها برابر text domain، صفر جداکنندهٔ backslash، `LinkType=Junction` و `Target` برابر source، آزاد بودن نام park.
- rename به `ai-site-assistant-autopilot.dev-junction` (تغییر نام **link**، نه هدفش)، سپس `Expand-Archive` در پوشهٔ plugins.
- ۱۴ assert روی پوشهٔ استخراج‌شده: reparse point نیست، `LinkType` خالی است، **هیچ‌یک از ۹ نشانهٔ منبع** (`handoff.md`، `docs`، `tests`، `scripts`، `vendor`، `node_modules`، `.git`، `test-artifacts`، `composer.json`) در آن نیست، شمار فایل‌ها **۱۷۰ برابر ۱۷۰** entry، و فایل اصلی بایت‌به‌بایت برابر منبع.
- حذف پوشهٔ استخراج‌شده **فقط پس از** تکرار همان assertها در `finally`. **هرگز حذف بازگشتی از میان junction.**
- restore: `LinkType=Junction`، `Target` برابر source، سه فایل منبع (`handoff.md`، فایل اصلی، `fa_IR.mo`) و فهرست ۳۲تایی سطح ریشه بایت‌به‌بایت بدون تغییر. سپس render smoke **۱۸/۰** و preflight پاک.

### نتیجهٔ Plugin Check

- Plugin Check **2.0.0**، mode `new`، **۲۷ static check موجود / ۲۷ اجراشده / ۰ اجرانشده**. جمع: **۱۱ error و ۱۳ warning** ← گیت ۰/۰ سبز نشد.
- **کنترل مثبت:** همان runner روی `akismet` **۳۳ error و ۱۰۱ warning** می‌دهد. یعنی runner واقعاً یافته پیدا می‌کند و یک ۰/۰ در افزونهٔ ما معنادار می‌بود.
- **۲۳ check از ۲۷ کاملاً پاک بودند**، از جمله سه تای پرریسک: **`plugin_readme` صفر** (readme.txt بازنویسی‌شدهٔ T21 از نظر Plugin Check کامل است)، **`trademarks` صفر** و **`prefixing` صفر** (پیشوند `aisaautopilot_` همه‌جا رعایت شده).
- **حکم: ۲۳ یافته از ۲۴ مثبت کاذب‌اند، یکی واقعی و کم‌اثر، و هیچ‌کدام آسیب‌پذیری نیستند.** جدول کامل حکم‌ها در جزئیات T23 رودمپ است. خلاصه:
  - ۸ error `late_escaping` روی `AnswerDeskHandoffPage` 251/255/382/386: خروجی `card_class()` و `state_class()` از مجموعهٔ بستهٔ `' is-ready'`، `' is-needs-setup'`، `' is-paused'`، `' is-off'` می‌آید؛
  - ۱ error روی پیام **سازندهٔ** `ProviderException` در `UsageCappedProvider:66` که به UI نمی‌رود (متن کاربر از `user_message()` می‌آید)؛
  - ۲ error `fclose()` روی `php://output` در `LogExport` و `ContentStudioHistoryExport` که `WP_Filesystem` جایگزینش نیست؛
  - warningهای nonce و unslash در `IntegrationsPage`، `SettingsPage` و `ContentStudioNavigation` که همگی در همان تابع یا تابع فراخوان پوشش دارند؛
  - **W10–W11** در `WooCommerceSettings.php:18` واقعی ولی **عمدی** است: مقدار خام `$_GET['tab']` ذخیره و پس از رندر aliasشده بازگردانده می‌شود، پس sanitize کردنش رفتار را عوض می‌کند؛
  - **تنها مثبت واقعی W12 است:** `AdminMenu.php:223` پارامتر نسخهٔ `wp_enqueue_style` گوگل‌فونت را `null` می‌دهد و sniff صریحاً `null` را هم `MissingVersion` می‌شمارد.
- **نگرانی گوگل‌فونت که T22 ثبت کرده بود منتفی شد.** `offloading_files`، `enqueued_resources` و `external_admin_menu_links` هر سه صفر. دلیلش در خود کد Plugin Check است: فهرست دامنه‌های ممنوع در `vendor/plugin-check/phpcs-sniffs/PluginCheck/Helpers/OffloadingServicesTrait.php` فقط `ajax\.googleapis\.com` را دارد و `gstatic.com` را با lookbehind صریحِ `(?<!fonts\.)gstatic\.com` استثنا می‌کند — یعنی Plugin Check 2.0.0 عمداً گوگل‌فونت را مجاز می‌داند. **پس bundle یا حذف فونت لازم نیست و هیچ ردیف تصمیمی برای آن ساخته نشد.**
- **گیت‌ها:**

  | گیت | نتیجه | شاهد |
  |---|---|---|
  | وارسی ZIP پیش از کار | SHA-256 و اندازه و ۱۷۰ entry دقیقاً برابر artifact T22 | `test-artifacts/plugin-check/0.0.1/junction-swap-run.txt` |
  | Plugin Check (static) | **۲۷/۲۷ اجراشده، ۱۱ error، ۱۳ warning** ← گیت ۰/۰ **سبز نشد** | `…/plugin-check-0.0.1.json`، `…/plugin-check-0.0.1.txt` |
  | کنترل مثبت | akismet: ۳۳ error، ۱۰۱ warning | `…/positive-control-akismet.json` |
  | جابه‌جایی junction | **SWAP_FAILURES 0**؛ junction با `LinkType=Junction` و `Target` درست بازگشت؛ منبع بایت‌به‌بایت دست‌نخورده | `…/junction-swap-run.txt` |
  | render smoke | **۱۸ pass / ۰ fail** | `test-artifacts/gates/20260920-231821-only` |
  | پاک‌سازی | preflight پایانی `v0.0.1`، `settings_stored=False`، WARNINGS 0، BLOCKERS 0 | `…/0.0.1/preflight-final.txt` |

- **بدون browser job و بدون fixture:** هیچ حساب QA ساخته نشد؛ پنجرهٔ مرورگر آزاد ماند.
- **چرا کد تغییر نکرد:** رسیدن به ۰/۰ ویرایش **۱۰ نقطه در ۸ فایل محصول** می‌خواهد که «کوچک و بی‌ریسک» نیست، و W10–W11 اصلاً بی‌ریسک نیست. مهم‌تر اینکه دقیقاً همان استدلالی که مالک امروز در D-33 به کار برد اینجا هم برقرار است: تغییر کد یعنی گیت‌های T15 تا T20 روی درختی سبز شده‌اند که دیگر همان نیست. رفع **جزئی** هم گیت را سبز نمی‌کند ولی artifact وارسی‌شدهٔ T22 را باطل می‌کند. پس انتخاب به مالک سپرده شد ← **D-34**.
- **اسناد:** رودمپ (ردیف T23 ← `نیازمند تصمیم`، بخش وضعیت جزئیات T23 با جدول کامل حکم‌ها، ردیف تازهٔ **D-34** با سه گزینه، و یک سطر ژورنال)، `docs/technical/QUALITY_AND_RELEASE_FA.md` (بخش «Plugin Check و پذیرش»: قرارداد runner، الزام کنترل مثبت و `checks_run > 0`، قواعد جابه‌جایی امن junction، و یافتهٔ گوگل‌فونت). راهنمای کاربر تغییری نمی‌خواهد: هیچ برچسب، جریان یا حالتی که کاربر ببیند عوض نشد. commit انجام نشد.
- **قدم بعدی:** مالک **D-34** را ببندد، مثلاً «D-34: الف». اگر (الف) → T23 با حکم مستند بسته می‌شود، یافته‌ها به محدودیت‌های شناخته‌شدهٔ `RELEASE_NOTES.md` افزوده می‌شوند و نوبت T24 است که خودش به **D-04** (وردپرس تمیز) نیاز دارد. اگر (ب) یا (ج) → ابتدا کد اصلاح، سپس ZIP دوباره ساخته و با همان `verify-zip.ps1` در `test-artifacts/release-zip/20260920-t22/` وارسی، SHA-256 تازه ثبت، و `-Tier full` دوباره اجرا شود.

## به‌روزرسانی ۲۰۲۶-۰۹-۲۰ — T22 تکمیل: ZIP انتشار `0.0.1`

- **درخواست مالک:** `/aisa-next T22`. وابستگی T21 تکمیل بود. preflight آغازین یک **BLOCKER گذرا** روی `wp-login.php` داد (timeout)؛ probe دوباره با ۶۰ ثانیه 200 گرفت، پس کار ادامه یافت. preflight پایانی `v0.0.1`، `settings_stored=False`، WARNINGS 0 و BLOCKERS 0.
- **تنها فایل محصول/ابزار که عوض شد `scripts/build-phase1-release.ps1` است.** هیچ فایلی از worktree حذف نشد. سه نقص:
  1. **`test-artifacts/` استثنا نبود** — نه در `$excludedDirectories` و نه در regex فایل‌های توسعه. افزوده شد (به‌همراه `.claude`، `dist`، `.github` و دو پوشهٔ cache در regex).
  2. **`handoff.md` در ریشهٔ افزونه allow-list ریشه را می‌شکست** و build را متوقف می‌کرد. به `$excludedFiles` افزوده شد.
  3. **نقص تازه‌ای که در همین task پیدا شد و از دو مورد بالا مهم‌تر است:** build با `msgfmt --check --output-file $moFile $poFile` روی **خودِ** `languages/ai-site-assistant-autopilot-fa_IR.mo` می‌نوشت. یعنی هر ساخت یک فایل tracked را بی‌صدا عوض می‌کرد و ZIP یک MO می‌برد که **هیچ گیتی رویش اجرا نشده بود**: اندازه از ۲۳۳٬۸۷۳ به ۲۴۳٬۵۸۰ بایت و hash از `03FAF4AA…` به `2C6E447A…` می‌رفت (msgfmt جدول hash می‌نویسد و `POT-Creation-Date` را می‌اندازد). محتوا یکی است — هر دو **۱۸۱۹** پیام ترجمه‌شده و `msgunfmt` روی دو فایل فقط **یک خط** اختلاف دارد — ولی MOیی که عرضه می‌شود باید همانی باشد که QA رویش اجرا شده. اکنون PO در یک فایل موقت `--check` می‌شود و آن فایل در `finally` پاک می‌شود، MO دست‌نخورده می‌ماند (تنها نویسنده‌اش `scripts/compile-mo.php` است)، و اگر MO از PO قدیمی‌تر باشد build با پیام «Run scripts/compile-mo.php» متوقف می‌شود.
  - **MO پس از هر دو اجرای آزمایشی با `scripts/compile-mo.php` بازگردانده شد و بایت‌به‌بایت برابر حالت اولیه است** (`03FAF4AA080ECCCD4A67DF8B5A5E4BDA9A25187897EB8D78F0BEA2FB880F864D`، ۲۳۳٬۸۷۳ بایت، ۱۸۱۹ از ۱۸۱۹).
- **وارسی مستقل، جدا از اعتبارسنجی خودِ build:** `test-artifacts/release-zip/20260920-t22/verify-zip.ps1` فهرست entryها را با درخت منبع می‌سنجد (صفر کمبود، صفر اضافه)، ۱۹ مسیر توسعه‌ای را رد می‌کند، جداکنندهٔ backslash را می‌شمارد، ریشه را allow-list می‌کند، `.mo`/`.po`/`.pot` و سه فایل ریشه را بایت‌به‌بایت با منبع مقایسه می‌کند و نسخه را **از داخل خود ZIP** در هر سه جا می‌خواند. **۳۱ PASS / ۰ FAIL.**
- **کنترل منفی:** نسخهٔ HEAD اسکریپت (`git show HEAD:scripts/build-phase1-release.ps1`) با **exit 1** روی «unexpected files in the plugin root: …/handoff.md» می‌شکند، و ZIP نیمه‌ساختهٔ آن **۱۸۴** entry دارد که **۱۳** تای آن‌ها `test-artifacts/`اند. یعنی اگر کسی فقط `handoff.md` را جابه‌جا می‌کرد، تصویرهای QA بی‌صدا منتشر می‌شدند.
- **گیت‌ها:**

  | گیت | نتیجه | شاهد |
  |---|---|---|
  | build | **exit 0**؛ ۱۷۰ entry، ۷۵۹٬۳۸۸ بایت، SHA-256 `90E5AE16B321DBAF1D4C6EDC3415E3E9C8336E3301FD025052ED1496BC33193E` | `dist/ai-site-assistant-autopilot-0.0.1.zip` |
  | وارسی مستقل ZIP | **31 PASS / 0 FAIL** | `test-artifacts/release-zip/20260920-t22/zip-verification.txt` |
  | کنترل منفی | اسکریپت HEAD: exit 1؛ ZIP آن ۱۸۴ entry با ۱۳ فایل `test-artifacts/` | `…/negative-control.txt`، `…/zip-entries-prefix-negative-control.txt` |
  | smoke | **8/8**: php-lint 234/234، node-check 45/45، PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit **156 / 547**، menu 5/0، assets 20/0، render 18/0 — برابر خط مبنای T21 | `test-artifacts/gates/20260920-160315-smoke` |
  | پاک‌سازی | preflight پایانی `v0.0.1`، `settings_stored=False`، WARNINGS 0، BLOCKERS 0 | `…/20260920-t22/preflight-final.txt` |

- **بدون browser job و بدون fixture:** هیچ حساب QA ساخته نشد؛ پنجرهٔ مرورگر آزاد ماند.
- **تصمیم تازهٔ باز، D-33:** پنج فایل بی‌hook (یافتهٔ Q19-3) در ZIP هستند: `assets/admin/dashboard.js`، `assets/frontend/chat-widget.js`، `assets/frontend/chat-widget.css`، `includes/Frontend/WidgetRenderer.php` و `includes/Voice/VoiceService.php`؛ روی هم **۳۹٬۶۵۰ بایت نافشرده**. امروز دوباره اثبات شد که هیچ ارجاع محصولی ندارند — تنها ارجاع در کل درخت، آزمونی است که *نبودن* `WidgetRenderer` روی `wp_footer` را تضمین می‌کند. سطح حمله نیستند چون هیچ‌کدام بارگذاری نمی‌شوند. **پیشنهاد ایجنت:** در `0.0.1` بمانند و در `0.0.2` حذف شوند، چون حذفشان تغییر کد است و گیت‌های T15 تا T20 روی درخت امروز سبز شده‌اند.
- **اسناد:** رودمپ (ردیف T22 ← تکمیل، بخش وضعیت جزئیات T22، یک سطر خط مبنا برای ZIP، ردیف تازهٔ D-33، و یک سطر ژورنال)، `docs/technical/QUALITY_AND_RELEASE_FA.md` (بخش «ساخت نسخهٔ انتشار» کامل بازنویسی شد: قاعدهٔ «build هرگز MO را نمی‌نویسد»، فهرست تازهٔ استثناها، خروجی معتبر فعلی با اندازه و SHA-256، و بند «اعتبارسنجی خودِ build را باور نکنید»). راهنمای کاربر تغییری نمی‌خواهد: هیچ برچسب، جریان یا حالتی که کاربر ببیند عوض نشد. commit انجام نشد.
- **قدم بعدی:** `/aisa-next T23` — Plugin Check روی همین ZIP. **دو یادآوری:** (۱) **Google Fonts از راه دور بار می‌شود** (`includes/Admin/AdminMenu.php:223`) و Plugin Check ممکن است آن را به‌عنوان منبع بیرونی علامت بزند؛ تصمیم دربارهٔ bundle کردن یا حذف فونت با مالک است. (۲) جابه‌جایی junction فقط با اسکریپت `try/finally` و اجرای dry-run نخست، طبق جزئیات T23.

## به‌روزرسانی ۲۰۲۶-۰۹-۲۰ — D-03 بسته شد؛ T21 تکمیل (نسخهٔ 0.0.1)

- **تصمیم مالک، D-03:** نسخه **`0.0.1`** است، نه 1.1.0. دلیل مالک: افزونه هنوز رسماً منتشر نشده و به نسخهٔ ۱ نرسیده است. **سیاست نسخه‌گذاری تا رسیدن به `1.0.0`: هر bump فقط راست‌ترین جزء (patch) را یک واحد بالا می‌برد، نه minor و نه major** — پس نسخهٔ بعدی `0.0.2` است. این در ردیف D-03 رودمپ و در `docs/technical/QUALITY_AND_RELEASE_FA.md` ثبت شد تا دوباره بحث نشود. `Tested up to: 7.1` تأیید شد.
- **نسخه در سه جا:** سربرگ `Version:`، `AISAAUTOPILOT_VERSION` و `Stable tag`. preflight پایانی روی سایت زنده `plugin v0.0.1` می‌دهد.
  - **نکتهٔ صادقانه:** `0.0.1` از `1.0.4` پایین‌تر است. هیچ‌جای کد `version_compare` روی نسخهٔ افزونه ندارد (`AISAAUTOPILOT_VERSION` فقط cache-busting و یک فیلد اطلاعاتی در `NativeFormConnector` است) و هیچ نسخه‌ای منتشر نشده، پس هیچ سایتی مسیر ارتقا ندارد. `tests/phpstan-bootstrap.php` هنوز یک stub با `'1.0.4'` دارد و `tests/bootstrap.php` یکی با `'0.6.0'`؛ هیچ‌کدام در ZIP نمی‌روند و عمداً دست‌نخورده ماندند.
- **`readme.txt` بازنویسی شد:**
  - توضیح اکنون یک **فهرست سادهٔ قابلیت‌های نسخهٔ ۱** است. **این عمدی و موقت است، به دستور صریح مالک**؛ خودش پیش از انتشار واقعی بازنویسی‌اش می‌کند. بقیهٔ بخش‌ها نهایی‌اند.
  - بخش تازهٔ `== What deleting the plugin removes ==`.
  - `== External services ==` یک سرویس افشانشده گرفت: **Google Fonts**. `includes/Admin/AdminMenu.php:223` دو webfont را از `fonts.googleapis.com` در صفحه‌های مدیریت بار می‌کند و در readme نبود. همچنین تصحیح شد که path دادهٔ Simple Icons درون افزونه است و هیچ درخواستی به آن سرویس نمی‌رود، و شمار پریست‌ها درست شد (۱۴ پریست + Custom).
  - Installation و FAQ با IA هشت‌صفحه‌ای هم‌خوان شدند (Setup & Connection، Form Studio، Advanced settings) و یک پرسش تازه دربارهٔ «افزونه خودش منتشر نمی‌کند» افزوده شد.
  - changelog تک‌ورودی `= 0.0.1 =` با یادداشت صریح که buildهای 1.0.1 تا 1.0.4 هرگز منتشر نشدند و یادداشت‌هایشان در `RELEASE_NOTES.md` است.
- **`RELEASE_NOTES.md`** از 1.0.2 به 0.0.1 بازنویسی شد: قرارداد محصول، فهرست عرضه، جدول «چه چیزی حذف می‌شود / چه چیزی می‌ماند»، جدول شواهد گیت‌های T15 تا T20، محدودیت‌های شناخته‌شده (صف ناپایدار ایندکس، نبود منبع PDF/URL، نبود voice realtime، پنج فایل بی‌hook، ارجاع به گزارش اختلاف‌های عمدی و به `ACTIVITY_LOG_QA_INCIDENT_FA.md`) و تاریخچهٔ buildهای توسعه.
- **`MANUAL-TESTING.md`** کامل بازنویسی شد. سند قبلی کهنه بود: منوهای «Settings»، «Copilot» و «Logs»، گام «Clear log» که با تصمیم A2.10 حذف شده، دو بخش با شمارهٔ Part 11، و «live voice conversation» در EFB که وجود ندارد. سند تازه ۱۳ بخش مطابق IA امروز دارد، گام‌هایی که فقط مالک می‌تواند تأیید کند با **(محیط واقعی)** نشانه‌گذاری شده‌اند، و Part 12 جدول پاک‌سازی حذف افزونه را گام‌به‌گام می‌آزماید.
- **inventory کامل پاک‌سازی (درخواست صریح مالک: «فقط فهرست قبلی را کپی نکن»):** grep کل `includes/` برای `add_option`/`update_option`/`delete_option`، `set_transient`، `CREATE TABLE`، `register_post_type`، کلیدهای meta، `update_site_option`، `update_user_meta`، `add_role`/`add_cap` و `wp_cache_*`. نتیجه: **۲۷ option نام‌دار، ۴ جدول، ۵ خانوادهٔ transient، ۱ cron، ۴ کلید post meta و ۲ post type. هیچ user meta، هیچ option شبکه‌ای، هیچ role یا cap و هیچ کلید اختصاصی object cache وجود ندارد.**
- **دو نقص واقعی در `uninstall.php` پیدا و رفع شد:**
  1. **هیچ transientی پاک نمی‌شد.** اکنون `_transient_aisaautopilot_%` و `_transient_timeout_aisaautopilot_%` با `DELETE ... LIKE` می‌روند.
  2. **جاروی پیشوند لازم بود.** `RebrandMigrator::migrate_options()` هر option `sitepilot_*` را با تغییر پیشوند منتقل می‌کند و فهرست را از دیتابیس کشف می‌کند، نه از آرایه‌ای ثابت؛ پس سایتی که از نام قدیمی ارتقا یافته می‌تواند ردیف `aisaautopilot_*` داشته باشد که نامش در کد امروز اصلاً اعلام نشده. اکنون یک جاروی پایانی `aisaautopilot_%` هست. پیشوند منجمد و مخصوص همین افزونه است.
  - `tests/UninstallInventoryTest.php` یک آزمون تازه گرفت که هر سه جارو را می‌خواهد؛ روی `uninstall.php` پیش از تغییر شکست می‌خورد.
- **F-11 بسته شد، بدون تغییر کد:** ناهم‌نامی `exclude_ids`/`widget_exclude_ids` در T73 رفع شده بود (یک خوانندهٔ واحد، `Settings::widget_exclusions()`، که هر سه کلید را یکی می‌کند و `ChatWidget`، `ChatWidgetSettings` و `AnswerDeskWidgetPage` همان را صدا می‌زنند). امروز با اجرای دوبارهٔ `tests/wordpress_widget_visibility_integration.php` (**۲۷ check، ۰ failed**) اثبات و رسماً بسته شد. `exclude_pages` و `widget_exclude_ids` طبق قاعدهٔ حفظ حذف نشدند و در `DATA_MODEL_FA.md` به‌عنوان کلید فقط‌خواندنی قدیمی تعریف‌اند.
- **یافتهٔ تازه، عمداً رفع‌نشده (قاعدهٔ حفظ):** کلید `monthly_token_budget` در `Settings::defaults()` هیچ نویسنده و خواننده‌ای ندارد؛ سقف واقعی option `aisaautopilot_monthly_budget` است که دکمهٔ «Save cap» در تب Usage & Cost می‌نویسد. هم‌خانوادهٔ F-12 است و برای T22/T29 ثبت شد.
- **گیت‌ها:**

  | گیت | نتیجه | شاهد |
  |---|---|---|
  | smoke | **8/8**: php-lint 234/234، node-check 45/45، PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit **156 / 547**، menu 5/0، assets 20/0، render 18/0 | `test-artifacts/gates/20260920-144553-smoke` |
  | widget visibility integration (F-11) | **27 check، 0 failed** | خروجی در `test-artifacts/release-metadata/20260920-t21/` |
  | وارسی متادیتا | نسخه در هر سه جا `0.0.1`، ۲۷ `delete_option`، ۳ جاروی `LIKE`، ۴ `DROP TABLE`، ۱ `wp_clear_scheduled_hook`، صفر نویسندهٔ user meta / site option / role / cap | `test-artifacts/release-metadata/20260920-t21/verification.txt` |
  | پاک‌سازی | preflight پایانی `plugin v0.0.1`، `settings_stored=False`، WARNINGS 0، BLOCKERS 0 | `…/20260920-t21/preflight-final.txt` |

- **بدون browser job:** هیچ fixture یا حساب QA ساخته نشد؛ پنجرهٔ مرورگر آزاد ماند.
- **اسناد:** رودمپ (ردیف D-03، ردیف T21، جزئیات T21، یک سطر خط مبنا و دو سطر ژورنال)، `docs/technical/DATA_MODEL_FA.md` (بخش «حذف افزونه» کامل بازنویسی شد؛ دو ردیف transient جا افتاده افزوده شد)، `docs/technical/QUALITY_AND_RELEASE_FA.md` (بخش تازهٔ «شمارهٔ نسخه و متادیتای انتشار» با سیاست bump). راهنمای کاربر تغییری نمی‌خواهد: هیچ برچسب، جریان یا حالتی که کاربر ببیند عوض نشد. commit انجام نشد.
- **قدم بعدی:** `/aisa-next T22`. اول `scripts/build-phase1-release.ps1` اصلاح شود (افزودن `test-artifacts` به `$excludedDirectories` و regex، و `handoff.md` به `$excludedFiles`)، سپس اجرا با مسیر مطلق و وارسی entryهای ZIP. یادآوری برای T23: **Google Fonts از راه دور بار می‌شود** و Plugin Check احتمالاً آن را به‌عنوان منبع بیرونی علامت می‌زند؛ تصمیم دربارهٔ bundle کردن فونت یا حذف آن با مالک است.
## به‌روزرسانی ۲۰۲۶-۰۹-۱۹ — T21 متوقف روی D-03

- **درخواست مالک:** `/aisa-next T21`. وابستگی‌ها تکمیل‌اند، اما شمارهٔ نسخه تصمیم مالک است و ایجنت آن را انتخاب نمی‌کند. ردیف T21 در رودمپ به «نیازمند تصمیم» رفت و ردیف D-03 با شواهد امروز تازه شد.
- **شواهد تازه:** REST نسبت به HEAD (459bfd8) فقط افزایشی است: پنج مسیر تازه و هیچ حذف. `HandoffInbox::SCHEMA_VERSION` اکنون `2` است، با مهاجرت افزایشی `dbDelta`. WP محلی 7.1.2-alpha است.
- **پیشنهاد:** نسخهٔ 1.1.0 و `Tested up to: 7.1`.
- **محیط:** preflight یک BLOCKER گذرا روی `wp-login.php` داد (timeout). probe دوباره با 60 ثانیه پاسخ 200 گرفت.
- کدی تغییر نکرد، گیتی اجرا نشد و commit انجام نشد.
- **قدم بعدی:** مالک D-03 را ببندد، مثلاً «D-03: 1.1.0». سپس `/aisa-next T21`. کار T21 پس از آن: نسخه در سه جا، `Tested up to`، readme (توضیح سطوح عرضه‌شده، External services، changelog، جدول پاک‌سازی uninstall)، بازنویسی `RELEASE_NOTES.md`، `MANUAL-TESTING.md`، grep کامل `add_option`، `update_option` و `set_transient` برای inventory، و بستن F-11 (`exclude_ids`).

## به‌روزرسانی ۲۰۲۶-۰۹-۱۹ — T18، T17 و T16 تکمیل

- **درخواست مالک:** «T16، T17 و T18 را تمام کن». ترتیب اجرا T18، سپس T17، سپس T16 بود، تا بازبینی فارسی روی ترجمهٔ کامل انجام شود.
- **T18:**
  - POT تازه با xgettext.
  - 510 ورودی obsolete حذف شد.
  - دو fuzzy و هشت ترجمهٔ فقط‌لاتین اصلاح شد.
  - اکنون، پس از پنج رشتهٔ T16، PO و POT **1819 از 1819** هستند. شواهد: `test-artifacts/i18n/20260919-t18/`.
- **T17:**
  - گزارش: [`docs/ACCESSIBILITY_Q4_3_REPORT_FA.md`](docs/ACCESSIBILITY_Q4_3_REPORT_FA.md).
  - آزمون دائمی تازهٔ `tests/admin_a11y_browser_matrix.cjs` روی ۲۶ route.
  - ده نقص A17 رفع شد.
  - نتیجه‌ها: a11y **1522/0**، ویجت **437/0** و EFB **156/0**.
- **T16:**
  - گزارش: [`docs/VISUAL_PARITY_Q4_2_REPORT_FA.md`](docs/VISUAL_PARITY_Q4_2_REPORT_FA.md). هر **۱۳۵** حالت موکاپ حکم دارد و نقص باز صفر است.
  - هشت نقص V16 رفع شد:
    - V16-1: عرض سربرگ؛
    - V16-2: گرید Answer Desk؛
    - V16-3: برچسب EFB در خط زمانی؛
    - V16-4 و V16-5: رشته‌های ثابت JS در Store و Voice؛
    - V16-6: ستون‌های پیشنهاد Store؛
    - V16-7: پیام تکراری Store؛
    - V16-8: برچسب نمودار Usage.
  - هشت اختلاف عمدی تازه، X-06 تا X-13، در `MOCKUP_FEATURE_GAP_REPORT_FA.md` ثبت شد.
  - ابزار ضبط در `test-artifacts/visual-parity/20260919-t16/` است:
    - `capture_admin.cjs`: عکس تمیز؛
    - `capture_states.cjs`: حالت‌ها با mock و fixture. با `T16_ONLY=name,name` فقط همان حالت‌ها دوباره ضبط می‌شوند.
- **گیت‌های پایانی:**
  - a11y `run5-t16`: **1528/0**؛ `run6-t16-final` (store-new، usage، answer-voice): **206/0**.
  - integrationهای Store، activity timeline، Content Studio، voice و usage سبز.
  - smoke **8/8** (`test-artifacts/gates/20260919-071048-smoke`).
  - preflight 0/0.
- **برای T22:** کد مرده که حذفش تصمیم مالک است: `dashboard.js`، `WidgetRenderer.php`، `chat-widget.js|css`، `VoiceService.php` و `copilot.js`. `settings.js` تابع `paintProviderFailure` را دارد که هیچ‌وقت اجرا نمی‌شود.
- **مرورگر:** پنجره برای نشست‌های `sitepilot-ai-ab` و `sitepilot-ai-b2` آزاد شد.
- commit انجام نشد.
- **قدم بعدی:** مالک D-03 را ببندد (پیشنهاد: 1.1.0). سپس `/aisa-next` → T21.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۹ — T20 تکمیل: بازبینی امنیتی بازطراحی

- **درخواست مالک:** «ادامه» پس از T19. دامنه: ۱۲۴ فایل (`git diff --name-only 56dd00b` + untracked در `includes/` و `assets/`). گزارش: [`docs/SECURITY_REVIEW_Q4_T20_REPORT_FA.md`](docs/SECURITY_REVIEW_Q4_T20_REPORT_FA.md).
- **روش:** sniffهای امنیتی استاندارد کامل `WordPress` (۱۸ خطا و ۵ هشدار در ۹ فایل، همه مثبت کاذب با حکم فایل‌به‌فایل)، ممیزی ۱۷۳ `phpcs:ignore`، خواندن دستی nonce/capability، SSRF، اسرار، SQL، CSV، `innerHTML` و rate limit، و **کنترل منفی** برای هر آزمون تازه روی 56dd00b.
- **چهار نقص رفع شد، هیچ ریسکی پذیرفته نشد:**
  - **S20-1 (متوسط)** XSS ذخیره‌شده در کنسول «Ask your knowledge base»: `escHtml()` در `assets/admin/chat.js` نقل‌قول را escape نمی‌کرد و خروجی داخل `href` می‌رفت؛ پاسخ تزریق‌شده روی محتوای سایت می‌توانست event handler به پیوند بچسباند. از 56dd00b وجود داشت. رفع + `tests/admin_chat_markdown_check.cjs` (7/0؛ روی نسخهٔ پایه شکست می‌خورد).
  - **S20-2 (متوسط)** حذف افزونه جدول‌های `aisaautopilot_widget_chats` و `aisaautopilot_handoffs` (دادهٔ بازدیدکننده) و ۱۱ option از جمله `aisaautopilot_wp_connector_key` را جا می‌گذاشت (Q19-1). `uninstall.php` کامل شد + `tests/UninstallInventoryTest.php` که هر ثابت option/جدول تازه در `includes/` را در `uninstall.php` می‌جوید.
  - **S20-3 (کم)** توکن Telegram بی‌فاصله پس از `/bot` در URL از `Redactor` می‌گذشت. lookbehind + آزمون.
  - **S20-4 (کم؛ R-1 — مالک «همین حالا رفع شود» را برگزید)** چهار مسیر تبدیل گفتار محتوای فایل را نمی‌سنجیدند. `VoiceTranscriber::detect_mime()` ۱۲ بایت آغازین را با امضای WebM/Ogg/MP4/WAV می‌سنجد؛ نام و نوع فایلی که به provider می‌رود از بایت‌ها ساخته می‌شود نه از سربرگ کلاینت. +۲ آزمون در `LanguageAndVoiceTest`.
- **گیت‌ها:**

  | گیت | نتیجه | شاهد |
  |---|---|---|
  | static | **5/5**: php-lint 234/234، node-check 43/43، PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit **155 / 544** (پیش از T20: 149 / 514) | `test-artifacts/gates/20260919-003345-static` |
  | integration | Phase 1 **45/0**؛ widget render **97/0**؛ chat console **14/0** | `test-artifacts/security-review/20260918-t20/` |
  | کنترل منفی | آزمون Markdown روی `chat.js` پایه: exit 1؛ `UninstallInventoryTest` روی `uninstall.php` پایه: 2 شکست | همان پوشه، `negative-control-*.txt` |
  | پاک‌سازی | preflight پایانی `settings_stored=False`، WARNINGS 0، BLOCKERS 0 | `…/preflight-final.txt` |

- **بدون browser job:** هر چهار رفع با آزمون واحد یا integration اثبات شد؛ پنجرهٔ مرورگر در دست T15 و T73 بود.
- **برای T22:** `includes/Voice/VoiceService.php` هم کد مرده است (کنار چهار فایل Q19-3)؛ حذف تصمیم مالک است.
- **اسناد:** گزارش تازه؛ `SECURITY_FA.md` (بخش تازهٔ «بارگذاری صدا»، ردیف Redactor، بند کنسول مدیر)؛ `REST_API_FA.md` (قرارداد `invalid_audio`)؛ `QUALITY_AND_RELEASE_FA.md` (دو لایهٔ آزمون تازه، خط مبنا 155/544)؛ رودمپ (ردیف T20، دو سطر خط مبنا، ژورنال، ورودی T21 و T22). راهنمای کاربر تغییری نمی‌خواهد (هیچ برچسب یا جریانی عوض نشد). commit انجام نشد.
- **قدم بعدی:** `/aisa-next` → T16؛ یا T18 که ویرایش PO نشست b2 تمام شده و دیگر مانعی ندارد. T17 هم آزاد است. هر browser run تک‌تک.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۸ — T73 تکمیل: F-11 و F-12 حل شدند (نشست `sitepilot-ai-b2`)

- **درخواست مالک:** دو محدودیتی که T14 «با تصمیم مستند» بسته بود حل شوند: اگر مشکل UI است پیاده‌سازی، اگر مستندات است تعریف. به‌علاوه، فهرست همهٔ قفل‌های صادقانه و بن‌بست‌های پروژه با علت هرکدام (در پاسخ همان گفتگو داده شد).
- **F-11، UI:** کارت «Pages where the chat appears» (`#answer-desk-visibility`) در `aisaautopilot-widget`: «Every page» یا «Every page except the ones below»، نوع محتوا، دستهٔ نوشته و جست‌وجوی مورد تکی با عنوان از `wp/v2/search`. ردیف خلاصه در برگهٔ Widget (`[data-sp-widget-where]`، پیوند «Choose pages»). خواننده سه نقص داشت که رفع شد: `widget_exclude_ids` به‌جای `exclude_ids`، `exclude_pages` بی‌اثر، و مقایسهٔ شناسهٔ صفحهٔ دسته با شناسهٔ نوشته. هیچ کلیدی حذف نشد؛ ذخیره `exclude_pages` و `widget_exclude_ids` را خالی می‌کند.
- **F-12، مستندات:** دوازده کلید بازنشسته در `Settings::WIDGET_RETIRED_DEFAULTS`، با جدول «کلید ← کنترل زنده» در `docs/technical/DATA_MODEL_FA.md`. به آن‌ها سیم‌کشی نشود؛ T21 دیگر تصمیمی دربارهٔ آن‌ها ندارد.
- **گیت‌ها:** integration تازه `tests/wordpress_widget_visibility_integration.php` **27/0**؛ `tests/widget_customise_browser_matrix.cjs` **85 PASS / 0 FAIL** (en_US 41، fa_IR 44)؛ smoke 8/8 (`test-artifacts/gates/20260918-213643-smoke`)؛ preflight 21:35 پاک. شواهد: `test-artifacts/widget-visibility/20260918-t73/`.
- **پیامد برای دیگر آزمون‌ها:** `browser_release_check.cjs` هر وقت `AISAAUTOPILOT_BROWSER_WIDGET_CUSTOMISE_FIXTURE=1` باشد `widgetVisibilityHealthy()` را هم اجرا می‌کند، و `tests/local_widget_customise_browser_fixture.php` اکنون Answer Desk را روشن، `kb_auto_index` را خاموش و یک برگهٔ QA نشان‌دار می‌سازد که restore حذفش می‌کند.
- **درس، برای همهٔ نشست‌ها:** سه نشست (T15، T56، T73) امروز دو بار هم‌زمان browser matrix اجرا کردند؛ حساب QA و option تنظیمات مشترک است و هیچ قفلی نیست. پیش از هر matrix یا gate، پردازه‌های `tests\*matrix*`، `browser_release_check` و `run-gates` را ببینید و با پیام «claimed / released» نوبت بگیرید. پیشنهاد برای بعد: یک lock در `tests/local_browser_user.php`.
- **یافتهٔ باز:** قفل «Match my theme colours» در برگهٔ Widget در گزارش شکاف و هیچ task‌ی نیامده است؛ برای T29 (اکنون پس از انتشار، D-13 ب).
- commit انجام نشد.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۸ — T15 تکمیل (browser smoke انتشار)

- **درخواست مالک:** «T15»، ادامه پس از قطع ساعت 20:31. وابستگی T14 تکمیل؛ preflight 20:38 با WARNINGS 0 و BLOCKERS 0.
- **wrapper:** `tests/release_smoke_browser_matrix.cjs` — برای هر locale کاربر QA موقت؛ گذر ۱ همهٔ routeها جز Activity Log در fixtureهای Store Copilot و Answer Desk (بازگردانی LIFO)؛ گذر ۲ Activity Log در fixture خودش. شمار route **۱۹** است، نه ۱۸ (Form Studio از T54).
- **گیت:** اجرای 6 (21:00 تا 21:25) exit 0: en_US **288 / 0**، fa_IR **299 / 0**، جمع **587 PASS / 0 FAIL**؛ هر ۱۹ route با HTTP 200، پوستهٔ `.sp-app` و `ROUTE_390 overflow=0`؛ صفر console error و pageerror. `FAILED_RESPONSES 4` عمدی است (سه پاسخ ساختگی و 400 واقعی `provider/test` که check انتظار دارد). شاهد: `test-artifacts/release-smoke/20260918-t15/run6-matrix.txt`، تصویرها در `test-artifacts/release-smoke/<locale>/`.
- **آنچه در آزمون‌ها تغییر کرد:** `tests/browser_release_check.cjs` (Form Studio در فهرست پیش‌فرض، `AISAAUTOPILOT_BROWSER_EXCLUDE_SLUGS`، تصویر 1440 و 390 هر route، و چهار assertion متن Content Studio و Activity Log که فقط در انگلیسی سبز می‌شدند اکنون locale-aware‌اند)؛ سه fixture دیگر بدون snapshot به تنظیمات زنده دست نمی‌زنند. کد محصول تغییر نکرد.
- **تاریخچهٔ اجراها:** اجرای 1 و 2 با matrixهای نشست‌های دیگر هم‌زمان بودند و بی‌اعتبارند؛ اجرای 3 در Setup timeout خورد، هم‌زمان با PHPStan گیت smoke نشستی دیگر، و تکرار نشد؛ اجرای 4 را مالک قطع کرد؛ اجرای 5 چهار FAIL فارسیِ ناشی از assertion انگلیسی داشت.
- **سه یافتهٔ بصری برای T17، رفع‌نشده:** F15-1، سربرگ Store Copilot در fa_IR به لبهٔ راست نمی‌چسبد؛ F15-2، در پیش‌نمایش ویجت Answer Desk متن انگلیسی ذخیره‌شده در RTL «?» را به ابتدای جمله می‌برد (سطح `sitepilot-ai-b2`)؛ F15-3، «محل نگهداری کلید» در Advanced settings در fa_IR از ابتدا بریده می‌شود.
- **گیت smoke:** پس از آزاد شدن پنجره به دست `sitepilot-ai-b2` (21:35) اجرا شد: **8/8** (php-lint 233/233، node-check 42/42، PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit 149 / 514، menu 5/0، assets 20/0، render 18/0). شاهد: `test-artifacts/gates/20260918-213643-smoke`. preflight 21:39 با `settings_stored=False`، WARNINGS 0 و BLOCKERS 0؛ پنجره ساعت 21:40 آزاد شد.
- **پاک‌سازی:** «Local browser QA account removed.»؛ preflight پایانی 21:26: `settings_stored=False`، WARNINGS 0، BLOCKERS 0. پیام آزادسازی پنجره در 21:26 به `sitepilot-ai-b2` و `sitepilot-ai-2a` فرستاده شد. commit انجام نشد.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۸ — D-13 بسته شد؛ T19 تکمیل

- **تصمیم مالک، D-13:** گزینهٔ (ب)، یعنی S5-A پس از انتشار. در رودمپ، ستون وابستگی T29 به «T14، T25 (D-13 گزینهٔ ب)» تغییر کرد تا preflight آن را پیش از انتشار پیشنهاد ندهد. T30 که پیش‌تر با دستور مستقیم مالک انجام شده بود در 1.1.0 می‌ماند.
- **T19، ممیزی قراردادها و preserve-list:** گیت سبز است. کد تغییر نکرد و روی سایت چیزی نوشته نشد؛ فقط اسکریپت‌های فقط‌خواندنی اجرا شد (preflight 20:59 با 0/0).
  - **REST:** 65 route و 66 handler، در برابر 46 route در 56dd00b. هیچ route حذف نشده و method هیچ‌کدام تغییر نکرده. عمومی‌ها همان ۷ route پیشین‌اند و ۵۹ handler مدیریتی `manage_options` می‌خواهند. ۱۱ route فرم و EFB شرط روشن بودن قابلیت را هم گرفته‌اند. ۱۹ route تازه همگی مدیریتی‌اند.
  - **WooCommerce:** ردیف‌های `get_capabilities()` دست‌نخورده‌اند و فقط `$available` سخت‌تر شده است. `smart-search` فقط‌خواندنی است. `wc_create_refund` و `$product->save()` خفته‌اند و هیچ route ندارند.
  - **شناسه‌ها:** `KeyVault` و `RebrandMigrator` با 56dd00b صفر خط diff دارند؛ salt و Text Domain بدون تغییرند؛ هر ۱۵ slug پایه ثبت‌اند؛ optionها و جدول‌ها فقط اضافه شده‌اند.
  - **preserve-list:** ۱۴ ردیف، همه «حفظ» با شاهد.
- **یافته‌ها:**
  - **Q19-1، مهم برای T20 و T21:** `uninstall.php` جدول‌های `aisaautopilot_handoffs` و `aisaautopilot_widget_chats` را، که دادهٔ بازدیدکننده دارند، drop نمی‌کند. ده option تازه را هم پاک نمی‌کند، و option قدیمی `aisaautopilot_last_diagnostics` پس از ارتقا یتیم می‌ماند. فهرست کامل در جزئیات T21 رودمپ است.
  - **Q19-2:** «Clear log» با تصمیم A2.10 حذف شده، ولی راهنما (F40 و G32) هنوز آن را قابلیت می‌شمارد. یافته در `GUIDE_PLAN_FA.md` ثبت شد.
  - **Q19-3:** `dashboard.js`، `WidgetRenderer.php` و `chat-widget.js|css` hook ندارند ولی در ZIP می‌روند (← T22 و T23).
- **اسناد:** [`docs/CONTRACT_AUDIT_Q4_T19_REPORT_FA.md`](docs/CONTRACT_AUDIT_Q4_T19_REPORT_FA.md) (تازه)؛ در رودمپ: ردیف T19، ردیف D-13، وابستگی T29، ورودی‌های T20 و T21 و ژورنال؛ یک یافته در `GUIDE_PLAN_FA.md`. شواهد در `test-artifacts/contract-audit/20260918-t19/`. commit انجام نشد.
- **هماهنگی نشست‌ها:** به نشست‌های `sitepilot-ai-ab` (T15) و `sitepilot-ai-b2` (T73) اعلام شد که T56 دیگر مرورگر لازم ندارد، پس ترتیب مرورگر اکنون T15 و سپس T73 است. نشست b2 روی PO، `ChatWidget.php`، `ChatWidgetSettings.php` و `Settings.php` کار می‌کند؛ **T18 باید پس از تمام شدن آن ویرایش‌ها و با خواندن دوبارهٔ PO شروع شود.**
- **قدم بعدی:** `/aisa-next T18`، سپس T20. T16 و T17 به T15 وابسته‌اند.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۸ — T56 بسته شد؛ **بستهٔ S6 تمام شد**

- **درخواست مالک:** «T56». وابستگی T54 تکمیل؛ preflight 18:57 با WARNINGS 0 و BLOCKERS 0.
- **آزمون دائمی تازه:** `tests/admin_ia_browser_check.cjs` و `tests/admin_ia_browser_matrix.cjs` — منوی ۸ آیتمی D-23 و نبود هر alias، هر ۸ صفحهٔ اصلی و سه تب پنهان Overview در `en_US` و `fa_IR` × 1440×960 و 390×844 (HTTP 200، `.sp-app`، جهت محاسبه‌شده، صفر overflow، عنوان فارسی، آیتم مالک در منو)، نوار تب با تنها یک `aria-current`، Tab با focus مرئی و Enter، صفحهٔ Form Studio و لینک عمیق `form-builder&mode=forms`. fixture ندارد (فقط می‌خواند)؛ اگر حساب QA مشترک در دست job دیگری باشد با exit 2 رد می‌کند.
- **سه نقص رفع شد:** (۱) تغییر نام T54 شش msgid «Form Studio» و `aria-label` «Overview sections» را بی‌ترجمه گذاشته بود — PO و MO (2324 از 2465؛ فارسی «استودیوی فرم»)؛ (۲) ردیف تب Overview با فاصلهٔ 0px به کارت اول چسبیده بود — `.sp-overview-tabs { margin-block-end: 24px }`؛ (۳) در 390px تب‌ها 3 + 1 می‌شکستند — در ≤520px شبکهٔ 2 × 2 با 44px. هر دو مورد CSS سنجهٔ دائمی گرفتند.
- **گیت‌ها:**

  | گیت | نتیجه | شاهد |
  |---|---|---|
  | admin IA matrix | **652 PASS / 0 FAIL**، `MATRIX RESULT PASS`: en_US 316، fa_IR 336؛ صفر console/pageerror/HTTP≥400 | `test-artifacts/admin-ia/20260918-t56/matrix-run5.txt`، تصویرها در `test-artifacts/admin-ia/<locale>/` |
  | smoke | 8/8: php-lint 233/233، node-check 42/42، PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit 149 / 514، menu 5/0، assets 20/0، render 18/0 | `test-artifacts/gates/20260918-202017-smoke` |
  | ترجمه | MO 2324 از 2465، `msgfmt --check` سالم | خروجی `scripts/compile-mo.php` |
  | پاک‌سازی | «Local browser QA account removed.»؛ preflight پیش از اجرای نهایی 0/0. preflight پایانی دو هشدار حساب QA دارد که **از آنِ matrix در حال اجرای T15 در نشست دیگر** است و دست نخورد | `…/20260918-t56/preflight-final.txt` |

- **افشای برخورد (مهم برای هر نشست):** اجرای چهارم matrix هم‌زمان با `widget_customise_browser_matrix.cjs` نشستی دیگر (حدود 19:40 تا 19:50) اجرا شد. همهٔ matrixهای مدیریتی **یک** رکورد حساب QA دارند (`aisaautopilot_local_browser_user`): matrix من locale آن را عوض کرد و در `finally` حساب را حذف کرد. نتیجهٔ اجرای چهارم (68 FAIL) دور ریخته شد و اجرای آن نشست هم احتمالاً بی‌اعتبار است؛ به نشست `sitepilot-ai-b2` پیام داده شد. پس از آن نشست دیگری T15 را اجرا می‌کرد (`release_smoke_browser_matrix.cjs`) و نگهبان تازه یک بار درست رد کرد. **راه‌حل ریشه‌ای** (نام حساب جدا برای هر job در `local_browser_user.php`) همهٔ matrixها را لمس می‌کند و به T15/T17 سپرده شد.
- **بیرون از دامنه، برای T17:** عنوان ردیف «Embeddings» در Health در fa_IR لاتین («Embedding») و چپ‌نشین است.
- **اسناد:** گزارش تازهٔ [`docs/OVERVIEW_FORM_STUDIO_S6_EXECUTION_REPORT_FA.md`](docs/OVERVIEW_FORM_STUDIO_S6_EXECUTION_REPORT_FA.md)، plan S6 (S6.1.8 و S6.3)، رودمپ (ردیف، جزئیات، سه سطر خط مبنا، ژورنال)، tracker، `docs/technical/QUALITY_AND_RELEASE_FA.md`، `docs/technical/ADMIN_SURFACES_FA.md`، `docs/user-guide/GUIDE_PLAN_FA.md` (G01، G05، G22، G23، G28 تا G31 و یک یافته). commit انجام نشد.
- **قدم بعدی:** `/aisa-next T29` که **به D-13 نیاز دارد**؛ یا مسیر فاز ۴ (T15 را نشست دیگری در دست دارد، T18، T19). هر browser run یا gate tier تک‌تک اجرا شود.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۸ — پیگیری T13 به درخواست مالک: حالت ضبط و sheet موبایل

- **درخواست مالک:** دو اختلاف عمدی گزارش T13 («کادر پیام هنگام ضبط دوخطی می‌شود» و «برگهٔ موبایل صفحهٔ پشت را قفل نمی‌کند») اگر کاربرپسند نیستند درست شوند، و حالت ضبط در دسکتاپ و موبایل هر دو درست باشد. هر دو کاربرپسند نبودند و رفع شدند.
- **F-25، حالت ضبط:** placeholder ضبط `i18n.listening` یک‌خطی است: «Listening…»، با msgid موجود، پس PO و MO تغییر نکردند. کادر پیام قاب coral، نقطهٔ زنده و زمان m:ss به رقم زبان صفحه می‌گیرد (`#spw-rec`). هر دو دکمهٔ میکروفن به دکمهٔ توقف (مربع) با `aria-label` و `title` «Stop recording» بدل می‌شوند. جملهٔ کامل «… tap the mic again to stop» فقط در ناحیهٔ status برای صفحه‌خوان ماند. ارتفاع کادر در 1440 و 390 و در هر دو زبان ثابت ماند (50px و 53px).
- **F-17، sheet موبایل:** تصمیم «قفل نمی‌شود» برگشت. `syncModal()` در `widget.js` پنل باز را در 640px و کمتر مثل full screen modal می‌کند: `aria-modal="true"`، Tab داخل sheet، و قفل `html`/`body` با snapshot. بستن، صفحه را همان‌جا برمی‌گرداند. چرخش گوشی با شنوندهٔ `change` دنبال می‌شود. پنل docked در 641px و بیشتر غیر modal ماند.
- **یافتهٔ جانبی:** `transition: all` دکمه‌های قالب hello-elementor به `.spw-mic` نشت می‌کرد، پس رنگ coral آهسته می‌آمد. تصویرهای T13 هم به همین دلیل میکروفن را کمرنگ نشان می‌دادند. `.spw-mic` اکنون transition خودش را دارد.
- **فایل‌ها:** `includes/Frontend/ChatWidget.php`، `includes/Core/Plugin.php`، `assets/frontend/widget.js`، `assets/frontend/widget.css`، `tests/front_chat_widget_browser_check.cjs` (+40 check) و `tests/wordpress_front_chat_widget_render_integration.php` (+4).
- **گیت‌ها:** matrix **425 PASS / 0 FAIL** (en_US 213، fa_IR 212)؛ render integration **96/0**؛ هشت integration مرتبط سبز؛ smoke **8/8** (`test-artifacts/gates/20260918-185323-smoke`)؛ preflight پایانی WARNINGS 0 و BLOCKERS 0، `settings_stored=False`. شواهد: `test-artifacts/front-chat-widget/20260918-t13-followup/`، تصویرها در `test-artifacts/front-chat-widget/t13-followup/<locale>/`.
- **شکست‌ها:** اجرای نخست 417/8 بود: چهار شکست transition میکروفن (نقص واقعی، رفع شد) و چهار شکست خود check، چون قالب `scroll-behavior: smooth` دارد. check اکنون با `behavior: 'instant'` می‌پرد. هیچ assertionی سست نشد.
- **هم‌زمانی:** همهٔ شواهد پیش از شروع T15 و T56 در همین worktree ثبت شد. آن جلسه‌ها پس از آن `ChatWidget.php` را هم تغییر دادند (دروازهٔ `widget_visibility`، F-11) که از حالت ضبط و sheet جداست. matrix ویجت پس از تغییر آن‌ها دوباره اجرا نشد، چون browser jobها نباید هم‌زمان اجرا شوند.
- **اجرای دوباره (همان شب، به درخواست مالک) — انجام شد:**
  - با اجازهٔ مالک، از جلسه‌های T15 و T73 خواسته شد browser job تازه‌ای شروع نکنند. هر دو پس از بازگرداندن fixtureهایشان مکث کردند، و در پایان «done» گرفتند.
  - اجرای اول 423/2 بود:
    - شکست اول نقص آزمون بود: `settle()` ثابت 250ms کوتاه‌تر از انیمیشن پنل بود. حالا تا پایان انیمیشن‌ها صبر می‌کند.
    - شکست دوم **F-26، نقص محصول** بود: دکمهٔ «آخرین پیام» (`#spw-jump`) وقتی focus داشت پنهان می‌شد، و focus به `<body>` و بیرون از پنل modal می‌افتاد. این **ریشهٔ شکست گذرای «Tab از full screen بیرون رفت» در T13** است. `syncJump()` اکنون focus را پیش از پنهان کردن به composer می‌دهد. check قطعی تازه در matrix و یک check قرارداد در render integration اضافه شد.
  - `timeout 590` اجرای اول را پیش از دو restore پایانی قطع کرد. fixture بی‌درنگ با `restore` خودش برگشت، و در اجراهای بعدی دیگر سقف زمانی گذاشته نشد.
  - اجرای دوم 428/1 بود: زمان ضبط در `fa_IR main 390` یک بار در 3 ثانیه عوض نشد. در دو اجرای `fa_IR main` با poll فاصله‌دار (194/0 هر دو) تکرار نشد، و ریشه‌اش ثابت نشد.
  - **نتیجهٔ نهایی:** matrix **429 PASS / 0 FAIL** (هر هفت restore اجرا شد)، render integration **97/0**، smoke **8/8** (`test-artifacts/gates/20260918-230345-smoke`)، preflight 23:04 تمیز.
  - **برای T17:** دکمهٔ شناور «آخرین پیام» روی لبهٔ فیلدهای کارت انتقال می‌افتد (`t13-followup-rerun/fa_IR/widget-fullscreen-1440.png`).
- **اسناد:** plan S3.3 (ردیف‌های F-17 و F-25، سطر voice، دو اختلاف عمدی، و بخش «پیگیری T13 به درخواست مالک»)، گزارش اجرای S3.3، رودمپ (خط مبنا، جزئیات T13 و ژورنال)، tracker، `docs/technical/ANSWER_DESK_FA.md`، `docs/technical/QUALITY_AND_RELEASE_FA.md` و یک یافته برای G14 و G19 در `GUIDE_PLAN_FA.md`. commit انجام نشد.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۸ — T14 بسته شد؛ **بستهٔ S3.3 (Front Chat Widget) تمام شد**

- **درخواست مالک:** «T14». وابستگی T13 تکمیل بود؛ preflight 18:08 با WARNINGS 0 و BLOCKERS 0.
- **این task کد را تغییر نداد.** هیچ فایل `includes/`، `assets/`، `tests/`، `languages/` یا `scripts/` عوض نشد؛ خروجی فقط سند و شواهد است.
- **گیت‌ها:**

  | گیت | نتیجه | شاهد |
  |---|---|---|
  | `run-gates.ps1 -Tier full` | **9/9** و exit 0، در **دو اجرای مستقل با شمارهای یکسان**: php-lint 232/232، node-check 39/39، PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit 149 / 514، menu 5/0، assets 20/0، render 18/0، Phase 1 45/0 | `test-artifacts/gates/20260918-181045-full`، `test-artifacts/gates/20260918-180938-full` |
  | browser matrix دائمی ویجت | **385 PASS / 0 FAIL** و `MATRIX RESULT PASS`؛ تفکیک دقیقاً برابر T13: en_US 193 (main 173، auto 4، quiet 16) و fa_IR 192 (172، 4، 16) | `test-artifacts/front-chat-widget/20260918-t14/matrix-t14.txt`، تصویرها در `test-artifacts/front-chat-widget/t14/<locale>/` |
  | render integration | **92/0** (برابر T13) | `…/20260918-t14/render-integration.txt` |
  | پاک‌سازی | fixture پس از هر شش ترکیب و دوباره در `finally` برداشته شد؛ preflight پایانی `settings_stored=False`، locale `en_US`، WARNINGS 0 و BLOCKERS 0 | `…/20260918-t14/preflight-final.txt` |

- **دو اجرای full عمدی نبود و پنهان نشده است:** فرمان نخست از راه Bash فرستاده شد و pipeline آن (`| Select-Object`) در Bash معتبر نبود، ولی خود `run-gates.ps1` کامل اجرا شد و تمام شد؛ پوسته با 127 برگشت، پس فرمان با ابزار PowerShell تکرار شد. بخش‌های سایت‌لمس‌کنندهٔ دو اجرا هم‌پوشانی نداشتند (اولی 18:11:53 تمام شد، دومی از 18:12:51 به سایت رسید) و خروجی‌ها عیناً یکسان‌اند. **درس:** فرمان PowerShell را با ابزار PowerShell بفرستید؛ Bash فقط بخش‌های ابتدایی را اجرا می‌کند و سپس شکست می‌خورد، که ممکن است یک اجرای سنگین نامرئی بسازد.
- **شکست گذرای T13** (خروج focus با Tab از full screen در en_US 1440) در این اجرا هم تکرار نشد؛ مجموع اجراهای بدون تکرار به ده رسید. حذف نشده و در بخش «محدودیت‌های باز» گزارش اجرا مانده است؛ اگر در T15 یا T17 دیده شد، پیام شکست خودِ check مسیر کامل focus و عنصر مقصد را چاپ می‌کند.
- **دو یافتهٔ باقی‌مانده با تصمیم مستند بسته شدند، نه با کد:**
  - **F-11:** استثنای نمایش ویجت (`widget_visibility`، `exclude_pages`، `exclude_cats`، `exclude_types`) هیچ فرم مدیری ندارد، و کلید پیش‌فرض `exclude_ids` با خوانندهٔ `ChatWidget.php` (`widget_exclude_ids`) هم‌نام نیست، پس این دو هرگز به هم نمی‌رسند. طبق قاعدهٔ حفظ حذف نشد؛ رابط هیچ ادعایی دربارهٔ این قابلیت نمی‌کند، پس UI جعلی هم نیست. ناهم‌نامی کلید در **T21** (فهرست uninstall) دیده می‌شود.
  - **F-12:** دوازده کلید پیش‌فرض بدون نویسنده و خواننده (`btn_size`، `btn_shape`، `btn_color`، `btn_icon`، `btn_animation`، `btn_offset_x`، `btn_offset_y`، `window_border_radius`، `window_accent_color`، `window_header_color`، `window_font_size`، `window_shadow`) نام‌به‌نام در گزارش ثبت شدند، برای **T21**.
- **سند تازه:** [`docs/FRONT_CHAT_WIDGET_S3_3_EXECUTION_REPORT_FA.md`](docs/FRONT_CHAT_WIDGET_S3_3_EXECUTION_REPORT_FA.md) — نتیجه، تغییرات فنی و UI، **۱۹ اختلاف عمدی با موکاپ**، قابلیت‌های افزونه بدون UI موکاپ، محدودیت‌های باز و صادقانه (شامل F-11، F-12، بی‌hook بودن `includes/Frontend/WidgetRenderer.php` و `assets/frontend/chat-widget.js|css`، نبود انتخاب کانال تا T61، و شکست گذرای بازتولیدنشده)، جدول شواهد و خروجی visual QA.
- **اسناد دیگر:** plan S3.3 (ردیف S3.3.7 تکمیل، F-11 و F-12 بسته، بخش «شواهد T14»)، رودمپ (ردیف T14 ← تکمیل، جزئیات، دو سطر خط مبنا، دو سطر ژورنال)، tracker (ردیف S3.3 ← **تکمیل** و لاگ تاریخ‌دار)، `docs/technical/ANSWER_DESK_FA.md` (اشاره به بسته شدن بسته و مرجع گزارش) و `docs/user-guide/GUIDE_PLAN_FA.md` (یافتهٔ تازه برای G13، G14، G15 و G19). commit انجام نشد.
- **خلاصهٔ آنچه S3.3 عوض کرد (برای مرورهای بعدی):** ویجت دیگر روی هر بارگذاری هر صفحه خودش باز نمی‌شود و پیش از باز شدن پنل هیچ درخواست RESTی نمی‌فرستد (F-01، F-15)؛ `POST /public/voice` دروازهٔ `widget.voice_enabled` را **پیش از rate limiter** دارد (F-09)؛ 400 `empty` دیگر `answer` نمی‌فرستد (F-14، تغییر قرارداد REST)؛ Markdown با DOM امن رندر می‌شود (F-13)؛ کارت انتقال بدون ادعای اپراتور یا زمان پاسخ است و در نبود کانال آماده اصلاً وجود ندارد (F-10، F-20 تا F-22)؛ صدا transcript-first ماند و سه فاز واقعی را برای صفحه‌خوان اعلام می‌کند (F-16، D-02)؛ قفل scroll و focus trap full screen درست شدند (F-08، F-18)؛ و کنتراست متن کمکی از 2.26:1 به دست‌کم 4.95:1 رسید (F-23 تا F-25).
- **قدم بعدی:** `/aisa-next T56` — تنها ردیف باز S6: browser matrix واقعی روی IA هشت‌صفحه‌ای (en_US و fa_IR، دسکتاپ و 390px) با بازبینی تصویری. پس از آن `/aisa-next T29` که **به D-13 نیاز دارد** (S5-A پیش از فاز ۴ اجرا شود یا پس از انتشار)؛ اگر مالک S5-A را عقب بیندازد، T15، T18 و T19 بدون هیچ تصمیم بازی اجراپذیرند. هشدار هم‌زمانی: هر browser run یا gate tier تک‌تک اجرا شود.
## به‌روزرسانی ۲۰۲۶-۰۹-۱۸ — T13 بسته شد (browser matrix دائمی ویجت)

- **درخواست مالک:** «T13». وابستگی‌های T09، T11 و T12 تکمیل. preflight 12:21 یک BLOCKER داشت (پاسخ ندادن `wp-login.php` در مهلت preflight)؛ probe دوباره با مهلت ۶۰ ثانیه 200 داد، پس گذرا بود.
- **آزمون دائمی تازه (جانشین fixture و check‌های موقت T07 تا T12 بیرون از repo):**
  - `tests/local_front_chat_widget_browser_fixture.php` — `prepare`/`restore`؛ snapshot کامل `aisaautopilot_settings` و `WPLANG` پیش از هر تغییر در `aisaautopilot_front_chat_widget_browser_fixture` (الگوی نام همان که `wp-probe.php` می‌شناسد)؛ restore بدون snapshot بی‌اثر. سناریو با `AISAAUTOPILOT_FRONT_WIDGET_SCENARIO`: `main` (کانال ایمیل آماده با گیرندهٔ `.invalid`، voice روشن، خواندن خودکار خاموش)، `auto` و `quiet` (بدون کانال و بدون voice). زبان با `AISAAUTOPILOT_BROWSER_LOCALE`.
  - `tests/front_chat_widget_browser_check.cjs` — بازدیدکنندهٔ خارج‌شده روی صفحهٔ اول قالب فعال؛ mock کامل `/widget/ask`، `/widget/handoff` و `/public/voice` و stub میکروفن و گفتار. تازه‌ها نسبت به check‌های موقت: keyboard از صفحه تا launcher، Tab و Shift+Tab در پنل با حلقهٔ focus مرئی برای هر کنترل، کنتراست WCAG برای 22 جفت متن و آیکون، هم‌لبه بودن عنوان و زیرعنوان، کامل دیده شدن placeholder هنگام ضبط، جهت composer.
  - `tests/front_chat_widget_browser_matrix.cjs` — `en_US,fa_IR` × `main,auto,quiet`؛ **حساب QA نمی‌سازد** چون ویجت سطح بازدیدکننده است.
- **matrix چهار نقص واقعی پیدا کرد که همین‌جا رفع شدند:**
  - **F-23 کنتراست:** متن کمکی کوچک بین 2.26 و 3.62 به 1 بود. `assets/frontend/widget.css`: توکن `--spw-faint` از `#9a9db8` به `#686b8a` (تم تیره از `#6b7280` به `#9098a6`)، و footnote و خط منبع که رنگ ثابت داشتند روی همین توکن. اکنون کمترین مقدار 4.95:1.
  - **F-24 هم‌ترازی:** عنوان پیش‌فرض لاتین با `dir="auto"` در سایت فارسی چپ‌چین و جدا از زیرعنوان بود. هم‌ترازی عنوان، زیرعنوان، متن nudge و پرسش ستون اکنون از `dir` ریشه می‌آید. تلاش نخست با `text-align: match-parent` در fa_IR 390 شکست خورد: Chrome آن را نمی‌شناسد.
  - **F-25 placeholder بریده:** «Listening… tap the mic again to stop» در composer یک‌خطی بریده می‌شد. `autoGrow()` در `assets/frontend/widget.js` کادر خالی را به اندازهٔ placeholder می‌گیرد و `setRecordingUi()` پس از عوض کردن placeholder آن را صدا می‌زند.
  - **تصمیم باز T10 (جهت composer):** Chrome کادر خالی `dir="auto"` را ltr حل می‌کند، پس `dir` ایستا گذاشته نشد؛ `autoGrow()` `dir="auto"` را فقط وقتی متن هست می‌گذارد.
- **F-17 بسته با تصمیم «قفل نمی‌شود»:** sheet موبایل modal نیست؛ `overscroll-behavior: contain` روی thread در matrix اثبات می‌شود.
- **گیت‌ها:**

  | گیت | نتیجه | شاهد |
  |---|---|---|
  | browser matrix | **385 PASS / 0 FAIL**، exit 0: en_US 193 (main 173، auto 4، quiet 16) و fa_IR 192 (172، 4، 16) | `test-artifacts/front-chat-widget/20260918-t13/matrix-run6.txt`، تصویرها در `test-artifacts/front-chat-widget/t13/<locale>/` |
  | تکرار main | سه اجرای دیگر، هرکدام 345/0 | `…/20260918-t13/flake-probe-1..3.txt` |
  | render integration | از 88/0 به **92/0** | `…/20260918-t13/render-integration.txt` |
  | integrationهای مرتبط | lifecycle 6/0، voice page 4/0، voice settings 5/0، widget page 4/0، widget settings 4/0، customise 12/0، handoff flow 21/0، usage cap response 6/0 | `…/20260918-t13/related-integrations.txt` |
  | smoke (شامل static) | 8/8: php-lint 232/232، node-check 39/39، PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit 149 / 514، menu 5/0، assets 20/0، render 18/0 | `test-artifacts/gates/20260918-131657-smoke` |
  | پاک‌سازی | «Front chat widget browser fixture removed.» پس از هر ترکیب و در `finally`؛ preflight پس از browser و در پایان WARNINGS 0 و BLOCKERS 0، `settings_stored=False`، locale `en_US` | `…/20260918-t13/preflight-after-browser.txt`، `preflight-final.txt` |

- **شکست گذرای باز:** در اجرای چهارم، یک بار در en_US 1440 focus با Tab از پنل full screen بیرون رفت. در پنج اجرای بعدی تکرار نشد و ریشه‌اش پیدا نشد. check اکنون مسیر کامل focus و مقصد را در پیام شکست چاپ می‌کند؛ اگر در T14 یا T17 تکرار شد، از همان پیام شروع کنید (مظنون‌ها: عنصری که `trapFocus()` به‌خاطر `offsetParent` یا `hidden` از فهرست خود کنار می‌گذارد ولی مرورگر focusپذیر می‌داند).
- **ترجمه:** رشتهٔ نمایشی تازه‌ای افزوده نشد؛ PO و MO تغییر نکردند.
- **اسناد:** رودمپ (ردیف، جزئیات، دو سطر خط مبنا و ژورنال)، plan S3.3 (S3.3.6 تکمیل، F-17 بسته، F-23 تا F-25، سه اختلاف عمدی تازه، بخش «شواهد T13»)، `docs/technical/ANSWER_DESK_FA.md` (جهت و اندازهٔ composer، توکن کنتراست، F-17، آزمون مرورگر)، `docs/technical/QUALITY_AND_RELEASE_FA.md` (ردیف matrix تازه)، `docs/user-guide/GUIDE_PLAN_FA.md` (DG-01 **بسته**، G14 نیاز به به‌روزرسانی پس از T13، یک یافته). tracker در T14 به‌روز می‌شود. commit انجام نشد.
- **برای T17:** همان رنگ‌های کم‌رنگ (`#a9abc4`، `#9a9db8`، `#7e81a3`) در `assets/admin/aisaautopilot-ui.css` هم هست و کنتراست‌شان سنجیده نشده.
- **قدم بعدی:** `/aisa-next T14` — `run-gates.ps1 -Tier full`، به‌روزرسانی plan S3.3، `docs/FRONT_CHAT_WIDGET_S3_3_EXECUTION_REPORT_FA.md` (با F-11، F-12 و فهرست اختلاف‌های عمدی)، ردیف tracker S3.3 ← تکمیل و بازنویسی handoff برای فاز ۴. T56 هنوز باز است؛ هیچ browser run یا gate tier هم‌زمان با آن اجرا نشود.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۸ — T12 بسته شد (voice صادقانه در ویجت)

- **درخواست مالک:** «T12». وابستگی T10 تکمیل و D-02 («overlay ساخته نمی‌شود») از ۲۰۲۶-۰۹-۱۷ تأییدشده بود. preflight 02:03 با WARNINGS 0 و BLOCKERS 0.
- **کد:**
  - `includes/Api/PublicChatController.php` — **F-09:** متد تازهٔ `is_voice_enabled()` و یک دروازهٔ 403 `voice_disabled` در `transcribe()`، **پیش از rate limiter**. تا پیش از این، route فقط `answer_desk_enabled()` را می‌سنجید، پس سایتی که میکروفن ویجت را خاموش کرده بود همچنان برای هر فراخوان ناشناس صدا را به provider می‌فرستاد و هزینه می‌داد. ترتیب عمدی است: دروازهٔ «این قابلیت اصلاً روشن است؟» نباید سهمیهٔ بازدیدکننده را بسوزاند.
  - `includes/Frontend/ChatWidget.php` — **F-16:** ناحیهٔ تازهٔ `<p class="spw-live" id="spw-voice-status" role="status" aria-live="polite" data-spw-voice-status>` در composer، که **فقط وقتی `voice_enabled` روشن است** رندر می‌شود.
  - `assets/frontend/widget.css`: کلاس `.spw-live` (بصری پنهان با `clip-path`، ولی نه `display:none` — ناحیهٔ پنهان‌شده اصلاً اعلام نمی‌شود).
  - `assets/frontend/widget.js` — **F-16:** `setVoiceStatus()` تازه و تنها نویسندهٔ ناحیه. سه فاز واقعی اعلام می‌شوند: ضبط (همان رشتهٔ `recording` که placeholder هم نشان می‌دهد، پس دیده و شنیده یکی است)، `transcribing`، و `transcriptReady` که صریح می‌گوید «هنوز چیزی ارسال نشده است». هر شش مسیر خطا ناحیه را خالی می‌کند (خودِ خطا در `role="log"` اعلام می‌شود) و `send()` هم هنگام ارسال عمدی آن را پاک می‌کند — جمعاً هفت بار پاک شدن.
  - `includes/Core/Plugin.php`: دو کلید i18n تازه، `transcribing` و `transcriptReady`. هر دو عمداً کوتاه‌تر از بلندترین کلید موجود انتخاب شدند تا هم‌ترازی `=>` و در نتیجه PHPCS دست‌نخورد.
  - PO و MO: ۳ msgid تازه با ترجمهٔ فارسی؛ MO با **2286** ترجمه از 2427 ورودی.
  - **سمت سرور جز همان دروازه دست نخورد:** `VoiceTranscriber`، سقف ۴۵ ثانیه، rate limit و شکل پاسخ‌ها همان‌اند.
- **D-02 اجرا شد:** هیچ overlay تمام‌صفحه‌ای ساخته نشد. زیرنویس زنده، barge-in، Mute، End، چیپ «auto-detected» و «Not recorded» هیچ‌کدام پشتوانهٔ backend ندارند و ساخته نشدند. این در plan و در `MOCKUP_FEATURE_GAP_REPORT_FA.md` (ردیف W-01، اکنون **بسته**) ثبت است.
- **آزمون:** `tests/wordpress_front_chat_widget_render_integration.php` از 76/0 به **88/0** (۱۲ check تازه: ناحیهٔ وضعیت در حالت روشن و خاموش، پنهان‌سازی بصری، دو رشتهٔ فاز، تنها نویسنده بودن `setVoiceStatus()`، هفت بار پاک شدن، نبود `send()` در `sendAudio()`، نبود overlay و `SpeechRecognition` و barge-in، قرارداد offset در `onboundary`، و 403 `voice_disabled` / 400 `invalid_audio` روی `/public/voice` با وارسی دست‌نخورده ماندن شمارندهٔ نرخ).
- **گیت‌ها:**

  | گیت | نتیجه | شاهد |
  |---|---|---|
  | check مرورگر موقت T12 | **126 PASS و 0 FAIL** در en_US و fa_IR × (voice-on 1440×960 و 390×844، read-aloud 1440، voice-off 1440) | `test-artifacts/front-chat-widget/20260918-t12/capture-t12.txt`، تصویرها در `test-artifacts/front-chat-widget/t12/` |
  | render integration | 88/0 | `…/20260918-t12/render-integration.txt` |
  | integrationهای مرتبط | ۹ suite سبز (voice settings 5/0، voice page 4/0، lifecycle 6/0، widget settings 4/0، widget page 4/0، handoff flow 21/0، customise 12/0، usage cap 6/0، Phase 1 45/0) | `…/20260918-t12/related-integrations.txt` |
  | static | 5/5: php-lint 231/231، node-check 37/37، PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit 149 / 514 | `test-artifacts/gates/20260918-022224-static` |
  | smoke | 8/8: menu 5/0، assets 20/0، render 18/0 | `test-artifacts/gates/20260918-022344-smoke` |
  | ترجمه | MO با 2286 ترجمه از 2427؛ `msgfmt --check` سالم | خروجی `scripts/compile-mo.php` |
  | پاک‌سازی | «T12 widget fixture removed.» پس از هر شش آماده‌سازی و دو بار در `finally`؛ preflight با `settings_stored=False` | `…/20260918-t12/preflight-after-browser.txt` |

- **هیچ صدایی از مرورگر بیرون نرفت و هیچ provider، کلید یا بودجه‌ای لمس نشد:** `getUserMedia`، `MediaRecorder`، `SpeechSynthesisUtterance` و `speechSynthesis` با `addInitScript` جایگزین شدند و `/public/voice` و `/widget/ask` با `page.route` پاسخ داده شدند. تنها فراخوان واقعی شبکه یک `POST /public/voice` **بدون صدا** در حالت voice-off بود، که دروازهٔ تازه پیش از هر جست‌وجوی کلید ردش کرد.
- **آنچه مرورگر اثبات کرد:** فشردن میکروفن هیچ درخواستی نمی‌سازد؛ کل جریان صوتی فقط یک `POST public/voice` است؛ transcript دست‌نخورده در کادر پیام می‌نشیند و focus به آن می‌رود؛ thread تا فشردن Send **هیچ نوبت بازدیدکننده‌ای ندارد**؛ ویرایش متن چیزی نمی‌فرستد؛ حین transcribe کادر پیام خالی می‌ماند (بدون زیرنویس زنده)؛ با voice خاموش نه میکروفن هست، نه ناحیهٔ وضعیت، و سرور هم 403 می‌دهد.
- **F-19 این بار در مرورگر اثبات شد:** با شلیک رویدادهای واقعی `onboundary` روی همان متنی که به موتور گفتار داده می‌شود، کلمهٔ اول، چهارم و آخر هرکدام **دقیقاً یک** span را روشن کردند، مرز `sentence` چیزی را تغییر نداد و `onend` همه را پاک کرد — در هر دو زبان.
- **اجرای نخست check مرورگر 122/4 بود:** هر چهار شکست در خود check بود، نه در محصول — برای وارسی «متن قابل ویرایش است»، check وسط کادر متن کلیک می‌کرد و مکان‌نما وسط transcript می‌نشست. مکان‌نما صریحاً به انتهای متن برده شد و هیچ سنجه‌ای ضعیف نشد.
- **اسناد:** plan S3.3 (ردیف S3.3.5 تکمیل، F-09، F-16 و F-19 بسته، ردیف اختلاف عمدی Voice، بخش «شواهد T12»)، رودمپ (ردیف، جزئیات، سه سطر خط مبنا و ژورنال T12)، tracker، `docs/technical/ANSWER_DESK_FA.md` (بخش تازهٔ «صدا (T12)»)، `docs/technical/REST_API_FA.md` (قرارداد کامل `/public/voice`)، `docs/technical/SECURITY_FA.md` (قاعدهٔ «ترتیب دروازه‌ها»)، `MOCKUP_FEATURE_GAP_REPORT_FA.md` (W-01 بسته) و یافتهٔ G14/G19 در `GUIDE_PLAN_FA.md`. commit انجام نشد.
- **باز و عمدی:**
  - فاز «در حال خواندن پاسخ با صدا» اعلام نمی‌شود، چون هم‌زمان با حرف زدن موتور گفتار یک اعلام صفحه‌خوان روی آن می‌افتد؛ F-16 هم فقط ضبط، transcribe و خطای میکروفن را می‌خواست.
  - مسیرهای مدیریتی `/chat/voice/transcribe`، `/forms/voice/transcribe` و `/efb/voice/transcribe` دروازهٔ `voice_enabled` ویجت را **نگرفتند**: آن‌ها به تنظیم ویجت بازدیدکننده ربطی ندارند.
  - F-17 (sheet موبایل بدون قفل scroll) همچنان برای T13 باز است.
- **fixture و check موقت:** `t12_widget_fixture.php` و `t12_capture.cjs` بیرون از repo در `test-artifacts/front-chat-widget/20260918-t12/`. fixture سه حالت دارد (`on`، `auto`، `off`)؛ fixture رسمی T13 می‌تواند T08 تا T12 را ادغام کند و این سه حالت را از همین‌جا بردارد.
- **قدم بعدی:** `/aisa-next T13` — browser matrix رسمی ویجت با سه فایل `tests/front_chat_widget_browser_matrix.cjs`، `tests/front_chat_widget_browser_check.cjs` و `tests/local_front_chat_widget_browser_fixture.php`، در `en_US` و `fa_IR` × 1440×960 و 390×844، با keyboard، focus، contrast، overflow و صفر console/pageerror/HTTP، و جفت‌کردن screenshotها با قاب‌های موکاپ. T56 هنوز در نشست دیگری است؛ browser run هم‌زمان با آن اجرا نشود.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۸ — بستهٔ S7 (پشتیبانی انسانی) باز شد — برنامه‌ریزی، بدون تغییر کد

- **درخواست مالک:** اختلاف‌های عمدی T11 به یک فرایند کاربرپسند تبدیل شوند و کل «اتصال به انسان» کامل شود: شش بستر قابل انتخاب (۱ پنل داخل سایت با اعلان پیامکی وقتی کسی آنلاین نیست، ۲ ایمیل، ۳ Telegram با نیروها و ساعت پاسخ‌گویی، ۴ WhatsApp Business، ۵ Slack، ۶ هر پلتفرم رایگان محبوب دیگر)، دسته‌بندی پشتیبانی (پشتیبانی، مالی، …) با حالت «همه / یکی / فقط یک نوع»، بررسی و ارجاع به فرد دیگر، پیوستن چند نفر به یک گفتگو، و **اعلام به بازدیدکننده که چه کسی وارد گفتگو شد** — و برای ایمیل، پیام مناسب به‌جای ادعای گفتگوی زنده. همچنین پنل پاسخگو در داشبورد وردپرس با جدول زمان‌بندی و سطح دسترسی‌ای که ادمین به نیرو می‌دهد.
- **نتیجهٔ این نشست:** سند اجرای کامل نوشته شد — `docs/HUMAN_SUPPORT_S7_AUDIT_AND_PLAN_FA.md`. **هیچ کد، تست، تنظیم، کانال یا ترجمه‌ای تغییر نکرد و هیچ گیتی لازم نبود.**
- **ممیزی وضعیت امروز (اندازه‌گیری‌شده، در سند با `file:line`):** `HandoffService::handoff()` فقط fan-out یک‌طرفه است؛ `GET /widget/handoff/status` تنها `enabled` برمی‌گرداند و هیچ وضعیتی از خود درخواست ندارد؛ `HandoffTelegram` فقط `sendMessage` دارد (نه webhook، نه inline keyboard)؛ هیچ نقش یا capability پشتیبانی وجود ندارد و همه‌چیز `manage_options` است؛ نیرو، شیفت و دپارتمان اصلاً در مدل داده نیستند. زیرساخت اسرار رمزشده، rate limit، صندوق و بایگانی سالم است و حفظ می‌شود.
- **تصمیم معماری:** **thread سایت منبع حقیقت است** و سرویس بیرونی فقط نما روی آن. قرارداد `SupportChannelInterface` با دوازده capability (`notify`، `claim`، `reply`، `assign`، `join`، `thread`، `presence`، `identity`، `close`، `attach`، `typing`، `native_handoff`) قاعدهٔ «بدون UI جعلی» را مکانیکی می‌کند: کنترلی که کانال قابلیتش را ندارد اصلاً رندر نمی‌شود.
- **ماتریس واقعی سرویس‌ها در سند:** پنل داخل سایت کامل؛ Slack کامل‌ترین سرویس بیرونی (تنها منبع «آنلاین» واقعی با `users.getPresence`)؛ Telegram پذیرش/ارجاع/پاسخ دوطرفه با inline keyboard و webhook دارای secret token ولی **بدون presence**؛ Discord فقط دکمه‌ها و `/reply` چون Gateway در وردپرس ممکن نیست؛ WhatsApp دو حالت (click-to-chat رایگان و Cloud API پولی با پنجرهٔ ۲۴ ساعته)؛ ایمیل و پیامک فقط اعلان.
- **صف و تصمیم‌ها:** T57 تا T72 و D-26 تا D-32 در `PRODUCT_COMPLETION_ROADMAP_FA.md` ثبت شدند. T37، T38 و T46 حذف نشدند و طبق قاعدهٔ ۶ فقط به ردیف تازه ارجاع دارند (T62، T61 و T65 تا T69).
- **محیط QA عمومی — تصمیم مالک در همین نشست (D-29):** Telegram، Slack، Discord و WhatsApp نشانی HTTPS عمومی لازم دارند و `http://127.0.0.1/wp` ندارد. مسیر تأییدشده: **نسخهٔ اول به‌صورت ZIP یک اپ Node.js روی Hostinger مستقر می‌شود تا زیرساخت و دامنهٔ موقت ساخته شود، و پس از آن داده‌ها و فایل‌ها از راه SSH به‌روز می‌شوند.** قواعد: محیط آزمایشی است نه سایت مالک، دادهٔ واقعی بازدیدکننده در آن نمی‌ماند، اعتبارنامه‌های واقعی را فقط خود مالک می‌گذارد، و **هیچ رمز یا کلیدی در هیچ سندی نوشته نمی‌شود** (دامنه و مسیر بله، رمز نه). تا آماده شدن این محیط، T65 تا T68 در صف `مسدود`اند. جزئیات: بخش ۱۱.۱ سند S7.
- **اسناد به‌روزشده:** `HUMAN_SUPPORT_S7_AUDIT_AND_PLAN_FA.md` (تازه)، `PRODUCT_COMPLETION_ROADMAP_FA.md` (صف، تصمیم‌ها، جزئیات task، ژورنال)، `MOCKUP_REDESIGN_TASK_TRACKER_FA.md` (فاز ۶ و لاگ)، `FRONT_CHAT_WIDGET_S3_3_AUDIT_AND_PLAN_FA.md` (اختلاف‌های عمدی به ردیف‌های S7 وصل شدند)، `docs/technical/ANSWER_DESK_FA.md` (اشارهٔ صریح «برنامه، نه وضعیت فعلی») و `docs/user-guide/GUIDE_PLAN_FA.md`.
- **بعدی:** پاسخ مالک به **D-26** (هستهٔ S7 در ۱.۱.۰ یا کل بسته پس از انتشار) و **D-27** (ادغام T37/T38/T46). تا آن زمان صف اصلی دست‌نخورده است و task اجراپذیر بعدی همچنان **T12** است.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۷ — T11 بسته شد (کارت انتقال به انسان در ویجت)

- **درخواست مالک:** «T11». وابستگی T10 تکمیل بود. preflight 22:24 یک blocker گذرا داشت (`wp-login.php` با مهلت پیش‌فرض timeout شد)؛ با مهلت ۶۰ ثانیه 200 داد و کار ادامه یافت. preflight پایانی: WARNINGS 0، BLOCKERS 0.
- **کد:**
  - `assets/frontend/widget.js`:
    - **F-10:** `syncChips()` تازه تنها نویسندهٔ `#spw-chips.hidden` است: `isProcessing || qs('#spw-msgs .spw-handoff:not(.is-sent)')`. یعنی chipهای نوشتهٔ مدیر هم هنگام نوشتن پاسخ و هم تا وقتی کارت انتقال منتظر تصمیم بازدیدکننده است کنار می‌روند، و با «Not now» یا پس از ارسال برمی‌گردند (موکاپ در حالت Handoff هیچ chipی ندارد).
    - **F-20 (تازه، همین‌جا رفع شد):** رونوشتی که کارت به تیم می‌فرستد `textContent` حباب را می‌خواند؛ از T10 پاسخ به پاراگراف و فهرست تبدیل می‌شود و `textContent` مرز بلوک‌ها را ندارد، پس تیم «… answer to that yet.Would you like me to …» می‌گرفت. `addMessage()` همان خوانش سادهٔ پاسخ (که برای خواندن با صدا ساخته می‌شود) را روی گره نگه می‌دارد و `requestHandoff()` آن را می‌فرستد.
    - `sending`/`sent` از `i18n` خوانده می‌شوند، نه از کلیدهای تکراری بالادست.
  - `includes/Core/Plugin.php` — **F-22:** چهار کلید `handoffText`، `handoffHook`، `handoffSending` و `handoffSent` از دادهٔ localize حذف شدند: دو تای نخست را `widget.js` هرگز نمی‌خواند (برچسب و قلاب در markup کارت رندر می‌شوند) و دو تای دوم تکرار `i18n.sending` و `i18n.sent` بودند. سپس `phpcbf` روی همان یک فایل هم‌ترازی `=>` را برگرداند (حذف بلندترین کلیدها ۷ هشدار alignment ساخته بود).
  - `includes/Frontend/ChatWidgetSettings.php` — **F-21:** پیش‌فرض برچسب دکمهٔ انتقال از `Talk to human` به `Talk to a human` تغییر کرد تا با renderer یکی باشد (`handoff_text` کلید `widget_defaults()` نیست، پس نخستین ذخیرهٔ فرم متن دیگری از آنچه بازدیدکننده می‌دید ذخیره می‌کرد).
  - **سمت سرور دست نخورد:** `ChatWidgetController::handoff()`، `HandoffService`، صندوق، rate limit و شکل پاسخ‌ها همان‌اند.
- **آزمون:** `tests/wordpress_front_chat_widget_render_integration.php` از 62/0 به **76/0** (۱۴ check تازه: حالت not-ready، کانال تیک‌خوردهٔ ناتمام، یک قالب و دو دکمه در حالت ready، ناحیهٔ `role="status"`، نبود ادعای اپراتور و زمان پاسخ، یکی بودن برچسب پیش‌فرض، نبود کلیدهای handoff در دادهٔ localize، قاعدهٔ `syncChips()` و متن رونوشت). `tests/wordpress_answer_desk_lifecycle_integration.php` یک assertion داشت که وجود همان چهار کلید را می‌خواست؛ **تضعیف نشد، بلکه دقیق‌تر شد**: greeting و voiceName باید در دادهٔ اسکریپت باشند و برچسب و قلاب ذخیره‌شدهٔ مدیر باید در markup دیده شوند (6/0).
- **گیت‌ها:**

  | گیت | نتیجه | شاهد |
  |---|---|---|
  | check مرورگر موقت T11 | **140 PASS و 0 FAIL** در en_US و fa_IR × 1440×960 و 390×844 × دو حالت کانال (ready، not-ready) | `test-artifacts/front-chat-widget/20260917-t11/capture-t11.txt`، تصویرها در `test-artifacts/front-chat-widget/t11/` |
  | render integration | 76/0 | `…/20260917-t11/render-integration.txt` |
  | integrationهای مرتبط | ۱۱ suite سبز (usage cap 6/0، lifecycle 6/0، widget settings 4/0، widget page 4/0، handoff flow 21/0، voice settings 5/0، conversations 5/0، customise 12/0، handoff settings 10/0، handoff page 7/0، handoff inbox 19/0) | `…/20260917-t11/related-integrations.txt` |
  | static | 5/5: php-lint 231/231، node-check 37/37، PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit 149 / 514 | `test-artifacts/gates/20260917-225056-static` |
  | smoke | 8/8: menu 5/0، assets 20/0، render 18/0 | `test-artifacts/gates/20260917-225233-smoke` |
  | پاک‌سازی | «T11 widget fixture removed.» پس از هر چهار آماده‌سازی و در `finally`؛ preflight با `settings_stored=False` | `…/20260917-t11/preflight-after-browser.txt` |

- **هیچ ارسال واقعی رخ نداد:** هر دو مسیر `/widget/ask` و `/widget/handoff` در مرورگر با `page.route` پاسخ داده شدند، `/public/*` مسدود بود و کانال fixture نشانی `.invalid` داشت؛ integrationها `pre_wp_mail` و `pre_http_request` بسته دارند. هیچ ایمیل، پیامک، پیام Telegram یا ردیف صندوقی ساخته نشد.
- **اجرای نخست check مرورگر 16/1 بود:** شکست در خودِ check بود، نه در محصول — انتظار برای «خطای فیلد» نخستین `[data-spw-handoff-error]` را می‌پایید که جعبهٔ خطای فیلد **نام** است و هرگز پر نمی‌شود. selector به فیلدی که سرور نام می‌برد دقیق شد (`[data-spw-handoff-field="email"][aria-invalid="true"]`) و هیچ سنجه‌ای ضعیف نشد.
- **PO و MO تغییر نکردند:** هیچ رشتهٔ نمایشی تازه‌ای افزوده نشد و `Talk to a human` از پیش ترجمه داشت. msgid بی‌استفادهٔ `Talk to human` تا بازتولید POT در T18 در PO می‌ماند.
- **اسناد:** plan S3.3 (ردیف S3.3.4 تکمیل، F-10 بسته، F-20 تا F-22 تازه، دو اختلاف عمدی تازه، بخش «شواهد T11»)، رودمپ (ردیف، جزئیات و ژورنال T11)، tracker، `docs/technical/ANSWER_DESK_FA.md` (فهرست کلیدهای localize، رونوشت، دروازهٔ کارت، قاعدهٔ chip، تأیید صادقانه، برچسب پیش‌فرض و شمار 76) و یافتهٔ G14/G17 در `GUIDE_PLAN_FA.md`. commit انجام نشد.
- **باز و عمدی:** انتخاب و ترتیب کانال هنوز نیست (`HandoffService` به همهٔ کانال‌های آماده می‌فرستد — W-02، T37 و T38)؛ کارت ارسال‌شده پاک نمی‌شود و بازدیدکننده می‌تواند کارت تازه بخواهد (سقف واقعی ۵ انتقال در ساعت)؛ رونوشت عمداً از DOM خوانده می‌شود تا نوبتی که با «Try again» برداشته شده فرستاده نشود.
- **fixture و check موقت:** `t11_widget_fixture.php` و `t11_capture.cjs` بیرون از repo در `test-artifacts/front-chat-widget/20260917-t11/`؛ fixture رسمی T13 می‌تواند T08 تا T12 را ادغام کند (حالت `ready`/`off` کانال از همین‌جا بردارید).
- **قدم بعدی:** `/aisa-next T12` — voice صادقانه: دروازهٔ `voice_enabled` روی `/public/voice` (F-09)، اعلام `aria-live` وضعیت ضبط و transcribe (F-16)، وارسی F-19 در مرورگر، بدون overlay (D-02). T56 هنوز در نشست دیگری است؛ browser run هم‌زمان با آن اجرا نشود.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۷ — T10 بسته شد (گفت‌وگوی ویجت)

- **درخواست مالک:** «T10». وابستگی T08 تکمیل و D-24 تأییدشده بود؛ preflight 19:00 با WARNINGS 0 و BLOCKERS 0.
- **کد:**
  - `assets/frontend/widget.js`:
    - **F-15:** `getSettings()` حذف شد. `voiceEnabled` (تازه)، `voiceAuto` و `voiceName` از `AISAAutoPilotWidget` خوانده می‌شوند، پس بازدیدکننده‌ای که گفت‌وگو را باز نمی‌کند هیچ درخواستی نمی‌فرستد. route `GET /widget/settings` حفظ شده ولی ویجت آن را صدا نمی‌زند.
    - **F-13:** `renderAnswer()` زیرمجموعهٔ Markdown پاسخ (پاراگراف، فهرست، عنوان، bold، italic، کد درون‌خطی، پیوند) را فقط با DOM API می‌سازد؛ پیوند از `safeLink()` تازه می‌گذرد (فقط http/https). `addClarifyOptions()` گزینه‌های مدل را زیر پاسخ می‌گذارد و کلیک **فقط composer را پر می‌کند** (مرز D-24؛ chipهای نوشتهٔ مدیر همچنان با کلیک ارسال می‌شوند).
    - **F-07:** برچسب دکمه هنگام انتظار «Stop waiting for the reply» (فارسی «توقف انتظار برای پاسخ»)؛ پس از کلیک یادداشت `p.spw-note[data-spw-note="stopped"]` می‌گوید سایت ممکن است پاسخ را تمام کند ولی اینجا نشان داده نمی‌شود. `NO_RETRY` برای `rate_limited`، `usage_cap_reached`، `question_too_long`، `empty` و `widget_disabled` دکمهٔ «Try again» را برمی‌دارد.
    - **F-14:** فقط `data.ok && data.answer` نوبت دستیار است. **F-06:** هر حباب `dir="auto"`.
    - **F-19 (تازه، همین‌جا رفع شد):** برجسته‌سازی کلمه هنگام خواندن با صدا همهٔ spanها را با offset ثابت صفر می‌سنجید و فقط کلمهٔ اول روشن می‌شد. هر span اکنون `start`/`end` خود را در متن سادهٔ خوانده‌شده دارد. **در مرورگر سنجیده نشده** (T12).
  - `assets/frontend/widget.css`: reset `ul`/`ol`/`li`، استایل `.spw-md-*`، `.spw-code` (و تم تیره)، `.spw-clarify`، `.spw-note`.
  - `includes/Core/Plugin.php`: `voiceEnabled`؛ `voiceName` بدون پیش‌فرض ثابت «Google UK English Female» (ویجت پیش‌تر مقدار REST را می‌خواند که خالی بود)؛ رشته‌های `stopWaiting`، `stoppedWaiting`، `clarifyLabel` به‌جای `stop`.
  - `includes/Api/ChatWidgetController.php`: 400 `empty` اکنون `message` دارد، نه `answer` (**تغییر قرارداد REST**، ثبت در `REST_API_FA.md`).
  - `includes/Frontend/ChatWidget.php`: `dir="auto"` روی greeting، chip، پرسش ستون، عنوان و زیرعنوان header و متن nudge.
  - PO/MO: 3 msgid تازه؛ MO با 2283 ترجمه از 2424.
- **آزمون:** `tests/wordpress_front_chat_widget_render_integration.php` از 49/0 به **62/0**.
- **گیت‌ها:**

  | گیت | نتیجه | شاهد |
  |---|---|---|
  | check مرورگر موقت T10 | **106 PASS و 0 FAIL** در en_US و fa_IR × 1440×960 و 390×844 | `test-artifacts/front-chat-widget/20260917-t10/capture-t10.txt`، تصویرها در `test-artifacts/front-chat-widget/t10/` |
  | render integration | 62/0 (دو اجرا) | `…/20260917-t10/render-integration-run2.txt` |
  | integrationهای مرتبط | 8 suite سبز: usage cap 6/0، lifecycle 6/0، widget settings 4/0، widget page 4/0، handoff flow 21/0، voice settings 5/0، conversations 5/0، customise 12/0 | `…/20260917-t10/related-integrations.txt` |
  | static | 5/5: php-lint 231/231، node-check 37/37، PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit 149 / 514 | `test-artifacts/gates/20260917-193637-static` |
  | پاک‌سازی | «T10 widget fixture removed.» پس از هر locale و در `finally`؛ preflight با `settings_stored=False`، WARNINGS 0 و BLOCKERS 0 | `…/20260917-t10/preflight-after-browser.txt` |

- **اجرای نخست check مرورگر 102/4 بود:** هر چهار شکست سنجهٔ console بود که سطرهای «Failed to load resource» خود Chrome برای پاسخ‌های 503/429/400 **عمداً mockشده** را می‌شمرد. فیلتر فقط برای سطرهایی که `location().url` آن‌ها `/widget/ask` است افزوده شد؛ هیچ سنجهٔ دیگری ضعیف نشد. اجرای سوم فقط مکث 400ms پیش از capture گرفت (تصویر وسط انیمیشن ورود پیام بود).
- **اسناد:** plan S3.3 (F-06، F-07، F-13، F-14، F-15 بسته؛ F-19 تازه؛ S3.3.3 تکمیل؛ بخش «شواهد T10»)، رودمپ (ردیف و جزئیات T10، خط مبنا، ژورنال)، tracker، `docs/technical/ANSWER_DESK_FA.md` (بخش «گفت‌وگو (T10)»)، `REST_API_FA.md`، `ARCHITECTURE_FA.md` (قاعدهٔ پیشنهاد طبق D-24)، `SECURITY_FA.md` و یافتهٔ G14/G19 در `GUIDE_PLAN_FA.md`. commit انجام نشد.
- **باز و عمدی:** composer `dir="auto"` ندارد (متن لاتین در سایت فارسی راست‌چین؛ تصمیم در T13)؛ Markdown عمداً محدود (بدون کد fenced و جدول)؛ F-17 (sheet موبایل بدون قفل scroll) برای T13.
- **fixture و check موقت:** `t10_widget_fixture.php` و `t10_capture.cjs` بیرون از repo در `test-artifacts/front-chat-widget/20260917-t10/`؛ fixture رسمی ویجت در T13 می‌تواند T08 تا T10 را ادغام کند.
- **قدم بعدی:** `/aisa-next T11` (کارت handoff: F-10 پنهان شدن chipها، ready و not-ready، پیام تأیید صادقانه) یا `/aisa-next T12` (voice: F-09 دروازهٔ `voice_enabled` روی `/public/voice`، F-16 اعلام `aria-live`، وارسی F-19). T56 هنوز در نشست دیگر است؛ browser run هم‌زمان با آن اجرا نشود.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۷ — T09 بسته شد (حالت full screen ویجت)

- **درخواست مالک:** «T08 & T09». ردیف T08 از همان روز **تکمیل** بود، پس دوباره اجرا نشد و فقط T09 اجرا شد. preflight 13:41 با WARNINGS 0 و BLOCKERS 0.
- **کد (فقط دو فایل asset):**
  - `assets/frontend/widget.js`: `lockPageScroll()` تازه — مقدار inline `overflow` روی `html` و `body` پیش از قفل snapshot می‌شود و هنگام خروج از full، کلیک scrim یا بسته شدن پنل دقیقاً بازمی‌گردد؛ بدون snapshot، باز کردن قفل بی‌اثر است (F-08). `setFull()` هنگام ورود focus را داخل پنل نگه می‌دارد و هنگام خروج **فقط وقتی** کنترل focus‌دار ناپدید شده (ستون پرسش‌ها) یا focus روی صفحه رها شده باشد (کلیک scrim) به `#spw-expand` برمی‌گرداند — composer نیمه‌نوشته focus را نگه می‌دارد. `trapFocus()` اکنون `input` و `select` را هم می‌شمارد (کارت انتقال فیلد ورودی دارد) و Shift+Tab از خود پنل به آخرین کنترل می‌پیچد. آستانهٔ `matchMedia` از 900px به 768px هم‌تراز شد.
  - `assets/frontend/widget.css`: بلوک تازهٔ `@media (min-width: 641px) and (max-width: 768px)` طبق تصمیم breakpoint T07 — full screen تمام viewport، بدون scrim، بدون ستون 270px، با بازگشت chipها به thread.
- **یافتهٔ تازه F-18 که همین‌جا رفع شد:** `.spw-panel--full:has(.spw-side__list) .spw-chips { display: none }` با specificity ‏(0,4,0) بر قاعدهٔ بازگردانی chip در بلوک ≤640px با ‏(0,3,0) غلبه می‌کرد؛ یعنی در full screen موبایل نه ستون بود و نه chip و پرسش‌های ذخیره‌شدهٔ مدیر اصلاً دیده نمی‌شدند. هر دو بلوک کوچک اکنون همان الگوی `:has` را دارند.
- **یافتهٔ تازهٔ باز F-17 (برای T13):** در 640px و کمتر، صفحهٔ پشت sheet باز همچنان scroll می‌شود. قفل عمداً فقط برای full screen گذاشته شد چون گیت T09 همان را می‌خواست؛ تصمیم دربارهٔ sheet موبایل در T13.
- **آزمون:** `tests/wordpress_front_chat_widget_render_integration.php` از 41/0 به **49/0** رسید: ستون پرسش‌ها در سه حالت (با پرسش، بدون پرسش ولی با کانال آماده، بدون هیچ‌کدام)، بلوک breakpoint، شمار سه‌گانهٔ قاعدهٔ chip، آستانهٔ 768px در JS و قرارداد قفل scroll.
- **گیت‌ها:**

  | گیت | نتیجه | شاهد |
  |---|---|---|
  | check مرورگر موقت T09 | **114 PASS و 0 FAIL** در en_US و fa_IR × 1440×960، 768×900 و 390×844 | `test-artifacts/front-chat-widget/20260917-t09/capture-t09.txt`، تصویرها در `test-artifacts/front-chat-widget/t09/` |
  | render integration | 49/0 (دو اجرا) | `…/20260917-t09/render-integration-run2.txt` |
  | static | 5/5: php-lint 231/231، node-check 37/37، PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit 149 / 514 (بدون افت) | `test-artifacts/gates/20260917-135629-static` |
  | پاک‌سازی | «T09 widget fixture removed.» پس از هر locale و دوباره در `finally`؛ تنظیمات همچنان ذخیره‌نشده، `WPLANG` خالی، option fixture غایب؛ preflight WARNINGS 0 و BLOCKERS 0 | `…/20260917-t09/preflight-after-browser.txt` |

- **اندازه‌های مرورگر:** 1440 قاب 940×760 با گوشهٔ 22px و scrim `block`؛ 768 پنل 768×900 با گوشهٔ 0 و scrim `display: none` و ستون `none` و 3 chip؛ 390 دکمهٔ expand `display: none`. `html`/`body` هر دو `hidden` و تلاش scroll صفر پیکسل، و پس از هر سه راه خروج دقیقاً به `""` برگشت. ۲۴ بار Tab و یک Shift+Tab از پنل بیرون نرفت. برچسب دکمه در فارسی «تمام‌صفحه» ↔ «خروج از تمام‌صفحه».
- **اسناد:** plan S3.3 (F-08 بسته، F-17 و F-18 تازه، جدول breakpoint، بخش «شواهد T09»، دو اختلاف عمدی تازه)، رودمپ (ردیف و جزئیات T09، سه سطر خط مبنا، ژورنال)، tracker (S3.3 و لاگ)، `docs/technical/ANSWER_DESK_FA.md` (بخش تازهٔ «حالت full screen») و یافتهٔ G14/G15 در `GUIDE_PLAN_FA.md`. هیچ رشتهٔ نمایشی تازه‌ای نبود، پس PO و MO دست‌نخورده‌اند. commit انجام نشد.
- **fixture و check موقت:** `t09_widget_fixture.php` و `t09_capture.cjs` بیرون از repo در `test-artifacts/front-chat-widget/20260917-t09/`. fixture رسمی ویجت همچنان در T13 ساخته می‌شود و می‌تواند همین دو را ادغام کند.
- **قدم بعدی:** `/aisa-next T10` — گفت‌وگو: رفتار chip طبق D-24، `dir="auto"` (F-06)، Stop صادقانه و 429 بدون retry فوری (F-07)، `clarify_options` و Markdown امن (F-13)، F-14 و درخواست settings فقط پس از باز شدن (F-15). T56 هنوز باز است و در نشست دیگری دنبال می‌شود؛ browser run هم‌زمان با آن اجرا نشود.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۷ — تصمیم‌های مالک و T08 بسته شد (launcher و panel ویجت)

- **درخواست مالک:** «پیشنهادت خوودت را انجام بده فعلا»، پس از گزارش T07. ثبت در رودمپ:
  - D-25: گزینهٔ الف، و `voice_auto` پیش‌فرض خاموش؛ اجرا در همین T08.
  - D-24: پرسش پیشنهادی با کلیک ارسال می‌شود؛ قاعدهٔ «فقط composer را پر کن» در `ARCHITECTURE_FA.md` در T10 به پیشنهاد AI محدود می‌شود.
  - D-02: overlay صوتی ساخته نمی‌شود؛ T12 transcript-first می‌ماند.
  - D-06: منتفی و بسته.
- **یافتهٔ مهم هنگام اجرا:** `Settings::update()` با هر ذخیره کل `widget_defaults()` را در option می‌نویسد. پس فقط عوض کردن پیش‌فرض روی سایت‌هایی که قبلاً چیزی ذخیره کرده‌اند اثری ندارد.
- **کد:**
  - `Settings`: پیش‌فرض‌های نسخهٔ 2 (`auto_open` 0، `voice_auto` false، 392×624، `panel_radius` 20، `defaults_revision` 2).
  - `Settings::migrate_widget_defaults()` در `Plugin::boot()`: فقط وقتی نشان بازبینی ذخیره نشده، و فقط مقدارهای قدیمیِ کلیدهای بدون نویسندهٔ UI (`auto_open` 5، `window_width` 380، `window_height` 550). `voice_auto` و `panel_radius` نویسندهٔ UI دارند و دست نمی‌خورند.
  - `Settings::widget_copy()`: عنوان، زیرعنوان، greeting و notice دست‌نخورده به زبان جاری. renderer، `AISAAutoPilotWidget`، `GET /widget/settings`، customiser و دو پیش‌نمایش مدیر از آن می‌خوانند.
  - `ChatWidgetSettings`: کنترل «Open the chat by itself» (Never، 5، 10، 20، 30، 60 ثانیه؛ سقف ذخیره 120). اگر متن ترجمه‌شدهٔ پیش‌فرض ذخیره شود، پیش‌فرض انگلیسی ذخیره می‌شود.
  - `widget.css`: نقطهٔ «آنلاین» حذف شد؛ `::before` ناحیهٔ کلیک دکمه‌های آیکنی را به 44px می‌رساند؛ `overflow: hidden` از `.spw-mic` برداشته شد؛ reduced motion حلقهٔ launcher را پنهان می‌کند. `aisaautopilot-ui.css`: نقطهٔ پیش‌نمایش customiser حذف شد.
  - PO و MO: 6 msgid تازه؛ MO با 2280 ترجمه از 2421.
- **آزمون:** تازه `tests/wordpress_front_chat_widget_render_integration.php`؛ `tests/browser_release_check.cjs` در بخش customise چهار check تازه گرفت. `tests/wordpress_answer_desk_menu_badge_integration.php` از T54 قرمز بود (انتظار 10 آیتم منو) و به 8 آیتم D-23 اصلاح شد.
- **گیت‌ها:**

  | گیت | نتیجه | شاهد |
  |---|---|---|
  | render integration | 41/0 در دو اجرا | `test-artifacts/front-chat-widget/20260917-t08/render-integration-run*.txt` |
  | integrationهای مرتبط | 11 suite با جمع 97/0؛ menu badge پس از اصلاح 6/0 | `…/related-integrations.txt`، `menu-badge-integration.txt` |
  | check مرورگر موقت T08 | 83 PASS و 0 FAIL در en_US و fa_IR، 1440 و 390 | `…/capture-t08.txt`، تصویرها در `test-artifacts/front-chat-widget/t08/` |
  | customise matrix | en_US 26/0 و fa_IR 29/0 | `…/widget-customise-matrix.txt` |
  | static | 5/5: php-lint 231/231، node-check 37/37، PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit 149 / 514 | `test-artifacts/gates/20260917-120906-static` |
  | full | 9/9: menu 5/0، assets 20/0، render 18/0، Phase 1 45/0، PHP diagnostic صفر | `test-artifacts/gates/20260917-122714-full` |
  | پاک‌سازی | fixtureها و کاربر QA حذف شدند؛ probe با 0 باقی‌مانده، تنظیمات ذخیره‌نشده و `WPLANG` خالی؛ preflight 0/0 | `…/preflight-after-browser.txt` |

- **اسناد:** plan S3.3 (یافته‌های F-01 تا F-05، S3.3.1، اختلاف‌های عمدی و بخش «شواهد T08»)، `docs/technical/ANSWER_DESK_FA.md`، `docs/technical/DATA_MODEL_FA.md`، رودمپ (D-02، D-06، D-24، D-25، T08، خط مبنا، ژورنال)، tracker، گزارش شکاف B-06، جدول وضعیت `EXTENDED_SURFACES_BRIEF.md` و یافتهٔ G13، G14 و G19 در `GUIDE_PLAN_FA.md`. commit انجام نشد.
- **fixture و check موقت:** `t08_widget_fixture.php` و `t08_capture.cjs` بیرون از repo در `test-artifacts/front-chat-widget/20260917-t08/` هستند. fixture رسمی ویجت همچنان در T13 ساخته می‌شود.
- **باز و عمدی:** F-15 (درخواست settings روی هر بارگذاری) در T10. ناحیهٔ کلیک chip، پیوند footnote و دکمه‌های کارت انتقال در T17 سنجیده می‌شوند.
- **قدم بعدی:** `/aisa-next T09` (full screen، breakpoint 768px، قفل scroll و focus trap) یا `/aisa-next T10` (گفت‌وگو با D-24). T56 هنوز منتظر است و در نشست دیگر دنبال می‌شود؛ browser run هم‌زمان با آن نشست اجرا نشود.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۷ — T54، T55 تکمیل؛ IA به هشت صفحهٔ اصلی + Form Studio بازآرایی شد

- **درخواست مالک:** دستور مستقیم در گفتگو (نه از مسیر رودمپ): «main pages» به هشت مورد تعریف شود — Overview، Setup & Connection، Integrations، Site Knowledge، Answer Desk، Store Copilot، Content Studio، Form Studio — و Usage & Cost/Health/Activity Log به تب زیر Overview بروند؛ اسم «Form Composer» نامناسب است، اسم بگرد و پیشنهاد بده؛ ساختار JSON فرم‌ها مثل Easy Form Builder باشد.
- **تصمیم ثبت‌شده:** D-23 در رودمپ. نام منتخب برای رتبهٔ صفحهٔ فرم: **Form Studio** (گزینه‌های دیگر: Form Copilot، Smart Forms — دلایل رد در `docs/OVERVIEW_FORM_STUDIO_S6_AUDIT_AND_PLAN_FA.md`).
- **کد:**
  - `OverviewNavigation` تازه (الگوی `ContentStudioNavigation`) نوار تب Overview/Usage & Cost/Health/Activity Log را بالای هر چهار صفحه رندر می‌کند.
  - `AdminMenu`: Usage/Connections/Logs از `$pages` به `$aliases` منتقل و در `ALIAS_OWNERS` به Overview نگاشت شدند؛ URL/slug/کلاس هرکدام دست‌نخورده.
  - `AdminMenu::SLUG_FORM_STUDIO` (`aisaautopilot-form-studio`) هشتمین آیتم visible؛ مستقیماً به `CopilotPage::render_forms()` (متد تازهٔ استخراج‌شده از بدنهٔ compose/review/result) می‌رود. لینک قدیمی `mode=forms` عیناً همان محتوا را نگه داشت؛ `ContentStudioNavigation::forms_url()` اکنون به مسیر تازه اشاره می‌کند.
  - رشتهٔ نمایشی «Form Composer» در هشت فایل PHP (`CopilotPage`، `IntegrationsPage`، `NativeFormsPage`، `SettingsPage`، `HomePage`، `EasyFormBuilderPage`، `CopilotController`، `ActivityTimeline`) به «Form Studio» تغییر کرد. شناسه‌های داخلی — کلید `form_composer_enabled`، id ماشینی `form-composer`، کلاس‌های CSS `sp-fc-*`، idهای `aisaautopilot-compose-screen`/`review-screen` — **عمداً دست‌نخورده** ماندند (همان الگوی «Content Studio» روی `SLUG_FORM_BUILDER`).
  - S6.2: بخش «قرارداد JSON فرم و سازگاری با Easy Form Builder» در `docs/technical/FORMS_CONTENT_STORE_FA.md` نوشته شد. یافته: `EfbFormConverter`/`EfbFormModel` از پیش دقیقاً همان شکل مسطح `form_structer` واقعی EFB را می‌سازند؛ محدودیت مسیر بومی (هفت نوع فیلد ساده) آگاهانه است، نه نقص.
- **تست‌ها:** `local_admin_menu_smoke.php` (فهرست ۸تایی)، `local_admin_assets_smoke.php` (+Form Studio)، و هفت integration (`wordpress_content_studio_navigation`، `wordpress_form_composer_page`، `wordpress_integrations_hub`، `wordpress_activity_timeline`، `wordpress_overview_metrics`، `wordpress_usage_cap_response`، `wordpress_form_proposal_context`) با IA/رشتهٔ تازه هم‌گام شدند.
- **گیت‌ها:** `run-gates.ps1 -Tier full` **9/9** — php-lint 230/230، node-check 37/37، PHPCS **134/134 با 0/0** (دو warning تراز `AdminMenu.php` با `phpcbf` رفع شد)، PHPStan 0، PHPUnit **149/514** (بدون افت)، menu-smoke 5/0، assets-smoke **20/0**، render-smoke 18/0، Phase 1 45/0. هفت integration مستقیم: **۶۱ چک، صفر شکست**. preflight پایانی WARNINGS 0 و BLOCKERS 0. — artifact: `test-artifacts/gates/20260917-093847-full`.
- **مستندات:** `docs/OVERVIEW_FORM_STUDIO_S6_AUDIT_AND_PLAN_FA.md` (سند کامل جدید)، `docs/technical/ADMIN_SURFACES_FA.md` (هشت صفحهٔ اصلی + چهار تب Overview + یازده alias)، `docs/technical/FORMS_CONTENT_STORE_FA.md`، رودمپ (T54/T55/T56، D-23) و tracker (S6.1–S6.3) به‌روز شدند.
- **باز مانده:** T56 (S6.3) — browser matrix واقعی (`en_US`/`fa_IR`، دسکتاپ/۳۹۰px) روی هر ۸ صفحه و ۴ تب تازه، با بازبینی تصویری. هیچ fixture یا کاربر QA در این نشست ساخته نشد (کاری جز رندر مستقیم PHP و اجرای گیت‌ها نبود)، پس preflight نیازی به cleanup نداشت.
- **بدون تغییر:** صف T07 تا T53 (نشست دیگری هم‌زمان روی T07 کار کرد؛ به AdminMenu.php این نشست دست نزد).

## به‌روزرسانی ۲۰۲۶-۰۹-۱۷ — T07 بسته شد؛ ممیزی و برنامهٔ S3.3

- **درخواست مالک:** «T07». وابستگی T06 تکمیل بود؛ preflight 09:06 با WARNINGS 0 و BLOCKERS 0.
- **نتیجهٔ اصلی:** بازطراحی موکاپ از پیش روی renderer واقعی (`ChatWidget` و `widget.js|css`) انجام شده است. baseline امروز در en_US و fa_IR با قاب‌های موکاپ هم‌خوان است. پس باقی S3.3 بستن ۱۶ یافتهٔ اندازه‌گیری‌شده و ساختن browser matrix است، نه ساختن دوباره.
- **سند تازه:** [`docs/FRONT_CHAT_WIDGET_S3_3_AUDIT_AND_PLAN_FA.md`](docs/FRONT_CHAT_WIDGET_S3_3_AUDIT_AND_PLAN_FA.md)، شامل:
  - تصمیم معماری و تأیید دوبارهٔ واقعیت فنی ۲۰۲۶-۰۹-۱۴ (ادعای «`aria-live` ندارد» کهنه بود)؛
  - قرارداد REST ویجت، نگاشت هر پنج حالت موکاپ و ابزارهای prototype؛
  - جدول نویسنده و مصرف‌کنندهٔ هر کلید تنظیم؛
  - تصمیم breakpoint: در 641 تا 768px، full screen تمام viewport را بگیرد؛
  - یافته‌های F-01 تا F-16 با task مقصد، و جدول ریزتسک S3.3.0 تا S3.3.7 با گیت.
- **مهم‌ترین یافته‌ها:**
  - **F-01:** `auto_open` پیش‌فرض 5 است و هیچ UI آن را نمی‌نویسد. ویجت روی هر بارگذاری هر صفحه پس از 5 ثانیه خودش باز می‌شود (در 640px و کمتر تمام صفحه) و nudge هرگز دیده نمی‌شود. ← D-25
  - پنل 380×550 به‌جای 392×624 موکاپ (F-02)؛ نقطهٔ سبز «آنلاین» بدون دادهٔ واقعی (F-03)؛ hit target 34 و 36px (F-04)؛ greeting و عنوان پیش‌فرض انگلیسی روی سایت فارسی (F-05).
  - `/public/voice` دروازهٔ `voice_enabled` ندارد (F-09)؛ `GET /widget/settings` روی هر بارگذاری هر صفحه فرستاده می‌شود (F-15)؛ `clarify_options` رندر نمی‌شود و Markdown خام نمایش داده می‌شود (F-13).
  - دوازده کلید پیش‌فرض ویجت (`btn_*` و `window_*`) نه نوشته و نه خوانده می‌شوند (F-12). استثنای صفحه UI ندارد و کلید شناسه ناهم‌نام است (F-11).
- **شواهد:**

  | مورد | نتیجه | مسیر |
  |---|---|---|
  | قاب‌های موکاپ | 28 تصویر: 4 حالت و 3 phase voice × LTR/RTL × 1440/390 | `test-artifacts/front-chat-widget/mockup/` |
  | baseline ویجت | 18 تصویر، 9 در هر locale؛ `direction` درست، تنها درخواست افزونه پیش از ارسال `GET widget/settings`، صفر console، pageerror و HTTP ≥ 400، overflow افقی 0 | `test-artifacts/front-chat-widget/baseline/`، `20260917-t07/capture-plugin.txt` |
  | پاک‌سازی | «T07 widget fixture removed.»، `aisaautopilot_settings` همچنان نبود، `WPLANG` همان رشتهٔ خالی، preflight با WARNINGS 0 و BLOCKERS 0 | `20260917-t07/preflight-after-capture.txt` |
  | بدون تغییر کد | mtime و اندازهٔ 279 فایل پیش و پس از task؛ تنها اختلاف `includes/Admin/AdminMenu.php` ساعت 09:24 از نشست هم‌زمان T54 است | `20260917-t07/code-mtime-before.txt`، `code-mtime-after.txt` |

- **fixture موقت:** `t07_widget_fixture.php` و `t07_capture.cjs` بیرون از repo‌اند (نسخه در `20260917-t07/`). fixture رسمی با همین الگو در T13 ساخته می‌شود: snapshot تنظیمات و `WPLANG` پیش از هر تغییر، و restore بدون snapshot بی‌اثر.
- **اسناد اصلاح‌شده:**
  - `docs/technical/ANSWER_DESK_FA.md`: ثبت نوبت در `/widget/ask` است نه `/widget/conversation`؛ notice زیر composer است؛ درخواست settings روی هر بارگذاری است؛ کلیدهای بدون UI فهرست شدند.
  - `MOCKUP_FEATURE_GAP_REPORT_FA.md`: ردیف B-06 ادعای UI برای باز شدن خودکار و استثناها را داشت که نادرست بود.
  - رودمپ (ردیف و جزئیات T07، D-02، D-24، D-25، ژورنال) و tracker (S3.3 ← در حال اجرا).
  - راهنمای کاربر تغییر لازم نداشت. commit انجام نشد.
- **تصمیم‌های باز مالک:**
  - **D-25 (لازم در T08):** پیش‌فرض `auto_open` به 0 برسد و کنترل آن اضافه شود؟ پیشنهاد: بله. پرسش فرعی: پیش‌فرض `voice_auto` هم `false` شود؟
  - **D-24 (لازم در T10):** پرسش پیشنهادی با کلیک ارسال شود؟ پیشنهاد: بله، و قاعدهٔ `ARCHITECTURE_FA.md` به پیشنهادهای AI محدود شود.
  - **D-02 (لازم در T12):** overlay voice؟ پیشنهاد: خیر.
- **هشدار محیط:** نشست دیگری هم‌زمان روی T54 کار می‌کند و رودمپ، tracker، `AdminMenu.php` و smokeهای منو را تغییر می‌دهد. پیش از هر ویرایش رودمپ فایل را تازه بخوانید. هیچ browser run یا gate را هم‌زمان با کار آن نشست اجرا نکنید: هر دو از حساب QA و تنظیمات مشترک استفاده می‌کنند.
- **قدم بعدی:** پاسخ D-25، سپس `/aisa-next T08`. T08 سه قسمت دارد: markup و CSS، `tests/wordpress_front_chat_widget_render_integration.php` و مقایسه با `mockup/widget-panel-ltr-1440.png`.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۷ — T06 بسته شد؛ S3.2 تمام است و S3.3 آغاز می‌شود

- **درخواست مالک:** «T06». وابستگی T05 تکمیل بود.
- **preflight 08:37:** WARNINGS 0 و **BLOCKERS 1**. `wp-login.php` در مهلت پاسخ نداد، با اینکه httpd و mysqld روشن بودند. T06 فقط سند است و گیتش به سایت وابسته نیست، پس اجرا شد. preflight پایانی همین نوبت `wp-login=200` با WARNINGS 0 و BLOCKERS 0 داد، پس کندی گذرا بود؛ جدول رودمپ پس از ویرایش 11/54 و next=T07 پارس شد. کندی سایت محلی تکرارشونده است: پیش از هر browser run در S3.3 preflight بگیرید.
- **آنچه ساخته شد:**
  - [`docs/EFB_COPILOT_S3_2_EXECUTION_REPORT_FA.md`](docs/EFB_COPILOT_S3_2_EXECUTION_REPORT_FA.md) با قالب S3.1: نتیجه؛ تغییرات فنی (قرارداد selected-field، رفع callback `/efb/log`، gate، لاگ اتمی، کاتالوگ 4.2.x، سبک step و progress)؛ تغییرات UI؛ هشت اختلاف عمدی با موکاپ؛ قابلیت‌های بدون UI موکاپ؛ محدودیت‌های باز؛ جدول شواهد و visual QA.
  - plan S3.2: S3.2.6 ← تکمیل و بخش «بستن بسته».
  - tracker: ردیف S3.2 ← تکمیل و لاگ ۲۰۲۶-۰۹-۱۷.
  - رودمپ: ردیف T06 ← تکمیل، وضعیت در جزئیات T06 و دو خط ژورنال.
- **شمارهای یکسان در هر چهار سند** (از T05؛ از آن زمان هیچ فایل کد یا آزمونی تغییر نکرده و این با mtime وارسی شد):

  | گیت | نتیجه | شاهد |
  |---|---|---|
  | full | 9/9 — php-lint 229/229، node-check 37/37، PHPCS 133/133 با 0/0، PHPStan 0، PHPUnit 149 / 514، menu 5/0، assets 19/0، render 18/0، Phase 1 45/0 | `test-artifacts/gates/20260916-214733-full` |
  | release | 60/60 — EFB Copilot contract 15/0، Logger concurrency 6/0، EFB details 7/0، usage cap EFB 2/0 | `test-artifacts/gates/20260916-215043-release` |
  | browser matrix EFB Copilot | `en_US` 76/0 و `fa_IR` 76/0 | `test-artifacts/efb-copilot/20260916-t04/` |
  | ترجمه | MO با 2274 ترجمه از 2415 msgid | `test-artifacts/efb-copilot/20260916-t03-reverify2/` |

- **آنچه عمداً انجام نشد:** خط زمینهٔ هدر («Contact form · 4 fields» در موکاپ) که T04 کاندید T06 دانسته بود ساخته نشد، چون T06 فقط سند است. در گزارش به‌عنوان اختلاف باز ثبت و برای مقایسهٔ بصری T16 گذاشته شد.
- **بدون تغییر:** کد، آزمون، PO و MO، `docs/technical/*` و راهنمای کاربر. commit انجام نشد.
- **قدم بعدی:** `/aisa-next T07` — ممیزی Front Chat Widget، تأیید دوبارهٔ واقعیت فنی ثبت‌شده در بخش S3.3 رودمپ، و ساخت `docs/FRONT_CHAT_WIDGET_S3_3_AUDIT_AND_PLAN_FA.md` با قالب plan S3.1 و S3.2. تصمیم D-02 (voice overlay) در T12 لازم می‌شود، نه در T07.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۶ — T05 بسته شد (regression کامل S3.2)

- **درخواست مالک:** «T05». وابستگی‌ها (T04 و T28) تکمیل بودند؛ preflight 21:46 با WARNINGS 0 و BLOCKERS 0.
- **نتیجه:** هیچ افتی نسبت به خط مبنا نیست و هیچ کدی تغییر نکرد.

  | گیت | نتیجه | شاهد |
  |---|---|---|
  | full (گیت T05) | **9/9**، exit 0 — php-lint 229/229، node-check 37/37، PHPCS 133/133 با 0/0، PHPStan 0، PHPUnit 149 / 514، menu 5/0، assets 19/0، render 18/0، Phase 1 45/0؛ PHP diagnostic صفر | `test-artifacts/gates/20260916-214733-full` |
  | release (پوشش integrationهای S3.2) | **60/60**، exit 0 — 51 suite؛ EFB Copilot contract 15/0، Logger concurrency 6/0، EFB details 7/0، usage cap EFB 2/0 | `test-artifacts/gates/20260916-215043-release` |
  | preflight پایانی | 21:55، WARNINGS 0 و BLOCKERS 0؛ fixture یا کاربر QA باقی نماند | `…-release/preflight-after.txt` |

- **آنچه اجرا نشد:** browser matrix EFB Copilot جزو گیت T05 نیست و دوباره اجرا نشد؛ آخرین نتیجه همان T04 است (76/0 در هر locale). از T04 به بعد `efb-copilot.js` تغییر نکرده است.
- **فایل‌های این نوبت:** رودمپ (ردیف، جزئیات T05، خط مبنا، ژورنال)، plan S3.2 (ردیف S3.2.6) و همین handoff. مستند فنی یا راهنمای کاربر تغییر لازم نداشت. commit انجام نشد.
- **قدم بعدی:** `/aisa-next T06` — plan S3.2، `docs/EFB_COPILOT_S3_2_EXECUTION_REPORT_FA.md` با قالب S3.1، ردیف tracker و بازنویسی handoff برای آغاز S3.3. عددهای report باید همین جدول باشد.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۶ — وارسی سوم T03 (بدون تغییر کد)

- **درخواست مالک:** «T03». ردیف از پیش تکمیل بود، پس کار دوباره انجام نشد. فقط گیت آن روی درخت امروز، پس از تغییر PO و MO در T30، دوباره اجرا شد.
- **نتیجه:** همه سبز. `php -l`، `node --check`، بدون رشتهٔ نمایشی hard-coded و بدون `innerHTML`، PHPCS `EfbBuilderButton.php` 0/0، ۸۷ رشته با ترجمهٔ فارسی (0 غایب و 0 خالی)، `msgfmt --check` با 2274 ترجمه و 141 بی‌ترجمه، MO هم‌خوان با PO (تنها تفاوت، header `POT-Creation-Date`)، و ChatEngine در `fa_IR` با 3/0.
- **اصلاح عدد:** «۸۹ فراخوانی `t()`» شمار سطرها بود. شمار واقعی فراخوانی 75 است.
- **شواهد:** `test-artifacts/efb-copilot/20260916-t03-reverify2/`. preflight: WARNINGS 0 و BLOCKERS 0.
- **قدم بعدی:** بدون تغییر، `/aisa-next T05`.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۶ — اطلاعات تماس انتقال، بایگانی صندوق و پرسش‌های بی‌پاسخ (T30)

- **دستور مالک:**
  - «قابل تنظیم باشد توسط ادمین که شماره موبایل و ایمیل و اسم و آدرس وب سایت یا چند مورد یا هیچ کدام را بگیرید» (D-20)؛
  - «درخواست های قدیمی قابلیت آرشیو داشته باشند» (D-21)؛
  - «اگر سوالی کاربر پرسید و ai نتونست جواب دهد به لیست سوالاتی که جواب پیش فرض برایش درنظر بگیرند و یا اینکه پیشنهاد برای ایجاد مطلب شود» (D-22).
- **۱. اطلاعات تماس در کارت انتقال:**
  - **مدیر:** بخش «Details to ask for» در tab Human handoff؛ برای نام، ایمیل، شمارهٔ موبایل و نشانی وب‌سایت یکی از Off، Optional و Required. پیش‌فرض همه Off است، پس رفتار سایت‌های فعلی عوض نمی‌شود. ذخیره با `POST /handoff/visitor-fields`.
  - **ویجت:** فیلدهای روشن در کارت با نوع و autocomplete درست، خطای زیر هر فیلد، و Enter برای ارسال.
  - **سرور:** `HandoffContact`؛ موردهای خاموش نادیده گرفته می‌شوند، رقم فارسی به لاتین تبدیل می‌شود، و وب‌سایت فقط `http` یا `https` است.
  - **ذخیره و ارسال:** ستون‌های تازهٔ `visitor_phone` و `visitor_website`؛ `Reply-To` در ایمیل؛ خط‌های تماس در Telegram و پیامک؛ پیوند `mailto:`، `tel:` و وب در کشوی صندوق.
  - «پاسخ با ایمیل» اکنون برای درخواست ویجت هم کار می‌کند، به شرط اینکه مدیر ایمیل را روشن کرده باشد.
- **۲. بایگانی صندوق:**
  - ستون `archived_at` (schema 2، اضافه‌شده با `dbDelta` روی جدول موجود).
  - فیلتر «Archived»، دکمهٔ «Archive» و «Move back to inbox» در کشو، و «Archive resolved» با confirm.
  - وضعیت حفظ می‌شود و گفت‌وگوی بایگانی‌شده در شمار منتظرها (نوار، حباب منو، نمای کلی) نیست.
  - ردیف‌های قدیمیِ دارای entity به‌جای مهاجرت با همین بایگانی کنار می‌روند.
- **۳. پرسش‌های بی‌پاسخ (T30 بسته شد):**
  - **فهرست:** دلیل هر پرسش (چهار نقطهٔ ChatEngine)، «Asked N times»، فیلتر «Last 7 days».
  - **Write it:** Content Studio › Write را با brief و `source_gap` باز می‌کند. پیش‌نویس پس از تأیید به پرسش پیوند می‌خورد و «Open draft» نشان داده می‌شود.
  - **Answer it:** پاسخ ذخیره‌شده می‌سازد. `ChatEngine` پیش از هر درخواست AI آن را برای همان پرسش، با تفاوت حروف، فاصله، نشانه‌گذاری، نیم‌فاصله، «ي/ك» و رقم فارسی، برمی‌گرداند. جور شدن معنایی عمداً ساخته نشد و رابط این را صریح می‌گوید.
  - **Saved answers:** کارت با Edit، Delete و Add an answer. هر دو کارت در حالت خالی دانش سایت هم نشان داده می‌شوند.
  - **نوشتن هم‌زمان:** `KbGaps` و `SavedAnswers` با `Support\OptionLock` می‌نویسند.
- **اصلاح‌های کناری که در همین کار پیدا شدند:**
  - `answer-handoff.js` برچسب وضعیت کانال («Paused»، «Ready»، «Edit»…) را پس از هر تغییر در fa_IR انگلیسی می‌گذاشت؛ اکنون از رشته‌های ترجمه‌شده می‌آید.
  - toast پس از بایگانی روی دکمهٔ «Move back to inbox» می‌افتاد.
  - پاسخ REST برچسب‌های دلیل و شمار را به زبان سایت می‌ساخت. `_locale=user` همراه سرآیند JSON لازم بود؛ دام در `QUALITY_AND_RELEASE_FA.md` ثبت شد.
  - `uninstall.php` option پاسخ‌های ذخیره‌شده را هم حذف می‌کند.
- **گیت‌ها:**

  | گیت | نتیجه |
  |---|---|
  | static (`20260916-203850-static`) | 5/5 — php-lint 229/229، node-check 37/37، PHPCS 133/133 با 0/0، PHPStan 0، PHPUnit **149 / 514** |
  | release (`20260916-204545-release`) | **60/60** با 51 integration suite؛ تازه `wordpress_kb_gaps_write_it_integration` 19/0؛ flow 21/0، inbox 19/0، handoff settings 10/0 |
  | `handoff_inbox_browser_matrix` | en_US و fa_IR، 58 PASS، 0 FAIL |
  | `answer_handoff_browser_matrix` (تازه) | en_US و fa_IR، 66 PASS، 0 FAIL؛ کارت ویجت روی صفحهٔ عمومی با `/widget/handoff` ساختگی |
  | `kb_answers_browser_matrix` (تازه) | en_US و fa_IR، 41 PASS، 0 FAIL |
  | ترجمه | 86 رشتهٔ تازه؛ MO با 2274 ترجمه از 2415 msgid؛ `msgfmt --check` سالم |
  | preflight | WARNINGS 0، BLOCKERS 0 |

- **فایل‌های تازه:**
  - کد: `includes/Support/HandoffContact.php`، `includes/Support/OptionLock.php`، `includes/Content/SavedAnswers.php`.
  - آزمون: `tests/Unit/HandoffContactTest.php`، `tests/Unit/UnansweredQuestionsTest.php`، `tests/wordpress_kb_gaps_write_it_integration.php`، `tests/answer_handoff_browser_matrix.cjs`، `tests/kb_answers_browser_matrix.cjs`، `tests/local_kb_answers_browser_fixture.php`.
- **اسناد:** `ANSWER_DESK_FA.md`، `AI_AND_KNOWLEDGE_FA.md`، `REST_API_FA.md`، `DATA_MODEL_FA.md`، `SECURITY_FA.md`، `ADMIN_SURFACES_FA.md`، `QUALITY_AND_RELEASE_FA.md`، رودمپ (D-20 تا D-22، T30، خط مبنا، ژورنال)، tracker، گزارش شکاف موکاپ (A-05)، plan S5 و GUIDE_PLAN (یافته‌ها برای G09، G14، G17، G18؛ DG-04 بسته شد). commit انجام نشد.
- **باقی‌مانده، ثبت و پنهان‌نشده:**
  - `uninstall.php` جدول‌های `aisaautopilot_handoffs` و `aisaautopilot_widget_chats` را هنوز حذف نمی‌کند (از پیش در فهرست T21).
  - زبان ویجت عمومی از `WPLANG` می‌آید، پس فیلدهای کارت روی سایت انگلیسی انگلیسی‌اند.
  - گفت‌وگوهای بایگانی‌شده فقط تا 100 مورد در صفحه بار می‌شوند.
- **قدم بعدی:** بدون تغییر، `/aisa-next T05`.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۶ — وارسی سرتاسری انتقال به انسان: دو خطای escape رفع شد

- **پرسش مالک:** «بررسی کردی که انتقال به انسان درست انجام می شود یا نه ؟». پیش از این نوبت فقط رابط مدیر، صندوق و مسیر رد (`handoff_disabled`) آزمون داشتند. هیچ آزمونی درخواست ویجت را از REST تا کانال‌ها و صندوق دنبال نمی‌کرد.
- **آزمون تازه:** `tests/wordpress_widget_handoff_flow_integration.php` با 16 بررسی.
  - مسیر واقعی `POST /widget/handoff` را اجرا می‌کند. ایمیل با `pre_wp_mail` گرفته می‌شود و Telegram با `pre_http_request` پاسخ ساختگی می‌گیرد، پس هیچ ارسال واقعی رخ نمی‌دهد.
  - سناریوها: هر دو کانال؛ Telegram خاموش؛ شکست ایمیل با پذیرش Telegram (`new`)؛ شکست همهٔ کانال‌ها (`failed` و `handoff_failed`)؛ نام و ایمیل؛ markup و فارسی با نیم‌فاصله؛ و رد شدن `invalid_email`، `no_messages`، `handoff_disabled` و `rate_limited` بدون ثبت و بدون تماس با کانال.
- **دو خطای واقعی که همین آزمون پیدا کرد:**
  1. `ChatWidgetController::clean_handoff_messages()` متن را با `sanitize_textarea_field` ذخیره می‌کرد. این تابع `<`، `&` و `"` را entity می‌کند. Telegram دوباره escape می‌کرد و تیم `Budget 3&amp;lt;4` می‌دید. صندوق هم به‌جای متن تایپ‌شده entity نگه می‌داشت.
  2. ایمیل (`HandoffEmail`)، Telegram (`HandoffTelegram::format_message`) و متن ساده (`PdfExporter::export_text`) `wp_strip_all_tags` داشتند. با متن خام، این تابع هر چیزی پس از `<` تایپ‌شده را می‌بُرد (`Budget 3<4 & rest` به `Budget 3` تبدیل می‌شد).
  - **رفع (قرارداد متن ساده):** متن خام ذخیره می‌شود و فقط UTF-8 نامعتبر و نویسه‌های کنترلی حذف می‌شوند. هر خروجی متن را خودش escape می‌کند: صندوق با `esc_html` و `textContent`، ایمیل با `esc_html`، Telegram با `htmlspecialchars` و `double_encode=false`، و پیامک متن ساده است. در [`ANSWER_DESK_FA.md`](docs/technical/ANSWER_DESK_FA.md) و [`SECURITY_FA.md`](docs/technical/SECURITY_FA.md) ثبت شد.
- **خطای خود آزمون، نه افزونه:** اجرای اول پس از `16 checks, 0 failed` با کد 255 خارج شد.
  - علت: closure کمکی در scope سراسری `$post` نام داشت و global وردپرس را بازنویسی می‌کرد. LiteSpeed Cache روی `shutdown` تابع `get_the_ID()` را صدا زد و fatal داد. این خطا فقط در `wp-content/debug.log` ثبت می‌شد.
  - نام به `$send_handoff` تغییر کرد. دام در `QUALITY_AND_RELEASE_FA.md` ثبت شد.
  - پر بودن درایو C (در میانهٔ کار 0 GB آزاد، اکنون حدود 7 GB) علت نبود.
- **mu-plugin محلی `efb-mail-capture.php`:** این mu-plugin با برچسب «temporary debug» از کار EFB است و هر `wp_mail()` را در `wp-content/efb-mail-capture/` ذخیره می‌کند. فیلتر آزمون جلوی آن را نمی‌گیرد.
  - آزمون اکنون در `finally` فقط نسخه‌های همان اجرا را با marker تصادفی‌اش پاک می‌کند.
  - 59 فایل باقی‌مانده از اجراهای قبلی همین آزمون، که marker «Handoff flow QA» داشتند، پاک شد. خود mu-plugin و 1803 فایل دیگر آن دست‌نخورده ماند.
- **یافته‌های باز (کدی تغییر نکرد؛ تصمیم با مالک):**
  - `widget.js` فقط `messages` و `conversation_id` می‌فرستد. پس درخواست‌های ویجت ایمیل بازدیدکننده ندارند و «پاسخ با ایمیل» صندوق برایشان هرگز فعال نمی‌شود. متن گفت‌وگو ممکن است ایمیل را در خود داشته باشد.
  - ردیف‌های صندوق پیش از این اصلاح entity دارند و `&lt;` نشان می‌دهند. مهاجرتی نوشته نشد.
  - EFB email trace گیرنده و فرستندهٔ هر ایمیل را در `debug.log` می‌نویسد.
- **گیت‌ها:**

  | گیت | نتیجه |
  |---|---|
  | آزمون تازهٔ جریان انتقال | 16/0، کد خروج 0، بدون fatal تازه در `debug.log` |
  | static (`20260916-162410-static`) | 5/5 — php-lint 222/222، node-check 35/35، PHPCS 130/130 با 0/0، PHPStan 0، PHPUnit 137 / 470 |
  | release (`20260916-162559-release`) | **59/59** — menu 5/0، assets 19/0، render 18/0، Phase 1 45/0 و 50 integration suite؛ handoff page 7/0، handoff settings 5/0، handoff inbox 12/0 |
  | `handoff_inbox_browser_matrix.cjs` | en_US و fa_IR، 44 PASS و 0 شکست (RTL و 390px). اجرای اول به‌خاطر کندی یک‌بارهٔ سایت (32 ثانیه برای صفحهٔ اول) در ورود timeout شد. سایت به حدود 3 ثانیه برگشت و اجرای دوم سبز بود. |
  | preflight پایانی | WARNINGS 0؛ fixture و کاربر QA باقی نماند |

- **فایل‌های این نوبت:** `includes/Api/ChatWidgetController.php`، `includes/Support/HandoffEmail.php`، `includes/Support/HandoffTelegram.php`، `includes/Support/PdfExporter.php`، `tests/wordpress_widget_handoff_flow_integration.php` (تازه)، `docs/technical/ANSWER_DESK_FA.md`، `docs/technical/SECURITY_FA.md`، `docs/technical/QUALITY_AND_RELEASE_FA.md`، رودمپ، tracker و همین handoff. commit انجام نشد.
- **قدم بعدی:** بدون تغییر، `/aisa-next T05`.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۶ — وارسی دوبارهٔ T03: عدد MO اصلاح شد و یک شکاف ترجمهٔ زنده رفع شد

- **درخواست مالک این نوبت:** «T03». ردیف T03 از ۲۰۲۶-۰۹-۱۶ **تکمیل** بود (خط «قدم بعدی» در بخش‌های قدیمی‌تر همین سند هنوز `/aisa-next T03` می‌گفت و احتمالاً منبع درخواست همان است). کار T03 دوباره انجام **نشد** و وضعیت ردیف عوض **نشد**؛ به‌جایش گیت همان task دوباره اجرا شد.
- **آنچه پابرجا بود:** `php -l` و `node --check` سبز؛ grep نهایی بدون رشتهٔ نمایشی hard-coded (هیچ انتساب `textContent`/`innerText` با literal نمانده و ۸۹ فراخوانی `t()` در فایل است)؛ PHPCS متمرکز `includes/Efb/EfbBuilderButton.php` با 0 error و 0 warning؛ پوشش i18n همان فایل ۸۷ رشته با **۰ غایب از PO و ۰ msgstr خالی**.
- **یافتهٔ ۱ — عدد ثبت‌شدهٔ MO نادرست بود.** `scripts/compile-mo.php` خروجی `count( $mo->entries )` را «translated entries compiled» می‌نامید، ولی آن شمار entryهای merge‌شدهٔ PO است و `MO::export_to_file()` فقط entryهای دارای ترجمه را می‌نویسد. پس «MO با 2329 entry» که در خط مبنای رودمپ، plan S3.2 و بخش T03 همین سند ثبت شده بود، اندازهٔ کاتالوگ عرضه‌شده را بزرگ‌تر از واقع نشان می‌داد: MO واقعی 2187 entry داشت.
  - اسکریپت اصلاح شد و اکنون هر سه عدد را چاپ می‌کند: «N PO entries merged؛ M translated and written؛ K untranslated, not written». عدد قابل‌ثبت **M** است.
  - کامپایل دوباره پیش از هر تغییر PO، MO را **بایت‌به‌بایت بدون تغییر** گذاشت (sha256 یکسان)، پس ترجمهٔ عرضه‌شده عوض نشد و فقط گزارش صادق شد.
  - هیچ آزمون یا اسکریپتی این رشتهٔ خروجی را parse نمی‌کند (grep شد)، پس تغییر قالب چیزی را نشکست.
- **یافتهٔ ۲ — یک رشتهٔ زندهٔ بی‌ترجمه، که همین‌جا رفع شد.** ۱۴۳ msgid بی‌ترجمهٔ PO بررسی شدند: ۱۳۰ مورد کهنه‌اند (msgid دیگر در `includes/` و `assets/` نیست) و ۱۲ مورد فقط زیررشتهٔ اتفاقی بودند، ولی یکی واقعی بود — `ChatEngine::not_found_message()` در `includes/Chat/ChatEngine.php:383`. دو جملهٔ همسایه‌اش ترجمه داشتند، پس **بازدیدکنندهٔ سایت فارسی پیام «پاسخی پیدا نشد» را نیمه‌انگلیسی می‌دید**. msgstr فارسی افزوده و MO دوباره کامپایل شد.
- **دام برای T18:** نبود ارجاع `#:` ملاک کهنه بودن یک entry نیست. هر ۱۴۳ مورد بی‌ارجاع بودند، ولی یکی از آن‌ها رشتهٔ زندهٔ محصول بود. تنها ملاک، جست‌وجوی خود msgid در منبع است. این در جزئیات T18 و در `docs/technical/QUALITY_AND_RELEASE_FA.md` نوشته شد.
- **گیت‌ها:**

  | گیت | نتیجه |
  |---|---|
  | static (`run-gates.ps1 -Tier static`) | 5/5 — php-lint 221/221، node-check 35/35، PHPCS 130/130 با 0/0، PHPStan 0، PHPUnit 137 / 470 (برابر خط مبنا) |
  | پوشش i18n `EfbBuilderButton.php` | ۸۷ رشته، ۰ غایب، ۰ خالی |
  | ترجمه | `msgfmt --check` سالم؛ MO از 2187 به 2188 entry در header (2187 ترجمه + header)؛ بی‌ترجمه از ۱۴۳ به ۱۴۲ |
  | runtime فارسی | `switch_to_locale( 'fa_IR' )` روی سه جملهٔ ChatEngine: `3 checks, 0 failed` |
  | preflight پایانی | WARNINGS 0 و BLOCKERS 0 |

- **آنچه اجرا نشد و پنهان نیست:** matrix مرورگر EFB Copilot دوباره اجرا **نشد**. هیچ JS، هیچ رشتهٔ رابط admin و هیچ assertion تغییر نکرد؛ تنها تغییرهای این نوبت یک اسکریپت build، یک msgstr در PO و MO بازکامپایل‌شده‌اند، و رشتهٔ اصلاح‌شده به ChatEngine تعلق دارد نه به سطح EFB Copilot. regression کامل در T05 انجام می‌شود.
- **فایل‌های این نوبت:** `scripts/compile-mo.php`، `languages/ai-site-assistant-autopilot-fa_IR.po`، `languages/ai-site-assistant-autopilot-fa_IR.mo`، `docs/PRODUCT_COMPLETION_ROADMAP_FA.md`، `docs/EFB_COPILOT_S3_2_AUDIT_AND_PLAN_FA.md`، `docs/technical/QUALITY_AND_RELEASE_FA.md`، `docs/user-guide/GUIDE_PLAN_FA.md` و همین handoff. هیچ کد محصولی جز ترجمه تغییر نکرد.
- **شواهد:** `test-artifacts/efb-copilot/20260916-t03-reverify/` (i18n-coverage.txt، untranslated-audit.txt، fa-runtime-check.txt، سه اسکریپت وارسی و preflight-after.txt) و `test-artifacts/gates/20260916-154356-static`.
- **یادداشت دربارهٔ بخش‌های قدیمی‌تر همین سند:** عدد «2329 entry» در بخش T03 پایین‌تر و «2281» در بخش طرح v2 همان شمار merge هستند و برای حفظ تاریخچه دست‌نخورده ماندند. عدد درست کاتالوگ عرضه‌شده همین بالا است.
- **قدم بعدی:** `/aisa-next T05` — regression کامل با `run-gates.ps1 -Tier full` و مقایسه با خط مبنا؛ سپس T06. هیچ‌کدام به تصمیم باز وابسته نیستند.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۶ — T04 بسته شد (browser و visual QA بستهٔ EFB Copilot) و ردیف‌های تصمیم تازه شدند

- **درخواست مالک این نوبت:** «D-13 (شروع S5-A) و D-02 تا D-07، به‌علاوهٔ ردیف‌های D». چون این متن نام task نبود، طبق قاعدهٔ ایجنت task بعدی صف (T04) اجرا شد و در کنار آن همهٔ ردیف‌های تصمیم باز با کد و محیط امروز وارسی و تازه شدند. **هیچ تصمیمی از طرف مالک گرفته نشد.**

### T04 — تکمیل

- **چه شد:** `tests/efb_copilot_browser_check.cjs` گسترش یافت (اسکریپت موازی ساخته نشد) و از ۴۲ به **۷۶ check در هر locale** رسید. matrix با exit 0: `en_US` 76 PASS / 0 FAIL و `fa_IR` 76 PASS / 0 FAIL، صفر console error، صفر pageerror و صفر HTTP غیرمنتظره.
- **پوشش تازه:** صفر درخواست هنگام باز کردن پنل و کلیک chip؛ prefill شدن chip با focus و بدون ثبت در گفت‌وگو؛ زبان واقعی رابط در `fa_IR` (عنوان، خط زمینه، برچسب FAB، خوشامد، placeholder و aria-label چهار action)؛ focus ring دیدنی، Tab، Enter، Shift+Enter و Escape؛ حالت `provider_not_configured` بدون ادعای موفقیت؛ کارت how-to، chipهای پیشنهاد موضوع و کارت Pro با `page.route()`؛ CTA Pro که دیالوگ خود EFB را باز می‌کند، ویرایشگر را ترک نمی‌کند و tab بیرونی نمی‌سازد؛ پیوند قیمت محلی‌شده (`easyformbuilder.ir` در فارسی، `whitestudio.team` در انگلیسی) با `rel="noopener noreferrer"`؛ و ۷ screenshot محدود در هر locale با `locator.screenshot()` به‌جای full-page، چون صفحهٔ ویرایشگر EFB بسیار بلند است.
- **سه اصلاح کوچک محصول (`assets/admin/efb-copilot.js`):**
  1. **chip دیگر ارسال نمی‌کند.** chipهای خوشامد و پیشنهاد موضوع متن خود را در composer می‌گذارند و focus را همان‌جا می‌برند. `docs/technical/ARCHITECTURE_FA.md` از پیش همین را برای EFB Copilot ادعا می‌کرد و کد با آن هم‌خوان نبود. موکاپ chip را بلافاصله اجرا می‌کند (`runChip`) و این اختلاف عمدی ثبت شد.
  2. **پیام «… را انتخاب کرده‌اید» تکرار نمی‌شود.** `lastIntroFieldId` تا ارسال پیام بعدی یا New chat نگه داشته می‌شود، پس پاک کردن و انتخاب دوبارهٔ همان فیلد خط تکراری نمی‌سازد. انتخاب فیلد دیگر هنوز با نام آن اعلام می‌شود.
  3. **New chat نوار وضعیت میکروفن را هم پاک می‌کند**، چون خطای اجازهٔ گفت‌وگوی قبلی پس از پاک شدن transcript دیگر معنا ندارد.
- **hookهای تازه:** `data-sp-efb-chips` (جعبه)، `data-sp-efb-chip` (`starter` یا `suggestion`) و `data-sp-efb-intro` (`field`، `form`، `new-form`).
- **اختلاف‌های عمدی با `EFB Builder Chat.dc.html`** (از بازبینی تصویر، همه در plan S3.2 ثبت شدند):
  - chip در موکاپ بلافاصله اجرا می‌شود، در افزونه فقط composer را پر می‌کند؛
  - خط زمینهٔ هدر در موکاپ «Contact form · 4 fields» است و در افزونه «در حال ویرایش فرم فعلی»؛ دادهٔ نام فرم و شمار فیلد در `valj_efb` هست ولی به هدر وصل نشده (کاندید T06)؛
  - کارت how-to در موکاپ آیکن و گام شماره‌دار دارد، در افزونه عنوان و پاراگراف‌های نثر، چون `efb-knowledge.json` پاسخ‌ها را نثر نگه می‌دارد؛
  - کارت Pro در موکاپ قاب کهربایی با قفل است، در افزونه یادداشت درون حباب با دکمهٔ طلایی و پیوند قیمت؛ محتوای افزونه صادقانه‌تر است (نام فیلد کنارگذاشته‌شده و پیوند رسمی) و اختلاف فقط بصری است؛
  - آیکن ارسال و متن placeholder از S3.2.1 متفاوت‌اند.
- **یافتهٔ باز برای T17 (RTL فاز ۴):** حباب پیام `dir` ندارد، پس پاسخی که زبانش با زبان رابط فرق دارد با جهت رابط رندر می‌شود (در این اجرا با payload انگلیسی mock در پنل فارسی دیده شد). در محصول پاسخ به زبان سایت است، پس فقط با پاسخ چندزبانه پیش می‌آید. راه‌حل پیشنهادی `dir="auto"` روی متن حباب است و باید با همهٔ حالت‌های RTL آزموده شود.
- **اجرای اول شکست خورد و پنهان نشده است:** کلیک ورود با timeout پیش‌فرض ۳۰ ثانیهٔ Playwright منتظر navigation ماند، پیش از آنکه هیچ check اجرا شود. ریشه همان کندی شناخته‌شدهٔ سایت محلی است؛ بودجهٔ کلیک و انتظار URL به ۶۰ ثانیه رسید و هیچ assertion سست نشد. fixture و حساب QA در همان اجرا هم پاک شدند.
- **گیت‌ها:**

  | گیت | نتیجه |
  |---|---|
  | browser matrix | exit 0؛ `en_US` 76 PASS / 0 FAIL و `fa_IR` 76 PASS / 0 FAIL (مجموع ۱۵۲) |
  | console/pageerror/HTTP | صفر غیرمنتظره؛ تنها 400 مجاز همان `provider_not_configured` خودِ mock است |
  | static (`run-gates.ps1 -Tier static`) | 5/5 — php-lint 221/221، node-check 35/35، PHPCS 130/130 با 0/0، PHPStan 0، PHPUnit 137 / 470 (برابر خط مبنا) |
  | پاک‌سازی | `EFB Copilot browser fixture removed.` و `Local browser QA account removed.` در هر سه اجرا؛ preflight پایانی WARNINGS 0 و BLOCKERS 0 |

- **فایل‌های این نوبت:** `assets/admin/efb-copilot.js`، `tests/efb_copilot_browser_check.cjs`، `docs/PRODUCT_COMPLETION_ROADMAP_FA.md`، `docs/EFB_COPILOT_S3_2_AUDIT_AND_PLAN_FA.md`، `docs/technical/FORMS_CONTENT_STORE_FA.md`، `docs/user-guide/GUIDE_PLAN_FA.md` و همین handoff. هیچ رشتهٔ نمایشی تازه‌ای افزوده نشد، پس PO و MO تغییر نکردند.
- **شواهد:** `test-artifacts/efb-copilot/20260916-t04/` (۱۴ screenshot در دو locale و `preflight-after.txt`)، سه لاگ اجرا در `test-artifacts/efb-copilot/20260916-t04-run{1,2,4}.txt` و `test-artifacts/gates/20260916-032208-static`.

### ردیف‌های تصمیم — وارسی‌شده، هیچ‌کدام تصمیم‌گیری نشده

ستون وضعیت هر ردیف باز اکنون می‌گوید تصمیم دقیقاً در کدام task لازم است:

| ردیف | لازم در | یافتهٔ تازهٔ امروز | پیشنهاد ایجنت |
|---|---|---|---|
| D-13 | T29 | T29 به T14 (پایان S3.3) وابسته است، پس پاسخ امروز هیچ ردیف S5-A را اجراپذیر نمی‌کند و تا T14 دست‌کم ۱۰ task فاصله است. اثر تصمیم فقط دامنهٔ 1.1.0 است. | (الف) بله، طبق متن؛ گزینه‌های دیگر: (ب) خیر و انتقال T29 تا T43 به بعد از T25، (ج) زیرمجموعه‌ای که مالک نام می‌برد |
| D-02 | T12 | هیچ session یا transport realtime در کد نیست و `AnswerDeskVoicePage.php:57`–`:62` همین حالا می‌گوید ویجت اتصال صوتی پیوسته باز نمی‌کند. overlay شبیه تماس با این قفل صادقانه تضاد دارد. | خیر؛ transcript-first مانند EFB |
| D-03 | T21 | نسخه 1.0.4 در سه جا. وارسی ۲۰۲۶-۰۹-۱۹: `RestRoutes.php` نسبت به HEAD فقط افزایشی است (پنج مسیر تازه، هیچ حذف)؛ `HandoffInbox` schema 2 با مهاجرت افزایشی `dbDelta` (ستون nullable `archived_at`)؛ `VisitorConversationStore` هنوز 1. پس تغییر ناسازگار نداریم. جدا: `readme.txt` هنوز `Tested up to: 7.0` است و سایت محلی 7.1.2-alpha. | 1.1.0 و `Tested up to: 7.1` |
| D-04 | T24 | در `C:\xampp\htdocs` نصب دومی برای نصب تمیز وجود ندارد. ساخت آن تصمیم و کار مالک است. | نصب دوم محلی که مالک می‌سازد |
| D-05 | T25 | worktree ۸۰ مسیر ثبت‌نشده دارد (۵۸ modified و ۲۲ untracked) و آخرین commit `459bfd8` است، یعنی کل بازطراحی هنوز هیچ‌جا ثبت نشده. | commitهای منطقی پس از امضای انتشار، و — اگر مالک بخواهد — یک commit snapshot ایمنی روی شاخهٔ تازه پیش از T25. ایجنت بدون اجازهٔ صریح هیچ commit نمی‌سازد |
| D-06 | — | موضوع منتفی شد: T00 در ۲۰۲۶-۰۹-۱۵ بسته شد و از S3.2 فقط T05 و T06 مانده است. | ردیف «منتفی» بسته شود |
| D-07 | پس از T25 | هر ردیف S5-B هم به T25 و هم به T43 وابسته است، پس پیش از انتشار چیزی را مسدود نمی‌کند. | پس از انتشار، با اولویت BE-04 |
| D-14 تا D-19 | T45 تا T52 | همگی در S5-B و پس از T25 و T43؛ پیش از انتشار مسدودکننده نیستند. | بدون تغییر نسبت به پیشنهادهای ثبت‌شده |

- **قدم بعدی:** `/aisa-next T05` (regression کامل `run-gates.ps1 -Tier full` و مقایسه با خط مبنا)، سپس T06 برای بستن اسناد S3.2. هیچ‌کدام به تصمیم باز وابسته نیستند.
## به‌روزرسانی ۲۰۲۶-۰۹-۱۶ — T03 بسته شد (localization باقی‌ماندهٔ EFB Copilot)

- **چه شد:** هر رشتهٔ نمایشی که `assets/admin/efb-copilot.js` در مرورگر می‌سازد، حالا از `cfg.i18n` می‌آید و fallback انگلیسی دارد. ۴۸ کلید تازه در `EfbBuilderButton::enqueue()` با comment مترجم، ۴۸ msgid فارسی در PO، و MO با **2329** entry (پیش‌تر 2281).
- **پوشش:** پیام نتیجهٔ همهٔ operationها (نام فرم، نام فیلد، اجباری/اختیاری، placeholder، عرض، متن راهنما، تنظیم فیلد و فرم، افزودن و حذف فیلد، مرحله و گزینه)، ۱۷ دلیل اعمال‌نشدن، یادداشت مالکیت Save، خطای رسم دوباره، پیام «ابتدا یک فرم باز کنید»، `Done.`، `Failed.` در کارت پیش‌نمایش و fallbackهای شبکه، پاسخ نامعتبر و تبدیل گفتار.
- **تغییرهای رفتاری کوچک:**
  - `t()` حالا `%1$s` و `%2$s` را هم می‌پذیرد و جایگزینی با تابع انجام می‌شود، پس دنباله‌ای مانند `$&` در برچسب فیلد دیگر تفسیر نمی‌شود.
  - دلیل شکست دیگر کد خام (`field_not_found`) نیست؛ رابط جملهٔ ترجمه‌شده می‌گوید و کد پایدار در `data-sp-efb-reason` و client log می‌ماند.
  - هر یادداشت نتیجه `data-sp-efb-note` دارد: `applied`، `failed`، `no-change`، `server`، `save-ownership`، `render-failed`.
  - ستون عرض در کارت پیش‌نمایش به‌جای حرف اول انگلیسی (`f`، `h`) درصد نشان می‌دهد (`100%`، `50%`، `33%`، `25%`).
- **قرارداد آزمون که عمداً عوض شد:** دو assertion در `tests/efb_copilot_browser_check.cjs` متن انگلیسی `invalid form property value` را می‌سنجیدند و پس از ترجمه در `fa_IR` قرمز می‌شدند. حالا hook `data-sp-efb-reason` و زبان رابط را می‌سنجند، و یک assertion تازه فارسی بودن یادداشت مالکیت Save را می‌سنجد. هر locale از ۴۱ به ۴۲ check رسید.
- **گیت‌ها:**

  | گیت | نتیجه |
  |---|---|
  | static (`run-gates.ps1 -Tier static`) | 5/5 — php-lint 221/221، node-check 35/35، PHPCS 130/130 با 0/0، PHPStan 0، PHPUnit 137 / 470 |
  | PHPCS متمرکز `EfbBuilderButton.php` | 0 error، 0 warning (۷۰ هشدار alignment ناشی از کلیدهای تازه با `phpcbf` روی همان یک فایل رفع شد) |
  | ترجمه | MO با 2329 entry؛ `msgfmt --check` سالم؛ هر ۸۷ رشتهٔ i18n این سطح msgstr فارسی ناتهی دارد |
  | browser matrix | `en_US` 42 PASS / 0 FAIL و `fa_IR` 42 PASS / 0 FAIL، exit 0 |

- **اجرای اول matrix شکست خورد و پنهان نشده است:** `page.goto` ویرایشگر EFB پس از ۴۵ ثانیه timeout شد، پیش از آنکه هیچ check اجرا شود. fixture و حساب QA پاک شدند و preflight 0/0 بود. ریشه‌یابی: `wp-login.php` در ۱.۵ ثانیه و صفحهٔ اول در ۵.۰ ثانیه پاسخ ۲۰۰ دادند و `debug.log` پس از ۰۱:۲۹ خطایی ندارد، یعنی کندی گذرای همین سایت محلی. هیچ timeout بالا نرفت و هیچ assertion سست نشد؛ اجرای دوم بدون تغییر تنظیمات سبز شد.
- **پاک‌سازی:** `EFB Copilot browser fixture removed.` و `Local browser QA account removed.` در هر دو اجرا؛ preflight پایانی با WARNINGS 0 و BLOCKERS 0.
- **اختلاف‌های عمدی و قفل‌های صادقانه:**
  - نام تنظیم در «تنظیم %1$s برای …» همان کلید کاتالوگ است، چون کاتالوگ برچسب ترجمه‌شده ندارد.
  - نام پیش‌فرض مرحله (`مرحلهٔ %s`) محتوای فرم است و به زبان مدیر نوشته می‌شود؛ عملاً fallback است، چون `EfbCustomizeSkill` عملیات `add_step` بدون label را کنار می‌گذارد.
  - نام سبک‌ها و «Step 1 of 2 · 50% complete» در preview از ترجمهٔ خود EFB می‌آیند و در `fa_IR` انگلیسی‌اند؛ سمت EFB است، نه این افزونه.
  - `fieldsCount` تک‌صورت می‌ماند؛ `_n` در T18 بررسی می‌شود.
  - متن‌های `clientLog` و `history` ترجمه نشدند: اولی داخلی است و دومی فقط زمینهٔ گفت‌وگو برای مدل است و روی رابط دیده نمی‌شود.
- **فایل‌های این نوبت:** `includes/Efb/EfbBuilderButton.php`، `assets/admin/efb-copilot.js`، `tests/efb_copilot_browser_check.cjs`، PO و MO فارسی، و اسناد رودمپ، plan S3.2، `docs/technical/FORMS_CONTENT_STORE_FA.md` و `docs/user-guide/GUIDE_PLAN_FA.md`.
- **مستندات:** بخش T03 با جدول inventory و جدول گیت در plan S3.2؛ قرارداد i18n و hookهای `data-sp-efb-*` در `docs/technical/FORMS_CONTENT_STORE_FA.md`؛ یافتهٔ تازه برای G24 در راهنمای کاربر (مقاله هنوز نوشته نشده، پس وضعیتش عوض نشد).
- **شواهد:** `test-artifacts/efb-copilot/20260916-t03/` و `test-artifacts/gates/20260916-020405-static`.
- **قدم بعدی:** `/aisa-next T04` — تکمیل browser و visual QA: screenshot محدود، help/Pro با mock، keyboard، 390×844 و فارسی واقعی. وابستگی‌های T04 (T01، T02، T03، T26، T27) همه تکمیل‌اند. مالک: D-13 و بقیهٔ تصمیم‌های باز.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۶ — بررسی و اجرای نسخهٔ Claude Design سطح‌های بیرون از موکاپ

- **دستور مالک:**
  - نسخهٔ Claude Design در `Docs/docs/design/claude-design-v2` قرار گرفت و بررسی شد.
  - مالک پنج مورد را «اگر طبق مستندات عملکرد سیستم است» تأیید کرد.
  - تصمیم‌های لازم هم «طبق مستندات» گرفته شد.
- **بررسی نسخهٔ Claude Design:**
  - چهار موکاپ اصلی بایت‌به‌بایت دست‌نخورده‌اند، `support.js` یکسان است و پوشهٔ `uploads` کلید یا دادهٔ شخصی ندارد.
  - هر ۱۴ صفحه با همهٔ حالت‌ها render شد: ۶۸ تصویر، RTL در همهٔ صفحه‌ها، نمای 390px بدون سرریز و بدون خطای console. شواهد در `test-artifacts/design-v2-claude-design/` است.
  - یک متن نادرست پیدا شد: حالت «No channel configured» ادعا می‌کند درخواست‌ها بدون کانال در صندوق می‌مانند. در افزونه بدون کانال آماده درخواستی ثبت نمی‌شود (`Settings::answer_desk_handoff_available()` و `ChatWidgetController`).
- **تصمیم‌ها طبق مستندات:**
  1. کارت‌های کانال موکاپ اصلی در tab انتقال ماندند و فقط نوار صندوق طرح تازه اضافه شد، چون موکاپ اصلی برای ده صفحهٔ منو منبع بالاتر است.
  2. متن حالت بدون کانال طبق رفتار واقعی نوشته شد.
  3. `design/claude-design-v2/AutoPilot Admin Extended.dc.html` با نسخهٔ Claude Design جایگزین شد. پیش‌نویس قبلی در `Docs/docs/design/claude-design-v2/uploads/` مانده است.
- **نوار «Waiting for a person» در انتقال به انسان:**
  - شمار واقعی `new` و `failed` را نشان می‌دهد (`HandoffInbox::count_failed()` تازه و `AnswerDeskHandoffPage`).
  - وقتی انتقال در دسترس نیست، صادقانه می‌گوید به بازدیدکنندگان گزینهٔ گفت‌وگو با یک فرد داده نمی‌شود.
  - با روشن یا خاموش کردن کانال در همان صفحه به‌روز می‌شود (`answer-handoff.js`).
- **کنسول «از دانش سایت بپرسید»:**
  - **حالت قفل:** علت واقعی را می‌گوید (سرویس یا محتوای ایندکس‌شده)، شمار محتوا را می‌آورد، دکمهٔ مسیر حل دارد و پیشنهادها قفل‌اند. زیر کادر «تا وقتی قفل است، چیزی فرستاده نمی‌شود» می‌آید.
  - **کارت خطا** با عنوان «پاسخی برنگشت»:
    - خطای provider: «دوباره بپرسید» و «باز کردن سلامت»؛
    - سقف مصرف: «باز کردن مصرف و هزینه»؛
    - خطای شبکه: «دوباره بپرسید».
  - متن ادعای timeout نمی‌کند، چون `ChatEngine` برای همهٔ خطاهای provider همان وضعیت `error` را برمی‌گرداند.
  - پاسخ خطای ذخیره‌شده هنگام باز کردن دوبارهٔ گفت‌وگو هم کارت خطا نمایش داده می‌شود.
- **محتوای ایندکس‌شده:**
  - هنگام بارگذاری ردیف‌های skeleton نشان داده می‌شود.
  - درخواست ناموفق یا ردشدهٔ `/kb/list` پیام «فهرست بارگذاری نشد» با «تلاش دوباره» می‌گیرد. پیش‌تر پاسخ ردشده حالت «Loading…» را بی‌پایان باقی می‌گذاشت.
- **تنظیمات پیشرفته:**
  - برچسب «فقط‌خواندنی»، دکمهٔ «باز کردن راه‌اندازی گام‌به‌گام» و متن توضیحی اضافه شد.
  - به‌جای «the key is tested» نوشته شد «the connection is checked before it can be saved»، چون کلید Connectors وردپرس در ویزارد تایپ نمی‌شود.
- **نوار ذخیرهٔ روشن:** در ویجت («Unsaved widget changes — visitors still see the saved version») و در تنظیمات پیشرفته («Unsaved changes on this page»).
- **ترجمه:** ۳۳ رشتهٔ فارسی تازه اضافه شد. MO با ۲۲۸۱ entry ترجمه‌شده کامپایل شد، `msgfmt --check` سالم است و در فایل‌های تغییرکرده ۰ رشتهٔ بی‌ترجمه مانده است.
- **آزمون integration:**

  | suite | نتیجه |
  |---|---|
  | handoff page | 7/0 |
  | chat console | 14/0 |
  | advanced settings | 11/0 |
  | widget customise | 12/0 |
  | knowledge hub | 8/0 |
  | menu badge | 6/0 |
  | handoff inbox | 12/0 |

- **آزمون مرورگر** (en_US و fa_IR، 1440 و 390):
  - chat console 60/0. اجرای اول به timeout محیطی خورد، چون هم‌زمان php-lint گیت static اجرا می‌شد؛ اجرای دوم سبز بود.
  - advanced settings 36/0 و widget 50/0.
  - Site Knowledge و tab انتقال 100/0.
  - بررسی‌های تازه:
    - کارت خطای سقف و provider و Ask again؛
    - نوار صندوق و هم‌گامی آن با روشن و خاموش کردن کانال؛
    - خطای فهرست محتوا با Try again؛
    - رنگ روشن نوار ذخیره.
- **یافته‌های بازبینی تصویر:**
  - نوار صندوق پس از روشن کردن کانال در همان صفحه همچنان «در دسترس نیست» می‌گفت. رفع شد و آزمون مرورگر آن را می‌سنجد.
  - برچسب فارسی «فقط‌خواندنی» برای خوانایی بزرگ‌تر شد.
- **گیت‌ها:**
  - static 5/5 (`test-artifacts/gates/20260916-000755-static`)
  - release 58/58 (`test-artifacts/gates/20260916-003727-release`)
- **مستندات:**
  - `docs/technical/ADMIN_SURFACES_FA.md`، `ANSWER_DESK_FA.md` و `ARCHITECTURE_FA.md`؛
  - جدول وضعیت و منبع در `design/claude-design-v2/EXTENDED_SURFACES_BRIEF.md`؛
  - دو یافتهٔ تازه در `docs/user-guide/GUIDE_PLAN_FA.md`.
- **قدم بعدی:** `/aisa-next T03`. مالک: D-13.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۵ — S5 به‌صورت task، سطح‌های بیرون از موکاپ، مستندات فنی و agent راهنمای کاربر

- **دستور مالک، پنج بخش:**
  1. موارد موکاپ بدون کد به‌صورت task کامل برای `/aisa` تعریف شوند.
  2. موارد کد بدون موکاپ با UI/UX خوب به طرح Claude Design اضافه شوند و نسخهٔ تازه اجرا شود.
  3. ایجنت به‌روز شود.
  4. مستندات فنی نوشته شود.
  5. agent کامل مقاله‌نویسی برای کاربران افزونه، با فهرست task و ویژگی‌ها، ساخته شود که برای هر مقاله خودش تصویر بگیرد.
- **(۱) S5:** T29 تا T53 در رودمپ و `docs/MOCKUP_GAP_S5_AUDIT_AND_PLAN_FA.md`. تصمیم‌های D-13 تا D-19 باز هستند.
- **(۲) طرح:**
  - `design/claude-design-v2/AutoPilot Admin Extended.dc.html` و `EXTENDED_SURFACES_BRIEF.md`، که حالا جدول وضعیت اجرا و اختلاف‌های عمدی را دارد.
  - **در پروژهٔ Claude Design بارگذاری نشده است.** توصیهٔ قبلی به اجرای `/design-login` نادرست بود: این فرمان در افزونهٔ VS Code وجود ندارد، و ابزار DesignSync فقط پروژه‌های design-system را در جریان `/design-sync` می‌نویسد، نه پروژهٔ طراحی معمولی مالک.
  - **راه‌های واقعی:** مالک `AutoPilot Admin Extended.dc.html` را خودش از مرورگر به پروژه اضافه کند (اگر پروژه `support.js` ندارد، آن را هم)، یا طرح به‌صورت canvas Artifact منتشر شود.
  - اجرای افزونه به این کار وابسته نیست.
- **(۲) اجرا:**
  - کنسول «از دانش سایت بپرسید» (`ChatPage`، `chat.js`، `voice.js`)
  - مدخل‌های دانش سایت با حذف و ایندکس دوباره با تأیید
  - صندوق انتقال به انسان (`HandoffInboxPage`، `handoff-inbox.js`، نوار «Open inbox» روی tab انتقال به انسان)
  - فرم‌های بومی، و reset مصرف با پنجرهٔ تأیید مشترک
  - سفارشی‌سازی ویجت و حریم خصوصی (`ChatWidgetSettings` با ذخیرهٔ محدود به کلیدهای همان صفحه)
  - تنظیمات پیشرفته (`SettingsPage::render_advanced()`)
  - جزئیات دستیار EFB (`EasyFormBuilderPage`)

  ترتیب تحویل (T37)، ویرایشگر الگو (T33 و T34) و کارت محصول (T39) ساخته نشدند.
- **آزمون‌های تازه:**
  - integration: chat console 12/0، handoff inbox 12/0، widget customise 12/0، advanced settings 10/0، efb details 7/0؛
  - matrix و fixture همین سطح‌ها در `tests/`.
- **رفع‌های این نوبت:**
  - **آزمون tab انتقال:** `wordpress_answer_handoff_page_integration` پس از افزودن عمدی نوار صندوق به tab انتقال، به‌جای نبود متن «Handoff inbox»، نبود فهرست و کشو و وجود پیوند درست را می‌سنجد (5/0).
  - **assertionهای مرورگر در `fa_IR`:** دو assertion (صف دانش سایت و توضیح ارسال هم‌زمان انتقال) فقط در `fa_IR` شکست می‌خوردند. متن انگلیسی را می‌سنجیدند و ترجمهٔ فارسی آن متن‌ها در همین نوبت اضافه شده بود.
    - hookهای پایدار `data-sp-kb-queue-scope="browser-session"` و `data-sp-handoff-delivery-mode="fan-out"` اضافه شدند و بررسی به زبان رابط وابسته شد.
    - integrationهای knowledge hub (8/0) و handoff page (5/0) هم hook را می‌سنجند.
- **browser regression:**
  - **اجرای اول:** دانش سایت، مصرف با fixture، فرم‌ها و انتقال به انسان. `en_US` سبز بود و `fa_IR` دو شکست بالا را داشت.
  - **اجرای دوم پس از اصلاح:** دانش سایت و انتقال به انسان، 90 PASS و 0 FAIL در هر دو locale، exit 0، fixtureها و حساب QA پاک شدند و preflight 0 هشدار و 0 مانع داد.
  - **شواهد:** `test-artifacts/regression-extended-surfaces/`.
- **گیت release:** `run-gates.ps1 -Tier release` **58/58**.
  - php-lint 221/221، node-check 35/35، PHPCS 130/130 با 0 error و 0 warning، PHPStan 0، PHPUnit 137 / 470؛
  - menu 5/0، assets 19/0، render 18/0، Phase 1 45/0 و هر 49 integration suite، بدون PHP diagnostic افزونه.
  - شواهد: `test-artifacts/gates/20260915-195425-release`. اجرای قبلی همین نوبت 57/58 بود و تنها شکستش قرارداد tab انتقال بود.
- **(۴) مستندات فنی:** یازده سند فارسی در `docs/technical/` نوشته شد:
  - README، ARCHITECTURE، ADMIN_SURFACES؛
  - REST_API (60 جفت مسیر و متد، 7 عمومی)؛
  - DATA_MODEL (با باقی‌مانده‌های uninstall برای T21)؛
  - AI_AND_KNOWLEDGE، ANSWER_DESK، FORMS_CONTENT_STORE، SECURITY، QUALITY_AND_RELEASE، EXTENDING.

  ادعاها با کد تطبیق داده شدند و دو نادرستی جزئی (`KbGaps::dismiss` و کلیدهای `AISAAutoPilotWidget`) اصلاح شد.
- **(۵) راهنمای کاربر:**
  - agent `.claude/agents/aisa-docs-writer.md`، فرمان `/aisa-docs`، حافظهٔ agent؛
  - `docs/user-guide/`:
    - GUIDE_PLAN با 42 ویژگی، 18 محدودیت، 36 مقاله و 7 کمبود ابزار و fixture؛
    - WRITING_GUIDE با واژه‌نامهٔ رابط از PO؛
    - ARTICLE_TEMPLATE و README؛
  - ابزار `scripts/agent/docs-screenshot.cjs`: plan JSON، کاربر موقت، fixture، mock یا abort همهٔ REST افزونه و رنگ پوشش خنثی.
  - **اجرای آزمایشی plan `handoff-inbox`، دو اجرا:**
    - **اجرای اول** نشت نام نشانگردار fixture روی پیام‌های کشو، منوی مدیریت و chip سقف مصرف، و پوشش سرخابی را نشان داد. plan با `clip` و پوشش `.sp-hoi-msg--visitor .sp-hoi-msg__who` و ابزار با `maskColor` خنثی اصلاح شد.
    - **اجرای دوم:** 8 تصویر `fa_IR` و `en_US` در `docs/user-guide/images/handoff-inbox/`، بدون درخواست مسدود. fixture و حساب موقت پاک شدند، preflight 0/0 و بازبینی تصویرهای فارسی بدون نشت.
  - agent راهنما هنوز هیچ مقاله‌ای ننوشته است. صف از G01 شروع می‌شود.
- **(۳) ایجنت اجرایی:**
  - `aisa-finisher` به‌روز شد: T00 تا T53 با دروازه‌های S5-A و S5-B، منبع طراحی v2، الگوهای رابط، assertion وابسته به زبان، اجرای تک browser job، به‌روز نگه داشتن `docs/technical` و علامت‌گذاری مقاله‌های راهنما.
  - skillهای `aisa-next`، `aisa-status`، `aisa-gates` و `aisa-browser-qa` هم به‌روز شدند. guard در self-test 43/0 است.
- **قدم بعدی:**
  - مالک: تصمیم D-13 برای S5-A، و انتخاب راه انتقال طرح v2 به Claude Design (افزودن دستی در مرورگر یا canvas Artifact).
  - اجرا: `/aisa-next T03`.
  - راهنما: `/aisa-docs` (G01).

## به‌روزرسانی ۲۰۲۶-۰۹-۱۵ — هم‌ترازی UI/UX با چهار موکاپ و گزارش شکاف دوطرفه

- **دستور مالک:** افزونه دقیقاً مطابق موکاپ‌ها و همهٔ سناریوها به‌روز شود، با حلقهٔ «بازبینی، اجرا، تست و بازگشت به بازبینی در صورت خطا»؛ قابلیت‌های موکاپ که در کد نیستند و قابلیت‌های کد که در موکاپ نیستند، با مرجع دقیق در یک فایل md ثبت شوند.
- **گزارش شکاف:** `docs/MOCKUP_FEATURE_GAP_REPORT_FA.md`. در موکاپ هست و در کد نیست یا قفل صادقانه است: 18 مورد Admin، 2 مورد ویجت، 3 مورد Form Composer و 1 مورد EFB. در کد هست و در موکاپ نیست: 15 مورد. اختلاف عمدی: 5 مورد. بخش ۵ همان سند پیشنهاد تصمیم برای مالک است.
- **آنچه ساخته یا اصلاح شد:**
  - پوستهٔ مشترک: header سفید full-bleed با خط زیرین از box-shadow (نسخهٔ `::after` کل wp-admin را اسکرول افقی می‌داد)، فاصلهٔ 24px تا بدنه، عنوان تمام‌عرض در عرض گوشی، زمینهٔ `#f5f6fa` و تب‌های موکاپ. منو روی هر هشت alias باز می‌ماند و آیتم مالک برجسته می‌شود (`AdminMenu::parent_file`؛ هستهٔ `get_admin_page_parent()` پیش‌تر آن را می‌بست).
  - Setup: wizard چهارمرحله‌ای بازنویسی شد (`SettingsPage.php`، `assets/admin/setup-wizard.js`) و کارت WordPress Connector در WP 7.1 واقعاً ثبت می‌شود (`Core/WpConnectors.php`).
  - Overview و Integrations هم‌تراز شدند؛ Usage پیام نمودار خالی دارد؛ Activity Log روزها و رویدادهای قدیمی را جمع می‌کند.
  - ویجت بازدیدکننده مطابق موکاپ بازنویسی شد: launcher و nudge، panel، full screen با ستون پرسش‌ها، کارت handoff درون گفتگو و reset محدود به `.spw-root` تا theme دکمه‌ها را نرنگد (`Frontend/ChatWidget.php`، `assets/frontend/widget.js` و `widget.css`).
  - aliasهای Chat، Handoffs، Forms و EFB با header مشترک؛ برچسب صادقانهٔ وضعیت EFB؛ Store › What it can do؛ Site Knowledge با قالب «x of y» و کارت پرسش‌های بی‌پاسخ با حالت خالی؛ Form Composer با چیپ‌های کوتاه و پیشنهادهای دستیار که فقط composer را پر می‌کنند؛ نشان شمار انتقال‌های «new» روی منوی Answer Desk از دادهٔ واقعی.
  - ترجمهٔ fa_IR: 0 رشتهٔ ترجمه‌نشده در `includes/`، MO با 2024 entry و `msgfmt --check` سالم.
- **گیت‌ها:** `run-gates.ps1 -Tier release` **53/53**: php-lint 213/213، node-check 28/28، PHPCS 130/130 با 0/0، PHPStan 0، PHPUnit 137 / 470، menu 5/0، assets 19/0، render 18/0، Phase 1 45/0 و هر 44 integration suite (از جمله menu badge 6/0 و Form Composer page 4/0). اجرای اول 51/52 بود: attribute تازهٔ `data-sp-form-assistant-chips` زیررشتهٔ `data-sp-form-assistant` بود و شمارش آزمون را دوبرابر کرد؛ به `data-sp-fc-suggestions` تغییر نام یافت. preflight ساعت 07:10 با WARNINGS 0 و BLOCKERS 0.
- **browser (Chrome headless، en_US و fa_IR، 1440 و 390):** همهٔ صفحه‌ها و tabها و aliasها 118 OK بدون overflow، console error یا HTTP خطا؛ ویجت 54/0؛ Setup wizard 106/0 (از جمله پاسخ واقعی 400 `invalid_config`)؛ check هدفمند alias و منو و گوشی 42/0. پاک‌سازی: `Answer Desk browser fixture removed.`، `WPLANG restored.` و `Local browser QA account removed.`. شواهد: `test-artifacts/mockup-parity/` (`plugin/`، `widget-after/`، `setup/`، `alias-check/`، `mockup/`) و `test-artifacts/gates/20260915-070523-release`.
- **آزمون تازه یا به‌روزشده:** `tests/wordpress_answer_desk_menu_badge_integration.php` (تازه، 6/0؛ یک رشتهٔ نشانه‌دار درج و دقیقاً همان حذف می‌شود). `tests/wordpress_form_composer_page_integration.php` به قرارداد تازهٔ چیپ‌ها (3 نمونه در بوم و 3 پیشنهاد دستیار) به‌روز شد و `tests/wordpress_phase1_integration.php` به جای `sp-page-head` روی `sp-pagebar` می‌سنجد.
- **اصلاح هشدار قبلی:** تغییرهای `ChatWidget.php`، `widget.js`، `widget.css`، `Plugin.php`، `aisaautopilot-ui.css` و صفحه‌های admin که در بخش‌های پایین به «نشست دیگر» نسبت داده شده بود، از همین کار هم‌ترازی UI بوده است و اکنون گیت browser و integration دارند.
- **یادداشت:** اسکریپت‌های capture این کار در scratchpad نشست ماندند و وارد repo نشدند؛ دام‌های غیرواضح آن در memory ثبت شد.
- **قدم بعدی:** تصمیم مالک روی بخش ۵ گزارش شکاف (به‌ویژه A-05، A-18 و بستهٔ C-01 تا C-03)؛ سپس `/aisa-next T03`.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۵ — پیشنهادهای تأییدشده اجرا شدند: T26، T27، T28 و اصلاح سند S4.1

- **دستور مالک:** «مواردی که پیشنهاد داده‌ای انجام بده… اول انجام، بعد تست؛ اگر اوکی نبود حلقه تکرار شود». تصمیم‌های ثبت‌شده: D-08 بله؛ D-09 گزینهٔ الف (← T28)؛ D-10 بک‌لاگ فاز ۵؛ D-11 الف برای خواندن و ب برای نوشتن و حذف؛ D-12 ساخته نمی‌شود. D-02 تا D-07 همچنان باز هستند.
- **T28 (تکمیل):** `Logger::log` لاگ‌های هم‌زمان را از دست می‌داد. `tests/wordpress_logger_concurrency_integration.php` پیش از اصلاح 6/2 داد (2 از 3 و 24 از 90 entry). اکنون نوشتن زیر قفل `GET_LOCK` و با خواندن مستقیم ردیف option است و نام option تغییر نکرده است. دو اجرا 6/0 (90 از 90).
- **T26 (تکمیل):** کاتالوگ `4.2.x` شد. در EFB `show_icon` و `show_pro_bar` یعنی «پنهان کن»؛ property نادرست `show_button_icon` حذف و `hide_steps_row` و `hide_progress_bar` جایش آمدند. `steps_style` و `progress_style` افزوده شدند، جدول entry به `emsfb_msg_` اصلاح شد و how-to `multi_step` به‌روز شد.
- **T27 (تکمیل):** Copilot سبک step و progress و نمایش آن‌ها را روی canvas زنده عوض می‌کند. enum در سرور و client سنجیده می‌شود، پیام نتیجه محلی‌شده است (شش msgid تازه، MO با 1235 entry) و پنل تنظیمات باز EFB بدون reload همگام می‌شود. Save فقط با دکمهٔ EFB است. matrix: `en_US` 41/0 و `fa_IR` 41/0؛ preview خود EFB سبک را در LTR و RTL رسم کرد؛ صفر POST ذخیره.
- **سند S4.1:** طبق D-10 تا D-12 و وارسی بازبینی خارجی اصلاح شد (دوازده بند در بخش «اصلاح‌های وارسی»). B-09 در بک‌لاگ ماند و هیچ کد S4.1 نوشته نشد.
- **regression کامل پس از همهٔ تغییرها:** `run-gates.ps1 -Tier release` 52/52: static 5/5 (php-lint 212/212، node-check 28/28، PHPCS 130/130 با 0/0، PHPStan 0، PHPUnit 137 / 470)، menu 5/0، assets 19/0، render 18/0، Phase 1 45/0 و هر 43 integration suite. preflight ساعت 05:59 با WARNINGS 0 و BLOCKERS 0.
- **یافته‌های باز:**
  - `Not applied: …` و `Applied to the canvas…` هنوز انگلیسی‌اند (T03).
  - نام سبک‌ها و «Step 1 of 2 · 50% complete» در preview از ترجمهٔ خود EFB می‌آیند و در `fa_IR` انگلیسی‌اند؛ سمت EFB است.
  - پس از New chat تنها پیام باقی «You selected …» است، نه welcome؛ assertion فعلی فقط تعداد را می‌شمارد (T04).
- **هشدار worktree:** نشست دیگری هم‌زمان روی S3.3 و فایل‌های admin کار می‌کند (`ChatWidget.php`، `widget.js`، `widget.css`، `Plugin.php` و صفحه‌های admin). modified در git از 17 به 32 رسید که 10 فایل آن از این نشست است. regression بالا روی همان worktree سبز بود، اما تغییرهای آن نشست گیت browser خود را ندارند.
- **فایل‌های این نشست:** `includes/Logging/Logger.php`، `includes/Efb/efb-capability-catalog.json`، `includes/Efb/efb-knowledge.json`، `includes/Efb/EfbBuilderButton.php`، `assets/admin/efb-copilot.js`، PO و MO فارسی، `tests/EfbCapabilityCatalogTest.php`، `tests/efb_copilot_browser_check.cjs`، `tests/wordpress_logger_concurrency_integration.php` (تازه)، و اسناد رودمپ، plan S3.2، سند S4.1 و همین handoff.
- شواهد: `test-artifacts/logger-concurrency/20260915-t28/`، `test-artifacts/efb-copilot/20260915-t27/`، `test-artifacts/gates/20260915-053701-static`، `test-artifacts/gates/20260915-054818-release`.
- **قدم بعدی:** `/aisa-next T03`. تصمیم‌های باز: D-02 تا D-07.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۵ — بازبینی خارجی S4.1 (B-09) وارسی شد

- **چه شد:** نشست دیگری از Claude Code (با ریشهٔ repo خود EFB) این‌ها را نوشت:
  - `docs/EFB_AGENT_ABILITIES_S4_1_AUDIT_AND_PLAN_FA.md`، پاسخی به قابلیت agent-callable که Gravity Forms در ۲۰۲۶-۰۸-۲۶ عرضه کرد؛
  - ردیف بک‌لاگ B-09 و تصمیم‌های D-10، D-11 و D-12 در رودمپ؛
  - ارجاع رو به جلو در `docs/EFB-INTEGRATION-ROADMAP.md`.
  - هر سه از قبل در commit `459bfd8` («T01») هستند و در `git diff` دیده نمی‌شوند.
- **آنچه این نشست کرد:** فقط وارسی و همگام‌سازی. S4.1 آغاز نشد و هیچ کد، تست، تنظیم، ردیف B-09، ردیف D یا سند plan تغییر نکرد. خط ژورنال تاریخ‌دار در رودمپ و همین بخش افزوده شدند.
- **سلامت:** preflight ساعت 03:24 با 0/0. جدول‌های markdown سالم‌اند (رودمپ 5 جدول و 64 ردیف، سند S4.1 دو جدول و 21 ردیف، بدون ناهمخوانی ستون) و preflight هنوز 28 ردیف T و next=T03 را می‌خواند.
- **ادعاهای درست سند:**
  - `EasyFormBuilderConnector::get_capabilities()` فقط `'form.create'` برمی‌گرداند و `ActionExecutor.php:59` آن را hardcode کرده است.
  - کاتالوگ هنوز `efb_version_target: "4.1.x"` است. سند S4.1 به T26 اشاره نمی‌کند؛ وابستگی باید با D-10 افزوده شود.
  - `wp_register_ability` در WP 7.1.1-RC1 هست.
  - نگاشت `emsfb_pro` با `get_license_type()` خود EFB یکی است.
- **اصلاح‌های لازم پیش از اجرای S4.1** (جزئیات و file:line در ژورنال رودمپ):
  - جدول پیام EFB `emsfb_msg_` است، نه `emsfb_message`.
  - نام ability نقطه نمی‌پذیرد و `category` و hook `wp_abilities_api_init` لازم است.
  - `EfbLicense` موجود همان گیت است و `EfbLicenseGate` تکراری است.
  - `RateLimiter` در `/efb/*` نیست.
  - `ActionStore` فیلد capability ندارد.
  - toggleها باید کلید `Settings` باشند.
  - option `emsfb_pro` پس از غیرفعال‌سازی EFB می‌ماند و گیت باید با `is_available()` ترکیب شود.
  - برای Pro، مرجع خود EFB `is_efb_pro()` است (بررسی کد با دامنه)، نه option خام؛ تست `test-addon-install-plan-gate.php:112` خود EFB همین را می‌گوید. پس «فقط `get_option`» برای Pro کافی نیست و با قید «بدون فراخوانی کلاس EFB» سند تناقض دارد (مرتبط با D-11).
  - **Free Plus را هر مدیر EFB رایگان و بدون کد انتخاب می‌کند**، پس گیت Free Plus/Pro دیوار پرداخت نیست.
- **گیت پلن:** سایت محلی واقعاً Pro است (`emsfb_pro="1"`). مقدارهای Free، Free Plus و بقیه فقط درون پردازش با `pre_option_emsfb_pro` شبیه‌سازی شدند (صفر نوشتن) و فقط 1 و 3 گیت را باز کردند. تأیید دستی Free و Free Plus روی نصب واقعی انجام نشد، چون پلن EFB مالک را عوض می‌کند، و بخشی از گیت S4.1.0 باقی است.
- **هشدار محیط:** preflight ساعت 03:32 یک کاربر QA باقی‌مانده (ثبت 03:30:55) و option `aisaautopilot_local_browser_user` نشان داد. هم‌زمان با `plugin_capture.cjs` و `npm run test:browser` یک نشست دیگر پدید آمدند، نه این نشست. حذف نشدند. پیش از هر browser run تازه با preflight بررسی شوند و اگر آن نشست تمام شده بود، با `tests/local_browser_user.php delete` پاک شوند.
- **هشدار worktree:** در همین بازه `assets/admin/aisaautopilot-ui.css` هم تغییر کرد (mtime 03:40:13، 12 خط): selectorهای `.sp-pagebar__title`، `.sp-pagebar__sub` و `.sp-subtabs__tab` با پیشوند `.sp-app`، رنگ muted با token و یک focus ring. کار نشست دیگری است، نه این نشست. revert نشد و هنوز گیت یا browser check ندارد؛ پیش از بستن S3.2 (T05) در regression کامل دیده شود.
- شواهد: `test-artifacts/efb-agent-abilities/20260915-external-review/`.
- **تصمیم‌های باز مالک:** D-10 (اولویت S4.1 نسبت به ۱.۱.۰)، D-11 (سطح پرداختی جدا یا کافی بودن گیت EFB)، D-12 (`efb.entries.generate_test` بدون فلگ آزمایشی)، به‌علاوهٔ D-02 تا D-09.
- **قدم بعدی:** `/aisa-next T03`. T26 هم اجراپذیر است. B-09 فقط پس از تصمیم مالک وارد صف می‌شود.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۵ — T02 بسته شد

- فایل تازه: `tests/wordpress_efb_copilot_contract_integration.php`. کد محصول، تنظیمات، کلید، budget و افزونه‌ها تغییر نکردند.
- نتیجه: run1 و run2 هر دو `15 checks, 0 failed` با exit 0. run-gates `php-lint` 211/211. preflight ساعت 03:20 با WARNINGS 0 و BLOCKERS 0.
- پاک‌سازی تأیید شد: کاربر موقت 0 و رکورد نشانه‌دار `t02contract` در لاگ 0؛ option تنظیمات و کلید KeyVault همچنان absent؛ budget همان 1200.
- آنچه سنجیده شد:
  - `/efb/log` روی `client_log` ثبت شده و callable است؛ همهٔ routeهای `/efb/*` callback و permission callable دارند.
  - client log مدیر 200 می‌دهد و یک رکورد پاک‌سازی‌شده ثبت می‌کند؛ نبود event 400 `invalid_payload` می‌دهد، نه 500.
  - subscriber و مدیر با `efb_copilot_enabled=false` رد می‌شوند، بدون لاگ و بدون درخواست provider. فعال‌سازی دوباره 200 می‌دهد.
  - `selected_field` معتبر با label و type از catalogue پاک‌سازی‌شده به prompt واقعی می‌رسد، نه از داده‌های client. ID ناموجود، شیء selection ساختهٔ client و حالت بدون فرم باز به prompt نمی‌رسند.
- روش بدون provider واقعی:
  - `pre_http_request` هر درخواست را قطع می‌کند و فقط برای host رزروشدهٔ `.invalid` پاسخ canned می‌دهد و body را در حافظه می‌خواند.
  - کلید placeholder موقت در KeyVault بر کلید Connectors مقدم است، پس کلید واقعی لمس نشد.
  - budget فقط در همان پردازش 0 شد. هر 12 option پیش از تغییر snapshot، در `finally` restore و سپس با snapshot مقایسه شدند.
- D-09 باز است: نوشتن ترتیبی لاگ سالم است، ولی این suite هم‌زمانی را بازتولید نمی‌کند.
- **هشدار:** این suite مانند بقیهٔ integrationها `aisaautopilot_logs` را snapshot و restore می‌کند. رکوردی که سایت زنده در همان چند ثانیهٔ اجرا بنویسد، با restore از بین می‌رود. آن را هم‌زمان با browser run اجرا نکنید.
- شواهد: `test-artifacts/efb-copilot/20260915-t02/` (run1، run2، preflight-after) و `test-artifacts/gates/20260915-031830-only`.
- **قدم بعدی:** `/aisa-next T03` (localization باقی‌ماندهٔ `efb-copilot.js`). T26 هم اجراپذیر است. تصمیم‌های باز: D-08 و D-09.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۵ — T01 بسته شد

- **پاسخ به پرسش مالک («چرا می‌گویی EFB فعال نیست؟»):** EFB فعال است و از اجرای دوم T00 (ساعت 01:19) همه‌جا فعال ثبت شده است.
  - شاهد تازه: preflight ساعت 01:51 و 02:14 `efb v4.2.0 active=True` داد؛ مقدار خام `active_plugins` شامل `easy-form-builder/emsfb.php` است؛ `is_plugin_active` true است و جدول `emsfb_form` وجود دارد.
  - جملهٔ «غیرفعال» فقط در بخش snapshot ۲۰۲۶-۰۹-۱۴ رودمپ و در سابقهٔ اجرای اول T00 مانده بود. در اجرای اول (ساعت 00:30) افزونه واقعاً در DB نبود. آن بخش رودمپ اکنون «منسوخ» علامت خورده است.
- matrix EFB Copilot با exit 0 اجرا شد: `en_US` 31 PASS / 0 FAIL و `fa_IR` 31 PASS / 0 FAIL. پاک‌سازی با `EFB Copilot browser fixture removed.` و `Local browser QA account removed.` تأیید شد. preflight ساعت 02:14 WARNINGS 0 و BLOCKERS 0 داد.
- **ریشهٔ 429:** `usage_cap_reached` خود افزونه؛ مسیر `UsageCappedProvider` و `ProviderException::status_code()` که 429 برمی‌گرداند. هیچ افزونهٔ بیرونی در این خطا نقش ندارد.
  - روی این سایت یک کلید واقعی provider (deepseek) در WordPress Connectors ذخیره است، پس `is_configured()` true است.
  - سقف مصرف محلی پر شده است: budget 1200 token و مصرف 1800.
  - بنابراین فرض قدیمی «QA همیشه `provider_not_configured` می‌گیرد» نادرست بود و فقط سقف مصرف مانع تماس با provider واقعی بود.
- تغییر فقط در `tests/efb_copilot_browser_check.cjs` است و کد محصول، تنظیمات، کلید، budget یا افزونه‌ها تغییر نکردند:
  - `/efb/chat` با `page.route()` و پاسخ 400 `provider_not_configured` کنترلر mock شد.
  - check پیش از شمارش روی wrapper صبر می‌کند.
  - پاسخ 200 `/efb/log` assert می‌شود؛ نتیجه در هر دو locale `[200,200]` بود.
  - کلیک واقعی روی کنترل درونی `-el` assert می‌شود. input `pointer-events:none` دارد و کلیک به `div#<id>-f` درون wrapper می‌رسد، پس انتخاب کار می‌کند و اصلاح bridge لازم نیست.
  - `getUserMedia` برای نتیجهٔ قطعی stub شد؛ پیام fallback، نبود auto-send و نبود upload assert می‌شوند.
  - login دیگر منتظر DOM داشبورد نمی‌ماند.
- یافته‌های باز:
  - **D-09:** در baseline، رکورد `efb.client.chat_submitted` هم‌زمان با درخواست chat در `aisaautopilot_logs` ثبت نشد. فرضیه: lost update در option. پیشنهاد: task کوچک پس از T02.
  - انتخاب دوبارهٔ همان فیلد پس از Clear پیام «You selected …» تکراری می‌سازد؛ برای T04.
- شواهد: `test-artifacts/efb-copilot/20260915-t01/` (baseline، run1 تا run4، final، preflight-after).
- **هشدار محیط:**
  - کلید واقعی provider در WordPress Connectors است و سقف مصرف پر است. هر تست تازه‌ای که به مسیر AI می‌رسد باید route را mock کند و به کلید یا budget دست نزند.
  - سایت کند است: صفحهٔ اول 12.6 ثانیه و بارگذاری داشبورد گاهی بیش از 30 ثانیه.
- **قدم بعدی:** `/aisa-next T02`. T03 و T26 هم اجراپذیرند. تصمیم‌های باز: D-08 و D-09.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۵ — T00 بسته شد

- هستهٔ EFB واقعاً فعال است: preflight ساعت 01:19 و 01:33 `efb v4.2.0 active=True` با WARNINGS 0 و BLOCKERS 0 داد؛ wp-probe: `is_plugin_active` true، جدول `emsfb_form` موجود، فرم QA، کاربر و option باقی‌مانده صفر. D-01 بسته شد.
- probe فقط‌خواندنی runtime روی EFB فعال (HEAD `031bfa75`) با fixture EFB و کاربر موقت `en_US` اجرا شد: exit 0، 19 PASS، صفر pageerror، console error و FAILED_RESPONSES. HTTP 200 هر دو صفحه، `valj_efb` واژگانی، `editFormEfb` function، 6 wrapper `-id`، `field-selected-efb`، و انتخاب bridge و banner پاس شدند. قرارداد bridge تغییر نکرده است.
- cleanup با این دو خط تأیید شد: `EFB Copilot browser fixture removed.` و `Local browser QA account removed.` preflight بعدی هم باقی‌مانده‌ای نشان نداد.
- **یادداشت مالک دربارهٔ فهرست فیلدها و UI تازهٔ multi-step و progress bar بررسی شد:**
  - palette `fields_efb` همان 48 نوع ۲۰۲۶-۰۸-۱۷ است و همه در کاتالوگ افزونه (68 نوع) هستند. در source EFB **نوع فیلد تازه‌ای پیدا نشد**.
  - تغییر واقعی commit `097de0b8` است: کلیدهای سطح فرم `steps_style` (`classic/circles/pills/chevrons`) و `progress_style` (`classic/bar/segments/ring`) با assetهای تازه. canvas ویرایش همچنان step را با `.efbField.stepNavEfb` رسم می‌کند، پس bridge آسیب نمی‌بیند.
  - کاتالوگ `includes/Efb/efb-capability-catalog.json` این دو کلید را ندارد، `show_icon` و `show_pro_bar` را اشتباه توصیف می‌کند (در EFB، `true` یعنی پنهان کردن ردیف step و progress bar) و هنوز `4.1.x` است.
  - T26 (همگام‌سازی کاتالوگ و دانش، منتظر) و T27 (operation نوشتنی سبک‌ها، نیازمند تصمیم D-08) افزوده شدند و T04 به T26 وابسته شد.
- کلیک Playwright روی input درونی (`-el`) هم فیلد را انتخاب کرد؛ ریسکی که از source دیده شده بود در عمل بازتولید نشد. assertion دائمی آن در T01 می‌ماند.
- هیچ کد، تست، تنظیم یا افزونه‌ای تغییر نکرد. probe در scratchpad بود و وارد repo نشد. شواهد: `test-artifacts/efb-env/20260915-t00-runtime/`.
- **قدم بعدی:** `/aisa-next T01` (browser matrix در `en_US` و `fa_IR`، ریشه‌یابی 429، assertion کلیک `-el`). T02، T03 و T26 هم اجراپذیرند.
- **پرسش باز از مالک:** اگر منظور از «فهرست فیلدهای به‌روزشده» چیزی جز لایهٔ steps/progress است (مثلاً نوع فیلدی در add-on Pro یا نسخه‌ای از EFB که هنوز روی این دیسک نیست)، مسیر یا commit آن را بدهید؛ source فعلی چنین تغییری ندارد. D-08 نیز باز است.

## به‌روزرسانی ۲۰۲۶-۰۹-۱۵ — T00 (اجرای اول، مسدود؛ جایگزین‌شده با بخش بالا)

- مالک D-01 را تأیید کرد و اعلام کرد هستهٔ EFB را فعال کرده است. **شواهد runtime این را نشان نمی‌دهند:** preflight ساعت 00:30 همچنان `efb v4.2.0 active=False` داد؛ `is_plugin_active('easy-form-builder/emsfb.php')` برابر false است و مقدار خام `active_plugins` در DB این افزونه را ندارد؛ `recently_activated` خالی است.
- علت خرابی دیده نشد: در `wp-content/debug.log` خطای Fatal نیست، `php -l` روی `emsfb.php`، `includes/class-Emsfb.php` و `includes/functions.php` سالم است و `vendor/autoload.php` هست. `wp-content/object-cache.php` drop-in خود Plugin Check است و cache واقعی نیست. محتمل‌ترین حالت: فعال‌سازی کامل نشده یا روی نصب دیگری انجام شده است.
- بخش فقط‌خواندنی T00 انجام شد: قرارداد DOM روی source نسخهٔ 4.2.0 با `file:line` در `docs/EFB_COPILOT_S3_2_AUDIT_AND_PLAN_FA.md` ثبت شد. شناسه‌ها تغییر نکرده‌اند (`let valj_efb` در `new-efb.js:16`، wrapper `.efbField[data-id="<id>-id"]` در `admin-efb.js:7162/7164`، `field-selected-efb`، slugهای `Emsfb`/`Emsfb_create`)، پس task اصلاح bridge لازم نیست. تنها تغییر 4.2.0 اصلاح تگ `<setion>` به `<section>` است.
- ریسک برای T01: کنترل‌های درونی فیلد هم کلاس `efbField` با `data-id="<id>-el"` دارند؛ bridge در `efb-copilot.js:148-151` روی آن‌ها متوقف می‌شود و کلیک مستقیم روی input انتخاب نمی‌کند. در browser run سنجیده شود.
- هیچ کد، تست، تنظیم یا افزونه‌ای تغییر نکرد. شواهد: `test-artifacts/efb-env/20260915-t00/` (preflight، activation-probe، efb-dom-contract).
- **قدم بعدی:** مالک از `wp-admin/plugins.php` هستهٔ «Easy Form Builder» را فعال کند و پیام موفقیت را ببیند، سپس `/aisa-next T00` تا preflight بدون هشدار EFB و بستن T00. تا آن زمان task اجراپذیر بعدی T02 است (به EFB فعال وابسته نیست).

## به‌روزرسانی ۲۰۲۶-۰۹-۱۴ — ایجنت اجرایی و رودمپ

- ادامهٔ کار با ایجنت `aisa-finisher` است (`C:\files\projects\SitePilot AI\.claude\agents\aisa-finisher.md`): `/aisa-status` برای وضعیت (فقط‌خواندنی)، `/aisa-next` برای task بعدی، `/aisa-next T02` برای یک task مشخص و `/aisa-next ادامه` برای اجرای پیاپی تا رسیدن به مانع.
- صف کامل تا انتشار، تصمیم‌های مالک و ژورنال: `docs/PRODUCT_COMPLETION_ROADMAP_FA.md`. قدم‌های ۱ تا ۶ پایین همین فایل به T01 تا T06 نگاشت شده‌اند؛ S3.3 از T07 و فاز ۴ و انتشار از T15 تا T25 است.
- ابزارها در `C:\files\projects\SitePilot AI\scripts\agent\`: `preflight.ps1` (محیط و task بعدی)، `run-gates.ps1` (گیت‌ها با شواهد در `test-artifacts/gates/`)، `wp-probe.php` و `guard.cjs` (hook ایمنی ایجنت).
- **مانع تازه برای قدم ۱:** هستهٔ Easy Form Builder (`easy-form-builder/emsfb.php` نسخهٔ 4.2.0) روی سایت محلی غیرفعال است، در حالی که add-onهای آن فعال‌اند. تا مالک آن را فعال نکند (D-01)، browser matrix EFB اجرا نمی‌شود. قدم ۲ (integration) و قدم ۳ (localization) به آن وابسته نیستند؛ task بعدی T02 است.
- گیت‌ها در همان روز سبز بودند: php-lint 210/210، node-check 28/28، PHPCS 130/130 بدون error و warning، PHPStan 0، PHPUnit 135 / 442؛ smokeهای admin: menu 5/0، assets 19/0، render 18/0 بدون PHP diagnostic.
- `scripts\sync-to-xampp.ps1` و `watch-and-sync.ps1` در ریشهٔ workspace مربوط به دوران پیش از junction هستند و نباید اجرا شوند.
- `scripts/build-phase1-release.ps1` پوشهٔ `test-artifacts/` افزونه را exclude نمی‌کند و `handoff.md` در root، allow-list ریشهٔ ZIP را می‌شکند؛ پیش از ساخت ZIP در T22 اصلاح می‌شود.

## هدف کاربر

افزونه WordPress باید براساس موکاپ‌های محلی، صفحه‌به‌صفحه و با قرارداد فنی واقعی بازطراحی شود. ترتیب مورد تأیید کاربر:

1. رفع PHPCS/PHP warning و اتصال WordPress محلی به همین source.
2. پیاده‌سازی موکاپ، صفحه‌به‌صفحه.
3. پس از هر صفحه: تست عملکرد، RTL و responsive؛ در پایان visual QA کامل.

هر مرحله باید به ریزتسک تقسیم شود و تا گیت تست همان مرحله سبز نشده، مرحله بعد آغاز نشود. قابلیت‌های واقعی افزونه که موکاپ برایشان UI ندارد باید در گزارش MD ثبت شوند؛ UI جعلی برای backend ناموجود ساخته نشود.

## مسیرها و محیط

- workspace: `C:\files\projects\SitePilot AI`
- source افزونه: `C:\files\projects\SitePilot AI\apps\wordpress-plugin\ai-site-assistant-autopilot`
- موکاپ canonical: `C:\files\projects\SitePilot AI\mockups\design_handoff_admin_redesign`
- موکاپ EFB: `EFB Builder Chat.dc.html`
- WordPress محلی: `C:\xampp\htdocs\wp`
- wordpress login username: admin
- wordpress login password: admin
- PHP: `C:\xampp\php\php.exe`
- Chrome: `C:\Program Files\Google\Chrome\Application\chrome.exe`
- WordPress plugin directory قبلاً تأیید شده که Junction به همین source است.
- `node_modules` همین افزونه دارای `playwright-core` است.
- shell محیط PowerShell است؛ برای متن فارسی از `Get-Content -Encoding utf8` استفاده شود.

برای Git در این محیط از safe.directory محلی استفاده شود:

```powershell
git -c safe.directory='C:/files/projects/SitePilot AI/apps/wordpress-plugin/ai-site-assistant-autopilot' status --short
```

## هشدار حیاتی درباره worktree

worktree بسیار dirty است و بخش بزرگی از تغییرها متعلق به مسیر طولانی همین پروژه یا کاربر است. هیچ reset/checkout/clean انجام نشود و فایل نامرتبط بازنویسی نشود. هیچ commit ساخته نشده است. فقط تغییرهای درون scope بررسی و ویرایش شوند.

## وضعیت فازهای تکمیل‌شده

- فاز ۰ baseline/PHPCS/اتصال source: تکمیل.
- فاز ۱ shell/navigation ده‌صفحه‌ای: تکمیل.
- فاز ۲ صفحات wp-admin شامل Overview، Setup، Integrations، Site Knowledge، Answer Desk، Store Copilot، Content Studio، Usage، Health و Activity Log: تکمیل و گزارش‌شده.
- S3.1 Form Composer: تکمیل با compose/review/result، approval و publish confirmation، RTL/390px و regression کامل.
- S3.2 EFB Copilot: تکمیل ۲۰۲۶-۰۹-۱۷ (گزارش: `docs/EFB_COPILOT_S3_2_EXECUTION_REPORT_FA.md`).
- S3.3 Front Chat Widget: هنوز آغاز نشده؛ بعدی T07.
- فاز ۴ final QA/release: هنوز انجام نشده.

ردیاب اصلی:

- `docs/MOCKUP_REDESIGN_TASK_TRACKER_FA.md`

گزارش‌های قبلی مهم:

- `docs/FORM_COMPOSER_S3_1_EXECUTION_REPORT_FA.md`
- `docs/EFB_COPILOT_S3_2_EXECUTION_REPORT_FA.md`
- `docs/ACTIVITY_LOG_QA_INCIDENT_FA.md` — رخداد حذف ناخواسته buffer قدیمی لاگ و غیرقابل‌بازیابی بودن تاریخچه را پنهان نکنید.

## تصمیم معماری S3.2

موکاپ EFB یک ویرایشگر نمایشی را برای سناریوها ساخته است. محصول نباید آن ویرایشگر را بازسازی کند؛ سطح canonical، FAB و پنجره Copilot تزریق‌شده روی **ویرایشگر واقعی Easy Form Builder** در `page=Emsfb`/`page=Emsfb_create` است.

سناریوسوییچر، داده‌های نمونه و builder جعلی موکاپ وارد محصول نمی‌شوند. صفحه پنهان compatibility افزونه (`EasyFormBuilderPage`) حفظ می‌شود ولی عضو منوی اصلی نیست.

گزارش ممیزی و برنامه S3.2:

- `docs/EFB_COPILOT_S3_2_AUDIT_AND_PLAN_FA.md`

این گزارش نگاشت موکاپ، قابلیت‌های افزونه بدون UI موکاپ و ریزتسک‌های S3.2.0 تا S3.2.6 را دارد.

## تغییرهای انجام‌شده در S3.2

### 1. قرارداد selected-field

فایل‌ها:

- `includes/Efb/EfbChatSkill.php`
- `includes/Api/EfbCopilotController.php`
- `tests/EfbChatSkillTest.php`
- `tests/bootstrap.php`

رفتار جدید:

- مرورگر فقط شناسه فیلد انتخاب‌شده را با `selected_field` می‌فرستد.
- controller این شناسه را با فهرست پاک‌سازی‌شده `fields` تطبیق می‌دهد؛ label/type از client selection پذیرفته نمی‌شوند.
- فقط انتخاب معتبر به شکل `SELECTED_FIELD: id=... label=... type=...` وارد prompt می‌شود.
- وقتی فرم باز نیست، context انتخاب‌شده وارد prompt نمی‌شود.
- bootstrap تست اکنون mock استاندارد `get_locale()` دارد.

### 2. بازطراحی UI روی EFB واقعی

فایل‌ها:

- `includes/Efb/EfbBuilderButton.php`
- `assets/admin/efb-copilot.js`
- `assets/admin/efb-copilot.css`

انجام‌شده:

- FAB navy با shield/ring و عنوان `Ask AI Site Assistant`.
- پنجره canonical با عرض 388 و ارتفاع 576، radius 18، gradient header، actions، messages، chips و composer مطابق ساختار موکاپ.
- selection banner با `Working on <label>` و Clear.
- bridge انتخاب فیلد روی DOM واقعی EFB از `.efbField[data-id="<id>-id"]`.
- highlight فیلد واقعی بدون تغییر کد EFB.
- انتخاب فیلد، پنجره را باز می‌کند ولی focus ویرایشگر را نمی‌دزدد.
- Clear، context و highlight را حذف می‌کند.
- reset تاریخچه را پاک می‌کند و فقط یک welcome message می‌سازد.
- Escape پنجره را می‌بندد و focus را به FAB بازمی‌گرداند.
- payload چت دارای selected ID و فهرست live fields/steps است.
- help/preview/Pro/operation rendererهای واقعی حفظ شده‌اند و Pro CTA از flow رسمی EFB استفاده می‌کند.
- responsive در 390px پنجره را داخل viewport نگه می‌دارد؛ RTL با logical properties پیاده شده است.
- `prefers-reduced-motion` اضافه شده است.

### 3. اصلاح صداقت voice

نسخه قبلی دکمه‌ای با عنوان «live voice conversation» داشت که فقط Web SpeechRecognition بود و پس از مکث خودکار command را ارسال می‌کرد؛ realtime transport/session واقعی وجود ندارد.

اکنون:

- کد realtime/auto-send حذف شده است.
- دکمه voice در header و mic در composer هر دو فقط ضبط/STT موجود را آغاز/متوقف می‌کنند.
- transcript داخل composer قابل بازبینی است و هرگز خودکار send نمی‌شود.
- وضعیت ضبط/transcribe به‌صورت visible و `aria-live` نمایش داده می‌شود.
- full-screen voice orb موکاپ عمداً پیاده نشده و دلیل آن در گزارش ممیزی ثبت شده است.

### 4. ترجمه

فایل‌ها:

- `languages/ai-site-assistant-autopilot-fa_IR.po`
- `languages/ai-site-assistant-autopilot-fa_IR.mo`
- `scripts/compile-mo.php`

رشته‌های پوسته، welcome/chips، selected-field، preview actions و voice اصلی فارسی شده‌اند. MO با اسکریپت جدید و POMO خود WordPress کامپایل شد:

```text
1229 translated entries compiled.
```

توجه: همه پیام‌های قدیمی و ریزِ `applyOps()` هنوز localize نشده‌اند؛ قبل از بستن S3.2 باید تمام متن‌های قابل‌نمایش باقی‌مانده در `assets/admin/efb-copilot.js` به `cfg.i18n` منتقل و فارسی شوند. متن‌های داخلی `clientLog` لازم نیست UI ترجمه شوند.

### 5. fixture و browser QA

فایل‌ها:

- `tests/local_efb_copilot_browser_fixture.php`
- `tests/efb_copilot_browser_check.cjs`
- `tests/efb_copilot_browser_matrix.cjs`

fixture:

- تنظیم کامل قبلی افزونه را نگه می‌دارد.
- EFB Copilot را موقتاً فعال می‌کند.
- فقط یک فرم با نام دقیق `AISAAutoPilot EFB Copilot browser QA` می‌سازد.
- هنگام restore فقط همان form ID را بعد از تطبیق دوباره نام حذف می‌کند.
- تنظیم قبلی را کامل restore می‌کند.
- matrix برای `en_US,fa_IR` حساب مدیر موقت می‌سازد و در `finally` حذف می‌کند.

### 6. باگ REST واقعی کشف و اصلاح‌شده

در `includes/Api/RestRoutes.php` مسیر `/efb/log` اشتباهاً callback ناموجود `EfbCopilotController::log` را ثبت می‌کرد و درخواست‌های client log با HTTP 500 می‌شکستند. callback به متد واقعی `client_log` تغییر کرد.

این اصلاح بعد از آخرین browser run انجام شد و **هنوز در browser matrix نهایی تأیید نشده است**. یک integration assertion برای callback/dispatch این route نیز باید اضافه شود تا regression برنگردد.

## آخرین نتایج تست

### گیت استاتیک فعلی — سبز

در ۲۰۲۶-۰۹-۱۱ پس از همه تغییرهای بالا اجرا شد:

- Node syntax:
  - `assets/admin/efb-copilot.js`
  - `tests/efb_copilot_browser_check.cjs`
  - `tests/efb_copilot_browser_matrix.cjs`
  - هر سه پاس.
- PHP syntax برای BuilderButton، ChatSkill، Controller، RestRoutes، fixture و compiler: همه پاس.
- PHPUnit متمرکز: `tests/EfbChatSkillTest.php` = **2 tests / 3 assertions / OK**.
- PHPCS متمرکز روی ۷ فایل PHP S3.2 = **5/5، بدون error یا warning**.
- PHPStan روی ۴ فایل PHP runtime S3.2 = **No errors**.

### browser run اول

اولین اجرا به دلیل یک خطای خود تست timeout شد: EFB آرایه `valj_efb` را با global lexical (`let`) تعریف می‌کند، نه `window.valj_efb`. شرط تست به `typeof valj_efb !== 'undefined'` اصلاح شد. fixture و account پاک شدند.

### browser run دوم (en_US، قبل از آخرین اصلاح‌ها)

موارد پاس:

- HTTP 200 ویرایشگر واقعی EFB.
- FAB اولیه و welcome واقعی.
- کلیک فیلد، banner، label و highlight.
- Clear و حذف highlight.
- selected ID در payload و حضور همان ID در live catalogue.
- voice بدون auto-send.
- reset.
- Escape و focus return.
- پنجره داخل viewport در 390px، بدون overflow داخلی.
- جهت پنجره و نبود pageerror.

چهار assertion قدیمی شکست خوردند و بعد از run اصلاح/ریشه‌یابی شدند:

1. شمارش generic `.efbField` انتظار ۳ داشت؛ تست اکنون ابتدا سه ID را از state واقعی می‌گیرد و فقط وجود دست‌کم یک field renderشده از همان IDها را می‌سنجد.
2. جهت با attribute خام `html[dir]` سنجیده می‌شد؛ اکنون computed direction سنجیده می‌شود.
3. ابعاد هنگام animation اندازه‌گیری می‌شد؛ اکنون 220ms صبر و tolerance سه پیکسل دارد.
4. console خطاهای 500/429 داشت. 500 از callback اشتباه `/efb/log` بود و اصلاح شد. تست اکنون URL/status responseها را نیز گزارش می‌کند. علت 429 باید در اجرای بعدی با URL دقیق بررسی شود؛ آن را بدون شاهد ignore نکنید.

آخرین اجرای تشخیصی بعد از این اصلاح‌ها توسط کاربر interrupt شد؛ نتیجه قابل استناد ندارد. سپس fixture/account موقت جداگانه restore/delete شدند.

artifact فعلی:

- `C:\files\projects\SitePilot AI\test-artifacts\efb-copilot\en_US`

### browser matrix T01 (۲۰۲۶-۰۹-۱۵) — جایگزین نتایج بالا

- exit 0؛ `en_US` 31 PASS / 0 FAIL و `fa_IR` 31 PASS / 0 FAIL.
- 429 ریشه‌یابی شد: `usage_cap_reached` خود افزونه (بخش T01 در بالای همین فایل).
- artifact: `C:\files\projects\SitePilot AI\test-artifacts\efb-copilot\20260915-t01\final\<locale>\`

تصویر `efb-copilot-chat-desktop.png` بصری بررسی شد: پنجره جدید روی ویرایشگر واقعی با هدر، welcome/chips و composer درست render می‌شود. full-page screenshot به‌خاطر رفتار/ارتفاع خود EFB بسیار بلند است؛ این عیب پنجره Copilot نیست، اما QA نهایی باید screenshot محدود به viewport یا locator panel نیز بگیرد تا مقایسه تمیزتر شود.

## وضعیت cleanup

پس از interrupt به‌صورت صریح اجرا شد:

```text
EFB Copilot browser fixture removed.
Local browser QA account removed.
```

یک پردازش Node با PID مشاهده شد، اما command آن `tests/test-onboarding-wizard-browser.js` و خارج از این task است؛ متوقف نشود.

## ترتیب دقیق ادامه کار

### قدم 1 — browser matrix فعلی را دوباره اجرا کن (T01 — تکمیل ۲۰۲۶-۰۹-۱۵)

> انجام شد. نکتهٔ پایین این بخش دربارهٔ «provider واقعی تنظیم نشده» منسوخ است: روی این سایت کلید واقعی در WordPress Connectors هست و check اکنون `/efb/chat` را mock می‌کند. matrix همچنان برای regression با همان دستور اجرا می‌شود.

از root افزونه، با مجوز دسترسی WordPress محلی:

```powershell
node tests/efb_copilot_browser_matrix.cjs
```

انتظار: هر دو `en_US` و `fa_IR` پاس، cleanup نهایی پاس. اگر 429/500 باقی ماند، `FAILED_RESPONSES` اکنون URL دقیق را چاپ می‌کند. خطای وابستگی/asset خود EFB را از خطای افزونه تفکیک کن، اما خطای `/aisaautopilot/v1/efb/*` را ignore نکن.

نکته: درخواست عمدی `/efb/chat` در QA چون provider واقعی تنظیم نشده با 400 `provider_not_configured` برمی‌گردد و فقط همان 400 در تست مجاز است. تست برای جلوگیری از هزینه/تماس خارجی credential اختراع نمی‌کند.

### قدم 2 — route و selected-field را integration-test کن

یک تست WordPress محلی مستقل اضافه کن، مثلاً:

- `tests/wordpress_efb_copilot_contract_integration.php`

حداقل assertions:

- `/aisaautopilot/v1/efb/log` ثبت شده و callback callable است.
- dispatch معتبر client log با nonce/capability داخلی test process پاسخ 200 می‌دهد و 500 نمی‌دهد.
- `selected_field` ناموجود یا خارج از `fields` به prompt راه پیدا نمی‌کند.
- selected ID معتبر با label/type گرفته‌شده از catalogue به skill می‌رسد.
- gate خاموش بودن `efb_copilot_enabled` permission را می‌بندد و restore انجام می‌شود.

هیچ provider یا سرویس خارجی در تست فراخوانی نشود.

### قدم 3 — localization باقی‌مانده UI

در `assets/admin/efb-copilot.js` همه stringهای قابل‌نمایش را پیدا کن، به‌ویژه:

- apply operation resultها (`Made`, `Removed`, `Added`, `Updated`).
- no-operation/render failure/save ownership note.
- open-form-first و generic fallbackها.
- preview `Failed` و پاسخ‌های local network.
- help history متن داخلی فقط اگر روی UI نمایش می‌یابد.

رشته‌های لازم را به `EfbBuilderButton::$i18n` و PO اضافه کن، سپس:

```powershell
& 'C:\xampp\php\php.exe' scripts/compile-mo.php 'C:\xampp\htdocs\wp' languages/ai-site-assistant-autopilot-fa_IR.po languages/ai-site-assistant-autopilot-fa_IR.mo
```

در Persian browser run بررسی کن که shell، welcome، chips، banner، placeholder و action labels واقعاً فارسی‌اند؛ فقط direction کافی نیست.

### قدم 4 — تکمیل browser/visual QA

افزون بر assertions فعلی:

- screenshot خود panel با `locator.screenshot()` برای fab/chat/selected و mobile بگیر.
- selected banner را در screenshot ثبت کن.
- حالت provider-not-configured را بدون ادعای موفقیت کنترل کن.
- help card و Pro card را بدون provider خارجی با پاسخ mock شده در سطح browser renderer یا contract test کنترل کن؛ CTA نباید ناخواسته navigation واقعی انجام دهد.
- keyboard Tab، Enter، Shift+Enter، Escape و focus ring را بررسی کن.
- mobile در 390×844 و RTL واقعی `fa_IR`.
- هیچ request هنگام open/chip prefill رخ ندهد؛ فقط submit صریح REST بزند.
- mic هرگز command را خودکار ارسال نکند.

### قدم 5 — regression کامل

پس از سبز شدن browser EFB:

```powershell
& 'C:\xampp\php\php.exe' vendor/bin/phpcs
& 'C:\xampp\php\php.exe' vendor/bin/phpstan analyse --configuration=phpstan.neon --memory-limit=2G
& 'C:\xampp\php\php.exe' vendor/bin/phpunit
& 'C:\xampp\php\php.exe' tests/local_admin_menu_smoke.php
& 'C:\xampp\php\php.exe' tests/local_admin_assets_smoke.php
& 'C:\xampp\php\php.exe' tests/local_admin_render_smoke.php
& 'C:\xampp\php\php.exe' tests/wordpress_phase1_integration.php
```

تست‌های WordPress/DB/browser ممکن است در sandbox به escalation نیاز داشته باشند. secrets یا credentialهای حساب موقت در خروجی نهایی چاپ نشوند.

baseline قبل از S3.2:

- PHPCS: 130/130 بدون warning/error.
- PHPStan: 0.
- PHPUnit: 133 tests / 439 assertions.
- Phase 1: 45/0.

با دو تست جدید EfbChatSkill انتظار شمار PHPUnit باید دست‌کم به 135 tests / 442 assertions برسد، مگر تست‌های دیگری هم افزوده شوند.

### قدم 6 — بستن مستندات S3.2 (T06 — تکمیل ۲۰۲۶-۰۹-۱۷)

> انجام شد. قدم‌های ۱ تا ۶ همه بسته‌اند و این بخش فقط سابقه است.

وقتی همه گیت‌ها سبز شدند:

- وضعیت S3.2.0 تا S3.2.6 را در `docs/EFB_COPILOT_S3_2_AUDIT_AND_PLAN_FA.md` تکمیل کن.
- `docs/EFB_COPILOT_S3_2_EXECUTION_REPORT_FA.md` بساز و قرارداد، اختلاف عمدی voice realtime، featureهای بدون UI موکاپ، شواهد تست و artifactها را ثبت کن.
- `docs/MOCKUP_REDESIGN_TASK_TRACKER_FA.md` را با نتیجه دقیق و شمار تست واقعی به‌روزرسانی کن.
- فقط پس از آن S3.3 Front Chat Widget را آغاز کن.

## ملاحظات فنی مهم

- EFB عناصر فیلد را با `data-id="<field-id>-id"` render می‌کند. selected bridge باید همیشه ID را با `valj_efb` تطبیق دهد.
- `valj_efb` در EFB یک lexical global است؛ در browser evaluate از `window.valj_efb` برای خواندن اولیه استفاده نکن.
- عملیات Copilot فقط canvas زنده را تغییر می‌دهد. persistence متعلق به دکمه سبز Save خود EFB است؛ متن UI باید همین را صریح بگوید.
- ساخت فرم جدید همچنان proposal + approval یک‌بارمصرف است؛ این contract حذف نشود.
- Pro fieldها باید fail-closed بمانند و CTA رسمی EFB نشان داده شود؛ substitute جعلی نساز.
- header voice فقط میانبر transcript-first است. تا وقتی backend session/stream واقعی وجود ندارد، آن را realtime/full-screen معرفی نکن.
- typing indicator فقط هنگام REST واقعی نمایش داده می‌شود و داده جعلی نیست.
- main menu باید دقیقاً ۸ آیتم اصلی visible بماند (D-23، ۲۰۲۶-۰۹-۱۷: Overview با ۴ تب داخلی + Setup & Connection + Integrations + Site Knowledge + Answer Desk + Store Copilot + Content Studio + Form Studio)؛ سطوح تخصصی EFB/Form Studio (نام تازهٔ Form Composer) routeهای پنهان یا تب هستند. جزئیات: T54/T55 در رودمپ و `docs/OVERVIEW_FORM_STUDIO_S6_AUDIT_AND_PLAN_FA.md`.
- فایل POT قدیمی‌تر از PO است و هنوز regenerate کامل نشده؛ بدون ابزار مطمئن، POT را ناقص بازنویسی نکن. این debt را در گزارش release نهایی نگه دار.

## تعریف پایان S3.2

S3.2 فقط وقتی کامل است که:

- FAB/chat/selected/help/Pro/voice transcript-first با داده واقعی موکاپ‌هم‌راستا باشند.
- selected-field سمت client و server fail-closed و تست‌شده باشد.
- `/efb/log` در REST واقعی 200 بدهد.
- فارسی واقعی، RTL، desktop و 390px پاس شوند.
- console/pageerror/HTTP غیرمنتظره صفر باشد.
- fixture و account پس از هر run حذف شوند.
- PHPCS/PHPStan/PHPUnit/Phase 1 و smokeهای admin کامل سبز باشند.
- execution report و tracker تکمیل شوند.

