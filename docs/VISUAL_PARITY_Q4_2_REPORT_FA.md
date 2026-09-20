# گزارش هم‌خوانی تصویری با موکاپ — T16 (Q4.2)

تاریخ: ۲۰۲۶-۰۹-۱۹  
شواهد: `C:\files\projects\SitePilot AI\test-artifacts\visual-parity\20260919-t16\` و فریم‌های موکاپ در `…\test-artifacts\mockup-parity\mockup\`

## نتیجه

**گیت T16 سبز است: همهٔ ۱۳۵ حالت موکاپ حکم دارند و نقص تصویری باز صفر است.** هشت نقص در همین task رفع شد (V16-1 تا V16-8)؛ پنج تای آن‌ها روی حالت‌هایی بود که پس از رفع «منطبق» یا «اختلاف عمدی» گرفتند. هر اختلافی که ماند یا عمدی است، یا backend ندارد و صادقانه قفل یا حذف شده، یا فقط در خود موکاپ معنا دارد. هر کدام به یک شناسه در `docs/MOCKUP_FEATURE_GAP_REPORT_FA.md` ارجاع دارد. هشت اختلاف عمدی تازه (X-06 تا X-13) به همان فهرست افزوده شد.

| حکم | شمار |
|---|---|
| منطبق | 82 |
| اختلاف عمدی | 30 |
| بدون backend (قفل صادقانه یا ناموجود) | 14 |
| بیرون از محصول (فقط در موکاپ) | 6 |
| نقص رفع‌شده در T16 (حالتی که حکمش به رفع بسته بود) | 3 |
| **جمع** | **135** |

## ۱. روش

- **فریم‌های موکاپ:** 186 فایل که 135 حالت یکتا دارند: 118 حالت مدیریتی `admin-*`، و 17 حالت ویژه (Form Composer، EFB Copilot و ویجت بازدیدکننده)، هر کدام در LTR، RTL و دو عرض.
- **عکس‌های تمیز افزونه:** `capture_admin.cjs` از 23 route در `en_US` و `fa_IR`، در 1440×960 و تمام‌صفحه، بدون هیچ تعامل (`plugin/admin/<locale>/`).
- **حالت‌هایی که با بارگذاری صفحه دیده نمی‌شوند:** `capture_states.cjs` 29 حالت را می‌گیرد (`plugin/states/en_US/`):
  - مرحلهٔ ۳ Setup با هر پاسخ `provider/test` و `provider/models`؛
  - پیشنهاد و نتیجه‌های Store Copilot؛
  - بازبینی و حالت مشغول Content Studio؛
  - Health پس از اجرای ناموفق و در حین اجرا؛
  - fixtureهای Usage، Site Knowledge، Content Studio History و Activity Log.
- **ایمنی ضبط:**
  - هر مسیر REST که به ارائه‌دهنده می‌رسد یا داده می‌نویسد، با شکل پاسخ واقعی controller mock شد. هر مسیر ارائه‌دهنده‌ای که mock نشده باشد abort می‌شود.
  - هیچ تنظیمی ذخیره نشد. هر fixture پس از استفاده restore شد و حساب QA حذف شد (`capture-states.txt`).
  - روی این سایت ارائه‌دهنده‌ای تنظیم نشده، پس composer در Content Studio غیرفعال render می‌شود. برای ضبط حالت‌های بازبینی و مشغول فقط ویژگی `disabled` برداشته شد.
- **حالت‌های ویژه:** شواهد matrixهای موجود به کار رفت:
  - `form-studio-matrix.txt` (T16)؛
  - `test-artifacts\front-chat-widget\20260919-t17\`؛
  - `test-artifacts\efb-copilot\20260919-t17\`.
- **بازبینی:** هر جفت با چشم مقایسه شد: ساختار، سلسله‌مراتب، فاصله‌ها، توکن‌های رنگ، تایپوگرافی و محتوای حالت.
- **این‌ها اختلاف شمرده نشدند:**
  - chrome وردپرس به‌جای قاب نمایشی موکاپ؛
  - نام ارائه‌دهنده و مدلِ همین سایت؛
  - chip سربرگ «Generation paused — cap reached» که وضعیت واقعی این سایت است.
- **ستون شاهد:**
  - «ت۱»: عکس تمیز T16؛
  - «ت۲»: عکس حالت T16؛
  - «FS»، «W» و «E»: matrixهای Form Studio، ویجت و EFB؛
  - «کد»: حکم از خواندن کد و رفتار ثبت‌شده در بسته‌های پیشین، بدون عکس تازه در T16.

## ۲. نقص‌های رفع‌شده

| # | نقص | رفع | شاهد |
|---|---|---|---|
| V16-1 | عرض سربرگ در شش صفحه (Answer Desk، Content Studio، Store Copilot، Logs، Health، Usage) با `inline-size: calc(100% - 40px)` و `max-inline-size` خودش محاسبه می‌شد. در نتیجه لبهٔ سربرگ با بقیهٔ صفحه‌ها یکی نبود. | همهٔ override‌های عرض، از جمله شش override گوشی، حذف شد و مدل مشترک `.sp-pagebody` حاکم شد: `aisaautopilot-ui.css` (یادداشت T16). | a11y matrix اجرای `run5-t16`: پراکندگی لبهٔ سربرگ 0px |
| V16-2 | گریدهای Widget، Human handoff و Real-time voice در Answer Desk با `margin-inline: auto` وسط‌چین بودند. در موکاپ از لبهٔ آغاز شروع می‌شوند. | `margin-inline: 0` | ت۱ `answers-*` |
| V16-3 | رویدادهای `efb.*` در خط زمانی فعالیت برچسب محصول نداشتند. | نگاشت به «Easy Form Builder Copilot»: `includes/Activity/ActivityTimeline.php`. در آزمون integration پنج سنجه افزوده شد (30/0). | `tests/wordpress_activity_timeline_integration.php` |
| V16-4 | «Expires in»، «Review product draft» و دو برچسب قفل Phase 1 در Store Copilot رشتهٔ انگلیسی ثابت در JS بودند و در `fa_IR` انگلیسی می‌ماندند. | به `sp-store-i18n` منتقل شد و ترجمهٔ فارسی گرفت: `StoreCopilotPage.php`، `woocommerce.js`. | ت۲ `store-proposal` |
| V16-5 | «On»، «Paused»، دو یادداشت پخش خودکار و جملهٔ آزمون صدا در Real-time voice رشتهٔ انگلیسی ثابت در JS بودند. | به `script_strings` منتقل شد و ترجمهٔ فارسی گرفت: `AdminMenu.php`، `answer-voice.js`. | ت۱ `answers-realtimevoice` |
| V16-6 | در پیشنهاد Store Copilot مقدارهای key/value در یک ستون نبودند. مقدار ردیف‌های قفل‌دار وسط ردیف می‌افتاد و بقیه به انتها می‌چسبیدند. آیکون قفل هم نبود. | ستون کلید ثابت 144px (در گوشی 112px)، مقدار از آغاز، یادداشت قفل در انتها با آیکون قفل: `.sp-store-proposal__fields` در `aisaautopilot-ui.css`. | ت۲ `store-proposal` پیش و پس از رفع |
| V16-7 | پس از تأیید یا خطا، همان پیام هم در خط وضعیت کنار دکمه و هم در جعبهٔ نتیجه تکرار می‌شد. | جعبهٔ نتیجه تنها live region است و خط وضعیت خالی می‌ماند: `woocommerce.js` (موفقیت، خطای plan، خطای execute و انقضا). | ت۲ `store-created` |
| V16-8 | در نمودار «Last 7 days» صفحهٔ Usage، ستون با ارتفاع 100% برچسب روز را پایین‌تر می‌راند و می‌برید. | برچسب `flex: none` و ستون قابل جمع‌شدن شد (به‌جز ستون صفر): `aisaautopilot-ui.css`. | ت۲ `usage-near` پیش و پس از رفع |

نقص‌های تصویری دیگر در همین بازه در T17 رفع و در `ACCESSIBILITY_Q4_3_REPORT_FA.md` ثبت شدند (A17-1 تا A17-10)، از جمله کنتراست `--sp-faint`، focus فیلدها، و متن بریده در Advanced و Setup.

یک اصلاح موقت در این نوبت برگشت داده شد: کارت خالی «Recent drafts» در Content Studio عیناً مانند حالت «No drafts yet» موکاپ است، پس پنهان کردن آن نقص را رفع نمی‌کرد و خود یک اختلاف تازه می‌ساخت.

## ۳. اختلاف‌های عمدی تازه

این هشت ردیف به جدول §۳ در `MOCKUP_FEATURE_GAP_REPORT_FA.md` هم افزوده شدند.

| # | اختلاف | مرجع | دلیل |
|---|---|---|---|
| X-06 | مرحلهٔ ۳ Setup کارت Model را پیش از بررسی اتصال هم نشان می‌دهد و فیلد «Model name» و دکمهٔ «Refresh from provider» دارد. | `SettingsPage.php`، `setup-wizard.js` | درخواست آزمون با همان مدل انتخاب‌شده فرستاده می‌شود، پس مدل باید پیش از آزمون انتخاب‌پذیر باشد. شناسهٔ مدل سفارشی هم فقط با فیلد آزاد ممکن است. |
| X-07 | در شکست، checklist دقیقاً مرحله‌ای را که شکست خورده «failed» نشان می‌دهد. در فریم «Key rejected (401)» موکاپ هر چهار مرحله تیک خورده‌اند. | `setup-wizard.js` (`FAILED_STAGE`) | تیک خوردن همهٔ مراحل در کنار «کلید رد شد» ادعای نادرست است. |
| X-08 | «No models returned» با لحن موفق («Connected») و دکمهٔ Next فعال نمایش داده می‌شود. موکاپ هشدار می‌دهد و Next را می‌بندد. | `setup-wizard.js` (`refreshModels`) | درخواست آزمون با مدل انتخاب‌شده موفق بوده، پس بستن مسیر مانعی بی‌دلیل است. راهنمای «How to fix it» همچنان نشان داده می‌شود. |
| X-09 | Health پس از اجرا بنر خلاصه‌ای که علت ریشه را حدس بزند (مثلاً «کلید سالم است؛ مشکل فایروال است») ندارد. هر ردیف وضعیت و جزئیات خودش را از گزارش واقعی دارد. | `connections.js`، `HealthDiagnostics` | گزارش diagnostics علت ریشه برنمی‌گرداند. بنری که آن را حدس بزند متن ساختگی است. |
| X-10 | نتیجهٔ ساخت پیش‌نویس در Store Copilot یک ردیف است: پیام سرور و «Review product draft». دکمهٔ «Propose another» جداگانه ندارد. | `woocommerce.js` (`showResult`) | composer همان بالا با «Write proposal» آماده است. پیوند مستقیم به ویرایشگر همان پیش‌نویس از «Open it in Products» دقیق‌تر است. |
| X-11 | Usage & Cost، Health و Activity Log زیر نوار tab «Overview» هستند و منوی وردپرس «Overview» را برجسته می‌کند. موکاپ برای هر کدام آیتم منوی جدا دارد. | S6 (بازآرایی IA)، `AdminMenu.php` | تصمیم بستهٔ S6 برای کوتاه کردن منو. |
| X-12 | دکمهٔ «Widget settings» در سربرگ Answer Desk به tab Widget می‌رود. موکاپ یک modal چهار-کلیدی باز می‌کند. | `AnswerDeskWidgetPage.php`، B-06 | tab Widget سفارشی‌ساز کامل با پیش‌نمایش است. یک modal ناقص کنار آن دو منبع حقیقت می‌ساخت. |
| X-13 | در Real-time voice، کلید «Speak replies aloud» و یادداشتش پیش از روشن شدن «transcribe + speak» غیرفعال می‌ماند. | `answer-voice.js` | پخش پاسخ بدون ورودی صوتی روشن معنایی ندارد. پیام دلیل را می‌گوید. |

## ۴. حکم هر حالت

راهنمای ستون حکم:

- **م**: منطبق.
- **ع**: اختلاف عمدی.
- **ب**: بدون backend (قفل صادقانه یا ناموجود).
- **خ**: بیرون از محصول (فقط در موکاپ).
- **ر**: نقص رفع‌شده.

### Overview (5)

| حالت | حکم | ارجاع | شاهد و یادداشت |
|---|---|---|---|
| home-default | م | A-06 (آمار) | ت۲ `home-default` با fixture مصرف: کارت‌های «Needs you» و «Running now» از دادهٔ واقعی. مبلغ دلاری به توکن تبدیل شده (A-16). |
| home-paused | م | — | ت۱ `home` (این سایت در سقف است): chip و بنر توقف. |
| home-busy | م | — | کد: نوار کار مشترک `Ui::job_bar` و `shell.js` هنگام ایندکس. Pause و Stop همان کنترل‌های واقعی صف batch هستند. |
| home-empty | م | — | کد: کارت‌های راه‌اندازی تا اتصال ارائه‌دهنده. در T15 با سایت بدون کلید دیده شد. |
| home-error | م | X-09 | کد: بنر خطای ارائه‌دهنده از آخرین وضعیت ذخیره‌شده. حدس علت ندارد. |

### Setup (21)

| حالت | حکم | ارجاع | شاهد و یادداشت |
|---|---|---|---|
| setup-1-default | م | — | ت۱ `setup`: دو کارت محل کلید. |
| setup-1-legacy | م | B-09 | کد: کارت WordPress Connector روی وردپرس پیش از 7.0 با `is-disabled` و متن نیاز نسخه. |
| setup-1-shared | م | B-09 | کد: حالت `wp_connectors` با یادداشت «کلید در Settings → Connectors». |
| setup-2-default | م | — | ت۲ مسیر `setup-3-*` از این مرحله می‌گذرد: شبکهٔ ارائه‌دهنده‌های محبوب. |
| setup-2-all | م | — | کد: «See all N services» در `filterProviders`. |
| setup-2-none | م | — | کد: «No service called …» با `data-sp-provider-empty`. |
| setup-2-picked | م | — | کد: کارت انتخاب‌شده با `is-selected` و `aria-pressed`. |
| setup-3-default | ع | X-06 | ت۲ `setup-3-default`: کارت Model پیش از آزمون دیده می‌شود. |
| setup-3-checking | م | X-01 | ت۲ `setup-3-checking`: جعبهٔ busy و checklist در حال اجرا. |
| setup-3-invalid | ع | X-07 | ت۲ `setup-3-invalid`: متن و دکمه‌ها منطبق. مرحلهٔ دوم failed. |
| setup-3-nocredit | ع | X-07 | ت۲ `setup-3-nocredit`: لحن هشدار، «Check again» و «Pick another service». |
| setup-3-ratelimit | ع | X-07 | ت۲ `setup-3-ratelimit`: لحن هشدار و Retry. |
| setup-3-network | ع | X-07 | ت۲ `setup-3-network`: Retry و «Run the full network test». |
| setup-3-notfound | ع | X-07 | ت۲ `setup-3-notfound`: «Edit the endpoint» و «Type a model name». |
| setup-3-ok | ع | X-01، X-06 | ت۲ `setup-3-ok`: «Connected» با شمار مدل. ترتیب مراحل از درخواست‌های واقعی. |
| setup-3-ok-advanced | م | A-01 | کد: بخش Advanced با Reply length قفل صادقانه و بدون timeout. |
| setup-3-nomodels | ع | X-08 | ت۲ `setup-3-nomodels` |
| setup-4-default | م | — | کد: کارت‌های Integrations در ویزارد، همان داده‌های صفحهٔ Integrations. |
| setup-4-alloff | م | — | کد: همهٔ کلیدها خاموش با یادداشت «می‌توانید بعداً روشن کنید». |
| setup-4-bare | م | — | کد: بدون WooCommerce و EFB، کارت‌های آن دو «Not installed». |
| setup-5-done | م | — | کد: پنل `data-sp-setup-complete` پس از ذخیرهٔ موفق. |
| setup-3 (مسیر بازگشت به سایت پیکربندی‌شده) | — | — | در موکاپ حالتی ندارد و شمرده نشده. ویزارد از مرحلهٔ ۱ باز می‌شود و `data-saved` فقط پس از POST ست می‌شود. رفتار عمدی است، نقص نیست. |

### Integrations (5)

| حالت | حکم | ارجاع | شاهد و یادداشت |
|---|---|---|---|
| integrations-default | م | — | ت۱ `integrations`: سه گروه کارت، کلید و «Configure». |
| integrations-alloff | م | — | کد: کارت‌ها با وضعیت Off از تنظیمات واقعی. |
| integrations-bare | م | — | کد: WooCommerce و EFB نصب‌نشده، «Install Easy Form Builder». |
| integrations-configure-drawer | م | — | کد: `action => dialog` در `IntegrationsPage.php` با modal مشترک روی `.sp-app`. تنظیم‌های بدون backend صادقانه قفل‌اند. |
| integrations-conflict | ب | A-18 | بنر «Another AI plugin is active» ساخته نشده (T31، S5-A). |

### Site Knowledge (13)

| حالت | حکم | ارجاع | شاهد و یادداشت |
|---|---|---|---|
| knowledge-overview-default | ع | A-04 | ت۲ `knowledge-overview-gaps`: «What has been read»، منبع‌ها، پرسش‌های بی‌پاسخ با «Write it» و «Answer it»، و Saved answers. منبع PDF و URL ندارد. |
| knowledge-overview-big | م | — | کد: همان کارت‌ها با شمار بزرگ و قالب «x of y». |
| knowledge-overview-busy | م | — | کد: نوار کار ایندکس (مانند `knowledge-jobbar`). |
| knowledge-overview-empty | م | — | کد: `#sp-kb-gaps-empty` و «0 of N». در T15 دیده شد. |
| knowledge-overview-failed | ع | A-03 | کد: خطای batch با Retry همان اجرا. «Skip» و صف پایدار ندارد. |
| knowledge-overview-stale | م | — | کد: «N changed since the last index» از `index_freshness`. |
| knowledge-jobbar | م | — | کد: `Ui::job_bar` و `shell.js`. |
| knowledge-collections-default | ع | A-02 | ت۱ `knowledge-collections`: فقط‌خواندنی با «Managed by index scope». |
| knowledge-collections-empty | م | — | کد: حالت خالی همان کارت. |
| knowledge-indexingqueue-default | ع | A-03 | ت۱ `knowledge-indexingqueue`: اجرای batch در همان درخواست. |
| knowledge-indexingqueue-empty | م | — | ت۱ `knowledge-indexingqueue` (این سایت صف ندارد). |
| knowledge-syncsources-default | ع | A-04 | ت۱ `knowledge-syncsources`: فقط post typeهای وردپرس. |
| knowledge-syncsources-empty | م | — | کد: حالت خالی post type. |
| knowledge (کارت Saved answers) | — | A-05 | بخشی از `knowledge-overview-default`. حالت جدا نیست و شمرده نشده. |

