# ممیزی و برنامهٔ اجرای S5 — قابلیت‌های موکاپ که هنوز کد ندارند

- تاریخ نگارش: ۲۰۲۶-۰۹-۱۵
- مجری: ایجنت `aisa-finisher` از مسیر `/aisa-next`؛ ردیف‌ها در `docs/PRODUCT_COMPLETION_ROADMAP_FA.md` با شناسهٔ T29 تا T53.
- منبع شکاف‌ها: `docs/MOCKUP_FEATURE_GAP_REPORT_FA.md` بخش ۱ (ردیف‌های A-01 تا A-18، W-01، W-02، C-01 تا C-03 و E-01).
- موکاپ‌ها: `mockups/design_handoff_admin_redesign/` (فقط‌خواندنی). قاب‌های گرفته‌شده: `test-artifacts/mockup-parity/mockup/`.
- مرجع خط موکاپ به شکل `نام فایل:خط` و مرجع کد نسبت به ریشهٔ افزونه است. شمارهٔ خط‌ها مربوط به تاریخ نگارش است و T29 باید پیش از اجرا دوباره تأییدشان کند.
- **T29 اجرا شد (۲۰۲۶-۱۱-۰۴):** مرجع‌های کد امروز، اصلاح‌های هر task، inventory REST و task تازهٔ T76 در **بخش ۸** آمده‌اند. جایی که بخش ۸ با مرجع کد بخش‌های ۴ و ۵ اختلاف دارد، بخش ۸ معتبر است.

## ۱. تصمیم معماری

- بسته دو بخش دارد:
  - **S5-A (T29 تا T43):** قابلیت‌هایی که قرارداد محصول را نمی‌شکنند، transport بیرونی تازه نمی‌خواهند و با backend موجود افزونه قابل ساخت‌اند. در جدول صف پس از S3.3 (T14) و پیش از فاز ۴ (T15) آمده‌اند تا QA نهایی یک‌بار و روی UI کامل اجرا شود. جایگاه آن‌ها تابع D-13 است.
  - **S5-B (T44 تا T53):** قابلیت‌هایی که transport یا API بیرونی تازه، وابستگی composer، تغییر قرارداد draft-only یا approval-first، یا پرسش حریم خصوصی دارند. طبق D-07 پس از انتشار (T25) و فقط با تصمیم صریح مالک اجرا می‌شوند.
- هیچ task این بسته کنترل جعلی نمی‌سازد. اگر backend یک کنترل در همان task کامل نشود، کنترل قفل صادقانه می‌ماند و گزارش شکاف به‌روز می‌شود.
- هر قابلیت پس از اجرا از بخش ۱ گزارش شکاف به بخش ۴ همان سند منتقل می‌شود، با مرجع کد و شاهد آزمون.
- اگر مالک برای task بخش S5-B پاسخ «ساخته نشود» بدهد، task با وضعیت `تکمیل` و خط ژورنال «طبق D-xx ساخته نشد» بسته می‌شود و ردیف گزارش شکاف به «اختلاف عمدی» تبدیل می‌شود.

## ۲. قواعد مشترک همهٔ taskهای S5

این قواعد در هر task تکرار نمی‌شوند ولی بخشی از گیت همه‌اند.

### ۲.۱ قرارداد محصول

- AI پیشنهاد می‌دهد، انسان تأیید می‌کند، backend اجرا می‌کند: هر مسیر نوشتنی تازه از plan، اعتبارسنجی، پیش‌نمایش تغییرناپذیر، تأیید صریح، اجرا، لاگ و ثبت مصرف می‌گذرد.
- کنترل بدون backend واقعی قفل و صادقانه است. عدد، وضعیت، هزینه یا «connected» ساختگی ممنوع است.
- ووکامرس draft-only می‌ماند مگر D-17.
- secret هرگز به DOM، پاسخ REST، لاگ، خروجی تست یا پیام نمی‌رسد؛ secretهای کانال با `Settings::set_handoff_secret` رمز می‌شوند.
- شناسه‌های منجمد عوض نمی‌شوند: namespace، پیشوند `aisaautopilot_*`، REST `aisaautopilot/v1`، slugهای admin، نام option و جدول‌های موجود، text domain و `KeyVault::KEY_DERIVATION_SALT`. option یا جدول تازه با پیشوند `aisaautopilot_` ساخته می‌شود.
- منوی visible دقیقاً ۸ آیتم اصلی می‌ماند (D-23، ۲۰۲۶-۰۹-۱۷، جای‌گزین قاعدهٔ پیشین ۱۰ آیتم تخت؛ Overview چهار تب داخلی دارد و Form Composer با نام Form Studio صفحهٔ اصلی مستقل است). T29 این تغییر را داده‌شده بداند؛ هیچ task دیگری آیتم/تب تازه‌ای فراتر از D-23 اضافه نمی‌کند. جزئیات: T54/T55 و `docs/OVERVIEW_FORM_STUDIO_S6_AUDIT_AND_PLAN_FA.md`. T40 (Form Composer مکالمه‌ای) اکنون روی صفحهٔ Form Studio اجرا می‌شود، نه یک deep link زیر Content Studio.

### ۲.۲ الگوی کد

- UI admin با `includes/Admin/Ui.php` و tokenهای `assets/admin/aisaautopilot-ui.css`؛ CSS با logical properties؛ هر رشتهٔ نمایشی با text domain و ترجمهٔ فارسی در PO و کامپایل MO با `scripts/compile-mo.php`.
- REST تازه زیر `aisaautopilot/v1` با `permission_callback` واقعی: مسیر admin با `manage_options` (یا capability دقیق‌تر ووکامرس) و nonce `wp_rest`؛ مسیر عمومی فقط با `RateLimiter` و محدودیت عمدی داده.
- ورودی با sanitize و اعتبارسنجی enum یا طول؛ خروجی با escaping؛ SQL با `$wpdb->prepare`؛ درخواست HTTP بیرونی فقط از `SafeHttp` یا الگوی موجود `HandoffTelegram` با timeout مشخص.
- option که از درخواست‌های هم‌زمان نوشته می‌شود با الگوی T28 (`GET_LOCK` و خواندن مستقیم ردیف) نوشته می‌شود تا lost update رخ ندهد.
- هیچ `innerHTML` با دادهٔ غیرقابل‌اعتماد؛ JS از آرایهٔ محلی‌شدهٔ PHP رشته می‌گیرد.

### ۲.۳ آزمون و گیت هر task

- `php -l`، PHPCS متمرکز بدون error و warning، PHPStan صفر، PHPUnit کامل و نه کمتر از خط مبنا، `node --check` برای JS تغییرکرده.
- منطق خالص با PHPUnit در `tests/*Test.php`.
- قرارداد WordPress با suite تازهٔ `tests/wordpress_<surface>_integration.php` که snapshot و restore همهٔ optionها را در `finally` انجام می‌دهد، فقط رکوردهای نشانه‌دار خودش را حذف می‌کند و درخواست بیرونی را با `pre_http_request` و ایمیل را با `pre_wp_mail` می‌بندد.
- UI با browser check در `en_US` و `fa_IR`، عرض 1440 و 390، keyboard و focus، صفر console error و pageerror، صفر پاسخ ناموفق `aisaautopilot/v1` بدون شمارش صریح، و جفت screenshot با قاب موکاپ. پیش از کار browser، skill `aisa-browser-qa` بارگذاری شود.
- پس از هر fixture یا browser run، preflight بدون باقی‌مانده.
- `render smoke`، `assets smoke` و `menu smoke` برای هر تغییر admin.
- گزارش شکاف، این plan، ردیف رودمپ و ژورنال با شمارهای یکسان به‌روز می‌شوند.

## ۳. جدول نگاشت

| Task | شکاف | کار | وابسته به | تصمیم | وضعیت |
|---|---|---|---|---|---|
| T29 | همه | ممیزی و تأیید D-13 | T14 | D-13 | تکمیل ۲۰۲۶-۱۱-۰۴ (بخش ۸) |
| T30 | A-05 | پرسش‌های بی‌پاسخ و «Write it» | T29 | D-22 | تکمیل ۲۰۲۶-۰۹-۱۶ (دستور مالک؛ همراه پاسخ ذخیره‌شده) |
| T31 | A-18 | تشخیص افزونهٔ هم‌پوشان | T29 | — | منتظر |
| T32 | A-14 | لحن Formal و Plain | T29 | — | منتظر |
| T33 | A-15 | شمار استفادهٔ الگو | T29 | — | منتظر |
| T34 | A-15 | ویرایشگر الگوی سفارشی | T33 | — | منتظر |
| T35 | A-01 | Reply length و timeout | T29 | — | منتظر |
| T36 | A-02 | Collections: وضعیت، Priority، افزودن | T29 | — | منتظر |
| T37 | A-08 | تحویل ترتیبی handoff | T29 | — | منتظر |
| T38 | W-02 | انتخاب راه تماس در کارت handoff ویجت | T37 | — | منتظر |
| T39 | A-12 | کارت محصول در پاسخ ویجت | T36 | — | منتظر |
| T40 | C-01، C-02، C-03 | Form Composer مکالمه‌ای | T29 | — | منتظر |
| T41 | A-17 | تأیید و Undo در Activity Log | T29 | — | منتظر |
| T42 | A-06 (آمار) | «answered without you» | T29 | — | منتظر |
| T76 | A-19 | پیشنهاد رنگ از پالت قالب در تب Widget | T29 | — | منتظر (تعریف در T29، بخش ۸.۸) |
| T43 | — | بستن S5-A | T30 تا T42 و T76 | — | منتظر |
| T44 | A-03 | صف پایدار ایندکس | T25، T43 | D-07 | نیازمند تصمیم |
| T45 | A-04 | منبع PDF و URL | T25، T43 | D-07، D-16 | نیازمند تصمیم |
| T46 | A-07 | transportهای Phase 2 | T25، T43 | D-07 | نیازمند تصمیم |
| T47 | A-09، W-01، E-01 | voice realtime | T25، T43 | D-02، D-07 | نیازمند تصمیم |
| T48 | A-10 | ووکامرس Phase 2 | T25، T43 | D-17 | نیازمند تصمیم |
| T49 | A-11 | وضعیت سفارش برای بازدیدکننده | T25، T43 | D-18 | نیازمند تصمیم |
| T50 | A-16 | price map و ایمیل سقف | T25، T43 | D-19 | نیازمند تصمیم |
| T51 | A-06 (مکان) | مکان بازدیدکننده | T25، T43 | D-15 | نیازمند تصمیم |
| T52 | A-13 | Publish on approval | T25، T43 | D-14 | نیازمند تصمیم |
| T53 | — | بستن S5-B | T44 تا T52 | — | منتظر |

