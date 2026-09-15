# رودمپ تکمیل محصول — AI Site Assistant AutoPilot

آخرین به‌روزرسانی: ۲۰۲۶-۰۹-۱۵  
مجری: ایجنت `aisa-finisher` (`C:\files\projects\SitePilot AI\.claude\agents\aisa-finisher.md`)  
نقطهٔ شروع: `handoff.md` مورخ ۲۰۲۶-۰۹-۱۱ — S3.2 در حال اجرا

این فایل صف اجرایی «از وضعیت فعلی تا محصول آمادهٔ انتشار» است. جای اسناد موجود را نمی‌گیرد:

| سند | نقش |
|---|---|
| این رودمپ | ترتیب، وابستگی، وضعیت ریزکارها، تصمیم‌های مالک و ژورنال اجرا |
| `docs/<AREA>_<ID>_AUDIT_AND_PLAN_FA.md` | نگاشت موکاپ به قرارداد واقعی و وضعیت ریزتسک‌های همان بسته |
| `docs/<AREA>_<ID>_EXECUTION_REPORT_FA.md` | نتیجه، اختلاف‌های عمدی و شواهد تست هنگام بستن بسته |
| `docs/MOCKUP_REDESIGN_TASK_TRACKER_FA.md` | وضعیت سطح بسته و لاگ تاریخ‌دار؛ ردیاب اصلی پروژه |
| `handoff.md` | پل میان جلسه‌ها؛ در پایان هر جلسه بازنویسی می‌شود |

## قواعد استفاده

1. **task بعدی** اولین ردیف جدول صف است که وضعیتش `در حال اجرا` یا `منتظر` باشد و همهٔ وابستگی‌هایش `تکمیل` باشند. ردیف‌های `نیازمند تصمیم` و `مسدود` اجرا نمی‌شوند.
2. هیچ task بدون گذر از گیت پذیرش خودش و ثبت شواهد واقعی `تکمیل` نمی‌شود: خروجی دستور، شمار دقیق تست و مسیر artifact. زمان صرف‌شده شاهد نیست.
3. هر جا مالک باید تصمیم بگیرد، وضعیت `نیازمند تصمیم` می‌شود و گزینه‌ها با پیشنهاد در بخش تصمیم‌ها ثبت می‌شوند. ایجنت به‌جای مالک تصمیم نمی‌گیرد.
4. ترتیب همگام‌سازی پس از هر task: ردیف همین جدول و ژورنال ← سند plan همان بسته ← (هنگام بستن بسته) execution report و tracker ← `handoff.md`.
5. وضعیت فقط یکی از این پنج واژه است، چون `scripts/agent/preflight.ps1` همین‌ها را می‌خواند: `منتظر`، `در حال اجرا`، `مسدود`، `نیازمند تصمیم`، `تکمیل`.
6. شناسهٔ task عوض یا بازاستفاده نمی‌شود. کار تازه با اولین شناسهٔ آزاد (T26 و بعد) در جای درست وابستگی افزوده و در ژورنال ثبت می‌شود.

## تعریف «محصول آماده»

محصول فقط وقتی آماده است که همهٔ این‌ها هم‌زمان برقرار باشند (جمع معیار §۸ `MOCKUP_REDESIGN_AUDIT_AND_PLAN_FA.md`، تعریف پایان S3.2 در handoff و گیت‌های انتشار):

- منوی visible دقیقاً ۱۰ صفحه به ترتیب موکاپ است و هر ۱۸ slug/alias با HTTP 200 و shell سالم باز می‌شود.
- سه سطح تخصصی Form Composer، EFB Copilot و Front Chat Widget با موکاپ و روی دادهٔ واقعی هم‌راستا هستند.
- هر کنترل فعال backend واقعی دارد؛ کنترل‌های بدون backend قفل و صادقانه‌اند و اختلاف‌های عمدی فقط در گزارش‌ها ثبت شده‌اند.
- PHPCS بدون error و warning، PHPStan صفر، PHPUnit سبز و نه کمتر از خط مبنا، Phase 1 و همهٔ integrationها سبز؛ console error، pageerror و PHP warning افزونه صفر.
- فارسی واقعی `fa_IR`، RTL و LTR، 390px، keyboard/focus و contrast حداقل 4.5:1 تأیید شده‌اند.
- POT، PO و MO همگام‌اند.
- ZIP از source جاری ساخته شده، Plugin Check روی همان ZIP صفر error و صفر warning است و نصب تمیز موفق است.
- گزارش آمادگی انتشار امضای مالک را دارد. commit فقط با تأیید صریح مالک ساخته می‌شود.

## خط مبنا

| گیت | آخرین مقدار معتبر | منبع |
|---|---|---|
| PHPCS | 130/130 فایل، 0 error، 0 warning | handoff، پیش از S3.2 |
| PHPStan | 0 error | handoff |
| PHPUnit | 135 test / 442 assertion (پیش از S3.2: 133 / 439) | run-gates static، ۲۰۲۶-۰۹-۱۴ |
| گیت استاتیک کامل | php-lint 210/210، node-check 28/28، PHPCS 130/130 با 0 error و 0 warning، PHPStan 0، PHPUnit 135 / 442 | `test-artifacts/gates/20260914-234954-static` |
| Phase 1 integration | 45/0 | handoff |
| render smoke | 18 route بدون warning | tracker، A2.7 |
| assets smoke | قرارداد 18 slug/alias | tracker، N1.3 |
| MO فارسی | 1229 entry | handoff |

## وضعیت محیط در شروع — ۲۰۲۶-۰۹-۱۴

- junction `wp-content/plugins/ai-site-assistant-autopilot` به همین source سالم است؛ Apache و MySQL فعال‌اند؛ `wp-login.php` پاسخ 200 می‌دهد؛ WordPress 7.1.1-RC1 و بستهٔ زبان `fa_IR` نصب است.
- (snapshot ۲۰۲۶-۰۹-۱۴، **منسوخ**: از ۲۰۲۶-۰۹-۱۵ ساعت 01:19 هستهٔ EFB فعال است؛ T00 را ببینید.) هستهٔ Easy Form Builder (`easy-form-builder/emsfb.php` نسخهٔ 4.2.0) در آن زمان غیرفعال بود، در حالی که دو add-on آن فعال بودند. source خود EFB امروز تغییر کرده و repo آن هفت تغییر ثبت‌نشده دارد. قرارداد DOM در source هنوز دیده می‌شود (`let valj_efb`، `data-id="…-id"`، `field-selected-efb`)، اما پیش از هر browser run باید روی EFB فعال دوباره تأیید شود.
- تنظیمات افزونه ذخیره نشده و پیش‌فرض‌ها فعال‌اند؛ بنابراین پاسخ 400 `provider_not_configured` در QA طبیعی است.
- worktree روی master @ `56dd00b` است: 78 فایل modified، 2 added و 115 untracked. commit تازه‌ای ساخته نشده است.
- پردازش node با فرمان `tests/test-onboarding-wizard-browser.js` متعلق به کار دیگری است و نباید متوقف شود.
- ابزارها: PHP 8.2.12، Node 24، Chrome، `playwright-core` در `node_modules`، `msgfmt`/`xgettext`/`msgmerge` در `C:\Program Files (x86)\Poedit\GettextTools\bin`. WP-CLI نصب نیست.

## جدول صف اجرا

