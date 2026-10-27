# کیفیت، آزمون و انتشار

## لایه‌های آزمون

| لایه | جا | چه چیزی را ثابت می‌کند | قاعده |
|---|---|---|---|
| واحد | `tests/*Test.php` و `tests/Unit/` (PHPUnit، `phpunit.xml` که بر `phpunit.xml.dist` مقدم است) | منطق خالص: validatorها، `ActionStore`، `KeyVault`، `Redactor`، کاتالوگ و مبدل EFB، اعتبارسنجی فرم و بایت‌های آغازین فایل صوتی (`LanguageAndVoiceTest`)، تحلیل پاسخ provider؛ و `UninstallInventoryTest` که هر ثابت option و جدول `includes/` را در `uninstall.php` می‌جوید | بدون WordPress و بدون شبکه |
| واحد (Node) | `tests/admin_chat_markdown_check.cjs` | رندر Markdown کنسول مدیر (`chat.js`) با یک DOM ساختگی: نقل‌قول درون نشانی پیوند نمی‌تواند از `href` بیرون بیاید | بدون مرورگر و بدون WordPress؛ `node tests/admin_chat_markdown_check.cjs`، هر شکست با exit 1 |
| smoke | `tests/local_admin_menu_smoke.php`، `local_admin_assets_smoke.php`، `local_admin_render_smoke.php` | ده آیتم منو و aliasها، اسکریپت‌های هر صفحه و رندر بدون خطای PHP | فقط‌خواندنی. render smoke همیشه با 0 خارج می‌شود و خطا را با خط `:: error ::` گزارش می‌کند که `run-gates.ps1` می‌خواند. |
| integration | `tests/wordpress_phase1_integration.php` و 51 فایل `tests/wordpress_*_integration.php` | قرارداد واقعی WordPress: رندر صفحه، ذخیره، REST، مجوز، مصرف، لاگ هم‌زمان، چرخهٔ حیات | `wp-load.php` را از `AISAAUTOPILOT_WP_ROOT` یا `C:\xampp\htdocs\wp` بار می‌کند، optionها را با نگهبان «وجود نداشت» snapshot و در `finally` بازمی‌گرداند، ترافیک بیرونی را با `pre_http_request` و ایمیل را با `pre_wp_mail` مسدود می‌کند، و خط‌های `PASS` و `FAIL` و کد خروج چاپ می‌کند. |
| مرورگر | `tests/browser_release_check.cjs`، matrixها و fixtureها | ظاهر و تعامل واقعی در `en_US` و `fa_IR`، در 1440 و 390 پیکسل، صفحه‌کلید، RTL، نبود درخواست خودکار و نبود خطای console | playbook: `.claude/skills/aisa-browser-qa/SKILL.md` |

`phpcs.xml.dist` پوشهٔ `tests` را استثنا می‌کند و PHPStan اسکریپت‌های آزمون را تحلیل نمی‌کند. پس فایل‌های integration فقط `php -l` می‌گیرند و باید خودشان تمیز نوشته شوند.

**دام‌های اسکریپت‌های integration:**

- **نام متغیر سراسری:** اسکریپت در scope سراسری اجرا می‌شود. متغیری با نام یکی از globalهای WordPress، مثل `$post`، `$wp_query`، `$wp` یا `$page`، آن global را بازنویسی می‌کند.
  - نمونه: در `wordpress_widget_handoff_flow_integration` یک closure به نام `$post` بود. LiteSpeed Cache روی `shutdown` تابع `get_the_ID()` را صدا زد و پس از 16 بررسی موفق، fatal و کد خروج 255 رخ داد.
  - این fatal فقط در `wp-content/debug.log` ثبت می‌شود. گیت کد خروج غیرصفر را شکست می‌داند.
- **mu-plugin ذخیرهٔ ایمیل:** اگر روی سایت محلی mu-pluginی مثل `efb-mail-capture.php` هر `wp_mail()` را ذخیره کند، فیلتر `pre_wp_mail` آزمون جلوی نوشتن آن را نمی‌گیرد.
  - آزمونی که ایمیل می‌فرستد باید فقط نسخه‌های همان اجرا را با marker تصادفی خودش پاک کند.
- **`??` روی مقدار null:** `null === ( $row['archived_at'] ?? 'missing' )` همیشه نادرست است، چون `??` مقدار null ذخیره‌شده را هم با پیش‌فرض عوض می‌کند. برای ستون‌های nullable از `array_key_exists` استفاده کنید. این دام دو بار در آزمون‌های ۲۰۲۶-۰۹-۱۶ پیش آمد.
- **زبان پاسخ REST:** WordPress پاسخ REST را به زبان سایت می‌سازد. `_locale=user` فقط وقتی اثر دارد که درخواست JSON باشد (سرآیند `Accept` یا `Content-Type` برابر `application/json`). یک GET بدون این سرآیند در `fa_IR` برچسب انگلیسی برمی‌گرداند. بررسی مرورگر در `fa_IR` باید متنی را که سرور می‌سازد هم بسنجد.