---

## ۴. S5-A — جزئیات taskها

### T29 — S5.0 ممیزی و تأیید

- **هدف:** پیش از هر کد، درستی هر ردیف بخش ۱ گزارش شکاف را روی کد و runtime جاری ثابت کن و این plan را نهایی کن.
- **کار:**
  1. D-13 را بررسی کن. اگر مالک هنوز پاسخ نداده، task را `نیازمند تصمیم` کن و با پرسش D-13 متوقف شو.
  2. برای هر ردیف A، W، C و E: خط موکاپ را با `sed -n` ببین و متن دقیق کنترل را ثبت کن؛ مرجع کد را با Grep تأیید کن؛ اگر شماره خط عوض شده، این plan را اصلاح کن.
  3. inventory REST از `rest_get_server()->get_routes()` برای namespace `aisaautopilot/v1` بگیر تا routeهای تازهٔ این بسته با route موجود برخورد نکنند.
  4. قاب‌های موکاپ مرتبط را از `test-artifacts/mockup-parity/mockup/` فهرست کن و قاب‌های ناموجود را با Playwright از `file://` بگیر (`admin-knowledge-*`، `admin-answers-*`، `admin-store-*`، `admin-studio-*`، `admin-logs-*`، `admin-integrations-conflict`، `admin-setup-3-ok-advanced`، `composer-*`، `widget-handoff-*`).
  5. فهرست افزونه‌های فعال سایت محلی را برای T31 ثبت کن (نوار کناری افزونه‌ای به نام Social Chat Buttons نشان می‌دهد؛ ممکن است مثبت واقعی باشد).
- **گیت:** همهٔ ردیف‌های جدول نگاشت با مرجع تأییدشده؛ خط ژورنال با فهرست اصلاح‌ها؛ هیچ تغییر کد.
- **وضعیت ۲۰۲۶-۱۱-۰۴: تکمیل.** نتیجه در بخش ۸. خط‌های موکاپ و قاب‌ها به‌دلیل نبودن فایل موکاپ در مخزن دوباره تأیید نشدند (بخش ۸.۲ و ۸.۴).

### T30 — S5.1 پرسش‌های بی‌پاسخ: دلیل، شمار و «Write it» (A-05)

- **موکاپ:** `AutoPilot Admin.dc.html:691` («Last 7 days»)، `:2364` (نشان «Asked N times»)، `:2366` (CTA «Write it» و پس از ساخت «Open draft»)؛ هر ردیف پرسش، یک خط دلیل و نشان تعداد دارد.
- **کد فعلی:**
  - option `aisaautopilot_kb_gaps` در `includes/Content/KbGaps.php` (`record` خط 21، `open_list` 76، `open_count` 104، `dismiss` 118، سقف 200 مورد).
  - ثبت در `includes/Chat/ChatEngine.php` خطوط 92 (بدون context)، 201 (پاسخ not_found مدل)، 243 (پاسخ خالی) و 249 (پاسخ بدون citation).
  - REST `GET /kb/gaps` و `POST /kb/gaps/dismiss` در `includes/Api/KnowledgeBaseController.php:176`؛ UI در `KnowledgeBasePage.php:192` و `assets/admin/knowledge-base.js:496`.
  - مسیر پیش‌نویس: `POST /content/plan` و `/content/execute` در `includes/Api/ContentController.php:30` و `:91` با `ContentDraftSkill` و `includes/Execution/ActionStore.php`؛ پیوند Write با brief از `ContentStudioNavigation::write_url()`.
- **backend:**
  1. `KbGaps::record( string $question, string $language = '', string $reason = '' )`؛ enum دلیل: `no_context`، `model_not_found`، `empty_answer`، `no_citation`. هر چهار نقطهٔ ChatEngine دلیل درست را بفرستند.
  2. هر ورودی gap این فیلدها را داشته باشد: `count`، `first_seen`، `last_seen` (ISO)، `reason`، `draft_post_id` (پیش‌فرض 0). ساختار قدیمی بدون این فیلدها با پیش‌فرض خوانده شود، بدون migration مخرب.
  3. نوشتن option زیر قفل نام‌دار با الگوی `Logger::log` (T28)، چون ChatEngine از درخواست‌های هم‌زمان بازدیدکنندگان می‌نویسد.
  4. `open_list()` برچسب ترجمه‌شدهٔ دلیل و `asked_label` را بسازد و فیلتر «۷ روز اخیر» را روی `last_seen` اعمال کند؛ `GET /kb/gaps` پارامتر اختیاری `window=7d|all` بگیرد.
  5. `POST /content/plan` پارامتر اختیاری `source_gap` (hash) بگیرد، وجود gap باز را تأیید کند و آن را در context تغییرناپذیر proposal داخل `ActionStore` نگه دارد. `/content/execute` پس از موفقیت، `KbGaps::link_draft( $hash, $post_id )` را صدا بزند. client هرگز post_id را برای پیوند نمی‌فرستد.
- **UI:**
  - ردیف gap مطابق موکاپ: متن پرسش، خط دلیل، نشان «Asked N times» با tone هشدار، دکمهٔ اصلی «Write it» و دکمهٔ ثانوی Dismiss.
  - «Write it» فقط به Content Studio › Write می‌رود با brief «یک مقالهٔ راهنما بنویس که به این پرسش پاسخ دهد: …» و `source_gap`؛ هیچ request خودکاری فرستاده نمی‌شود و مسیر plan، پیش‌نمایش و تأیید همان مسیر فعلی است.
  - پس از پیوند، دکمه «Open draft» می‌شود و به ویرایش همان پیش‌نویس می‌رود؛ gap تا Dismiss دستی باز می‌ماند و متن UI همین را می‌گوید.
  - در Write، وقتی `source_gap` هست، یک خط اطلاع «Drafting an answer to: …» نمایش داده شود.
- **امنیت:** `source_gap` فقط hash معتبر؛ link فقط از context سرور؛ Open draft فقط وقتی `current_user_can( 'edit_post', $id )` و marker `_aisaautopilot_generated` برقرار است.
- **آزمون و گیت:**
  - PHPUnit: نگاشت دلیل به برچسب و فیلتر پنجرهٔ زمانی (منطق را در متد خالص جدا کن).
  - `tests/wordpress_kb_gaps_write_it_integration.php`: record با چهار دلیل، شکل `open_list`، سازگاری ورودی قدیمی، `source_gap` در context proposal، پیوند پس از execute با provider canned، رد hash نامعتبر، ثبت هم‌زمان دو پرسش بدون گم شدن. snapshot و restore برای `aisaautopilot_kb_gaps` و optionهای proposal؛ پیش‌نویس ساخته‌شده حذف شود.
  - browser: fixture gapها (snapshot option)، «Write it» به Write با brief و صفر request خودکار، نمایش «Open draft» پس از پیوند fixture، en/fa، 1440/390.
- **ریسک:** رشد option (سقف 200 حفظ شود)؛ ترجمهٔ پرسش فارسی در brief (متن پرسش بدون ترجمه قرار گیرد).

### T31 — S5.2 تشخیص افزونهٔ هم‌پوشان (A-18)

- **موکاپ:** `AutoPilot Admin.dc.html:1543` (سناریوی `conflict`) و `:1998`: بنر هشدار با عنوان «Another AI plugin is active»، متن «“WP AI Chatbot” also puts a chat widget on your site. Two widgets will overlap in the same corner.» و CTA «Open Plugins».
- **کد فعلی:** ناموجود. `Ui::banner()` در `includes/Admin/Ui.php:404` آماده است؛ صفحه `includes/Admin/IntegrationsPage.php`.
- **backend:**
  1. کلاس `includes/Core/ConflictDetector.php` (final) با فهرست شناخته‌شدهٔ `plugin_basename => kind`، که `kind` یکی از `chat_widget` یا `ai_assistant` است. فهرست از افزونه‌های واقعی مخزن WordPress.org با slug تأییدشده ساخته می‌شود، نه حدس؛ T31 هر slug را با صفحهٔ افزونه یا فایل اصلی آن تأیید و منبع را در این plan ثبت کند. قابل‌گسترش با filter `aisaautopilot_conflicting_plugins`.
  2. ورودی: `get_option( 'active_plugins' )` و در multisite `get_site_option( 'active_sitewide_plugins' )`؛ نام نمایشی از header `get_plugins()`.
  3. نوع `chat_widget` فقط وقتی هشدار «هم‌پوشانی» می‌دهد که ویجت Answer Desk فعال باشد (`Settings::answer_desk_enabled()`)؛ نوع `ai_assistant` متن متفاوت «دو دستیار AI ممکن است به یک پرسش پاسخ متفاوت دهند» دارد.
  4. رد بنر برای هر کاربر در user meta `aisaautopilot_dismissed_conflicts` (فهرست basename) با `POST /integrations/conflicts/dismiss` (nonce، `manage_options`)؛ افزونهٔ تازهٔ شناسایی‌شده دوباره بنر می‌سازد.
- **UI:** بنر بالای بدنهٔ Integrations مطابق موکاپ، با نام افزونهٔ واقعی، CTA «Open Plugins» به `plugins.php` و دکمهٔ Dismiss قابل‌دسترسی با keyboard. ادعای «overlap» فقط برای `chat_widget` و با واژهٔ «may».
- **آزمون و گیت:** PHPUnit برای matcher با فهرست تزریقی (single و sitewide)؛ integration با `pre_option_active_plugins` درون‌پردازشی برای رندر بنر، متن هر نوع، dismiss و بازگشت با افزونهٔ تازه؛ browser برای حالت بدون تداخل (یا مثبت واقعی سایت محلی، طبق inventory T29) و dismiss با keyboard.
- **ریسک:** مثبت کاذب؛ فهرست کوتاه و فقط slugهای تأییدشده.

### T32 — S5.3 لحن Formal و Plain (A-14)