| ID | مرجع | کار | وابسته به | وضعیت |
|---|---|---|---|---|
| T00 | S3.2 | پیش‌نیاز محیط: فعال‌سازی هستهٔ EFB توسط مالک و تأیید دوبارهٔ قرارداد DOM روی EFB 4.2.0 | — | تکمیل |
| T01 | S3.2.1، S3.2.2، S3.2.4 | اجرای دوبارهٔ browser matrix در en_US و fa_IR و ریشه‌یابی 429 | T00 | تکمیل |
| T02 | S3.2.0، S3.2.2 | تست integration قرارداد `/efb/log`، `selected_field` و gate `efb_copilot_enabled` | — | منتظر |
| T03 | S3.2.3 | انتقال رشته‌های نمایشی باقی‌ماندهٔ `efb-copilot.js` به i18n، PO و MO | — | منتظر |
| T26 | S3.2.0، S3.2.3 | همگام‌سازی کاتالوگ و دانش Copilot با لایهٔ steps/progress در EFB 4.2.0 (`steps_style`، `progress_style`، معنای `show_icon` و `show_pro_bar`) | T00 | منتظر |
| T27 | S3.2.3 | operationهای نوشتنی سبک step و progress روی فرم باز، تابع D-08 | T26 | نیازمند تصمیم |
| T04 | S3.2.3، S3.2.4، S3.2.5 | تکمیل browser و visual QA: screenshot محدود، help/Pro با mock، keyboard، 390×844 و فارسی واقعی | T01، T02، T03، T26 | منتظر |
| T05 | S3.2.6 | regression کامل و مقایسه با خط مبنا | T04 | منتظر |
| T06 | S3.2.6 | بستن اسناد S3.2: plan، execution report، tracker و handoff | T05 | منتظر |
| T07 | S3.3.0 | ممیزی Front Chat Widget، قرارداد واقعی و ریزبرنامه | T06 | منتظر |
| T08 | S3.3.1 | launcher و panel docked مطابق موکاپ روی renderer واقعی | T07 | منتظر |
| T09 | S3.3.2 | حالت full screen: expand/collapse، scrim، focus trap و Escape | T08 | منتظر |
| T10 | S3.3.3 | گفتگو: chips، typing واقعی، source از citation واقعی و خطا/retry/429 | T08 | منتظر |
| T11 | S3.3.4 | کارت handoff با قرارداد واقعی کانال آماده | T10 | منتظر |
| T12 | S3.3.5 | voice صادقانه: transcript-first، گفتار مرورگر و تصمیم overlay | T10 | منتظر |
| T13 | S3.3.6 | browser matrix ویجت: en/fa، desktop/390، keyboard و visual | T09، T11، T12 | منتظر |
| T14 | S3.3.7 | regression، execution report، tracker و handoff بستهٔ S3.3 | T13 | منتظر |
| T15 | Q4.1 | browser smoke هر ۱۸ slug/alias در en_US و fa_IR | T14 | منتظر |
| T16 | Q4.2 | مقایسهٔ بصری side-by-side با چهار موکاپ و گزارش اختلاف‌های عمدی | T15 | منتظر |
| T17 | Q4.3 | accessibility، RTL و responsive کامل | T15 | منتظر |
| T18 | Q4.3 | همگام‌سازی POT، PO و MO با xgettext محلی | T14 | منتظر |
| T19 | Q4 | ممیزی preserve-list، قرارداد REST و WooCommerce و شناسه‌های منجمد | T14 | منتظر |
| T20 | Q4 | بازبینی امنیتی کل تغییرات بازطراحی | T19 | منتظر |
| T21 | R5 | متادیتای انتشار: نسخه، readme.txt، RELEASE_NOTES، MANUAL-TESTING و uninstall | T16، T17، T18، T20 | منتظر |
| T22 | Q4.4 | ساخت ZIP انتشار و اعتبارسنجی ساختار آن | T21 | منتظر |
| T23 | Q4.4 | Plugin Check روی ZIP ساخته‌شده با جابه‌جایی امن junction | T22 | منتظر |
| T24 | R5 | پذیرش نصب تمیز ZIP | T23 | منتظر |
| T25 | R5 | گزارش آمادگی انتشار، امضای مالک و برنامهٔ commit | T24 | منتظر |

## تصمیم‌های مالک

| ID | task | پرسش | پیشنهاد ایجنت | وضعیت |
|---|---|---|---|---|
| D-01 | T00 | هستهٔ EFB روی سایت محلی فعال شود؟ | بله، فقط برای QA؛ فعال‌سازی را خود مالک از wp-admin انجام دهد | تأیید و اعمال شد ۲۰۲۶-۰۹-۱۵؛ preflight ساعت 01:19 و 01:33 `efb v4.2.0 active=True` |
| D-02 | T12 | overlay تمام‌صفحهٔ voice موکاپ بدون backend realtime ساخته شود؟ | خیر؛ مانند EFB، transcript-first و اختلاف عمدی مستند | باز |
| D-03 | T21 | شمارهٔ نسخهٔ انتشار؟ | 1.1.0، چون UI بزرگ تغییر کرده ولی قرارداد API و داده نشکسته است | باز |
| D-04 | T24 | نصب تمیز ZIP روی کدام WordPress؟ | نصب دوم محلی، مثلاً `C:\xampp\htdocs\wp-clean`، که مالک می‌سازد | باز |
| D-05 | T25 | commit و snapshot چگونه ساخته شود؟ | پس از امضای انتشار، چند commit منطقی به تفکیک فاز | باز |
| D-06 | T07 | اگر T00 طول بکشد، ممیزی سندی S3.3.0 زودتر آغاز شود؟ | فقط با اجازهٔ صریح؛ handoff آغاز S3.3 را پس از بستن S3.2 مجاز می‌داند | باز |
| D-07 | بک‌لاگ | backendهای Phase 2 (BE-03، BE-04، BE-05 و…) کی اجرا شوند؟ | پس از انتشار، با اولویت BE-04 که voice را در ویجت، EFB و Answer Desk باز می‌کند | باز |
| D-08 | T27 | Copilot بتواند سبک step (`classic/circles/pills/chevrons`) و progress bar (`classic/bar/segments/ring`) و نمایش آن‌ها را روی فرم باز تغییر دهد؟ | بله، پس از T26 و فقط از مسیر موجود `set_form_property` با allowlist چهار کلید و enum سمت سرور؛ فقط canvas زنده، Save همچنان با دکمهٔ EFB. اگر خیر، T27 حذف و Copilot فقط راهنمای how-to می‌دهد | باز |
| D-09 | T02، S3.2.6 | lost update احتمالی در `Logger` (option `aisaautopilot_logs`): در baseline T01 رکورد client log هم‌زمان با درخواست chat ثبت نشد. پیش از بستن S3.2 اصلاح شود؟ | بله، به‌صورت task کوچک پس از T02: ابتدا integration نوشتن هم‌زمان برای بازتولید، سپس نوشتن اتمی بدون تغییر نام option یا جدول. اگر خیر، در گزارش S3.2 به‌عنوان محدودیت مستند شود | باز |
| D-10 | B-09 | بستهٔ EFB Agent Abilities (S4.1) پیش از انتشار ۱.۱.۰ وارد صف اجرای فعال (جدول T) شود یا در بک‌لاگ فاز ۵ بماند؟ | بک‌لاگ فاز ۵؛ انتشار فعلی نزدیک T25 است و این بسته جزو تعریف «محصول آماده» فعلی نیست | باز |
| D-11 | B-09 | گیت S4.1 علاوه بر Free Plus/Pro خودِ EFB، یک سطح پرداختی/اعتباری جدا هم در AI Site Assistant AutoPilot داشته باشد؟ | نه در نسخهٔ اول؛ فقط گیت EFB کافی است، بعد از دیدن دادهٔ مصرف واقعی تصمیم گرفته شود | باز |
| D-12 | B-09 | `efb.entries.generate_test` بدون فلگ واقعی «آزمایشی» در دیتابیس EFB (پس در آمار EFB واقعی شمرده می‌شود) قابل‌قبول است یا این ability تا افزودن چنین فلگی توسط تیم EFB ساخته نشود؟ | با محدودیت مستند ساخته شود؛ رفع کامل نیاز به تغییر سمت EFB دارد که خارج از این افزونه است | باز |

## جزئیات taskها — S3.2 EFB Copilot