### Answer Desk (26)

سناریوی موکاپ (default، empty، live، escalated، noknowledge و offline) chip سربرگ، ردیف‌های Conversations و بنر هر چهار tab را عوض می‌کند. حکم هر tab در هر سناریو در جدول زیر است.

| حالت | حکم | ارجاع | شاهد و یادداشت |
|---|---|---|---|
| answers-conversations-default | ع | A-06 | ت۱ `answers-conversations`: گفتگوهای ذخیره‌شده و پیش‌نمایش. آمار «answered without you» و مکان بازدیدکننده ندارد. |
| answers-conversations-empty | م | — | ت۱ (Answer Desk این سایت خاموش است): «No visitor widget is live yet». |
| answers-conversations-live | ب | A-06 | «Answering now» و نشانگر نوشتن زنده نیازمند push بلادرنگ است که وجود ندارد. |
| answers-conversations-escalated | م | B-04 | کد: نوار «Open inbox» و نشان شمار «new» روی منو. |
| answers-conversations-noknowledge | م | — | کد: بنر ایندکس نشده با پیوند Site Knowledge. |
| answers-conversations-offline | م | — | کد: chip خطای ارائه‌دهنده در سربرگ مشترک. |
| answers-widget-default | ع | A-19، B-06 | ت۱ `answers-widget`: پیش‌نمایش و سفارشی‌ساز. «Match my theme colours» قفل «Manual palette». |
| answers-widget-empty | م | — | ت۱ `answers-widget` |
| answers-widget-live | خ | — | فقط chip سربرگ را عوض می‌کند و tab Widget در افزونه تغییری ندارد. |
| answers-widget-escalated | خ | — | همان. |
| answers-widget-noknowledge | م | — | کد: همان بنر ایندکس. |
| answers-widget-offline | م | — | کد: chip خطای ارائه‌دهنده. |
| answers-widget-modal | ع | X-12 | modal «Chat widget» به tab Widget تبدیل شده. |
| answers-humanhandoff-default | ع | A-07، A-08 | ت۱ `answers-humanhandoff`: Email، Telegram و SMS فعال. WhatsApp، Slack، Discord و Webhook قفل Phase 2. تحویل fan-out. |
| answers-humanhandoff-empty | م | — | کد: بدون گیرنده، یادداشت «Add a recipient». |
| answers-humanhandoff-live | خ | — | فقط chip سربرگ. |
| answers-humanhandoff-escalated | م | B-04 | کد: نوار «Open inbox» روی tab. |
| answers-humanhandoff-noknowledge | م | — | کد |
| answers-humanhandoff-offline | م | — | کد |
| answers-handoff-edit-open | م | — | کد: ویرایش کارت کانال در جا (`answer-handoff.js`) با آزمون تحویل mock‌شده. |
| answers-realtimevoice-default | ب | A-09، W-01 | ت۱ `answers-realtimevoice`: فقط transcribe + speak مرورگر. realtime، barge-in و صداهای نام‌دار قفل‌اند. X-13 |
| answers-realtimevoice-empty | م | — | ت۱ (صدا خاموش): «Paused» و کلیدهای وابسته غیرفعال. |
| answers-realtimevoice-live | خ | — | فقط chip سربرگ. |
| answers-realtimevoice-escalated | خ | — | همان. |
| answers-realtimevoice-noknowledge | م | — | کد |
| answers-realtimevoice-offline | م | — | کد |