- **موکاپ:** Content Studio › Write، ردیف چیپ‌های Voice (قاب `admin-studio-write-default`).
- **کد فعلی:** چیپ‌های قفل در `includes/Admin/CopilotPage.php:290` و `:291`؛ `ContentController::plan` فقط `prompt` می‌گیرد؛ prompt در `ContentDraftSkill` ساخته می‌شود.
- **backend:**
  1. پارامتر `tone` با enum `site|formal|plain` (پیش‌فرض `site`) در `/content/plan`؛ مقدار نامعتبر 400 با کد `invalid_tone`.
  2. `ContentDraftSkill::plan( $prompt, $tone )` یک بند دستور لحن به system prompt اضافه کند: `formal` ثبت رسمی و بدون محاوره؛ `plain` جمله‌های کوتاه و واژهٔ ساده؛ `site` همان رفتار فعلی (بدون تغییر prompt برای جلوگیری از regression).
  3. `tone` در context تغییرناپذیر proposal ثبت شود و پیش‌نمایش و History آن را نشان دهند؛ `execute` مقدار client را نپذیرد.
  4. آخرین انتخاب هر کاربر در user meta `aisaautopilot_content_tone`.
- **UI:** چیپ‌ها به radiogroup با `role="radiogroup"`، `aria-checked`، کلیدهای جهت و focus قابل‌مشاهده؛ برچسب لحن در کارت پیش‌نمایش.
- **آزمون و گیت:** integration با `pre_http_request` canned که body را می‌خواند و وجود بند لحن را برای `formal` و `plain` و نبود تغییر برای `site` assert می‌کند؛ 400 برای مقدار نامعتبر؛ freeze در context؛ restore user meta. browser: keyboard روی چیپ‌ها و وجود `tone` در payload با `page.route`.

### T33 — S5.4 شمار استفادهٔ الگو (A-15)

- **موکاپ:** کارت‌های Templates با «Used 38 times» (`AutoPilot Admin.dc.html:1699`) و «Edit template» (`:1254`؛ ویرایشگر در T34).
- **کد فعلی:** چهار الگوی ثابت در `includes/Admin/CopilotPage.php:344` تا `:368` بدون شناسه؛ History متن «Template not recorded» دارد (`includes/Content/ContentStudioReadModel.php` و `includes/Admin/HistoryPage.php`).
- **backend:**
  1. رجیستری `includes/Content/ContentTemplates.php` با شناسه‌های پایدار `product_description`، `blog_outline`، `meta_description` و `support_answer`؛ CopilotPage از آن بخواند.
  2. پیوند «Use template» پارامتر `template` بفرستد؛ `/content/plan` پارامتر `template_id` را با رجیستری اعتبارسنجی و در context ثبت کند؛ `execute` پس از موفقیت post meta `_aisaautopilot_template_id` بنویسد.
  3. شمار = تعداد postهای دارای marker `_aisaautopilot_generated` و همان `_aisaautopilot_template_id` (بدون trash)، با transient ده‌دقیقه‌ای که پس از execute پاک می‌شود.
  4. History برای پیش‌نویس‌های تازه نام الگو را نشان دهد؛ قدیمی‌ها همان «Template not recorded» صادقانه بمانند.
- **UI:** «Used N times» با `_n()` و برای صفر «Not used yet».
- **آزمون و گیت:** integration برای شمار پس از execute canned، رد `template_id` ناشناخته، برچسب History؛ browser شمارها روی postهای fixture نشانه‌دار که پس از اجرا حذف می‌شوند.

### T34 — S5.5 ویرایشگر الگوی سفارشی (A-15)

- **موکاپ:** «Edit template» در `AutoPilot Admin.dc.html:1254`.
- **backend:**
  1. option `aisaautopilot_content_templates`: حداکثر 20 الگو؛ فیلدها `id` (slug یکتا، پیشوند `custom_`)، `name` (حداکثر 80 نویسه)، `body` (حداکثر 1000 نویسه، متن ساده)، `updated_at`.
  2. REST `GET /content/templates`، `POST /content/templates` (ساخت یا ویرایش) و `DELETE /content/templates/(?P<id>[a-z0-9_-]+)` با `manage_options` و nonce. الگوهای داخلی ویرایش نمی‌شوند؛ «Duplicate to edit» نسخهٔ سفارشی می‌سازد.
  3. placeholderها با الگوی `\{[a-z_]{1,32}\}` استخراج و نمایش داده شوند؛ متن با `sanitize_textarea_field`.
  4. شمار استفاده (T33) برای شناسه‌های سفارشی هم کار کند.
- **UI:** dialog ویرایش با focus trap، Escape و بازگشت focus؛ فیلد نام و متن، چیپ placeholderهای شناسایی‌شده، Save، Cancel و Delete با تأیید دوم؛ کارت «New template».
- **آزمون و گیت:** integration برای CRUD، سقف 20، طول، slug تکراری، capability و nonce، و restore option؛ browser برای dialog با keyboard، ذخیره، حذف با تأیید و RTL.

### T35 — S5.6 Reply length و timeout (A-01)

- **موکاپ:** `AutoPilot Admin.dc.html:331` («Temperature, reply length, timeout, semantic search») و `:347` (کنترل Reply length با گزینه‌های `lengthOptions` و متن «Caps tokens per reply so a runaway answer cannot drain your credit.»). T29 برچسب دقیق گزینه‌ها و جای کنترل timeout را از `lengthOptions` و خطوط اطراف ثبت کند.
- **کد فعلی:** کنترل قفل در `includes/Admin/SettingsPage.php:908`؛ timeout در `includes/Providers/ProviderManager.php:68` فقط از config با پیش‌فرض 45 خوانده می‌شود و کلید تنظیمات ندارد؛ سقف توکن خروجی در providerها ثابت است (مثلاً `AnthropicProvider::MAX_TOKENS`).
- **backend:**
  1. کلیدهای `reply_length` (enum مطابق برچسب‌های موکاپ، پیش‌فرض همان رفتار فعلی) و `request_timeout` (عدد صحیح 10 تا 120، پیش‌فرض 45) در `Settings::defaults()`.
  2. پیش از هر تغییر، سقف توکن فعلی پاسخ‌های مکالمه‌ای را اندازه بگیر و گزینهٔ پیش‌فرض را دقیقاً برابر آن نگه دار.
  3. نگاشت `reply_length` به `max_tokens` فقط برای پاسخ‌های مکالمه‌ای (ChatEngine ویجت، چت مدیر، `/efb/chat`). planهای ساخت‌یافته (`/forms/plan`، `/content/plan`، `/woocommerce/plan`) سقف خودشان را حفظ می‌کنند تا JSON ناقص نشود.
  4. `request_timeout` از `ProviderManager` به همهٔ فراخوانی‌ها برسد، به‌جز `health_check` و فهرست مدل‌ها که سقف کوتاه خودشان را نگه می‌دارند.
  5. ذخیرهٔ این دو کلید رسید آزمون اتصال را باطل نکند (فقط provider، base_url، model و کلید API آن را باطل می‌کنند)؛ این را در `SettingsPage::maybe_save` تأیید کن.
- **UI:** بازکردن کنترل در accordion پیشرفتهٔ مرحلهٔ ۳ Setup و alias تنظیمات؛ radiogroup برای طول و ورودی عددی با واحد ثانیه برای timeout؛ متن کمکی موکاپ.
- **آزمون و گیت:** PHPUnit نگاشت؛ integration که `timeout` و `max_tokens` در args درخواست را با `pre_http_request` برای پاسخ ویجت و plan محتوا می‌سنجد؛ `wordpress_setup_verification_integration.php` بدون افت؛ browser accordion در en/fa و 390.

### T36 — S5.7 Collections: وضعیت، Priority و افزودن (A-02)

- **موکاپ:** `AutoPilot Admin.dc.html:711` تا `:727` (هر ردیف: toggle، نام، «items · read»، «Priority …»، برچسب وضعیت) و `:716` («Add a collection»)؛ نمونهٔ ردیف‌ها در `:1614`.
- **کد فعلی:** ردیف‌های فقط‌خواندنی با «Managed by index scope» در `KnowledgeBasePage.php:242`؛ scope از `ContentIndexer::get_indexable_types()` (`includes/Content/ContentIndexer.php:446`) و کلید `kb_post_types` (`includes/Core/Settings.php:330`)؛ بازیابی در `KnowledgeBase::search` (`includes/Content/KnowledgeBase.php:314`) و `VectorSearch::search` (`includes/Content/VectorSearch.php:45`).
- **backend:**
  1. کلید `kb_collections` به شکل `post_type => { enabled: bool, priority: low|normal|high }`؛ پیش‌فرض همه روشن و `normal`.
  2. `get_indexable_types()` نوع خاموش را کنار بگذارد. بازیابی کلیدواژه‌ای و برداری نوع خاموش را فوراً کنار بگذارد و score را پیش از مرتب‌سازی در وزن ضرب کند (`high` 1.15، `normal` 1.0، `low` 0.85؛ ثابت‌ها در یک کلاس خالص قابل‌آزمون).
  3. خاموش کردن مدخل‌ها را حذف نمی‌کند؛ UI این را می‌گوید و اقدام صریح «Remove its indexed entries» با تأیید دوم از مسیر حذف موجود (`/kb/delete`) انجام می‌شود.
  4. «Add a collection» یک post type عمومی (غیر attachment، `show_ui`) را به `kb_post_types` می‌افزاید؛ REST `POST /kb/collections` (partial update با enabled یا priority) و `POST /kb/collections/add` با `manage_options` و nonce.
- **UI:** toggle واقعی، منوی priority، برچسب وضعیت («Indexed» یا «Paused» یا «Not read yet»)، dialog افزودن با فهرست post typeهای مجاز و شمار منتشرشده؛ متن صریح اثر خاموش کردن.
- **آزمون و گیت:** PHPUnit وزن‌دهی و مرتب‌سازی؛ integration با postهای fixture در دو نوع برای کنار گذاشتن نوع خاموش در بازیابی کلیدواژه‌ای، ترتیب بر اساس priority، افزودن نوع عمومی و رد attachment؛ browser برای toggle و priority با keyboard و بقا پس از reload.

### T37 — S5.8 تحویل ترتیبی handoff (A-08)