مرجع‌ها: `handoff.md` (ترتیب قدم ۱ تا ۶)، `docs/EFB_COPILOT_S3_2_AUDIT_AND_PLAN_FA.md`، موکاپ `mockups/design_handoff_admin_redesign/EFB Builder Chat.dc.html`. سطح canonical پنجرهٔ Copilot روی ویرایشگر واقعی EFB در `page=Emsfb` و `page=Emsfb_create` است؛ ویرایشگر نمایشی موکاپ ساخته نمی‌شود.

### T00 — پیش‌نیاز محیط EFB

- کار: D-01 را به مالک گزارش کن. پس از فعال‌سازی توسط مالک، preflight را دوباره اجرا کن و قرارداد DOM را از source فعلی EFB با `file:line` تأیید کن: `let valj_efb` در `includes/admin/assets/js/admin-efb.js`، رندر `.efbField` با `data-id="<id>-id"`، کلاس انتخاب `field-selected-efb` و slugهای `Emsfb` و `Emsfb_create`.
- گیت: preflight بدون هشدار EFB؛ جدول قرارداد با شواهد در plan S3.2. اگر قرارداد تغییر کرده باشد، task اصلاح bridge با شناسهٔ تازه پیش از T01 افزوده می‌شود.
- ممنوع: فعال‌سازی یا غیرفعال‌سازی افزونه توسط ایجنت؛ هر تغییر در source EFB.
- وضعیت ۲۰۲۶-۰۹-۱۵ (اجرای اول): D-01 تأیید شد و جدول قرارداد DOM در plan S3.2 ثبت شد (شناسه‌ها بدون تغییر). گیت باز ماند چون هسته در `active_plugins` نبود. تصحیح: `let valj_efb` سطح بالا در `includes/admin/assets/js/new-efb.js:16` است؛ نسخهٔ `admin-efb.js` محلی است.
- وضعیت ۲۰۲۶-۰۹-۱۵ (اجرای دوم): **تکمیل.** preflight بدون هشدار و مانع با `efb v4.2.0 active=True`؛ probe فقط‌خواندنی runtime روی EFB فعال (HEAD `031bfa75`) با exit 0، صفر pageerror، console error و پاسخ ناموفق، و cleanup تأییدشده. قرارداد bridge بدون تغییر است، پس task اصلاح bridge لازم نیست؛ لایهٔ تازهٔ steps/progress به T26 و T27 منتقل شد. شاهد: `test-artifacts/efb-env/20260915-t00-runtime/`.

### T01 — اجرای دوبارهٔ browser matrix

- دستور از root افزونه: `node tests/efb_copilot_browser_matrix.cjs`
- انتظار: هر دو `en_US` و `fa_IR` با exit 0، و پیام‌های پاک‌سازی `EFB Copilot browser fixture removed.` و `Local browser QA account removed.`. فقط 400 `provider_not_configured` روی `/efb/chat` مجاز است.
- 429 را با URL دقیق `FAILED_RESPONSES` ریشه‌یابی کن و منشأ را تفکیک کن: RateLimiter افزونه، limit-login-attempts-reloaded، Jetpack یا EFB. خطای `/aisaautopilot/v1/efb/*` هرگز ignore نمی‌شود؛ خطای وابستگی بیرونی فقط با شاهد و دلیل مکتوب در allow-list تست می‌ماند.
- پاسخ 200 برای `/efb/log` را در شبکهٔ مرورگر تأیید کن؛ اصلاح callback به `client_log` هنوز در مرورگر تأیید نشده است.
- کلیک مستقیم روی کنترل درونی فیلد (`.efbField[data-id$="-el"]`، مثلاً input یا textarea) را هم بسنج: از روی source، `closest()` روی خود کنترل متوقف می‌شود (`efb-copilot.js:148-151`) و probe runtime T00 هم همین را نشان داد (`closest` برابر `work_email-el`). با این حال کلیک Playwright روی همان input در Chrome فیلد را انتخاب کرد و banner نشان داده شد، احتمالاً چون کنترل در حالت ویرایش EFB `disabled` است و رویداد به wrapper می‌رسد. این رفتار را به‌صورت assertion دائمی در matrix بیاور، نه فقط یادداشت؛ اگر در کلیک واقعی شکست بود، اصلاح bridge با شناسهٔ آزاد بعدی پیش از T04 افزوده می‌شود.
- گیت: exit 0 در هر دو locale، نبود پاسخ ناموفق غیرمجاز، و preflight بدون fixture یا کاربر باقی‌مانده. artifact: `test-artifacts/efb-copilot/<locale>/`.
- وضعیت ۲۰۲۶-۰۹-۱۵: **تکمیل.** matrix با exit 0: `en_US` 31 PASS / 0 FAIL و `fa_IR` 31 PASS / 0 FAIL، با هر دو پیام پاک‌سازی و preflight 02:14 بدون warning و blocker. 429 از `usage_cap_reached` خود افزونه بود: کلید واقعی provider در WordPress Connectors است و سقف مصرف محلی پر شده. به همین دلیل `/efb/chat` در check با پاسخ 400 `provider_not_configured` کنترلر mock شد. `/efb/log` در مرورگر `[200,200]` داد. کلیک واقعی روی `-el` فیلد را انتخاب می‌کند چون input `pointer-events:none` دارد، پس اصلاح bridge لازم نیست. شاهد: `test-artifacts/efb-copilot/20260915-t01/final/<locale>/`.

### T02 — integration قرارداد `/efb/log` و `selected_field`

- فایل: `tests/wordpress_efb_copilot_contract_integration.php` با الگوی `tests/wordpress_answer_voice_settings_integration.php` (snapshot تنظیمات، فهرست check، restore در `finally` و خروجی `N checks, M failed`).
- حداقل checkها: route `/aisaautopilot/v1/efb/log` ثبت شده و callback آن callable است؛ dispatch معتبر client log با مدیر 200 می‌دهد و 500 نمی‌دهد؛ کاربر subscriber رد می‌شود؛ `selected_field` ناموجود یا بیرون از `fields` به prompt نمی‌رسد؛ ID معتبر با label و type برگرفته از catalogue پاک‌سازی‌شده به skill می‌رسد، نه از داده‌های client؛ `efb_copilot_enabled=false` مجوز را می‌بندد و تنظیم قبلی restore می‌شود.
- هیچ provider یا سرویس بیرونی فراخوانی نمی‌شود. prompt را از seam موجود `EfbChatSkill` بسنج، یا `pre_http_request` را طوری hook کن که هر درخواست بیرونی را رد و شمارش کند. اگر seam تازه لازم شد، کوچک‌ترین تغییر آزمون‌پذیر را بساز و مستند کن.
- گیت: `N checks, 0 failed` با N دست‌کم 6؛ اجرای دوم هم سبز؛ اگر `includes/` تغییر کرد، PHPCS و PHPStan متمرکز و PHPUnit کامل.

### T03 — localization باقی‌ماندهٔ EFB Copilot

- inventory: هر رشتهٔ قابل‌نمایش در `assets/admin/efb-copilot.js` که از `cfg.i18n` نمی‌آید؛ به‌ویژه نتیجهٔ operationها (`Made`، `Removed`، `Added`، `Updated`)، no-operation، خطای render، یادداشت مالکیت Save، open-form-first، fallbackها، `Failed` در preview، پاسخ‌های شبکهٔ محلی و متن help history اگر روی UI دیده می‌شود. متن‌های داخلی `clientLog` لازم نیست.
- کار: کلید در `EfbBuilderButton::$i18n` با `__()` یا `_n()` و placeholder و comment مترجم؛ مصرف در JS با fallback انگلیسی؛ entry فارسی در PO؛ سپس کامپایل:
  `& 'C:\xampp\php\php.exe' scripts/compile-mo.php 'C:\xampp\htdocs\wp' languages/ai-site-assistant-autopilot-fa_IR.po languages/ai-site-assistant-autopilot-fa_IR.mo`