### Store Copilot (24)

| حالت | حکم | ارجاع | شاهد و یادداشت |
|---|---|---|---|
| store-newproduct-default | م | — | ت۱ `store-newproduct` |
| store-newproduct-empty | م | — | کد: بدون محصول، نمونه‌های «Try one of these». |
| store-newproduct-validation | م | — | ت۲ `store-newproduct-validation`: «Describe the product you want to draft.» بدون درخواست شبکه. |
| store-newproduct-failed | م | — | ت۲ `store-result-failed`: جعبهٔ خطا با پیام سرور. |
| store-newproduct-claimed | م | — | کد: `ActionStore` claim یک‌باره. تأیید دوباره خطای «already used» می‌دهد. |
| store-newproduct-noperm | م | — | ت۲ `store-result-noperm`: 403 با پیام وردپرس. تب بدون `manage_woocommerce` قفل می‌شود. |
| store-newproduct-noprovider | م | — | ت۲ `store-result-noprovider` |
| store-newproduct-off | م | — | کد: `data-sp-store-enabled="0"` دکمهٔ پیشنهاد را قفل می‌کند و دلیل را می‌گوید. |
| store-proposal | ر | V16-4، V16-6 | ت۲ `store-proposal`: پس از رفع، ستون‌ها و قفل‌ها منطبق‌اند. عنوان‌ها «-» به‌جای «—» دارند که اختلاف جزئی نگارشی است. |
| store-created | ر | V16-7، X-10 | ت۲ `store-created` |
| store-result-claimed | م | — | کد: مانند `store-newproduct-claimed`. |
| store-result-failed | م | V16-7 | ت۲ `store-result-failed` |
| store-result-noperm | م | — | ت۲ `store-result-noperm` |
| store-result-noprovider | م | — | ت۲ `store-result-noprovider` |
| store-result-validation | م | — | کد: خطای اعتبارسنجی plan (`prompt_too_long`) در جعبهٔ نتیجه. |
| store-whatitcando-default | ب | A-10 | ت۱ `store-whatitcando`: توانایی‌های Phase 2 قفل صادقانه. |
| store-whatitcando-empty | م | — | کد |
| store-whatitcando-off | م | — | کد |
| store-products-default | ع | A-12 | ت۱ `store-products`: جست‌وجوی محلی. «Smart product search» برای بازدیدکننده قفل. |
| store-products-empty | م | — | کد |
| store-products-off | م | — | کد |
| store-orders-default | ب | A-11 | ت۱ `store-orders`: خواندن وضعیت سفارش برای بازدیدکننده ساخته نشده (D-18). |
| store-orders-empty | م | — | کد |
| store-orders-off | م | — | کد |
| store (Phase 2 انتشار) | ب | A-10 | بخشی از `whatitcando`. حالت جدا نیست و شمرده نشده. |
| store (chip سربرگ) | — | — | وضعیت واقعی سایت. |