- **موکاپ:** `AutoPilot Admin.dc.html:1721` (Email: «Always on as the fallback») و `:2405` («They’re tried top to bottom until one accepts. Email stays on as the fallback.»).
- **کد فعلی:** `HandoffService::handoff()` در `includes/Support/HandoffService.php:15` تا `:58` هر سه کانال Email، Telegram و Twilio را مستقل اجرا می‌کند (fan-out)؛ متن صریح در `includes/Admin/AnswerDeskHandoffPage.php:57`؛ تنظیم partial در `POST /handoff/admin-settings`.
- **backend:**
  1. کلیدهای widget: `handoff_delivery` با `all|ordered` (پیش‌فرض `all` برای حفظ رفتار فعلی) و `handoff_order` (فهرست `telegram`، `twilio` و کانال‌های آینده).
  2. در `ordered`: کانال‌ها به ترتیب اجرا می‌شوند و با اولین «پذیرش» متوقف می‌شوند. پذیرش یعنی پاسخ موفق transport (برای Telegram پاسخ 2xx با `ok: true`، برای Twilio 201، برای Email بازگشت `true` از `wp_mail`)، نه برداشتن پیام توسط انسان؛ متن UI همین را بگوید. Email اگر فعال باشد آخرین fallback است.
  3. نتیجه فهرست تلاش‌ها با ترتیب و نتیجهٔ هر کانال را برگرداند؛ وضعیت صندوق انتقال (`HandoffInbox`) اگر هیچ کانالی نپذیرفت `failed` شود.
  4. اعتبارسنجی `handoff_order` در `AdminHandoffSettingsController`: فقط کانال‌های شناخته‌شده و بدون تکرار.
- **UI:** در تب Human handoff، سوییچ «All at once» یا «In order»؛ فهرست کانال‌ها با دکمه‌های Move up و Move down قابل‌دسترسی (نه فقط drag)؛ Email ثابت در انتها با برچسب fallback؛ متن توضیح مطابق موکاپ.
- **آزمون و گیت:** integration با `pre_http_request` (Telegram 500، Twilio 201 و توقف، بدون فراخوانی Email) و `pre_wp_mail`؛ حالت `all` بدون تغییر رفتار؛ اعتبارسنجی ترتیب؛ browser برای جابه‌جایی با keyboard و بقا. هیچ ارسال واقعی.

### T38 — S5.9 انتخاب راه تماس در کارت handoff ویجت (W-02)

- **موکاپ:** `Front Chat Widget.dc.html:152` تا `:160`: عنوان «I'll bring in a person»، متن «That one's better answered by our team. Leave a way to reach you and they'll pick it up.» و دو دکمه «Email me a reply» و «Chat on Telegram».
- **کد فعلی:** template یک‌دکمه‌ای در `includes/Frontend/ChatWidget.php:193`؛ `showHandoffCard` و `requestHandoff` در `assets/frontend/widget.js:538` و `:561`؛ `POST /widget/handoff` در `includes/Api/ChatWidgetController.php:136` (پارامترهای name، email و messages)؛ ایمیل تیم هدر Reply-To ندارد (`HandoffService::try_email`).
- **backend:**
  1. «Email me a reply»: ایمیل بازدیدکننده الزامی و با `is_email` اعتبارسنجی شود؛ ایمیل به تیم با `Reply-To` برابر ایمیل بازدیدکننده ارسال شود؛ پاسخ REST فقط `sent` یا `handoff_failed` بدهد.
  2. «Chat on Telegram»: کلید تازهٔ widget `handoff_telegram_public_url` که فقط `https://t.me/<username>` با الگوی `^[A-Za-z0-9_]{5,32}$` می‌پذیرد. دکمه فقط وقتی این کلید تنظیم است نمایش داده شود. کلیک ابتدا handoff را با `contact_method=telegram` ثبت می‌کند تا رونوشت به کانال‌های تیم برسد، سپس پیوند را در تب تازه با `rel="noopener"` باز می‌کند.
  3. اگر هیچ‌یک از دو پیش‌نیاز نیست، همان کارت فعلی «ارسال به تیم» باقی می‌ماند.
  4. `HandoffInbox` روش تماس را نگه دارد (فیلد `contact_method` در messages meta یا ستون تازه با dbDelta و افزایش `SCHEMA_VERSION`).
- **UI:** کارت مطابق موکاپ با دو دکمه؛ فرم کوچک ایمیل پس از «Email me a reply»؛ پیام تأیید صادقانه («Sent. The team will reply to you at …») بدون ادعای اتصال زنده یا زمان پاسخ؛ برای Telegram متن «The conversation continues in Telegram».
- **حریم خصوصی:** notice گفتگو توضیح دهد ایمیل برای پاسخ تیم ذخیره و ارسال می‌شود.
- **آزمون و گیت:** lifecycle integration برای سه حالت کارت بر اساس تنظیمات؛ اعتبارسنجی ایمیل و URL تلگرام؛ هدر Reply-To با `pre_wp_mail`؛ browser matrix ویجت با `page.route` برای `/widget/handoff` در en/fa، desktop/390 و keyboard.

### T39 — S5.10 کارت محصول در پاسخ ویجت (A-12)

- **موکاپ:** Store › Settings «Smart product search» با متن «Lets a visitor find products by describing them instead of matching the exact name» (`AutoPilot Admin.dc.html:1143` و `:1144`). ویجت موکاپ UI محصول ندارد؛ کارت محصول با زبان طراحی ویجت ساخته و در Claude Design ثبت می‌شود (بخش ۶).
- **کد فعلی:** `WooCommerceController::smart_search` (`includes/Api/WooCommerceController.php:84`) فقط برای admin و کلیدواژه‌ای است؛ متن صادقانهٔ فعلی در `StoreCopilotPage.php:298`.
- **backend:**
  1. وقتی ووکامرس فعال، `enable_search` روشن و collection محصول (T36) فعال است، `ChatEngine` برای منابع بازیابی‌شده از نوع `product` دادهٔ کارت عمومی بسازد: عنوان، قیمت قالب‌بندی‌شده با `wc_price` از `WC_Product`، تصویر شاخص کوچک، permalink و موجودی.
  2. فقط محصول `publish` با `catalog_visibility` قابل‌مشاهده؛ هرگز draft، private، قیمت عمده یا متای داخلی.
  3. بدون فراخوانی provider اضافه؛ همان بازیابی KB (برداری در صورت فعال بودن embeddings).
- **UI:** کارت‌های محصول زیر پاسخ در ویجت (حداکثر 3)، با «View product» که در همان تب باز می‌شود؛ RTL و 390.
- **آزمون و گیت:** integration برای نبود کارت وقتی `enable_search` خاموش است، و رد محصول draft و private؛ browser با `page.route` برای `/widget/ask` که منبع محصول دارد.

### T40 — S5.11 Form Composer مکالمه‌ای (C-01، C-02، C-03)

- **موکاپ:** `Form Composer.dc.html:366` و `:367` (سناریوهای `form`، `selecting` و `advanced`)؛ چیپ‌های «Editing:»، زیرعنوان «2 fields referenced»، پیشنهادهای «Make both required»، «Add help text» و «Move message to the end»؛ کارت «That needs Easy Form Builder» با «Keep it simple»، «What can native forms do?» و «Open EFB» (`:287` تا `:289`)؛ زیرعنوان header «Describe a form, click any field to fine-tune it».
- **کد فعلی:** جریان «توصیف، طرح، تأیید، ساخت» با `POST /forms/plan` و `/forms/execute` (`includes/Api/RestRoutes.php:18` و `:33`)؛ پیش‌نمایش در `assets/admin/form-builder.js:215` و `:236`؛ «Back and edit» در `:390`؛ نتیجه در `:429`؛ نوار ثابت EFB در `includes/Admin/CopilotPage.php:108`؛ freeze در `tests/wordpress_form_proposal_context_integration.php`.
- **backend:**
  1. `POST /forms/refine` با `proposal_token`، `field_refs` (شناسه‌های طرح فعلی) و یکی از این دو:
     - `operations` قطعی بدون AI: `set_required`، `set_help`، `set_label`، `move_to_end` و `remove`؛
     - `instruction` آزاد (حداکثر 500 نویسه) که با provider به patch تبدیل می‌شود.
  2. هر دو مسیر patch را روی نسخهٔ سرور proposal اعمال می‌کنند، با همان validator `/forms/plan` بررسی می‌کنند، proposal تازه با token تازه می‌سازند و token قبلی را باطل می‌کنند. خروجی: plan و خلاصهٔ تغییر (افزوده، حذف‌شده، تغییرکرده). سقف مصرف و `usage_cap_reached` مانند `/forms/plan`.
  3. درخواست نوعی که فرم بومی ندارد (بارگذاری فایل، پرداخت، امضا، چندمرحله‌ای، منطق شرطی) کد `requires_efb` با فهرست قابلیت برگرداند، بدون تغییر طرح.
  4. ساخت نهایی همچنان فقط با تأیید صریح در `/forms/execute`.
- **UI:**
  - پیش‌نمایش زنده: هر فیلد دکمه‌ای با `aria-pressed` که انتخابش چیپ «Editing: <label> ×» در composer می‌سازد؛ Clear؛ placeholder «What should change about these fields?».
  - چیپ‌های پیشنهادی فقط composer یا عملیات قطعی را آماده می‌کنند و خودکار ارسال نمی‌کنند.
  - پاسخ `requires_efb` کارت درون‌گفتگو مطابق موکاپ می‌سازد؛ «Open EFB» فقط وقتی EFB در دسترس است.
  - زیرعنوان header فقط پس از انتشار این قابلیت به متن موکاپ برمی‌گردد (X-04 گزارش شکاف بسته می‌شود).
- **آزمون و گیت:** PHPUnit برای عملیات patch و validator `requires_efb`؛ integration برای جایگزینی token، TTL، سقف مصرف، instruction با provider canned و رد `field_refs` ناشناخته؛ browser matrix Form Composer با سناریوهای selecting و advanced در en/fa، 1240/390 و صفر ارسال خودکار.

### T41 — S5.12 تأیید و Undo در Activity Log (A-17)

- **موکاپ:** `AutoPilot Admin.dc.html:1363` («Approve all waiting»)، `:1578` تا `:1587` (سناریوهای `waiting` و `automatic`) و `:2605` (اقدام‌های ردیف: Open draft، Approve، Restore، Undo).
- **کد فعلی:** read model `includes/Activity/ActivityTimeline.php:24` که فقط اقدام «باز کردن منبع ساخته‌شده» دارد؛ `LogsPage` فقط‌خواندنی با CSV (`includes/Admin/LogsPage.php:44`)؛ proposalها در `includes/Execution/ActionStore.php`.
- **backend:**
  1. read model `PendingApprovals`: proposalهای معتبر و منقضی‌نشدهٔ فرم، محتوا و محصول از `ActionStore` با پیوند به صفحهٔ بازبینی همان proposal. «Approve» فقط به پیش‌نمایش می‌رود و از لاگ مستقیم اجرا نمی‌شود.
  2. «Approve all waiting» ساخته نمی‌شود، چون تأیید یک‌جا پیش‌نمایش هر مورد را دور می‌زند. اختلاف عمدی ثبت شود.
  3. Undo فقط برای منابع قابل‌بازگشت: پیش‌نویس post، page، محصول یا فرم بومی با marker `_aisaautopilot_generated` که هنوز `draft` یا `pending` است، با `wp_trash_post`. Restore با `wp_untrash_post`. هرگز Undo برای محتوای منتشرشده، تحویل پیام یا عمل بیرونی.
  4. REST `POST /activity/undo` و `POST /activity/restore` با `post_id`، nonce و `current_user_can( 'delete_post' )` یا `edit_post`؛ رویدادهای `activity.undo` و `activity.restore` با شناسهٔ کاربر ثبت شوند.