- گیت: جدول inventory (رشته، کلید، ترجمه) در plan S3.2؛ grep نهایی بدون رشتهٔ نمایشی hard-coded؛ `node --check`؛ PHPCS متمرکز روی `includes/Efb/EfbBuilderButton.php`؛ شمار entry کامپایل‌شده بیشتر از 1229. تأیید بصری فارسی در T04 انجام می‌شود.
- ممنوع: بازنویسی POT؛ آن کار T18 است.

### T26 — همگام‌سازی کاتالوگ و دانش Copilot با لایهٔ steps/progress در EFB 4.2.0

- زمینه: مالک در ۲۰۲۶-۰۹-۱۵ گفت فهرست فیلدهای ساخت فرم به‌روز شده و UI تازهٔ multi-step و progress bar افزوده شده است. شواهد T00:
  - palette `fields_efb` در `val-efb.js:139-195` در HEAD `031bfa75` همان 48 نوع commit `03214035` (۲۰۲۶-۰۸-۱۷) است، به‌علاوهٔ `persiaPay` که فقط در `fa_IR` با `val-efb.js:2303` افزوده می‌شود. هر 48 نوع در `field_types` کاتالوگ (68 نوع) هستند و در runtime هم تأیید شد. **نوع فیلد تازه‌ای افزوده یا حذف نشده است.**
  - تغییر واقعی commit `097de0b8` (۲۰۲۶-۰۹-۱۰) است: کلیدهای سطح فرم `steps_style` (`classic/circles/pills/chevrons`) و `progress_style` (`classic/bar/segments/ring`) روی `valj_efb[0]`، با fallback به `classic` برای مقدار ناشناخته (`steps-progress-builder-efb.js:147-158`، `includes/functions.php:356-370`). picker در `val-efb.js:892` و `val-efb.js:903` فقط وقتی toggle پنهان‌سازی خاموش است دیده می‌شود؛ writer در `admin-efb.js:4931-4951` است و رندر front در `class-Emsfb-public.php:1625-1665`.
- نقص‌های کاتالوگ `includes/Efb/efb-capability-catalog.json`:
  - `form_settings_contract.show_icon` «button icon visibility» ثبت شده، اما `showSIconsEl` («dontShowIconsStepsName») با مقدار `true` ردیف stepها را پنهان می‌کند (`admin-efb.js:2122-2126`).
  - `show_pro_bar` «EFB branding/pro bar» ثبت شده و در `form_properties` با `ai_writable:false` و دلیل licensing آمده است، اما `showSprosiEl` («dontShowProgressBar») با `true` progress bar را پنهان می‌کند (`admin-efb.js:2127-2130`).
  - `steps_style` و `progress_style` در کاتالوگ نیستند و `efb_version_target` هنوز `4.1.x` است.
- کار: اصلاح نقش `show_icon` و `show_pro_bar` و دلیل آن‌ها؛ افزودن `steps_style` و `progress_style` با enum، پیش‌فرض `classic`، وابستگی به toggle مربوط و منبع file:line؛ `efb_version_target` ← `4.2.x`؛ به‌روزرسانی پاسخ how-to `multi_step` در `efb-knowledge.json` با مسیر واقعی تنظیم سبک step و progress؛ بررسی اینکه `EfbFormConverter` برای فرم چندمرحله‌ای تازه کلید سبک نمی‌نویسد، پس EFB همان `classic` را رندر می‌کند. تا D-08، `ai_writable` این چهار کلید false می‌ماند و هیچ operation نوشتنی تازه‌ای افزوده نمی‌شود.
- گیت: `ConvertFrom-Json` یا `json_decode` معتبر؛ PHPUnit برای کلیدها، enum، نقش‌ها و `ai_writable`؛ PHPUnit کامل نه کمتر از خط مبنا؛ اگر PHP تغییر کرد، PHPCS و PHPStan متمرکز.
- ممنوع: هر تغییر در source EFB.

### T27 — operationهای نوشتنی سبک step و progress (تابع D-08)

- فقط اگر D-08 تأیید شود: `set_form_property` با allowlist `steps_style`، `progress_style`، `show_icon` و `show_pro_bar`؛ اعتبارسنجی enum سمت سرور در `EfbCustomizeSkill` و سمت client در applyOps `efb-copilot.js`؛ تغییر فقط روی canvas زنده و Save همچنان با دکمهٔ EFB؛ پیام نتیجهٔ محلی‌شده؛ picker EFB پس از اعمال همگام شود.
- گیت: PHPUnit برای مقدار معتبر، نامعتبر و کلید بیرون از allowlist؛ browser با پاسخ `page.route()` mock شده که سبک را روی `valj_efb[0]` عوض کند و preview EFB همان سبک را نشان دهد، بدون Save خودکار. اگر تأیید شود، پیش از T05 اجرا و بخش مربوط در T04 پوشش داده می‌شود؛ اگر رد شود، ردیف در ژورنال بسته و فقط راهنمای how-to باقی می‌ماند.

### T04 — تکمیل browser و visual QA

- `tests/efb_copilot_browser_check.cjs` را گسترش بده، نه اسکریپت موازی: `locator.screenshot()` برای FAB، chat، banner انتخاب و موبایل؛ حالت provider-not-configured بدون ادعای موفقیت؛ کارت help و Pro با پاسخ `page.route()` mock شده و CTA بدون navigation واقعی؛ Tab، Enter برای ارسال، Shift+Enter برای خط تازه، Escape و focus ring قابل‌مشاهده؛ 390×844؛ `fa_IR` واقعی که shell، welcome، chips، banner، placeholder و برچسب actionها را فارسی نشان دهد، نه فقط جهت؛ صفر request هنگام باز کردن پنجره و prefill با chip؛ mic هرگز خودکار ارسال نکند.
- screenshotها را با Read ببین و با موکاپ مقایسه کن؛ هر اختلاف عمدی در گزارش ثبت می‌شود.
- گیت: matrix در هر دو locale با exit 0؛ console، pageerror و HTTP غیرمنتظره صفر؛ فهرست screenshotهای بازبینی‌شده؛ cleanup تأییدشده.

### T05 — regression کامل S3.2

- دستور: `powershell -NoProfile -ExecutionPolicy Bypass -File "C:\files\projects\SitePilot AI\scripts\agent\run-gates.ps1" -Tier full`
- گیت: همهٔ gateها PASS؛ PHPCS بدون error و warning؛ PHPStan صفر؛ PHPUnit دست‌کم 135 / 442؛ Phase 1 دست‌کم 45/0؛ smokeها بدون diagnostic افزونه. هر افت نسبت به خط مبنا ریشه‌یابی و رفع می‌شود، نه توجیه. artifact: `test-artifacts/gates/<stamp>-full/summary.md`.

### T06 — بستن اسناد S3.2

- `docs/EFB_COPILOT_S3_2_AUDIT_AND_PLAN_FA.md`: وضعیت S3.2.0 تا S3.2.6 با گیت واقعی.
- `docs/EFB_COPILOT_S3_2_EXECUTION_REPORT_FA.md` با قالب `FORM_COMPOSER_S3_1_EXECUTION_REPORT_FA.md`: قرارداد selected-field، رفع callback `/efb/log`، اختلاف عمدی voice realtime، قابلیت‌های بدون UI موکاپ، جدول شواهد و artifactها.
- tracker: ردیف S3.2 ← `تکمیل` و لاگ تاریخ‌دار با شمار واقعی. `handoff.md` برای آغاز S3.3 بازنویسی می‌شود.
- گیت: همهٔ شمارها در report، tracker، این رودمپ و handoff یکسان‌اند.

## جزئیات taskها — S3.3 Front Chat Widget

مرجع بصری: `mockups/design_handoff_admin_redesign/Front Chat Widget.dc.html` با پنج حالت Bubble، Panel، Full screen، Voice mode و Handoff، به‌همراه LTR و RTL. داده‌های نمونهٔ موکاپ (Northwind، پاسخ‌ها، popular questions و چرخهٔ خودکار voice) ابزار prototype هستند و وارد محصول نمی‌شوند.