### Content Studio (9)

| حالت | حکم | ارجاع | شاهد و یادداشت |
|---|---|---|---|
| studio-write-default | ع | A-14 | ت۲ `studio-write-drafts`: composer و «Recent drafts». لحن Formal و Plain قفل‌اند. |
| studio-write-empty | م | — | ت۱ `studio-write`: «Nothing written yet» و کارت خالی «Recent drafts»، عیناً مانند موکاپ. |
| studio-write-busy | م | — | ت۲ `studio-write-busy`: «Writing...» و دکمهٔ قفل‌شده. |
| studio-write-review | ع | A-13 | موکاپ صف «Three await approval» را نشان می‌دهد. افزونه صف انتشار ندارد: پیش‌نویس در وردپرس ساخته می‌شود و بازبینی پیش از ساخت است (ت۲ `studio-write-review`). |
| studio-write-autopublish | ب | A-13 | «Published automatically» عمداً ساخته نشده (قرارداد approval-first). |
| studio-templates-default | ع | A-15 | ت۱ `studio-templates`: شمار استفاده ندارد. |
| studio-templates-empty | م | — | کد |
| studio-history-default | م | — | ت۲ `studio-history-default` با fixture. |
| studio-history-empty | م | — | ت۱ `studio-history`: «Nothing written yet» و Export CSV. |