- **UI:** ردیف‌های «Waiting for you» بالای لاگ با شمار واقعی؛ اقدام هر ردیف مطابق وضعیت؛ dialog تأیید Undo با توضیح «پیش‌نویس به زباله‌دان می‌رود و قابل بازگرداندن است»؛ toast نتیجه؛ keyboard.
- **آزمون و گیت:** integration با پیش‌نویس fixture نشانه‌دار برای undo و restore، رد post بدون marker یا منتشرشده، capability و ثبت لاگ؛ browser با fixture در en/fa و 390.

### T42 — S5.13 آمار «answered without you» (A-06، بخش آمار)

- **موکاپ:** `AutoPilot Admin.dc.html:794` («94% answered without you») در header کارت «Today’s conversations».
- **کد فعلی:** جدول `VisitorConversationStore` (`includes/Chat/VisitorConversationStore.php:30`) نتیجهٔ هر نوبت را ندارد؛ `append_turn` در `:48`؛ ChatEngine وضعیت `answered`، `not_found` و `clarify` را برمی‌گرداند.
- **backend:**
  1. `append_turn` فیلد `outcome` را روی پیام assistant ذخیره کند (`answered`، `not_found`، `clarify`، `error`) و درخواست handoff در همان رشته علامت `handoff: true` بگذارد.
  2. آمار دوره (امروز به وقت سایت): پاسخ‌های `answered` در رشته‌های بدون handoff تقسیم بر همهٔ پاسخ‌های assistant دارای `outcome`. نوبت‌های قدیمی بدون `outcome` از مخرج کنار گذاشته شوند.
  3. فقط وقتی ثبت گفتگو روشن است و دست‌کم 5 پاسخ دارای `outcome` در دوره هست.
- **UI:** نشان «x% answered without you» فقط با دادهٔ کافی؛ در غیر این صورت پنهان؛ tooltip «از گفتگوهای ثبت‌شدهٔ امروز».
- **آزمون و گیت:** PHPUnit فرمول با حالت‌های مرزی؛ integration با ردیف‌های fixture با token نشانه‌دار که پس از اجرا حذف می‌شوند؛ browser برای نمایش و پنهان بودن.

### T43 — S5.14 بستن S5-A

- `run-gates.ps1 -Tier release` با همهٔ suiteهای تازه؛ browser smoke همهٔ routeها؛ جفت screenshot هر قابلیت با قاب موکاپ.
- `docs/MOCKUP_GAP_S5_EXECUTION_REPORT_FA.md` با قالب `FORM_COMPOSER_S3_1_EXECUTION_REPORT_FA.md`: نتیجه، تغییر فنی و UI، اختلاف‌های عمدی، جدول شواهد.
- گزارش شکاف: ردیف‌های اجراشده به بخش ۴ و اختلاف‌های تازه به بخش ۳؛ tracker و handoff.
- گیت: همه PASS و شمارها در همهٔ اسناد یکسان.

---

## ۵. S5-B — جزئیات taskها (پس از انتشار و با تصمیم مالک)

### T44 — S5.20 صف پایدار ایندکس (A-03)

- **موکاپ:** `AutoPilot Admin.dc.html:640` تا `:760` («Indexing stopped at item 312 of 500»، «Resume from 312»، «Skip the three»، «Retry the 3 failed»)؛ job bar سراسری.
- **کد فعلی:** batch در همان صفحه با `/kb/index/batch` (`includes/Api/RestRoutes.php:387`) و متن صریح «صف پایدار ندارد» در `KnowledgeBasePage.php:266`؛ `Ui::jobbar()` در `Ui.php:428`.
- **backend:** جدول `aisaautopilot_kb_queue` (id، post_id، post_type، state با `queued|processing|done|failed|skipped`، attempts، last_error_code، updated_at) با dbDelta و `SCHEMA_VERSION`؛ پردازش با WP-Cron رویداد `aisaautopilot_kb_queue_tick` در دسته‌های 10تایی زیر `GET_LOCK`؛ توقف خودکار روی `usage_cap_reached`؛ REST `GET /kb/queue`، `POST /kb/queue/pause`، `/resume`، `/retry` (همه یا یک مورد) و `/skip`، همه با `manage_options` و nonce؛ دلیل خطا فقط کد و متن ترجمه‌شده، بدون پاسخ خام provider.
- **UI:** تب Indexing Queue مطابق موکاپ؛ job bar با پیشرفت واقعی؛ Resume از cursor واقعی.
- **آزمون و گیت:** integration با cron اجراشده به‌صورت دستی و provider canned برای خطا و retry؛ هم‌زمانی دو tick؛ پاک‌سازی جدول در uninstall (T21).
- **تصمیم:** D-07.

### T45 — S5.21 منبع PDF و External URL (A-04)

- **موکاپ:** `AutoPilot Admin.dc.html:1614` و `:2373` تا `:2374` («Uploaded PDFs … 14 files, 620 chunks»، «External URL … Not configured»).
- **کد فعلی:** فقط post typeهای وردپرس (`KnowledgeBasePage.php:309`).
- **backend:**
  - PDF از Media Library انتخاب می‌شود (بدون upload مسیر تازه) و متن با parser استخراج می‌شود. D-16 تعیین می‌کند parser وابستگی composer (پیشنهاد `smalot/pdfparser`، که مالک نصب می‌کند) باشد یا ابزار سیستمی. سقف حجم و صفحه، و chunk با شناسهٔ منبع.
  - URL فقط https، میزبان عمومی (رد IP خصوصی و loopback پس از DNS)، احترام به `robots.txt`، سقف 2MB، تبدیل HTML به متن، re-read زمان‌بندی‌شده و ثبت ETag.
  - مدخل‌های KB ستون یا meta `source_type` و `source_ref` بگیرند.
- **آزمون و گیت:** integration برای SSRF (IP خصوصی، redirect به داخلی)، سقف حجم و پاسخ canned HTML؛ PHPUnit برای chunking.
- **تصمیم:** D-07 و D-16.

### T46 — S5.22 transportهای Phase 2 (A-07)

- **موکاپ:** `AutoPilot Admin.dc.html:1729` تا `:1732` (Slack، Discord، Webhook با «Send a test post»)؛ WhatsApp در کارت اصلی.
- **کد فعلی:** قفل در `includes/Admin/AnswerDeskHandoffPage.php:206` تا `:230`؛ الگوی transport در `includes/Support/HandoffTelegram.php` و `HandoffTwilio.php`.
- **backend:** کلاس‌های `HandoffSlack` (incoming webhook روی `hooks.slack.com`)، `HandoffDiscord` (webhook روی `discord.com/api/webhooks`)، `HandoffWebhook` (فقط https عمومی، بدنهٔ JSON با امضای HMAC-SHA256 در هدر `X-AISA-Signature` و timestamp ضد replay) و `HandoffWhatsApp` (WhatsApp Cloud API با phone number id و token). secretها رمزشده؛ «Send a test post» فقط admin با پیام آزمایشی بدون دادهٔ بازدیدکننده؛ حضور در fan-out و ترتیب T37.
- **آزمون و گیت:** integration برای هر transport با `pre_http_request` (موفق، 4xx، timeout)، رد میزبان نامجاز و نبود secret در پاسخ REST و لاگ.
- **تصمیم:** D-07.

### T47 — S5.23 voice realtime (A-09، W-01، E-01)

- **موکاپ:** `AutoPilot Admin.dc.html:1754` و `:2420` (realtime، barge-in، صداهای Aria، Verse، Sol و Nova، تشخیص زبان خودکار)؛ `Front Chat Widget.dc.html:267`، `:348` تا `:352` (orb با فازهای Connecting، Listening، Thinking و Speaking و «Tap the mic to interrupt any time»)؛ `EFB Builder Chat.dc.html:390` و `:221` تا `:224` (صفحهٔ تماس با Mute، End و Chat).
- **کد فعلی:** قفل در `includes/Admin/AnswerDeskVoicePage.php:57` تا `:75` و `:102` تا `:104`؛ transcript-first در `assets/frontend/widget.js:452` و `assets/admin/efb-copilot.js:533`.
- **backend:** مسیر `POST /voice/realtime/session` که session کوتاه‌عمر provider را سمت سرور می‌سازد و فقط token موقت برمی‌گرداند (کلید API هرگز به مرورگر نمی‌رسد)؛ rate limit و سقف مصرف صوتی؛ نگاشت نام صداها به صداهای provider؛ رضایت صریح میکروفون؛ ثبت مصرف؛ fallback به transcript-first در نبود پشتیبانی provider.
- **UI:** overlay ویجت، صفحهٔ تماس EFB و بازکردن تب Real-time voice؛ کنترل‌های Mute و End؛ `prefers-reduced-motion` برای orb.
- **آزمون و گیت:** integration برای session (نبود secret، TTL، cap)؛ browser با stub `RTCPeerConnection` و `getUserMedia`؛ هیچ اتصال واقعی provider در تست.
- **تصمیم:** D-02 و D-07.

### T48 — S5.24 ووکامرس Phase 2 (A-10)

- **موکاپ و کد:** ردیف‌های Phase 2 در `includes/Admin/StoreCopilotPage.php:259` تا `:269`، `:299` و `:407`؛ controllerهای خفتهٔ `WooCommerceOrderController` و `WooCommerceContentController`.
- **طرح:** هر قابلیت (ویرایش محصول موجود به‌صورت revision پیش‌نویس، انتشار، تغییر گروهی، تغییر وضعیت سفارش، بازپرداخت) یک زیرتسک مستقل با diff پیش‌نمایش، تأیید صریح، capability دقیق (`edit_products`، `publish_products`، `edit_shop_orders`، و برای بازپرداخت `manage_woocommerce`)، order note، لاگ و بازبینی امنیتی مانند T20.
- **تصمیم:** D-17؛ پیش از آن `WooCommerceConnector::get_capabilities()` بدون تغییر می‌ماند.