واقعیت فنی کشف‌شده در ۲۰۲۶-۰۹-۱۴ که T07 باید دوباره تأیید کند:

- renderer canonical: `includes/Frontend/ChatWidget.php` که `Core/Plugin::render_widget` آن را در `wp_footer` و فقط با `Settings::answer_desk_enabled()` و `ChatWidget::should_render_widget()` رندر می‌کند؛ assetها `assets/frontend/widget.js` و `widget.css`؛ shortcode `[aisaautopilot_chat]` از `ChatWidget::render_standalone`.
- `includes/Frontend/WidgetRenderer.php` و `assets/frontend/chat-widget.js|css` به هیچ hook متصل نیستند و تست lifecycle همین را assert می‌کند. حذف نمی‌شوند؛ فقط در گزارش ثبت می‌شوند.
- ویجت فعلی expand، handoff، ضبط صدا، گفتار مرورگر، suggestion، citation و typing دارد؛ `aria-live` در JS ندارد.
- قراردادهای A2.5 پابرجا هستند: logging فقط opt-in با notice، handoff فقط وقتی دست‌کم یک کانال آماده است، `voice_enabled` و `voice_auto`، رنگ accent و نمایش source فقط با citation واقعی.
- **تله:** زبان front-end از زبان سایت می‌آید، نه از meta کاربر. اجرای `fa_IR` روی front-end یعنی fixture باید `WPLANG` را snapshot، موقتاً عوض و در `finally` بازگرداند.

### T07 — S3.3.0 ممیزی و ریزبرنامه

- خروجی: `docs/FRONT_CHAT_WIDGET_S3_3_AUDIT_AND_PLAN_FA.md` با قالب plan بسته‌های S3.1 و S3.2: تصمیم معماری، نگاشت هر حالت و کنترل موکاپ به قرارداد واقعی (renderer، REST در `includes/Api/RestRoutes.php`، settings)، عناصر موکاپ بدون backend (orb و barge-in و realtime)، قابلیت‌های افزونه بدون UI موکاپ (PDF transcript، notice logging، برجسته‌سازی کلمه در گفتار، stop و retry، rate limit، shortcode، تنظیمات عمیق ظاهر)، تصمیم breakpoint برای عرض 768px و کمتر، و جدول ریزتسک S3.3.0 تا S3.3.7 با گیت.
- قاب‌های موکاپ را با Playwright از `file://` در هر پنج حالت و هر دو جهت بگیر و در `test-artifacts/front-chat-widget/mockup/` بگذار؛ screenshot پایهٔ ویجت فعلی را هم روی front page واقعی با fixture بگیر.
- گیت: سند کامل، همهٔ کنترل‌های موکاپ نگاشت‌شده، D-02 مطرح‌شده، و بدون تغییر در کد.

### T08 — S3.3.1 launcher و panel

- markup و CSS حباب launcher و panel docked (header با عنوان و actionهای expand/close، پیام‌ها، chips و composer با mic و send) روی renderer واقعی؛ جداسازی از CSS قالب با selectorهای scoped؛ logical properties؛ `prefers-reduced-motion`؛ hit target دست‌کم 44px؛ accent از settings.
- گیت: `tests/wordpress_front_chat_widget_render_integration.php` با N/0 (gate روشن و خاموش، `dir`، متغیر accent، config محلی‌شده و نبود secret)؛ `node --check`؛ PHPCS و PHPStan متمرکز؛ مقایسهٔ screenshot دسکتاپ با قاب Panel.

### T09 — S3.3.2 حالت full screen

- expand و collapse، scrim، قفل scroll بدنه، focus trap و بازگشت focus با Escape؛ پرسش‌های پیشنهادی فقط از suggestionهای واقعی تنظیم‌شده؛ در 768px و کمتر، full screen تمام viewport را می‌گیرد.
- گیت: browser check برای expand، collapse، Escape و بازگشت focus؛ نبود overflow افقی در 390px؛ screenshot دسکتاپ و موبایل در LTR و RTL.

### T10 — S3.3.3 گفتگو

- رفتار chips صریح و مستند؛ typing فقط در طول REST واقعی؛ source chip فقط از citation واقعی؛ stateهای خطا، retry، stop و پیام 429؛ notice logging.
- گیت: browser با `page.route` برای پاسخ‌های با source، بدون source، 500 و 429؛ صفر request هنگام باز شدن؛ integration اگر قرارداد PHP تغییر کند.

### T11 — S3.3.4 کارت handoff

- کارت فقط وقتی بازدیدکننده انسان می‌خواهد و دست‌کم یک کانال آماده است؛ جریان `/widget/handoff` در browser mock می‌شود؛ پیام تأیید صادقانه، بدون ادعای «اپراتور وصل شد» یا زمان پاسخ ساختگی؛ در حالت not-ready دکمه‌ای وجود ندارد.
- گیت: integration برای offer در حالت ready و not-ready، و browser mock شده. هیچ ارسال واقعی رخ نمی‌دهد؛ `pre_wp_mail` و `pre_http_request` بسته‌اند.

### T12 — S3.3.5 voice صادقانه

- transcript-first: ضبط، transcribe از endpoint عمومی، متن قابل‌ویرایش در composer و ارسال فقط با اقدام صریح؛ گفتار مرورگر با `voice_auto`؛ وضعیت با `aria-live`. overlay تمام‌صفحه تابع D-02 است؛ پیش‌فرض: ساخته نمی‌شود. اگر مالک بخواهد، فقط phaseهای واقعی ضبط، transcribe و گفتار نمایش داده می‌شوند، بدون متن realtime یا barge-in.
- گیت: browser ثابت کند mic هرگز خودکار ارسال نمی‌کند (شمار request یا mock `getUserMedia`/`MediaRecorder`)؛ نبود متن realtime؛ screenshot حالت ضبط.

### T13 — S3.3.6 browser matrix ویجت

- فایل‌های تازه با الگوی EFB: `tests/front_chat_widget_browser_matrix.cjs`، `tests/front_chat_widget_browser_check.cjs` و `tests/local_front_chat_widget_browser_fixture.php` (snapshot تنظیمات و `WPLANG`، فعال‌سازی موقت، restore).
- `en_US` و `fa_IR`، 1440×960 و 390×844، keyboard، focus، contrast، overflow و صفر console/pageerror/HTTP روی front page قالب واقعی؛ جفت screenshot با قاب‌های موکاپ.
- گیت: exit 0 در هر دو locale؛ preflight بدون باقی‌مانده؛ فهرست بازبینی بصری.

### T14 — S3.3.7 regression و بستن اسناد

- `run-gates.ps1 -Tier full`؛ به‌روزرسانی plan S3.3؛ `docs/FRONT_CHAT_WIDGET_S3_3_EXECUTION_REPORT_FA.md`؛ tracker S3.3 ← `تکمیل` با لاگ تاریخ‌دار؛ بازنویسی handoff برای فاز ۴.
- گیت: همه PASS و شمارها در همهٔ اسناد یکسان.

## جزئیات taskها — فاز ۴ و انتشار

### T15 — Q4.1 browser smoke همهٔ routeها

- یک wrapper matrix با الگوی `tests/form_composer_browser_matrix.cjs` بساز (credential هرگز در stdout نیست): برای هر locale کاربر موقت با `tests/local_browser_user.php create` ساخته، `tests/browser_release_check.cjs` بدون `AISAAUTOPILOT_BROWSER_SLUGS` روی هر ۱۸ route اجرا و در `finally` کاربر حذف می‌شود. screenshotها در `test-artifacts/release-smoke/<locale>/`.
- گیت: exit 0 در `en_US` و `fa_IR`؛ HTTP 200، shell حاضر و صفر console/pageerror برای هر ۱۸ route؛ screenshotها بازبینی‌شده؛ preflight بدون باقی‌مانده.