### Usage & Cost (6)

| حالت | حکم | ارجاع | شاهد و یادداشت |
|---|---|---|---|
| usage-default | ب | A-16 | ت۲ `usage-near` با fixture: واحد توکن به‌جای دلار. |
| usage-near | ر | V16-8، A-16 | ت۲ `usage-near` پس از رفع نمودار. |
| usage-over | م | A-16 | ت۱ `usage` (این سایت در سقف است). |
| usage-free | خ | — | «Free tier» مفهومی از ارائه‌دهنده است که افزونه نمی‌بیند. |
| usage-nodata | م | B-15 | کد: پیام صریح نمودار خالی. |
| usage-cap-editor | ب | A-16 | ت۲ `usage-cap-editor`: فیلد عددی سقف توکن به‌جای slider دلاری. ایمیل هنگام سقف ندارد. |

### Health (5)

| حالت | حکم | ارجاع | شاهد و یادداشت |
|---|---|---|---|
| health-default | ع | X-11 | ت۱ `health`: یک کارت هشت‌ردیفی با ترتیب موکاپ، زیر نوار tab Overview. |
| health-busy | م | — | ت۲ `health-busy`: queued و checking، دکمهٔ قفل و `aria-busy`. |
| health-error | ع | X-09 | ت۲ `health-error`: ردیف‌ها منطبق. بنر خلاصه ندارد. |
| health-expired | ع | X-09 | کد: ردیف API key «Rejected» با جزئیات. بنر «Paste a new key» ندارد. |
| health-cron | ع | X-09 | کد: ردیف WP-Cron با جزئیات واقعی. |