## گیت‌ها

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File "C:\files\projects\SitePilot AI\scripts\agent\run-gates.ps1" -Tier static|smoke|full|release
powershell -NoProfile -ExecutionPolicy Bypass -File "C:\files\projects\SitePilot AI\scripts\agent\run-gates.ps1" -Only phpcs,phpstan
```

| tier | گیت‌هایی که اضافه می‌کند | اثر جانبی |
|---|---|---|
| `static` | php-lint، node-check، PHPCS (0 خطا و 0 هشدار)، PHPStan (سطح 5)، PHPUnit | ندارد |
| `smoke` | menu-smoke، assets-smoke، render-smoke | bootstrap فقط‌خواندنی WordPress |
| `full` | phase1-integration | روی سایت محلی می‌نویسد و در `finally` بازمی‌گرداند |
| `release` | همهٔ integrationهای دیگر | همان، و برخی suiteها EFB یا WooCommerce فعال می‌خواهند |

- **خروجی:** هر اجرا `summary.md`، `summary.json` و لاگ هر گیت را در `test-artifacts\gates\<stamp>-<tier>\` می‌نویسد. شمار خطاهای PHP افزونهٔ ما (`ours`) و افزونه‌های دیگر (`others`) کنار هر گیت WordPress می‌آید.
- **composer:** `composer test`، `composer lint` و `composer analyze` هم هستند. `lint` هشدارها را نادیده می‌گیرد و جای گیت را نمی‌گیرد.

### خط مبنای فعلی

جدول رسمی خط مبنا در [`PRODUCT_COMPLETION_ROADMAP_FA.md`](../PRODUCT_COMPLETION_ROADMAP_FA.md) است. اعداد زیر از اجرای 2026-09-16 هستند (`test-artifacts/gates/20260916-204545-release`، نتیجهٔ کلی **60/60**).

| گیت | مقدار |
|---|---|
| php-lint و node-check | 229 از 229 و 37 از 37 فایل |
| PHPCS | 133 فایل، 0 خطا، 0 هشدار |
| PHPStan | 0 خطا |
| PHPUnit | 155 آزمون، 544 assertion (T20؛ پیش از آن 149 / 514) |
| menu، assets و render smoke | 5، 19 و 18 مورد موفق |
| Phase 1 | 45 موفق و 0 ناموفق |
| release | هر 51 suite موفق، بدون خطای PHP افزونه |

**قاعدهٔ مقایسه:**
- شمار PHPUnit فقط می‌تواند زیاد شود و PHPCS و PHPStan صفر می‌مانند.
- پیش از نامیدن شکست release به‌عنوان regression، با `scripts\agent\preflight.ps1` بسنجید که suite به افزونهٔ غیرفعالی نیاز ندارد.
- assertion را برای سبز شدن سست نکنید. اگر قرارداد عمداً تغییر کرده، آزمون را دقیق‌تر بازنویسی و دلیل را ثبت کنید. نمونه: `wordpress_answer_handoff_page_integration` پس از افزودن نوار «صندوق انتقال به انسان» به tab انتقال، به‌جای نبود متن، نبود فهرست و کشو و وجود پیوند درست را می‌سنجد.

## آزمون مرورگر

- **الگو:** matrix کاربر موقت می‌سازد، fixture را آماده، check را اجرا و fixture را بازمی‌گرداند. در `finally` کاربر حذف می‌شود و اطلاعات ورود فقط در env زیرفرایند می‌ماند.
- **دادهٔ آزمون:** همهٔ مسیرهای AI و تحویل با `page.route` پاسخ ساختگی می‌گیرند و هیچ provider، ایمیل، پیامک یا Telegram واقعی صدا زده نمی‌شود.

| matrix | fixture | صفحه |
|---|---|---|
| `activity_log_browser_matrix.cjs` | `local_activity_log_browser_fixture.php` | `aisaautopilot-logs` با حالت‌های پیش‌نویس، خالی و خطا |
| `advanced_settings_browser_matrix.cjs` | `local_advanced_settings_browser_fixture.php` | `aisaautopilot-settings` |
| `chat_console_browser_matrix.cjs` | ندارد: همهٔ مسیرهای `/chat/*` در check پاسخ داده می‌شوند | `aisaautopilot-chat` |
| `efb_copilot_browser_matrix.cjs` | `local_efb_copilot_browser_fixture.php` | ویرایشگر EFB با `efb_copilot_browser_check.cjs` |
| `efb_details_browser_matrix.cjs` | ندارد | `aisaautopilot-efb` |
| `form_composer_browser_matrix.cjs` | ندارد | Content Studio › فرم‌ساز |
| `answer_handoff_browser_matrix.cjs` | `local_answer_conversations_browser_fixture.php` (snapshot کل تنظیمات) | Answer Desk › Human handoff، همراه کارت انتقال ویجت روی صفحهٔ عمومی با `/widget/handoff` ساختگی |
| `handoff_inbox_browser_matrix.cjs` | `local_handoff_inbox_browser_fixture.php` (نشانگر نام `AutoPilot QA · `؛ پنج گفت‌وگو، یکی بایگانی‌شده و یکی با موبایل و وب‌سایت) | `aisaautopilot-handoffs`؛ «Archive resolved» فقط وقتی اجرا می‌شود که همهٔ گفت‌وگوهای حل‌شدهٔ سایت از fixture باشند |
| `kb_answers_browser_matrix.cjs` | `local_kb_answers_browser_fixture.php` (snapshot دو option پرسش‌ها و پاسخ‌ها و یک پیش‌نویس با نشانگر) | Site Knowledge › پرسش‌های بی‌پاسخ و پاسخ‌های ذخیره‌شده، و ورود به Content Studio › Write |
| `widget_customise_browser_matrix.cjs` | `local_widget_customise_browser_fixture.php` | `aisaautopilot-widget` |
| `admin_ia_browser_matrix.cjs` (T56) | ندارد: check فقط پیمایش و اندازه‌گیری می‌کند و چیزی ذخیره نمی‌کند؛ فقط کاربر QA موقت | IA هشت‌صفحه‌ای D-23 با `admin_ia_browser_check.cjs`: منوی کناری دقیقاً ۸ آیتم به ترتیب D-23 و بدون هیچ alias، هر ۸ صفحهٔ اصلی و سه تب پنهان Overview (Usage & Cost، Health، Activity Log) در 1440×960 و 390×844 با HTTP 200، پوستهٔ `.sp-app`، جهت محاسبه‌شده، صفر overflow افقی، عنوان فارسی در `fa_IR` و برجسته شدن آیتم مالک در منو؛ نوار تب `nav.sp-overview-tabs` با ۴ تب و تنها یک `aria-current="page"`؛ Tab با focus مرئی و Enter روی تب؛ صفحهٔ Form Studio و لینک عمیق قدیمی `form-builder&mode=forms` بدون redirect؛ صفر console/pageerror/HTTP≥400. قابل محدود کردن با `AISAAUTOPILOT_ADMIN_IA_LOCALES`؛ تصویرها در `test-artifacts/admin-ia/<locale>/` |
| `admin_a11y_browser_matrix.cjs` (T17) | ندارد: فقط‌خواندنی؛ فقط کاربر QA موقت، و اگر حساب مشترک در دست job دیگری باشد با exit 2 رد می‌کند | دسترس‌پذیری، RTL و responsive همهٔ ۲۶ route مدیریتی (۱۸ route render smoke، کنسول KB، سفارشی‌سازی ویجت، EFB Copilot، Form Studio و alias قدیمی `aisaautopilot-woocommerce`) با `admin_a11y_browser_check.cjs`، در 1440، 1024 و 390: صفر overflow افقی، هیچ کنترل یا متن بیرون از viewport، هیچ متن بریده بی‌ellipsis، هیچ مقدار بریده با ellipsis بی‌`title`، عنوان صفحه روی لبهٔ آغاز ردیف (F15-1)، لبهٔ پایان سربرگ در همهٔ صفحه‌ها یکسان (T16، V16-1)؛ در 1440: نام دسترس‌پذیر برای هر دکمه و پیوند، برچسب برای هر فیلد، کنتراست WCAG AA برای هر متن مرئی (4.5:1، و 3:1 برای متن بزرگ؛ کنترل `:disabled` معاف)، و گذر Tab روی صفحه با نشانگر focus مرئی برای هر توقف (سبک focus پس از پایان transition سنجیده می‌شود)؛ در 390: WCAG 2.5.8 (24×24 یا استثنای فاصله)؛ `prefers-reduced-motion` بدون هیچ انیمیشن بلندتر از 1ms در `.sp-app`؛ و dialog Integrations، confirm مشترک (reset مصرف، بدون درخواست پس از رد) و کشوی صندوق انتقال با Enter، focus درون، Escape و بازگشت focus. قابل محدود کردن با `AISAAUTOPILOT_A11Y_LOCALES` و `AISAAUTOPILOT_A11Y_ROUTES`؛ تصویرها در `test-artifacts/admin-a11y/<locale>/` |
| `release_smoke_browser_matrix.cjs` (T15) | در گذر ۱ `local_store_copilot_browser_fixture.php` و `local_answer_conversations_browser_fixture.php` (هر دو snapshot کل تنظیمات؛ بازگردانی LIFO)، در گذر ۲ `local_activity_log_browser_fixture.php` | browser smoke انتشار: `browser_release_check.cjs` با فهرست پیش‌فرض ۱۹ route (با `aisaautopilot-form-studio`) و همهٔ بررسی‌های پیش‌فرض سطح‌ها. گذر ۱ همهٔ routeها جز Activity Log، گذر ۲ فقط Activity Log در fixture خودش، چون fixture Answer Desk پستی منتشر می‌کند که index آن با WP-Cron یک ردیف واقعی به لاگ می‌افزاید و شمار دقیق ردیف‌های fixture لاگ را می‌شکند. با `AISAAUTOPILOT_BROWSER_CAPTURE=1` از هر route تصویر 1440 و 390 (`admin-<slug>-desktop|mobile.png`) و overflow در 390 (`ROUTE_390`، تشخیصی) ثبت می‌شود. قابل محدود کردن با `AISAAUTOPILOT_RELEASE_SMOKE_LOCALES`؛ تصویرها در `test-artifacts/release-smoke/<locale>/` |
| `front_chat_widget_browser_matrix.cjs` | `local_front_chat_widget_browser_fixture.php` (snapshot کل `aisaautopilot_settings` و `WPLANG`؛ سناریوی `AISAAUTOPILOT_FRONT_WIDGET_SCENARIO` برابر `main`، `auto` یا `quiet`؛ تنها گیرنده `.invalid`) | ویجت بازدیدکننده روی صفحهٔ اول قالب فعال، **بدون کاربر QA** چون سطح بازدیدکنندهٔ خارج‌شده است؛ زبان از زبان سایت می‌آید، نه meta کاربر. check با `front_chat_widget_browser_check.cjs`: keyboard از صفحه تا launcher، حلقهٔ Tab با focus مرئی، کنتراست WCAG، hit target، overflow، گفت‌وگو، کارت انتقال، voice (حالت ضبط یک‌خطی، قاب coral، زمان و دکمهٔ توقف)، full screen، و sheet modal در 390px (قفل صفحه با wheel، نگه داشتن Tab و بازگشت جای scroll پس از بستن). پیش از سنجش رنگ، پایان CSS transitionها را انتظار می‌کشد، چون قالب hello-elementor روی دکمه‌ها `transition: all` دارد، و scroll صفحه را با `behavior: 'instant'` می‌برد، چون قالب `scroll-behavior: smooth` دارد. تنها فراخوان واقعی `POST /public/voice` با voice خاموش است که 403 می‌گیرد. قابل محدود کردن با `AISAAUTOPILOT_FRONT_WIDGET_LOCALES` و `AISAAUTOPILOT_FRONT_WIDGET_SCENARIOS` |

fixtureهای بدون matrix با پرچم‌های env مستقیم کنار `browser_release_check.cjs` به کار می‌روند:
- `local_answer_conversations_browser_fixture.php` (کل option تنظیمات را snapshot می‌کند)
- `local_content_studio_history_browser_fixture.php`
- `local_store_copilot_browser_fixture.php`
- `local_usage_browser_fixture.php` با `AISAAUTOPILOT_BROWSER_USAGE_FIXTURE=1`

**پرچم‌های مهم `browser_release_check.cjs`:**

| پرچم | کاربرد |
|---|---|
| `AISAAUTOPILOT_BROWSER_SLUGS` | فهرست صفحه‌ها؛ پیش‌فرض ۱۹ route از جمله `aisaautopilot-form-studio` (T15) |
| `AISAAUTOPILOT_BROWSER_EXCLUDE_SLUGS` | حذف slugهایی از فهرست که wrapper جداگانه با fixture خودشان اجرا می‌کند (T15) |
| `AISAAUTOPILOT_BROWSER_CAPTURE=1` | تصویر 1440 و 390 هر route و چاپ `ROUTE_390 <slug> overflow=<px>` |
| `AISAAUTOPILOT_BROWSER_ANSWER_DESK` | `conversations`، `widget`، `handoff`، `voice` یا `all` |
| `AISAAUTOPILOT_BROWSER_SKIP_SPECIALIST=1` | رد کردن بررسی فرم‌ساز و ویجت عمومی |
| `AISAAUTOPILOT_BROWSER_EXPECT_RTL` | انتظار RTL در `fa_IR` |
| `AISAAUTOPILOT_BROWSER_QUICK_KB` | بررسی سریع دانش سایت |
| `AISAAUTOPILOT_SCREENSHOT_DIR` | مسیر تصویرها |

**پس از هر اجرا:** `scripts\agent\preflight.ps1` باید نشان دهد option باقی‌ماندهٔ fixture، کاربر QA یا فرم QA در EFB وجود ندارد.

## ترجمه

1. **رشته:** هر رشتهٔ نمایشی با text domain `ai-site-assistant-autopilot` در PHP نوشته می‌شود. متن JS با `Ui::json_script()` یا `wp_localize_script` از PHP می‌آید.
2. **ترجمه:** ترجمهٔ فارسی در `languages/ai-site-assistant-autopilot-fa_IR.po` اضافه می‌شود، با `msgid_plural` و دو `msgstr[n]` برای `_n()`.
3. **کامپایل:** MO با `C:\xampp\php\php.exe scripts/compile-mo.php C:/xampp/htdocs/wp languages/ai-site-assistant-autopilot-fa_IR.po languages/ai-site-assistant-autopilot-fa_IR.mo` ساخته می‌شود و هرگز دستی نوشته نمی‌شود. `msgfmt --check` از `C:\Program Files (x86)\Poedit\GettextTools\bin` درستی قالب را می‌سنجد.
   - **خواندن خروجی اسکریپت:** خط پایانی سه عدد می‌دهد — «N PO entries merged؛ M of them translated and written to the MO؛ K untranslated, not written». عددی که باید ثبت شود **M** است، چون `MO::export_to_file()` فقط entryهای دارای ترجمه را می‌نویسد؛ **N** شمار کل msgidهای PO است و اگر به‌جای M ثبت شود، اندازهٔ کاتالوگ عرضه‌شده را بزرگ‌تر از واقع نشان می‌دهد. تا ۲۰۲۶-۰۹-۱۶ اسکریپت فقط N را چاپ می‌کرد و آن را «translated entries compiled» می‌نامید؛ خط مبنای رودمپ به همین دلیل اصلاح شد.
   - **K بی‌ترجمه‌ها:** بیشترشان entryهای کهنه‌ای هستند که msgid آن‌ها دیگر در `includes/` و `assets/` نیست و ارجاع `#:` ندارند. T18 با `msgmerge` آن‌ها را obsolete می‌کند. پیش از آن، تنها راه تشخیص «کهنه» از «شکاف واقعی ترجمه» جست‌وجوی msgid در منبع است، نه اتکا به نبود `#:`.
4. **بررسی:** رشته‌ها در اجرای مرورگر `fa_IR` دیده می‌شوند و check آن فارسی بودن متن‌های کلیدی را می‌سنجد.
5. **POT:** POT فقط در task T18 و با روال همان task بازسازی می‌شود.

## شمارهٔ نسخه و متادیتای انتشار

**نسخهٔ جاری `0.0.1`** (تصمیم مالک D-03، ۲۰۲۶-۰۹-۲۰). نسخه دقیقاً در سه جا نوشته می‌شود و هر سه باید یکی باشند:

1. سربرگ `Version:` در `ai-site-assistant-autopilot.php`؛
2. `define( 'AISAAUTOPILOT_VERSION', ... )` در همان فایل؛
3. `Stable tag:` در `readme.txt`.

**سیاست bump، الزامی تا رسیدن به `1.0.0`:** فقط **راست‌ترین جزء (patch)** یک واحد بالا می‌رود؛ minor و major دست نمی‌خورند. یعنی نسخهٔ بعدی `0.0.2` است. این تصمیم مالک است و در task تازه دوباره بررسی نمی‌شود.

`AISAAUTOPILOT_VERSION` جایی برای منطق مهاجرت نیست: تنها مصرف‌هایش cache-busting در `wp_enqueue_style/script` و یک فیلد اطلاعاتی `version` در `NativeFormConnector` است. هیچ‌جای کد `version_compare` روی نسخهٔ افزونه ندارد، پس پایین رفتن شماره از 1.0.4 به 0.0.1 رفتاری را عوض نمی‌کند (و هیچ سایتی هم مسیر ارتقا ندارد، چون هیچ نسخه‌ای منتشر نشده است).

`Tested up to` در `readme.txt` برابر بالاترین major.minor واقعاً آزموده است — اکنون **7.1**؛ سایت محلی 7.1.2-alpha است و شمارهٔ alpha نوشته نمی‌شود.

`readme.txt` علاوه بر توضیح و changelog دو بخش الزامی دارد: `== External services ==` که هر درخواست بیرونی را نام می‌برد (۱۴ provider AI و endpoint سفارشی، Telegram، Twilio، WhatsApp Business، Slack، Signal و **webfontهای Google Fonts در صفحه‌های مدیریت**)، و `== What deleting the plugin removes ==` که با جدول `## حذف افزونه` در `DATA_MODEL_FA.md` یکی است. `RELEASE_NOTES.md` همان فهرست را با اعداد گیت‌ها دارد و `MANUAL-TESTING.md` آن را به‌صورت گام دستی Part 12 می‌آزماید.

## ساخت نسخهٔ انتشار

`scripts/build-phase1-release.ps1` (با مسیر مطلق اجرا شود):

1. **نسخه:** نسخه را از سربرگ افزونه می‌خواند.
2. **ترجمه — build هرگز MO را نمی‌نویسد.** اگر `msgfmt` در دسترس باشد، PO را با `--check` در یک فایل **موقت** کامپایل می‌کند تا فقط درستی قالبش سنجیده شود، و آن فایل در `finally` پاک می‌شود. سپس دو شرط را می‌سنجد: `languages/ai-site-assistant-autopilot-fa_IR.mo` وجود دارد، و از PO قدیمی‌تر نیست؛ در غیر این صورت با پیام «Run scripts/compile-mo.php» متوقف می‌شود. **دلیل (نقص رفع‌شده در T22):** اسکریپت تا آن روز با `msgfmt --output-file $moFile` روی خودِ MO ریپو می‌نوشت. یعنی هر ساخت یک فایل tracked را بی‌صدا عوض می‌کرد و ZIP نسخه‌ای از MO می‌برد که هیچ گیتی رویش اجرا نشده بود: ۲۴۳٬۵۸۰ بایت به‌جای ۲۳۳٬۸۷۳ (msgfmt جدول hash می‌نویسد و `POT-Creation-Date` را می‌اندازد). محتوای پیام یکی است — هر دو ۱۸۱۹ پیام ترجمه‌شده و `msgunfmt` فقط یک خط اختلاف می‌دهد — ولی MOیی که عرضه می‌شود باید همانی باشد که QA رویش اجرا شده است. تنها نویسندهٔ MO `scripts/compile-mo.php` است (بخش «ترجمه» بالا).
3. **کپی:** افزونه را با `robocopy` در پوشهٔ موقت کپی می‌کند:
   - پوشه‌های استثنا: `.git`، `.github`، `.claude`، `vendor`، `node_modules`، `tests`، `docs`، `scripts`، `dist`، **`test-artifacts`** و cacheها؛
   - فایل‌های استثنا: پیکربندی ابزارها، markdownهای توسعه و **`handoff.md`** (که در ریشهٔ افزونه است و allow-list ریشه را می‌شکند).
   - این‌ها فهرست استثنا هستند، نه حذف: هیچ فایلی از worktree پاک نمی‌شود.
4. **ZIP:** `dist/ai-site-assistant-autopilot-<version>.zip` را با جداکنندهٔ `/` می‌سازد. `Compress-Archive` در PowerShell 5.1 جداکنندهٔ `\` می‌نویسد و افزونه روی WordPress.org نصب نمی‌شود.
5. **اعتبارسنجی ZIP:**
   - فایل‌های لازم (`ai-site-assistant-autopilot.php`، `readme.txt`، `includes/Core/Plugin.php`) وجود دارند؛
   - هیچ entry جداکنندهٔ `\` ندارد؛
   - ریشه فقط `ai-site-assistant-autopilot.php`، `uninstall.php` و `readme.txt` را دارد (allow-list، نه deny-list)؛
   - هیچ پوشهٔ توسعه‌ای در ZIP نیست: regex اکنون `vendor|node_modules|tests|docs|scripts|dist|test-artifacts|.git|.github|.claude|.phpstan.cache|.phpunit.cache` را می‌گیرد.

**خروجی معتبر فعلی (بازسازی ۲۰۲۶-۰۹-۲۱):** `dist/ai-site-assistant-autopilot-0.0.1.zip` — ۱۷۰ entry (۱۳۴ PHP، ۲۴ JS، ۵ CSS، سه فایل ریشه، سه فایل `languages/`)، **۷۶۰٬۴۳۲ بایت، SHA-256 `68FA68A56CFDBB7C11608B7794B42D375DE3F1596F14954AD29C45EB527F3B47`**. کپی پشتیبان و `zip-verification.txt` (۳۱ PASS / ۰ FAIL) در `test-artifacts/release-zip/20260921-t22-rebuild/`.

> **ساخت پیشین منسوخ شد.** ساخت T22 (۲۰۲۶-۰۹-۲۰، ۷۵۹٬۳۸۸ بایت، SHA-256 `90E5AE16…`) پس از بازگشت تصمیم D-34 و رفع هر ۲۴ یافتهٔ Plugin Check جایگزین شد و با پیشوند `SUPERSEDED-` در `test-artifacts/release-zip/20260920-t22/` فقط برای سابقه مانده است. اختلاف دو ساخت ۱٬۰۴۴ بایت است و فقط کامنت، `phpcs:ignore` و `esc_attr()`؛ **MO دست نخورد** و همان `03FAF4AA…` ماند. شمارهٔ نسخه `0.0.1` ماند چون هرگز منتشر نشد.

**وارسی مستقل، جدا از خودِ build:** `test-artifacts/release-zip/20260920-t22/verify-zip.ps1` فهرست entryها را با درخت منبع می‌سنجد (هر فایل `includes/`، `assets/` و `languages/` باید باشد و چیز دیگری نباید باشد)، `.mo`/`.po`/`.pot` و سه فایل ریشه را بایت‌به‌بایت با منبع مقایسه می‌کند، و نسخه را **از داخل ZIP** در هر سه جا می‌خواند. اعتبارسنجی خودِ build را باور نکنید: فهرست استثنا deny-list است و یک فایل تازه در پوشه‌ای مجاز بی‌صدا عرضه می‌شود.

`scripts/check-zip.ps1` فقط ابزار بازرسی دستی چند entry در ZIP نسخهٔ 1.0.4 است.

### Plugin Check و پذیرش

- **Plugin Check:**
  - روی ZIP ساخته‌شده اجرا می‌شود، نه پوشهٔ توسعه: ZIP در `plugins/ai-site-assistant-autopilot/` باز می‌شود و junction به‌صورت امن جابه‌جا می‌شود؛
  - با `php -d memory_limit=2G`؛
  - روش کامل در `.claude/skills/aisa-release/SKILL.md` است.
- **قرارداد runner (تثبیت‌شده در T23، ۲۰۲۶-۰۹-۲۰، روی Plugin Check 2.0.0):**
  - `Check_Context` با مسیر **مطلق** فایل اصلی، slug برابر text domain و mode `new` (سخت‌گیرانه‌ترین حالت، مخصوص افزونهٔ تازه)؛
  - checkها از `Default_Check_Repository::get_checks( Check_Repository::TYPE_STATIC )` و فیلتر با `Static_Check` که از `WordPress\Plugin_Check\Checker` وارد می‌شود — namespaceِ `...\Checker\Checks` بی‌صدا صفر check می‌دهد؛
  - اجرای هر check جداگانه با `Checks::run_checks( $context, array( $check ) )` تا هر یافته به slug خودش نسبت داده شود؛ این متد آرگومان سوم (`Check_Runner`) را اختیاری می‌گیرد و `Preparation` هر check را خودش اجرا و پاک می‌کند؛
  - **هیچ نتیجه‌ای بدون `checks_run > 0` باور نمی‌شود**، و runner اگر صفر check resolve شود با خطا خارج می‌شود؛
  - در این نسخه **۲۷ static check** وجود دارد (۳۳ check ثبت‌شده منهای ۶ runtime) و هیچ check تجربی‌ای نیست، پس `INCLUDE_EXPERIMENTAL` چیزی اضافه نمی‌کند؛
  - **کنترل مثبت الزامی است:** همان runner روی یک افزونهٔ واقعی دیگر (مثلاً `akismet`: ۳۳ error و ۱۰۱ warning) اجرا شود تا ۰/۰ گرفتن معنا داشته باشد.
- **جابه‌جایی امن junction** فقط با اسکریپت `try/finally` و `-DryRun` پیش از اجرای واقعی. هرگز حذف بازگشتی از میان junction. پوشهٔ استخراج‌شده تنها وقتی حذف می‌شود که هم‌زمان: reparse point نباشد، `LinkType` خالی باشد، هیچ نشانهٔ منبع (`handoff.md`، `docs`، `tests`، `scripts`، `vendor`، `node_modules`، `.git`، `test-artifacts`، `composer.json`) در آن نباشد، و شمار فایل‌هایش با شمار entryهای ZIP برابر باشد. پس از restore باید `LinkType=Junction` و `Target` برابر مسیر منبع تأیید شود و سپس preflight و `run-gates.ps1 -Only render-smoke` اجرا شود.
- **گوگل‌فونت علامت نمی‌خورد.** `includes/Admin/AdminMenu.php:223` دو webfont را از `fonts.googleapis.com` بار می‌کند و این در `readme.txt` بخش `== External services ==` افشا شده است. Plugin Check 2.0.0 آن را «منبع بیرونی» نمی‌شمارد: فهرست دامنه‌ها در `vendor/plugin-check/phpcs-sniffs/PluginCheck/Helpers/OffloadingServicesTrait.php` فقط `ajax\.googleapis\.com` را دارد و `gstatic.com` را با lookbehind صریحِ `(?<!fonts\.)gstatic\.com` استثنا می‌کند. تنها یافتهٔ آن خط، نبودِ پارامتر نسخه است (`EnqueuedResourceParameters.MissingVersion`)، چون sniff مقدار `null` را هم «نسخهٔ تنظیم‌نشده» می‌شمارد.
- **وضعیت `0.0.1`: ۲۷ از ۲۷ check با ۰ error و ۰ warning (`RESULT PASS`).** شاهد: `test-artifacts/plugin-check/0.0.1-rebuild-20260921/`.
  - سابقه: اجرای اول T23 روی ساخت ۲۰۲۶-۰۹-۲۰ **۱۱ error و ۱۳ warning** داد که ۲۳ از ۲۴ مثبت کاذب بودند. مالک ابتدا پذیرش مستند را برگزید و سپس در **D-34** به گزینهٔ (ج) برگشت، پس هر ۲۴ یافته در **۱۰ فایل** رفع شد و ZIP دوباره ساخته شد.
  - **دو جایی که رفع بدیهی غلط می‌بود و عمداً فقط ignore مستند گرفتند:** `Providers/UsageCappedProvider.php` (پیام سازندهٔ `ProviderException` فقط به Logger و بدنهٔ `wp_json_encode()` می‌رود؛ `esc_html__()` موجودیت HTML را داخل JSON می‌گذاشت) و `Admin/WooCommerceSettings.php` (مقدار خام `$_GET['tab']` فقط ذخیره و در `finally` بازگردانده می‌شود؛ sanitize کردنش superglobal فراخواننده را بی‌صدا بازنویسی می‌کرد).
  - پس از تغییر، تیر `-Tier full` کامل دوباره اجرا شد (**۹/۹**، `test-artifacts/gates/20260921-013502-full`)، ZIP دوباره وارسی مستقل شد (**۳۱ PASS / ۰ FAIL**) و پذیرش نصب تمیز تکرار شد (**۲۳ PASS / ۰ FAIL**، `debug.log` صفر بایت رشد).
- **پذیرش نصب تمیز (T24، تثبیت‌شده ۲۰۲۶-۰۹-۲۱):** روی یک وردپرس جدا انجام می‌شود، نه نصب توسعه.
  - محیط باید واقعاً تمیز باشد: صفر افزونهٔ فعال، **صفر mu-plugin**، locale `en_US`، cron روشن، `wp-config.php` بدون فایل سناریو و ثابت اضافه، و **بدون هیچ junction** — افزونه واقعاً از ZIP باز می‌شود. یک نصبِ harness (مثل `wp-test-ai` که mu-plugin سراسری و تزریق ثابت دارد) هیچ‌چیز را اثبات نمی‌کند.
  - **`debug.log` باید پیش از شروع وجود نداشته باشد**، وگرنه «فعال‌سازی بدون PHP warning» سنجیدنی نیست. هر خطی که بعد پیدا شد باید تک‌تک انتساب داده شود؛ deprecationهای خود هسته (مثل `the_block_template_skip_link` که `default-filters.php` روی `wp_footer` می‌بندد) از افزونه نیستند و با grep اثبات می‌شوند.
  - ردپا در سه نقطه snapshot می‌شود — پیش از نصب، پس از فعال‌سازی، پس از حذف — و شش شمارنده باید به پایه برگردند: option، جدول، transient، cron، legacy option و post meta.
  - حذف با `deactivate_plugins()` و سپس `delete_plugins()` انجام می‌شود، چون همان مسیری است که `wp-admin/plugins.php` می‌رود و فقط این‌طور `uninstall.php` واقعاً اجرا می‌شود.
  - smokeهای `tests/local_admin_*_smoke.php` مسیر `C:/xampp/htdocs/wp/wp-load.php` را hard-code دارند؛ برای نصب دیگر **کپی scratchpad** ساخته می‌شود و هیچ فایل مخزنی تغییر نمی‌کند. نکته: `local_admin_assets_smoke.php` وجود فایل را با `dirname( __DIR__ )` می‌سنجد که نسبت به **مسیر خود اسکریپت** حل می‌شود؛ در کپی باید `AISAAUTOPILOT_DIR` شود تا نسخهٔ نصب‌شده سنجیده شود.
  - **کپی را با BOM ننویسید.** `Set-Content -Encoding utf8` در PowerShell 5.1 خروجی BOM‌دار می‌دهد و `declare(strict_types=1)` دیگر اولین دستور نیست، پس PHP با fatal error می‌ایستد. از `[IO.File]::WriteAllText` با `UTF8Encoding($false)` استفاده کنید.
  - **`/aisaautopilot/v1` خام مال افزونه نیست.** هستهٔ وردپرس برای هر namespace یک index کشف با callback `WP_REST_Server::get_namespace_index` و بدون `permission_callback` می‌سازد. شرط «هر مسیر permission دارد» باید روی مسیرهای زیرِ `/aisaautopilot/v1/` اجرا شود (اکنون ۶۵ مسیر و ۱۳۲ handler)، و هویت core بودن آن index جداگانه سنجیده شود.
- **روال انتشار:** T18 تا T25 در نقشهٔ راه، با تصمیم‌های مالک:
  - D-03 برای نسخه؛
  - D-04 برای وردپرس تمیز؛
  - D-05 برای commit.

## تعریف «انجام شد» برای یک بسته

- PHPCS با 0 خطا و 0 هشدار، PHPStan 0، PHPUnit سبز و نه کمتر از خط مبنا، Phase 1 و همهٔ integrationها سبز.
- اجرای مرورگر در `fa_IR` (RTL) و در 390 پیکسل، بدون خطای console، pageerror یا پاسخ ناموفق `/aisaautopilot/v1/`.
- ترجمهٔ فارسی کامل و MO کامپایل‌شده.
- شواهد در `test-artifacts/` و ردیف نقشهٔ راه، journal و `handoff.md` با اعداد یکسان.
- اثبات پاک‌سازی fixture و کاربر با preflight.