### T16 — Q4.2 مقایسهٔ بصری با چهار موکاپ

- قاب‌های هر state و scenario چهار فایل `.dc.html` را با Playwright از `file://` در 1440 و 390 بگیر و با screenshot همان state افزونه (دادهٔ واقعی یا fixture) جفت کن؛ هر جفت را با Read ببین.
- خروجی: `docs/VISUAL_PARITY_Q4_2_REPORT_FA.md`؛ برای هر صفحه و state: مسیر جفت تصویر و حکم `منطبق`، `اختلاف عمدی` (با دلیل و سند مرجع) یا `نقص`. هر نقص به task تازه تبدیل می‌شود.
- گیت: هر state موکاپ یا جفت دارد یا ارجاع صریح به «بدون backend» یا «بیرون از محصول»؛ اختلاف بی‌توضیح صفر.

### T17 — Q4.3 دسترس‌پذیری، RTL و responsive

- عبور فقط با keyboard از ۱۰ صفحه و سه سطح تخصصی؛ focus قابل‌مشاهده؛ `aria-label` روی دکمه‌های فقط-icon؛ Escape و بازگشت focus در dialogها؛ نسبت contrast محاسبه‌شده برای متن‌ها دست‌کم 4.5:1؛ overflow در 390، 1024 و 1440؛ hit target ویجت دست‌کم 44px؛ `prefers-reduced-motion`.
- خروجی: `docs/ACCESSIBILITY_Q4_3_REPORT_FA.md`. گیت: نقص باز صفر، یا هر نقص با task تازه در صف پیش از T21.

### T18 — همگام‌سازی POT، PO و MO

- POT فعلی از نسخهٔ 0.6.0 است و 102 msgid دارد، در حالی که PO حدود 1230 msgid دارد.
- ابتدا نسخهٔ پشتیبان PO را در scratchpad بگذار. سپس با `xgettext` در `C:\Program Files (x86)\Poedit\GettextTools\bin` روی `includes/**/*.php`، فایل اصلی و `uninstall.php` استخراج کن: `--language=PHP --from-code=UTF-8 --add-comments=translators: --sort-by-file` با keywordهای `__`، `_e`، `esc_html__`، `esc_html_e`، `esc_attr__`، `esc_attr_e`، `_x:1,2c`، `_ex:1,2c`، `esc_html_x:1,2c`، `esc_attr_x:1,2c`، `_n:1,2`، `_nx:1,2,4c`، `_n_noop:1,2` و `_nx_noop:1,2,3c`. سپس `msgmerge` روی PO، ترجمهٔ ورودی‌های تازه یا fuzzy، `msgfmt --check --statistics` و کامپایل MO با `scripts/compile-mo.php`.
- پیش از استخراج با grep مطمئن شو JS از `wp.i18n` استفاده نمی‌کند؛ رشته‌های JS از آرایه‌های PHP محلی‌سازی می‌شوند.
- گیت: POT همهٔ رشته‌های PHP را دارد؛ PO بدون untranslated و fuzzy برای UI؛ `msgfmt --check` بدون خطا؛ spot-check فارسی سه صفحه در مرورگر.

### T19 — ممیزی قراردادها و preserve-list

- هر مورد §۶ `docs/MOCKUP_REDESIGN_WORKFLOW_FA.md` و §۱۰ `mockups/design_handoff_admin_redesign/REDESIGN_PROMPT.md` با شاهد در دسترس بودن (صفحه، route یا REST) ثبت می‌شود.
- inventory REST زیر `aisaautopilot/v1` از `rest_get_server()->get_routes()` با method و وجود `permission_callback`، مقایسه با `git show 56dd00b:includes/Api/RestRoutes.php`. حذف route بدون تصمیم مستند مجاز نیست.
- `WooCommerceConnector::get_capabilities()` نسبت به HEAD بدون تغییر و draft-only؛ `KeyVault::KEY_DERIVATION_SALT` همچنان `'sitepilot-ai|'`؛ نام‌های قدیمی `RebrandMigrator` دست‌نخورده؛ text domain، optionها و جدول‌ها بدون rename.
- گیت: جدول ممیزی کامل با صفر قابلیت یا route حذف‌شدهٔ بی‌تصمیم.

### T20 — بازبینی امنیتی تغییرات بازطراحی

- دامنه: `git diff 56dd00b` به‌همراه فایل‌های untracked در `includes/` و `assets/`.
- بررسی: `permission_callback` هر route (مسیرهای عمومی فقط با rate limit و محدودیت عمدی)، nonce و capability در handlerهای `admin-post`، escaping خروجی، sanitization ورودی، `$wpdb->prepare`، SSRF در base URLها، formula injection در CSV، نبود secret در DOM، REST و log، و محدودیت اندازه و نوع فایل صوتی.
- گیت: جدول یافته‌ها؛ هر یافته با تست رفع شده یا با پذیرش صریح مالک ثبت شده است.

### T21 — متادیتای انتشار

- نسخه طبق D-03 در header فایل اصلی، `AISAAUTOPILOT_VERSION` و `Stable tag` در `readme.txt`؛ `Tested up to` برابر نسخهٔ واقعاً آزموده؛ توضیح readme فقط برای سطوح واقعاً عرضه‌شده؛ بخش External services برای همهٔ درخواست‌های بیرونی تازه؛ changelog؛ بازنویسی `RELEASE_NOTES.md` (فعلاً مربوط به 1.0.2 است)؛ به‌روزرسانی `MANUAL-TESTING.md`.
- `uninstall.php`: inventory هر option، جدول، transient و meta تازه از 1.0.4 به بعد و اطمینان از پاک‌سازی مطابق readme.
- گیت: نسخه در هر سه جا یکسان؛ جدول inventory حذف هنگام uninstall؛ بررسی readme در T23.

### T22 — ساخت ZIP انتشار

- **نقص فعلی build که باید اول رفع شود:** `scripts/build-phase1-release.ps1` پوشهٔ `test-artifacts/` داخل افزونه را exclude نمی‌کند و تصاویر QA بی‌صدا در ZIP می‌روند؛ همچنین `handoff.md` در root افزونه allow-list ریشه را می‌شکند و build متوقف می‌شود. exclusionها و regex فایل‌های توسعه اصلاح شوند؛ فایل‌ها حذف نمی‌شوند.
- اجرا با مسیر مطلق: `powershell -NoProfile -ExecutionPolicy Bypass -File "C:\files\projects\SitePilot AI\apps\wordpress-plugin\ai-site-assistant-autopilot\scripts\build-phase1-release.ps1"`
- وارسی افزوده: هر فایل runtime در `includes/`، `assets/` و `languages/` در ZIP هست؛ MO همان نسخهٔ source است؛ `test-artifacts`، `handoff.md` و `.claude` در ZIP نیستند؛ SHA-256 ثبت می‌شود.
- گیت: exit 0 و مقایسهٔ فهرست فایل‌ها بدون کمبود یا فایل اضافه.

### T23 — Plugin Check روی ZIP

- runner در scratchpad طبق حافظهٔ ایجنت: bootstrap `wp-load.php`، require `plugin-check/plugin.php`، `Check_Context` با مسیر مطلق فایل اصلی، `Static_Check` از `WordPress\Plugin_Check\Checker` و `get_checks( Check_Repository::TYPE_STATIC )`، با `php -d memory_limit=2G`.
- جابه‌جایی junction فقط در یک اسکریپت با `try/finally` و اجرای dry-run پیش از اجرای واقعی: تأیید `LinkType=Junction` ← rename به `ai-site-assistant-autopilot.dev-junction` ← extract ZIP با نام پوشهٔ برابر text domain ← اجرای check ← حذف پوشهٔ extract‌شده فقط پس از assert اینکه link نیست ← بازگرداندن نام junction ← تأیید `Target` برابر source، preflight و render smoke.
- گیت: 0 error و 0 warning در همهٔ static checkها؛ گزارش در `test-artifacts/plugin-check/<version>/`؛ junction سالم.

