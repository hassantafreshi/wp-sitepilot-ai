# Handoff — ادامه بازطراحی افزونه براساس موکاپ

آخرین به‌روزرسانی: ۲۰۲۶-۰۹-۱۵
وضعیت جاری: **S3.2 — EFB Copilot در حال اجرا؛ T00 و T01 تکمیل؛ بعدی T02**

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
- S3.2 EFB Copilot: در حال اجرا.
- S3.3 Front Chat Widget: هنوز آغاز نشده.
- فاز ۴ final QA/release: هنوز انجام نشده.

ردیاب اصلی:

- `docs/MOCKUP_REDESIGN_TASK_TRACKER_FA.md`

گزارش‌های قبلی مهم:

- `docs/FORM_COMPOSER_S3_1_EXECUTION_REPORT_FA.md`
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

### قدم 6 — بستن مستندات S3.2

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
- main menu باید دقیقاً ۱۰ آیتم visible بماند؛ سطوح تخصصی EFB/Form Composer routeهای پنهان هستند.
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