### Activity Log (4)

| حالت | حکم | ارجاع | شاهد و یادداشت |
|---|---|---|---|
| logs-default | ع | A-17، X-11 | ت۲ `logs-default`: خط زمانی روزانه و Export CSV. Approve و Undo ندارد. |
| logs-empty | م | — | ت۲ `logs-empty`: «No changes yet». ردیف «Today 7 changes» زیر حالت خالی موکاپ خطای خود موکاپ است. |
| logs-waiting | ب | A-17 | «Waiting for you» با Approve all نیازمند صف تأیید است. |
| logs-automatic | ب | A-17 | Undo و Restore ساخته نشده. |

### Form Composer / Form Studio (6)

| حالت | حکم | ارجاع | شاهد و یادداشت |
|---|---|---|---|
| composer-welcome | م | — | FS و `plugin/form-studio/` در دو locale. |
| composer-selecting | ب | C-01 | انتخاب فیلد روی بوم ساخته نشده (X-04). |
| composer-form | ع | C-02 | پیش‌نمایش طرح با tabها به‌جای فرم زنده روی بوم. |
| composer-needemail | م | — | کد: «Set the notification email before a plan can be built.» (`CopilotPage.php`). |
| composer-created | م | — | FS: «Explicit approval produces the real result controls». |
| composer-advanced | ب | C-03 | کارت «That needs Easy Form Builder» ساخته نشده. |