### T24 — پذیرش نصب تمیز

- طبق D-04 روی WordPress تمیز: نصب ZIP، فعال‌سازی بدون PHP warning، منوی دقیق ۱۰تایی، smokeهای admin با `AISAAUTOPILOT_WP_ROOT` همان نصب، ویجت عمومی به‌طور پیش‌فرض خاموش، سپس deactivate و uninstall و وارسی حذف optionها و جدول‌ها مطابق readme.
- گیت: checklist با شواهد PASS. تا وقتی D-04 پاسخ ندارد، وضعیت `نیازمند تصمیم` است.

### T25 — آمادگی انتشار و امضای مالک

- `docs/RELEASE_READINESS_<version>_FA.md`: خلاصهٔ گیت‌ها با شمار، artifactها، محدودیت‌های شناخته‌شده (قابلیت‌های قفل Phase 2، رخداد Activity Log، renderer قدیمی بدون hook)، checklist پذیرش محیطی که فقط مالک انجام می‌دهد (کلید واقعی provider با `tests/ai_runtime_check.php`، تحویل SMTP، میکروفون مرورگر واقعی، draft واقعی WooCommerce) و برنامهٔ commit طبق D-05.
- پس از امضای مالک: ردیف‌های Q4 در tracker ← `تکمیل` و بازنویسی نهایی handoff. commit فقط پس از تأیید صریح.
- گیت: تأیید مالک با تاریخ در ژورنال.

## بک‌لاگ پس از انتشار — فاز ۵

این ردیف‌ها بیرون از صف اجرایند و فقط با D-07 به جدول صف منتقل می‌شوند.

| ID | مرجع | کار | وضعیت |
|---|---|---|---|
| B-01 | BE-03 | transportهای handoff برای WhatsApp، Slack، Discord و webhook امضاشده | نیازمند تصمیم |
| B-02 | BE-04 | voice realtime با streaming و barge-in؛ باز کردن orb در ویجت، EFB و Answer Desk | نیازمند تصمیم |
| B-03 | BE-05 | صف پایدار indexing در Site Knowledge با pause، resume، retry، skip و دلیل خطا | نیازمند تصمیم |
| B-04 | S3.1 | اصلاح مکالمه‌ای Form Composer با reference chip و patch plan | نیازمند تصمیم |
| B-05 | A2.8 | price map دلاری برای Usage & Cost | نیازمند تصمیم |
| B-06 | A2.2 | backend طول پاسخ و detector تداخل با افزونه‌های AI دیگر | نیازمند تصمیم |
| B-07 | A2.7 | ویرایشگر template و شمارش استفاده در Content Studio | نیازمند تصمیم |
| B-08 | Store | قابلیت‌های Phase 2 ووکامرس به‌عنوان task امنیتی مستقل | نیازمند تصمیم |
| B-09 | S4.1 | EFB Agent Abilities: خواندن/نوشتن/حذف فرم، entry، اعلان/confirmation غیرشرطی و تنظیمات پرداخت EFB از طریق WordPress Abilities API، گیت‌شده روی EFB Free Plus/Pro؛ برنامهٔ کامل در `docs/EFB_AGENT_ABILITIES_S4_1_AUDIT_AND_PLAN_FA.md` (واکنش به قابلیت agent-callable که Gravity Forms در ۲۰۲۶-۰۸-۲۶ در همهٔ لایسنس‌هایش عرضه کرد) | نیازمند تصمیم (D-10) |

<!-- TASK-DETAILS -->

## ژورنال اجرا

جدیدترین مورد بالا است. قالب: `تاریخ — task — نتیجه — گیت‌ها و شمارها — artifactها — بعدی`.

- ۲۰۲۶-۰۹-۱۵ — T01 — تکمیل — گیت: `node --check` روی `tests/efb_copilot_browser_check.cjs` و `tests/efb_copilot_browser_matrix.cjs` با exit 0. `node tests/efb_copilot_browser_matrix.cjs` با exit 0: `en_US` 31 PASS / 0 FAIL و `fa_IR` 31 PASS / 0 FAIL. پاک‌سازی: `EFB Copilot browser fixture removed.` و `Local browser QA account removed.`. preflight ساعت 02:14 با WARNINGS 0 و BLOCKERS 0.
  - EFB: مقدار خام `active_plugins` شامل `easy-form-builder/emsfb.php` است و `is_plugin_active` true. جملهٔ «EFB غیرفعال است» فقط در snapshot کهنهٔ ۲۰۲۶-۰۹-۱۴ مانده بود و «منسوخ» علامت خورد.
  - baseline بدون تغییر (`en_US` 21 PASS / 3 FAIL): `FAILED_RESPONSES 429 http://127.0.0.1/wp/wp-json/aisaautopilot/v1/efb/chat`. منشأ خود افزونه است، نه limit-login-attempts-reloaded، Jetpack یا EFB.
    - زنجیرهٔ trace `efb-1t67penyovnju`: `efb.chat.received`، سپس `efb.chat.provider_error code=usage_cap_reached`، سپس `efb.client.chat_response_error`.
    - مسیر کد: `UsageCappedProvider::assert_available()` خطا می‌دهد و `ProviderException::status_code()` آن را 429 می‌کند. RateLimiter افزونه در `/efb/*` به کار نمی‌رود.
    - علت محیطی: کلید واقعی provider (deepseek) در WordPress Connectors ذخیره است، پس `is_configured()` true است، و سقف مصرف محلی پر شده (budget 1200 token، مصرف 1800).
    - فرض «`provider_not_configured` در QA» نادرست بود؛ فقط سقف مصرف مانع تماس با provider واقعی بود.
  - اصلاح تست، فقط در `tests/efb_copilot_browser_check.cjs` و بدون تغییر کد محصول:
    - (۱) `/efb/chat` با `page.route()` و همان پاسخ 400 `provider_not_configured` کنترلر mock شد تا هیچ provider واقعی صدا زده نشود. allow-list تغییری نکرد.
    - (۲) پیش از شمارش، روی wrapper صبر می‌شود.
    - (۳) assertion پاسخ 200 برای `/efb/log`: `[200,200]` در هر دو locale؛ callback `client_log` در مرورگر تأیید شد.
    - (۴) assertion دائمی کلیک واقعی روی `-el`: input `readonly` است و `pointer-events:none` دارد. عنصر رو در مرکز آن `div#full_name-f` درون wrapper `full_name-id` است، پس کلیک واقعی فیلد را انتخاب می‌کند. ریسک `closest` فقط برای رویداد synthetic روی خود input است و task اصلاح bridge لازم نیست.
    - (۵) `getUserMedia` با `addInitScript` رد می‌شود و پیام fallback، نبود auto-send و نبود upload صوت assert شد.
    - (۶) login به‌جای DOM داشبورد، منتظر ترک `wp-login.php` می‌ماند.
  - تلاش‌ها:
    - run1: کلیک actionability در Playwright روی `-el` timeout شد و تست هنگام Clear crash کرد.
    - run2: login روی داشبورد 30 ثانیه timeout شد؛ صفحهٔ اول سایت 12.6 ثانیه طول کشید.
    - run3: FAIL متناوب reset، چون پیام «میکروفن رد شد» پس از New chat رسید.
    - run4: سبز، اما وابسته به زمان‌بندی.
    - final: با stub میکروفن سبز.
  - یافته‌های باز:
    - (الف) رکورد `efb.client.chat_submitted` برای trace baseline در `aisaautopilot_logs` ثبت نشد، در حالی که رکوردهای هم‌زمان دیگر ثبت شدند. فرضیه: lost update در نوشتن هم‌زمان option. ← D-09
    - (ب) انتخاب دوبارهٔ همان فیلد پس از Clear هر بار پیام «You selected …» تازه می‌سازد (سه پیام تکراری در run). به T04 سپرده شد.
    - (ج) متن پاسخ mock انگلیسی است؛ رشته‌های client در T03 بررسی می‌شوند.
  
  artifactها: `test-artifacts/efb-copilot/20260915-t01/` (baseline، run1 تا run4، final با screenshotهای `en_US` و `fa_IR`، preflight-after) — بعدی: T02