### T49 — S5.25 وضعیت سفارش برای بازدیدکننده (A-11)

- **موکاپ:** `AutoPilot Admin.dc.html:2436` (منبع «Store Copilot · order lookup» برای «My order 4821 has not shipped yet»).
- **کد فعلی:** فقط `GET /woocommerce/orders/recent` برای مدیر (`RestRoutes.php:264`)؛ متن `StoreCopilotPage.php:267`.
- **طرح:** مشتری واردشده فقط سفارش‌های خودش را با `wc_get_orders( array( 'customer_id' => get_current_user_id() ) )` می‌بیند؛ مهمان فقط طبق D-18 با شمارهٔ سفارش و ایمیل billing (مقایسهٔ زمان‌ثابت و rate limit سخت). خروجی فقط وضعیت، تاریخ و کد رهگیری در صورت وجود؛ بدون آدرس، مبلغ یا روش پرداخت؛ لاگ بدون PII.
- **تصمیم:** D-18.

### T50 — S5.26 price map و ایمیل سقف (A-16)

- **موکاپ:** مبالغ دلاری Usage & Cost و «At the cap, AutoPilot pauses every AI feature and emails you» (`AutoPilot Admin.dc.html:1295`).
- **کد فعلی:** توکن‌محور (`includes/Admin/UsagePage.php:9`)؛ `UsageTracker::get_report()` گروه‌بندی `by_model` دارد (`includes/Usage/UsageTracker.php:235`).
- **طرح:** جدول قیمت قابل‌ویرایش توسط مدیر (قیمت ورودی و خروجی برای هر یک میلیون توکن در هر model) در option `aisaautopilot_price_map`؛ بدون قیمت پیش‌فرض بسته‌بندی‌شده که کهنه شود؛ هزینه فقط برای مدل‌های قیمت‌دار و با برچسب «برآورد از قیمت‌هایی که شما وارد کرده‌اید»؛ سقف همچنان توکن‌محور. ایمیل هنگام رسیدن به سقف: یک‌بار در هر دوره به `admin_email` با رویداد لاگ.
- **آزمون:** PHPUnit محاسبه؛ integration با `pre_wp_mail` برای ایمیل یک‌باره.
- **تصمیم:** D-19.

### T51 — S5.27 مکان بازدیدکننده (A-06، بخش مکان)

- **موکاپ:** `AutoPilot Admin.dc.html:2436` («Visitor · Berlin»).
- **پیشنهاد:** ساخته نشود. اگر D-15 تأیید شد: فقط کشور از هدر اعتمادشدهٔ CDN (مثلاً `CF-IPCountry`) با تنظیم opt-in، بدون ذخیرهٔ IP، و متن حریم خصوصی.
- **تصمیم:** D-15.

### T52 — S5.28 Publish on approval (A-13)

- **موکاپ:** وضعیت «Published automatically» (`AutoPilot Admin.dc.html:2527`).
- **پیشنهاد:** انتشار خودکار ساخته نشود. اگر D-14 تأیید شد: گزینهٔ صریح «Publish on approval» در همان پیش‌نمایش، که انتشار را به تأیید انسانی گره می‌زند؛ برچسب UI «Published on approval» به‌جای «automatically».
- **تصمیم:** D-14.

### T53 — S5.29 بستن S5-B

- مانند T43 برای taskهای S5-B، با گزارش `docs/MOCKUP_GAP_S5B_EXECUTION_REPORT_FA.md`.

---

## ۶. پیوند با Claude Design

- قابلیت‌هایی که موکاپ برایشان UI کامل ندارد (کارت محصول ویجت در T39، dialog ویرایش الگو در T34، ترتیب کانال‌ها در T37) پیش از اجرا در پروژهٔ Claude Design با همان زبان طراحی ثبت می‌شوند و نسخهٔ دانلودشده مرجع بصری task است.
- قابلیت‌های کدِ بدون موکاپ (بخش ۲ گزارش شکاف) طراحی تازه در همان پروژه می‌گیرند؛ مرجع محلی نسخهٔ دانلودشده در `design/claude-design-v2/` است و فولدر `mockups/` دست نمی‌خورد.

## ۷. تصمیم‌های مالک مرتبط

| ID | پرسش | پیشنهاد |
|---|---|---|
| D-13 | S5-A پیش از فاز ۴ و در نسخهٔ 1.1.0 اجرا شود؟ | بله؛ قرارداد را نمی‌شکند و QA نهایی یک‌بار روی UI کامل اجرا می‌شود |
| D-14 | «Published automatically» ساخته شود؟ | خیر؛ در صورت نیاز فقط «Publish on approval» صریح |
| D-15 | مکان بازدیدکننده نمایش داده شود؟ | خیر؛ اگر لازم شد فقط کشور، opt-in و بدون ذخیرهٔ IP |
| D-16 | parser PDF و سیاست دریافت URL؟ | `smalot/pdfparser` با نصب مالک؛ URL فقط https عمومی، robots و سقف 2MB |
| D-17 | قرارداد draft-only ووکامرس برای Phase 2 باز شود؟ | پس از انتشار و هر قابلیت با بستهٔ امنیتی مستقل؛ شروع از ویرایش به‌صورت revision پیش‌نویس |
| D-18 | وضعیت سفارش برای بازدیدکننده با چه احرازی؟ | ابتدا فقط مشتری واردشده؛ مهمان بعداً با شمارهٔ سفارش، ایمیل و rate limit |
| D-19 | منبع قیمت و ایمیل سقف؟ | جدول قیمت قابل‌ویرایش بدون پیش‌فرض بسته‌بندی‌شده؛ ایمیل یک‌بار در هر دوره |

---

## ۸. نتیجهٔ T29 — ممیزی ۲۰۲۶-۱۱-۰۴

- **مجری و روش:** هر ادعای «کد فعلی» در بخش‌های ۴ و ۵ و هر ردیف بخش ۱ گزارش شکاف روی کد HEAD (`569dd8a`) با Grep و خواندن فایل وارسی شد. سپس ادعاهای کلیدی دوباره با `sed -n` تأیید شدند: قفل Discord و Webhook، نبود caller برای `KnowledgeBase::search`، فهرست سفید `ActionStore::create`، هدر Reply-To و سقف توکن providerها. هیچ کد محصولی تغییر نکرد.
- **حکم:** جایی که این بخش با مرجع‌های کد در بخش‌های ۴ و ۵ اختلاف دارد، **این بخش معتبر است**. شمارهٔ خط‌ها مربوط به همین تاریخ است.

### ۸.۱ گام ۱: D-13

D-13 بسته است: تأیید مالک در ۲۰۲۶-۰۹-۱۸ گزینهٔ (ب) بود، یعنی S5-A پس از انتشار. T25 در جدول صف `تکمیل` است، پس T29 اجراپذیر بود و متوقف نشد.

### ۸.۲ گام ۲: ردیف‌های گزارش شکاف

**محدودیت محیط:** فایل‌های موکاپ (`mockups/design_handoff_admin_redesign/*.dc.html`) و قاب‌های `test-artifacts/mockup-parity/mockup/` در این مخزن نیستند. در شاخهٔ `main` مخزن `hassantafreshi/sitepilot-ai` هم نیستند؛ این در ۲۰۲۶-۱۱-۰۴ با sparse clone بررسی شد. پس:
- خط‌های موکاپ (`:331`، `:1543` و…) دوباره تأیید **نشدند** و همان مرجع ۲۰۲۶-۰۹-۱۵ ماندند؛
- برچسب دقیق گزینه‌های `lengthOptions` برای T35 ثبت **نشد**.

هر task که به این دو نیاز دارد، پیش از کار UI باید آن‌ها را روی ماشین مالک بگیرد، یا مالک فولدر موکاپ را در مخزن بگذارد. بخش کد کامل وارسی شد:

| ردیف | نتیجه | مرجع کد امروز | چه عوض شده |
|---|---|---|---|
| A-01 | درست | قفل Reply length: `SettingsPage.php:912` تا `:918`. متن accordion در `:897`: «Temperature, reply length, semantic search». timeout: `ProviderManager.php:68` با پیش‌فرض 45. | کارت سقف ماهانه در `SettingsPage.php:952` تا `:972`. کلید `reply_length` و `request_timeout` نیست. |
| A-02 | درست | قفل «Managed by index scope»: `KnowledgeBasePage.php:266`. ردیف‌ها `:243` تا `:268`. `ContentIndexer::get_indexable_types()` در `:496`. `kb_post_types` در `Settings.php:447`. | بازیابی گفتگو اکنون `KnowledgeSearch::retrieve` است (`includes/Content/KnowledgeSearch.php:60`، `rank` در `:153`، `fuse` در `:302`). `KnowledgeBase::search` (`KnowledgeBase.php:330`) هیچ caller ندارد. `VectorSearch` فقط fallback است (`ChatEngine.php:192` تا `:194`). |
| A-03 | درست، با تکمیل | متن صادقانه: `KnowledgeBasePage.php:290`. «Resume indexing» پس از توقف سقف: `:543`. `/kb/index/batch`: `RestRoutes.php:449`. | cron بازسازی پایدار `aisaautopilot_kb_rebuild` با cursor و انتظار یک‌ساعته پس از سقف: `ContentHooks.php:59` تا `:90`، `KnowledgeIndex::REBUILD_OPTION`. فقط پس از تغییر قالب ایندکس اجرا می‌شود، نه برای اجرای مدیر. |
| A-04 | درست | `KnowledgeBasePage.php:333` («Only the WordPress post types listed above are connected in this version.») | جدول chunks (`KnowledgeIndex.php:52` تا `:61`) ستون `source_type` ندارد. |
| A-06 | درست | شمار رشته‌ها: `AnswerConversationsPage.php:154`. جدول: `VisitorConversationStore.php:30`. `append_turn`: `:48`. | ChatEngine علاوه بر `answered`، `clarify` و `not_found`، وضعیت‌های `error`، `not_configured` و `usage_cap_reached` هم دارد (`ChatEngine.php:68`). `append_turn` فقط پس از پاسخ موفق صدا زده می‌شود (`ChatWidgetController.php:105`). |
| A-07 | **بیشتر منسوخ** | کانال‌های واقعی: WhatsApp Business (`AnswerDeskHandoffPage.php:307` تا `:360`)، Slack (`:363` تا `:389`)، Signal (`:392` تا `:441`؛ در موکاپ نیست). قفل‌مانده: Discord و Custom webhook (`:457`، `:458`). متن: `:460`. آزمون: دکمهٔ «Check connection» و `POST /widget/test/(whatsapp\|slack\|signal)` (`RestRoutes.php:803`). | قفل `:206` تا `:230` دیگر وجود ندارد. |
| A-08 | درست | متن fan-out: `AnswerDeskHandoffPage.php:69`. `HandoffService::handoff()`: `HandoffService.php:21` تا `:71`. | fan-out اکنون ۶ کانال دارد: email، telegram، twilio، whatsapp، slack و signal (`:32` تا `:50`). صندوق با شکست همهٔ کانال‌ها `failed` می‌شود (`ChatWidgetController.php:259` تا `:266`). |
| A-09 | درست | `AnswerDeskVoicePage.php:57` تا `:76` و `:102` تا `:105`. «Browser default»: `:91` و `:92`. | — |
| A-10 | **بخشی منسوخ** | ردیف‌های Phase 2: `StoreCopilotPage.php:298` تا `:300` و `:307`، `:308`. قفل «Product editing by AI»: `:341`. | تب Descriptions (`:445` تا `:483`): توضیح بلند محصول موجود با پیش‌نویس AI، بازبینی، اعمال مستقیم و Undo از meta `_aisaautopilot_previous_description` (`ProductDescriptions.php:173` تا `:217`). متن تب Products هنوز می‌گوید هیچ محصول موجودی ویرایش نمی‌شود (`:418`، `:436`): **تناقض**؛ بخش ۸.۷. |
| A-11 | درست | `StoreCopilotPage.php:306`، `:496`، `:499`. `/woocommerce/orders/recent`: `RestRoutes.php:326`. | — |
| A-12 | درست | متن: `StoreCopilotPage.php:337`. `WooCommerceController::smart_search`: `WooCommerceController.php:84`. route: `RestRoutes.php:302` تا `:314`. | ایندکس KB محصولات را با `content_type` برابر `product` برچسب می‌زند (`ContentIndexer.php:468`، `:469`). |
| A-13 | درست (عمدی) | `ContentDraftExecutor.php:54`، `ContentController.php:91`، `CopilotPage.php:330` | — |
| A-14 | درست | چیپ‌های قفل: `CopilotPage.php:349`، `:350`. `/content/plan`: `RestRoutes.php:49` تا `:60`. | `ContentController::plan` علاوه بر `prompt`، `source_gap` هم می‌گیرد. الگوی لحن در Store Descriptions هست (`ProductDescriptions.php:30`). |
| A-15 | درست | الگوها: `CopilotPage.php:403` تا `:425` (بدون شناسه؛ «Use template» متن brief را می‌فرستد: `ContentStudioNavigation.php:55` تا `:66`). «Template not recorded»: `ContentStudioReadModel.php:120`. | الگوی «Used %s times» در پاسخ‌های ذخیره‌شدهٔ KB هست (`KnowledgeBasePage.php:514` تا `:518`). |
| A-16 | درست، با تکمیل | `UsagePage.php:9` تا `:12` و `:132`. `UsageTracker::get_report()`: `UsageTracker.php:233`. | Setup سقف ماهانهٔ پیشنهادی دارد، ولی برحسب توکن (`SettingsPage.php:950` تا `:973`). اعلان درون برنامه هست: بنر Overview (`HomePage.php:77` تا `:87`) و هشدار ۷۵٪ (`:310` تا `:322`). ایمیل سقف هنوز نیست. |
| A-17 | درست | `LogsPage.php:44`، `ActivityTimeline.php:24` و `:175` تا `:209`. فقط `GET /activity` هست. | Undo توضیح محصول در Store هست، ولی Activity Log برای آن اقدامی ندارد. |
| A-18 | درست | `Ui::banner()`: `Ui.php:404`. `IntegrationsPage.php` فقط بنر «همه خاموش» دارد (`:166`). | `ConflictDetector` وجود ندارد. |
| A-19 | درست | `AnswerDeskWidgetPage.php:105` تا `:111`؛ آزمون `browser_release_check.cjs:569` | — |
| W-01 | درست (D-02 بسته) | `#spw-voice`: `ChatWidget.php:106`. `#spw-mic`: `:159`. `startRecording`: `widget.js:827`. دروازهٔ `voice_disabled`: `PublicChatController.php:58`. | — |
| W-02 | **بخشی منسوخ** | کارت: `ChatWidget.php:214` تا `:251`. `showHandoffCard`: `widget.js:923`. `requestHandoff`: `:1002`. `ChatWidgetController::handoff`: `:167`. | **Reply-To ساخته شده:** `HandoffService.php:131`، `:132` و `HandoffEmail.php:81` تا `:83`، با آزمون `wordpress_widget_handoff_flow_integration.php`. فیلدهای تماس (نام، ایمیل، تلفن، وب‌سایت) قابل تنظیم‌اند (`ChatWidget.php:263` تا `:286`). موتور می‌تواند خودش انتقال را پیشنهاد کند (`widget.js:560`). انتخاب کانال هنوز نیست. |
| C-01 | **بخشی ساخته شده** | بازبینی با پیام: `form-builder.js:370` تا `:372` روی همان `POST /forms/plan` (`:386`). | «Back and edit» دیگر نیست. کلیک روی فیلد، چیپ «Editing» و `/forms/refine` هنوز نیستند. انتخاب فیلد با چیپ در EFB Copilot الگوی آماده است (`efb-copilot.js:204` تا `:277`). |
| C-02 | **بخشی منسوخ** | `renderPlanCanvas`: `form-builder.js:193` تا `:220`. پیش‌نمایش فقط‌خواندنی: `:235` تا `:257`. نتیجه: `:259` تا `:291`. | تب‌های Preview و Structure حذف شدند؛ فهرست فیلدها کنار پیش‌نمایش است. |
| C-03 | درست | `CopilotPage.php:115` تا `:123` | دیگر نوار ثابت نیست؛ فقط در حالت شروع دیده می‌شود و متنش «dates» را هم دارد. درخواست پیشرفته فقط از راه `notes` نوشتهٔ AI (`CreateFormSkill.php:49`) به گفتگو می‌رسد. |
| E-01 | **بخشی منسوخ** | یک دکمهٔ ضبط: `#aisaautopilot-efb-voice` (`EfbBuilderButton.php:353`). `startVoice`: `efb-copilot.js:922` تا `:947`. | `aisaautopilot-efb-live-voice` دیگر نیست. متن زنده و سپس transcript سرور در composer، بدون ارسال خودکار (`:909`). |
| X-04 | متن منسوخ، اختلاف پابرجا | `CopilotPage.php:81`: «Simple forms, emailed to you. Describe one, check the plan, then create it.» | — |

### ۸.۳ گام ۳: inventory REST و برخورد route

`rest_get_server()->get_routes()` روی WordPress 7.1.3 با WooCommerce 11.2.1 و EFB 4.3.1 فعال، **۷۶ route** زیر `aisaautopilot/v1` داشت (بدون ریشهٔ namespace). هیچ‌یک از routeهای تازهٔ این بسته با route موجود برخورد ندارد:

| route تازه | task | وضعیت |
|---|---|---|
| `POST /integrations/conflicts/dismiss` | T31 | آزاد |
| `GET/POST /content/templates` و `DELETE /content/templates/(?P<id>…)` | T34 | آزاد |
| `POST /kb/collections` و `POST /kb/collections/add` | T36 | آزاد |
| `POST /forms/refine` | T40 | آزاد |
| `POST /activity/undo` و `POST /activity/restore` | T41 | آزاد؛ `GET /activity` جداست و تداخلی ندارد |

routeهای تازه از ۲۰۲۶-۰۹-۱۵ که planها باید بدانند:
- `/forms/outbox/{id}/resend` و `/forms/outbox/{id}/delete`
- `/forms/delivery/test`
- `/handoff/visitor-fields`
- `/handoffs/*`
- `/kb/answers` و `/kb/answers/delete`
- `/privacy/retention/run`
- `/usage/budget`
- `/widget/test/(whatsapp|slack|signal)`
- `/woocommerce/descriptions` (به‌همراه `/draft`، `/apply` و `/undo`)
- `/woocommerce/carts/{id}/remind` و `/woocommerce/carts/{id}/delete`
- `/woocommerce/analytics/dashboard`

### ۸.۴ گام ۴: قاب‌های موکاپ

فهرست و گرفتن قاب‌ها در این محیط شدنی نبود، چون فایل‌های موکاپ نیستند (بخش ۸.۲). قاب‌های مورد نیاز همان فهرست گام ۴ است. هر task که UI می‌سازد (T31 تا T42 و T76)، پیش از کار browser، قاب خودش را روی ماشین مالک بگیرد.

### ۸.۵ گام ۵: افزونه‌های فعال برای T31

سایت آزمایشی محیط ابری (WordPress 7.1.3): `ai-site-assistant-autopilot` 0.0.1، `easy-form-builder` 4.3.1، `woocommerce` 11.2.1 و `plugin-check` 2.1.0. هیچ‌کدام افزونهٔ چت یا دستیار AI هم‌پوشان نیستند، پس browser check T31 در این محیط حالت «بدون تداخل» را می‌آزماید. حالت مثبت باید با fixture یا `pre_option_active_plugins` آزموده شود.

افزونهٔ Social Chat Buttons که سایت محلی مالک (XAMPP) نشان می‌داد، اینجا وارسی نشد. T31 slug آن را روی ماشین مالک یا از صفحهٔ WordPress.org تأیید کند.

### ۸.۶ اصلاح planها

**S5-A:**

- **T31:** بدون تغییر.
- **T32:**
  - `ContentController::plan` اکنون `prompt` و `source_gap` می‌گیرد.
  - `ActionStore::create` فقط `status` و `source_gap` را در `execution_context` نگه می‌دارد (`ActionStore.php:42` تا `:48`). `tone` بدون افزودن به این فهرست سفید دور ریخته می‌شود؛ این تغییر بخشی از T32 است.
  - واژه‌های لحن با واژه‌های Store Descriptions (`ProductDescriptions.php:30`) هماهنگ شوند، یا اختلاف ثبت شود.