### EFB Copilot (6)

| حالت | حکم | ارجاع | شاهد و یادداشت |
|---|---|---|---|
| efb-fab | م | — | E: FAB در ویرایشگر EFB، LTR و RTL. |
| efb-welcome | م | — | E |
| efb-selected | م | — | E: چیپ فیلد انتخاب‌شده از `valj_efb`. |
| efb-help | م | — | E |
| efb-pro | م | — | E: CTA نسخهٔ Pro رهگیری می‌شود و ناوبری ناخواسته ندارد. |
| efb-voice | ب | E-01 | صفحهٔ تماس زنده ساخته نشده. فقط دیکتهٔ مرورگر. |

### ویجت بازدیدکننده (5)

| حالت | حکم | ارجاع | شاهد و یادداشت |
|---|---|---|---|
| widget-launcher | ع | X-02 | W: جای ویجت در RTL همان جای انتخابی مدیر است. |
| widget-panel | ع | X-03 | W: متن «Answers come from this site and can be wrong.» |
| widget-fullscreen | م | — | W: `.spw-panel--full` با ستون پرسش‌های پرتکرار. |
| widget-handoff | ع | W-02 | W: یک دکمهٔ ارسال به همهٔ کانال‌های فعال. |
| widget-voice | ب | W-01 | overlay صوتی با orb ساخته نشده. |

### شمارش

- **ردیف‌های توضیحی:** چهار ردیف با برچسب «شمرده نشده» حالت موکاپ نیستند و در شمار نمی‌آیند: مسیر بازگشت به Setup، کارت Saved answers، Phase 2 انتشار و chip سربرگ Store.
- **جمع بخش‌ها:** 5 + 21 + 5 + 13 + 26 + 24 + 9 + 6 + 5 + 4 + 6 + 6 + 5 = **135**. پوشش را اسکریپت با نام فایل‌های موکاپ سنجید: 135 حالت، هر کدام دقیقاً یک ردیف.
- **حکم «ر»:** سه حالت (`store-proposal`، `store-created`، `usage-near`). شمار هشت‌تایی V16 شمار نقص‌هاست، نه حالت‌ها.

## ۵. دروازه‌ها

- a11y matrix مدیریتی پس از پاک‌سازی عرض‌ها (`run5-t16`): 1528 PASS / 0 FAIL در `en_US` و `fa_IR`.
- smoke و integrationهای مرتبط پس از آخرین ویرایش: جدول baseline نقشهٔ راه و `handoff.md`.