- ۲۰۲۶-۰۹-۱۵ — T01 — آغاز (برنامه) — preflight ساعت 01:51 بدون warning و blocker با `efb v4.2.0 active=True`؛ probe فقط‌خواندنی: مقدار خام `active_plugins` شامل `easy-form-builder/emsfb.php` و `is_plugin_active` true. مالک پرسید چرا گفته شده EFB فعال نیست: آن جمله متعلق به snapshot ۲۰۲۶-۰۹-۱۴ و اجرای اول T00 (00:30) بود و در بخش «وضعیت محیط در شروع» اصلاح‌نشده مانده بود. برنامه: (۱) اجرای baseline بدون تغییر و ریشه‌یابی 429؛ (۲) اصلاح check برای انتظار روی wrapper، assertion پاسخ 200 `/efb/log` و assertion دائمی کلیک کنترل درونی `-el`؛ (۳) matrix کامل `en_US` و `fa_IR`؛ (۴) `node --check` و preflight پایانی.

- ۲۰۲۶-۰۹-۱۵ — T00 — تکمیل — گیت: preflight 01:19 و 01:33 با `efb v4.2.0 active=True`، WARNINGS 0 و BLOCKERS 0؛ wp-probe: `is_plugin_active` true، جدول `emsfb_form` موجود، فرم QA 0 و option یا کاربر باقی‌ماندهٔ صفر. probe فقط‌خواندنی runtime (fixture EFB و کاربر موقت `en_US`) با exit 0 و 19 PASS، 0 FAIL:
  - HTTP 200 برای `Emsfb_create` و `Emsfb` edit-form؛ `valj_efb` واژگانی و نه `window.valj_efb`؛ `editFormEfb` (تنها تابع EFB که `efb-copilot.js:327` صدا می‌زند) function است.
  - globalهای تازهٔ steps/progress (`efbStepsShellHeadEfb`، `efbStepsStyleNameEfb`، `efbProgressStyleNameEfb`، `efbStepsItemClassEfb`، `efbPickStepStyleEfb`) و CSS/runtime/builder آن‌ها بارگذاری شده‌اند؛ `EFB_STEP_STYLES_EFB` و `EFB_PROGRESS_STYLES_EFB` همان enum source هستند.
  - wrapperهای فیلد: 6 (`section` و `div`)؛ کنترل درونی `-el`: 3؛ `field-selected-efb` و انتخاب bridge و banner با کلیک wrapper پاس.
  - palette runtime: 48 نوع، همه در کاتالوگ (68 نوع)؛ نوع فیلد تازه‌ای نیست. صفر pageerror، console error و FAILED_RESPONSES.
  - cleanup: `EFB Copilot browser fixture removed.` و `Local browser QA account removed.`
  
  یافته‌ها: (۱) قرارداد bridge بدون تغییر است و task اصلاح لازم نیست. (۲) کلیک روی input درونی `-el` در Chrome فیلد را انتخاب کرد، هرچند `closest` روی `-el` متوقف می‌شود؛ ریسک T01 در عمل بازتولید نشد، ولی assertion دائمی در T01 می‌ماند. (۳) لایهٔ تازهٔ `097de0b8`: کاتالوگ `steps_style` و `progress_style` را ندارد، `show_icon` و `show_pro_bar` را اشتباه توصیف می‌کند و هنوز `4.1.x` است. T26 (منتظر) و T27 (نیازمند تصمیم با D-08) افزوده شدند و T04 به T26 وابسته شد. (۴) اجرای اول probe یک FAIL از خود probe داشت (اندازه‌گیری DOM پیش از رسم canvas)؛ با انتظار روی wrapper اصلاح و دوباره اجرا شد. هیچ کد، تست، تنظیم یا افزونه‌ای تغییر نکرد. گیت کد لازم نبود. — `test-artifacts/efb-env/20260915-t00-runtime/` (preflight-start، probe-stdout، runtime-contract.json، preflight-after، efb-copilot-fab-on-active-efb.png) — بعدی: T01

- ۲۰۲۶-۰۹-۱۵ — T00 — آغاز دوباره (برنامه) — preflight ساعت 01:19: `efb v4.2.0 active=True` بدون warning و blocker؛ wp-probe: `is_plugin_active` برابر true، جدول `emsfb_form` موجود، فرم QA و کاربر یا option باقی‌مانده صفر. HEAD تازهٔ EFB `031bfa75` (فقط `readme.txt`). مالک گفت فهرست فیلدهای ساخت فرم به‌روز شده و UI تازهٔ multi-step و progress bar افزوده شده است. برنامه: (۱) probe فقط‌خواندنی runtime قرارداد DOM روی EFB فعال با fixture و کاربر موقت و cleanup؛ (۲) مقایسهٔ palette `fields_efb` و لایهٔ steps/progress (`097de0b8`) با `includes/Efb/efb-capability-catalog.json`؛ (۳) ثبت نتیجه در plan S3.2 و افزودن task تازه در صورت نیاز؛ (۴) preflight پایانی.

- ۲۰۲۶-۰۹-۱۵ — T00 — مسدود — مالک D-01 را تأیید و فعال‌سازی هستهٔ EFB را اعلام کرد، اما شواهد runtime آن را نشان نمی‌دهند: preflight `efb v4.2.0 active=False`، `is_plugin_active` برابر false، مقدار خام `active_plugins` بدون `easy-form-builder/emsfb.php`، `recently_activated` خالی. نشانه‌ای از شکست فعال‌سازی نیست: Fatal در `debug.log` نیست، `php -l` روی `emsfb.php`، `includes/class-Emsfb.php` و `includes/functions.php` سالم، `vendor/autoload.php` موجود، و `object-cache.php` فقط stub خود Plugin Check است. بخش فقط‌خواندنی T00 انجام شد: جدول قرارداد DOM روی source نسخهٔ 4.2.0 (HEAD `63871604`) با file:line در plan S3.2 ثبت شد؛ شناسه‌ها تغییر نکرده‌اند و task اصلاح bridge لازم نیست (تنها تغییر: `<setion>` به `<section>` در commit `26515b6f`). تعارض سند: `let valj_efb` سطح بالا در `new-efb.js:16` است، نه `admin-efb.js`. ریسک تازه برای T01: کنترل‌های درونی `.efbField[data-id$="-el"]`. گیت T00 (preflight بدون هشدار EFB) هنوز قرمز است؛ هیچ کد، تست، تنظیم یا افزونه‌ای تغییر نکرد و گیت کد اجرا نشد. — `test-artifacts/efb-env/20260915-t00/` — بعدی: مالک هسته را از wp-admin فعال کند و `/aisa-next T00`؛ task اجراپذیر بدون EFB: T02

- ۲۰۲۶-۰۹-۱۴ — راه‌اندازی — رودمپ، ایجنت `aisa-finisher`، skillها و ابزارهای `scripts/agent` ساخته شدند. preflight: junction سالم، سرویس‌ها فعال، EFB core غیرفعال (T00 ← نیازمند تصمیم)، fixture یا کاربر QA باقی‌مانده وجود ندارد. گیت استاتیک 5/5 سبز: php-lint 210/210، node-check 28/28، PHPCS 130/130 (0/0)، PHPStan 0، PHPUnit 135 / 442. smokeهای admin: menu 5/0، assets 19/0، render 18/0 بدون PHP diagnostic. — `test-artifacts/gates/20260914-234954-static`، `test-artifacts/gates/20260915-000038-only` — بعدی: T02