- **T33:**
  - الگوها در `CopilotPage.php:403` تا `:425` هستند، نه `:344` تا `:368`.
  - «Use template» متن brief را می‌فرستد، نه شناسه را.
  - `template_id` هم به همان فهرست سفید `ActionStore` نیاز دارد.
  - رشته‌های جمع «Used %s times» و «Not used yet» از KB قابل استفاده‌اند.
- **T34:** بدون تغییر.
- **T35:**
  - کنترل قفل در `SettingsPage.php:912` است.
  - «رفتار فعلی» یکسان نیست: Anthropic همیشه `max_tokens` 8192 می‌فرستد (`AnthropicProvider.php:32`، `:196`)، ولی `OpenAiCompatibleProvider` در گفتگو `max_tokens` نمی‌فرستد. پس گزینهٔ پیش‌فرض «Standard» یعنی «همان سقف هر provider». برای OpenAI-compatible یعنی نفرستادن `max_tokens`، وگرنه رفتار عوض می‌شود.
  - fallback `max_completion_tokens` از پیش هست (`:389` تا `:391`).
  - `ProviderInterface` پارامتر سقف توکن ندارد، پس امضای آن تغییر می‌کند.
  - فهرست مدل‌ها اکنون `min( 20, $timeout )` است.
  - متن accordion در `:897` واژهٔ «timeout» را ندارد.
- **T36:**
  - وزن‌دهی و کنار گذاشتن نوع خاموش باید در `KnowledgeSearch::rank` و `fuse` و مسیر semantic آن (`:276` تا `:283`) انجام شود، نه در `KnowledgeBase::search` که caller ندارد.
  - جدول‌های chunks و terms ستون `post_type` ندارند. فیلتر یا با join روی post، یا با ستون تازه و افزایش `KnowledgeIndex` SCHEMA_VERSION و بازسازی انجام شود. این تصمیم در خود T36 گرفته و ثبت شود.
  - `get_indexable_types()` در `ContentIndexer.php:496` و `kb_post_types` در `Settings.php:447` است.
- **T37:**
  - `handoff_order` باید `telegram`، `twilio`، `whatsapp`، `slack` و `signal` را بپذیرد و با `AdminHandoffSettingsController::ENABLE_KEYS` اعتبارسنجی شود.
  - Email اکنون اولین عضو آرایهٔ fan-out است و در حالت `ordered` باید به انتها برود.
  - قاعدهٔ پذیرش برای کانال‌های تازه: WhatsApp پاسخ 2xx همراه `messages[0].id` (`HandoffWhatsApp.php:223`)، Slack پاسخ 200 (`HandoffSlack.php:139`) و Signal پاسخ 2xx (`HandoffSignal.php:104`).
  - گام ۳ (`failed` در صندوق) از پیش ساخته شده است.
- **T38:**
  - گام ۱ backend، یعنی هدر Reply-To، **از پیش ساخته شده** و حذف می‌شود.
  - کار باقی‌مانده:
    - دکمهٔ «Email me a reply» با ایمیل الزامی؛
    - `handoff_telegram_public_url` و دکمهٔ Telegram؛
    - `contact_method` در صندوق.
  - کارت تازه باید با فیلدهای تماس قابل تنظیم (`handoff_asks`) و پیشنهاد خودکار انتقال (`handoff_offer`) سازگار بماند.
  - مرجع‌ها همان مرجع‌های ردیف W-02 در بخش ۸.۲ است.
- **T39:**
  - برچسب `content_type` برابر `product` در ایندکس KB از پیش هست و کارت محصول می‌تواند روی آن بنا شود.
  - منابع ChatEngine فعلاً `post_id`، `title`، `url` و `relevance` دارند (`ChatEngine.php:55`).
- **T40:**
  - بازبینی با پیام (C-01، بخشی) روی `/forms/plan` ساخته شده است. `/forms/refine` همچنان کار تازه است.
  - دو پیش‌نیاز تازه:
    1. `CopilotController::build_preview` (`CopilotController.php:269` تا `:293`) `key`، `help`، `placeholder` و `width` فیلد را دور می‌ریزد. `field_refs` به `key` نیاز دارد. بازبینی فعلی با پیام هم متن راهنما را به مدل برنمی‌گرداند و ممکن است آن را گم کند.
    2. هر بازبینی proposal تازه می‌سازد و proposal قبلی تا پایان TTL پانزده‌دقیقه‌ای روی سرور قابل تأیید می‌ماند. فقط دکمه‌اش در مرورگر غیرفعال می‌شود. `/forms/refine` باید token قبلی را باطل کند.
  - مرجع‌های `form-builder.js` با بخش ۸.۲ جایگزین شوند.
  - آزمون freeze بازبینی در `browser_release_check.cjs:1451` تا `:1616` است.
  - کلیدهای i18n مرده: `backAndEdit` (`CopilotPage.php:504`) و `structure` (`:483`).
- **T41:**
  - `ActionStore` فهرست یا index ندارد؛ proposalها transient جدا با TTL پانزده‌دقیقه هستند (`ActionStore.php:15`، `:26`، `:59`). `PendingApprovals` به index تازه نیاز دارد.
  - اقدام «Restore previous description» برای رویداد `store.description_applied` از مسیر Undo موجود (`/woocommerce/descriptions/undo`) کم‌هزینه است.
- **T42:**
  - `outcome` برابر `error` هرگز نوشته نمی‌شود، چون نوبت ناموفق ثبت نمی‌شود. فقط `answered`، `not_found` و `clarify`.
  - علامت `handoff` را می‌توان در `/widget/handoff` با `conversation_id` موجود گذاشت (`ChatWidgetController.php:202`).
  - نگهداری پیش‌فرض گفتگو ۳۰ روز است (`Retention.php:36`) و برای آمار «امروز» کافی است.
- **T76 (تازه):** A-19، یعنی «Match my theme colours». گزارش شکاف تعیین task آن را به T29 سپرده بود؛ بخش ۸.۸.

**S5-B (فقط اصلاح مرجع؛ همچنان `نیازمند تصمیم`):**

- **T44:** cron بازسازی `aisaautopilot_kb_rebuild` با cursor و توقف سقف (`ContentHooks.php:59` تا `:90`) الگوی آماده است. صف تازه نباید هم‌زمان با آن اجرا شود.
- **T45:** `source_type` و `source_ref` باید روی جدول‌های chunks و terms هم بیایند (افزایش schema).
- **T46:** دامنه به **Discord و Custom webhook** کوچک شد، چون WhatsApp Business و Slack ساخته شده‌اند و Signal افزوده شده است. دکمهٔ آزمون «Check connection» است، نه «Send a test post»: یا اختلاف عمدی ثبت شود یا متن عوض شود.
- **T47:** مرجع‌های transcript-first عبارت‌اند از `widget.js:827` تا `:902` و `efb-copilot.js:909` تا `:947`.
- **T48:**
  - ویرایش توضیح بلند محصول موجود (مستقیم، با Undo و بدون revision) از پیش ساخته شده است.
  - دامنهٔ باقی: قیمت، موجودی، عنوان و فیلدهای دیگر، انتشار، تغییر سفارش و بازپرداخت.
  - `WooCommerceOrderController` برای `recent` ثبت شده و `update_status` و `refund` آن route ندارند. `WooCommerceContentController` ثبت نشده است.
- **T49:** route در `RestRoutes.php:326` و متن در `StoreCopilotPage.php:306` است.
- **T50:**
  - `UsageTracker.php:233`.
  - سقف Setup و بنر Overview از پیش هستند؛ فقط ایمیل و نقشهٔ قیمت باقی است.
  - تناقض متنی: Setup می‌گوید «every AI feature pauses» (`SettingsPage.php:962`)، ولی Usage می‌گوید «only requests covered by this token cap pause» (`UsagePage.php:89`). بخش ۸.۷.
- **T51 و T52:** بدون تغییر.

### ۸.۷ یافته‌های جانبی (خارج از دامنهٔ T29؛ برای تصمیم مالک)

| # | یافته | شدت | پیشنهاد |
|---|---|---|---|
| F-1 | تب Products در Store Copilot می‌گوید «Read-only in Phase 1 - AutoPilot cannot edit what already exists» و «can read but not touch until Phase 2» (`StoreCopilotPage.php:418`، `:436`). از زمان قابلیت Descriptions (۲۰۲۶-۱۱-۰۴) این دقیق نیست. ردیف‌های «Edit a product that already exists» و «Change many products at once» (`:299`، `:300`) هم استثنای توضیح را نمی‌گویند. | متوسط: متن نادرست در UI | اصلاح متن با ذکر استثنای توضیح بلند، ترجمهٔ فارسی و آزمون |
| F-2 | پس از بازبینی طرح فرم با پیام، proposal قبلی تا ۱۵ دقیقه روی سرور قابل اجراست و فقط مرورگر دکمه‌اش را غیرفعال می‌کند. | کم: فقط مدیر و فقط طرحی که خودش دیده | باطل کردن token قبلی در T40 |
| F-3 | بازبینی با پیام، متن راهنما و placeholder فیلدها را به مدل نمی‌فرستد (`build_preview`) و ممکن است در طرح تازه گم شوند. | کم | در T40 |
| F-4 | تناقض متن سقف مصرف بین Setup (`SettingsPage.php:962`) و Usage (`UsagePage.php:89`) | کم | یکسان کردن متن |
| F-5 | کد مرده: `KnowledgeBase::search` (بدون caller) و کلیدهای i18n `backAndEdit` و `structure` | کم | حذف در یک کار پاک‌سازی |

### ۸.۸ تعیین task برای A-19

A-19 («Match my theme colours») task نداشت. **T76** در S5-A تعریف می‌شود: وابسته به T29، پیش از T43 و بدون تصمیم مالک، چون قرارداد را نمی‌شکند.
- **کار:** پالت قالب از `wp_get_global_settings( array( 'color', 'palette' ) )` (theme و default) خوانده می‌شود و در تب Widget به‌صورت پیشنهاد رنگ، کنار رنگ‌های برند، نمایش داده می‌شود. انتخاب یک رنگ فقط فرم را پر می‌کند و ذخیره همچنان با دکمهٔ Save همان صفحه است. قالب بدون پالت با متن صادقانه و قفل فعلی می‌ماند.
- **گیت:** integration با فیلتر `wp_theme_json_data_theme` برای پالت ساختگی و حالت بدون پالت. browser در en/fa و 390 با keyboard. آزمون `browser_release_check.cjs:569` به‌روز شود.
