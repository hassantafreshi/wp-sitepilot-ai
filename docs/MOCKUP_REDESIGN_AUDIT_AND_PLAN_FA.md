# گزارش مهندسی موکاپ، نقشهٔ UI و برنامهٔ بازطراحی

تاریخ ارزیابی: ۳۰ اوت ۲۰۲۶  
وضعیت: **baseline فنی، shell/navigation، Overview، Setup & Connection و Integrations تثبیت شده‌اند؛ اجرای صفحه‌به‌صفحهٔ موکاپ در جریان است.**

## نتیجهٔ کوتاه

منبع UI/UX باید چهار موکاپ hi-fi جدید باشد، نه طراحی‌های قدیمی موجود در `mockups/uploads/`. ساختار منوی نهایی افزونه باید **دقیقاً ۱۰ صفحهٔ قابل‌مشاهده** داشته باشد. صفحات و مسیرهای قدیمی حذف نمی‌شوند؛ فقط به alias یا مسیرهای عمیق تبدیل می‌شوند تا bookmark، REST و قابلیت‌های فعلی نشکنند.

درخت فعلی افزونه از قبل تغییرات گسترده و ثبت‌نشدهٔ مرتبط با بازطراحی دارد. این تغییرات در این ارزیابی دست‌نخورده باقی ماندند. PHPCS کامل و اتصال WordPress محلی اکنون اصلاح و تأیید شده‌اند؛ با این حال معیار «تطبیق قطعی با موکاپ» تا اجرای QA بصری مرورگر، RTL و responsive برای همهٔ صفحه‌ها کامل نیست.

## پیشرفت پس از ارزیابی — ۲۰۲۶-۰۸-۳۰

- PHPCS کامل: **124/124 فایل، ۰ error و ۰ warning**؛ PHPStan: **۰ error**؛ PHPUnit: **131 test / 429 assertion**.
- نصب محلی WordPress با junction به همین source متصل است و افزونهٔ قدیمیِ تکراری فقط deactivate شده است؛ حذف فیزیکی انجام نشده است.
- منوی visible دقیقاً ۱۰ آیتم موکاپ دارد، شامل Store Copilot در حالت نبود WooCommerce؛ routeهای قدیمی alias و پنهان مانده‌اند.
- تست یکپارچهٔ WordPress محلی: **45 checks, 0 failed**. browser smoke واقعی اکنون همهٔ ۱۸ route/alias را با HTTP 200، shell حاضر و بدون خطای console/pageerror تأیید کرده است؛ QA بصریِ مقایسه‌ای، RTL و responsive همچنان گیت بازِ مرحله‌های صفحه‌ای و نهایی‌اند.
- Site Knowledge مطابق handoff تثبیت شد: دقیقاً چهار tab، empty/populated Overview با coverage/source/gap واقعی، Collections و Sources مشتق از backend و Queue صادقانهٔ browser-session-only. lifecycle cleanup با auto-index خاموش نیز اکنون تضمین می‌شود؛ QA اختصاصی desktop/RTL/390px، Escape/focus picker و console/pageerror با browser واقعی پاس شدند.
- Answer Desk در A2.5.1 قرارداد runtime واحد گرفت و A2.5.2 تا A2.5.5 هر چهار tab Conversations، Widget، Human handoff و Real-time voice را با state/data/persistence واقعی و QA browser تکمیل کرد: Integration gate و Widget visibility با predicate مشترک عمل می‌کنند، فقط configurable widget (citation، opt-in logging، handoff و voice input) برای بازدیدکننده render می‌شود و handoff ناقص offer/button کاذب ندارد. Widget compact UI با partial REST save فقط visibility/accent را تغییر می‌دهد؛ Handoff روی route canonical با channel-scoped REST، credential encrypted و lock صادقانهٔ transportهای بدون backend است؛ Voice نیز route canonical و partial REST دارد، اما streaming/barge-in بدون backend قفل است و مسیر واقعی transcribe + browser speech را نشان می‌دهد.
- Overview مطابق ساختار موکاپ تثبیت شد: ۴ metric، Needs you و ۶ integration در Running now. منبع metricها فقط دادهٔ قابل‌ردیابی افزونه است (turnهای مکالمهٔ ثبت‌شده و draftهای markerدار) و مقدار «هزینهٔ دلاری» جعل نمی‌شود؛ token واحد اصلی باقی می‌ماند. desktop، RTL و ۳۹۰px با browser واقعی و screenshot بازبینی شده‌اند.
- Setup & Connection مطابق wizard چهارمرحله‌ای موکاپ تثبیت شد: ۱۵ سرویس قابل‌جست‌وجو، انتخاب مدل/refresh، outcomeهای واقعی تست provider و integration toggleهای واقعی. ذخیره فقط بعد از تست موفقِ همان پیکربندی ممکن است؛ receipt موقت، user-bound، HMAC شده، ۱۵ دقیقه‌ای و one-time است و secret خام ذخیره یا برگردانده نمی‌شود. desktop، RTL و ۳۹۰px با browser واقعی و screenshot بازبینی شده‌اند.
- Integrations مطابق handoff تثبیت شد: چهار گروه، دقیقاً شش کارت، `Always on`/`On`/`Off`/`Needs dependency`، CTAهای نصب یا مسیر درست و dialogهای Configure فقط با کنترل‌های persist‌شونده. Form Composer، Content Studio و EFB در REST و UI با همان settingها گیت شدند؛ Site Knowledge فقط auto-index را تغییر می‌دهد و هرگز به‌دروغ خاموش نمایش داده نمی‌شود. desktop، RTL، ۳۹۰px، Escape/focus dialog و console/pageerror با browser واقعی پاس شدند.

برآورد از وضعیت فعلی برای یک توسعه‌دهندهٔ مسلط به WordPress:

| خروجی | زمان کاری برآوردی |
|---|---:|
| UI/UX جدید با ۱۰ صفحه، سه سطح تخصصی و حالت‌های اصلی موکاپ | **۱۰ تا ۱۴ روز کاری** |
| همان خروجی، با QA مرورگر، RTL، accessibility و رفع regression تا سطح تحویل | در همین بازه، به شرط آماده‌بودن WordPress/WooCommerce/EFB و کلید آزمایشی |
| قابلیت‌های واقعاً جدیدی که موکاپ نمایش می‌دهد اما backend ندارد (streaming voice، transportهای handoff، queue پایدار و مانند آن) | **۵ تا ۸ روز کاری افزوده**؛ جمعاً **۱۵ تا ۲۲ روز کاری** |

این زمان، تخمین تحویل قابل‌آزمون است؛ نه صرفاً تبدیل CSS. UI نباید قابلیت غیرواقعی را شبیه فعال نشان دهد.

## ۱. منبع حقیقت و محدودهٔ درست

### موکاپ‌های معتبر

چهار فایل زیر منبع حقیقت بصری و تعاملی هستند:

1. `mockups/design_handoff_admin_redesign/AutoPilot Admin.dc.html`
2. `mockups/design_handoff_admin_redesign/Front Chat Widget.dc.html`
3. `mockups/design_handoff_admin_redesign/Form Composer.dc.html`
4. `mockups/design_handoff_admin_redesign/EFB Builder Chat.dc.html`

نسخه‌های هم‌نام در ریشهٔ `mockups/` از نظر SHA-256 با این چهار فایل یکسان‌اند. برای جلوگیری از دوگانگی، پوشهٔ `design_handoff_admin_redesign` مرجع مستندات اجرایی در نظر گرفته می‌شود.

فایل‌های `mockups/uploads/ai-settings.html`، `mockups/uploads/ai-assistant.html` و snapshot افزونه در `mockups/uploads/ai-site-assistant-autopilot/` طرح و کد قدیمی‌اند؛ استفاده از آن‌ها به‌عنوان مرجع UI جدید ممنوع است.

### قراردادهای فنی غیرقابل‌تغییر

- namespace `AISAAutoPilot`، پیشوندهای `aisaautopilot_*`، slugها، option keyها و REST namespace `aisaautopilot/v1` تغییر نمی‌کنند.
- قرارداد Phase 1 ووکامرس ثابت است: فقط product draft. انتشار، ویرایش، bulk edit، mutation سفارش و refund هرگز با کنترل فعال جدید عرضه نمی‌شوند.
- کلیدها و secretها نباید به DOM، پاسخ REST یا log برگردند.
- هر متن جدید i18n دارد و CSS جدید با logical propertyهای RTL-safe نوشته می‌شود.
- کنترل بدون backend واقعی باید disabled/locked یا با توضیح صادقانهٔ «Phase 2» باشد؛ دادهٔ نمونه در production جایگزین دادهٔ واقعی نمی‌شود.

## ۲. معماری اطلاعات نهایی — دقیقاً ۱۰ صفحهٔ قابل‌مشاهده

| # | صفحهٔ visible مطابق موکاپ | پیاده‌سازی/slug فعلی یا مقصد | زیرسطح‌های لازم | وضعیت ارزیابی |
|---:|---|---|---|---|
| ۱ | Overview | `HomePage` / `aisaautopilot-home` | کارت‌های واقعی، Needs you، Running now، empty/error/paused | تکمیل؛ دادهٔ واقعی، RTL/390px و browser QA پاس |
| ۲ | Setup & Connection | `SettingsPage` / `aisaautopilot-setup` | wizard چهارمرحله‌ای؛ ۱۵ provider؛ ۸ outcome اتصال | تکمیل؛ تست server-side، desktop، RTL و 390px پاس |
| ۳ | Integrations | `IntegrationsPage` / `aisaautopilot-integrations` | گروه‌ها، gate و config drawer واقعی | تکمیل؛ ۴ گروه/۶ کارت، persistence، RTL/390px و browser QA پاس |
| ۴ | Site Knowledge | `KnowledgeBasePage` / `aisaautopilot-kb` | Overview، Collections، Indexing Queue، Sync Sources | تکمیل UI Phase 1؛ دادهٔ واقعی، aliasهای Ask/Entries، RTL/390px و browser QA پاس |
| ۵ | Answer Desk | entry: `aisaautopilot-conversations` | Conversations، Widget، Human handoff، Real-time voice | چهار tab canonical، data/persistence و QA صفحه‌ای تکمیل؛ inbox advanced alias است و streaming realtime همچنان locked/Phase 2 است |
| ۶ | Store Copilot | `StoreCopilotPage` / `aisaautopilot-wc-dashboard` | New product، What it can do، Products، Orders | تکمیل A2.6؛ در نبود WooCommerce visible و locked می‌ماند؛ QA واقعی desktop/RTL/390px پاس |
| ۷ | Content Studio | `CopilotPage` / `aisaautopilot-form-builder` | Write، Templates، History | Form Composer مسیر تخصصی جداست؛ به §۴ مراجعه شود |
| ۸ | Usage & Cost | `UsagePage` / `aisaautopilot-usage` | cap، 7-day usage، feature split، empty/near/over | هزینهٔ دلاری بدون price map نباید جعل شود |
| ۹ | Health | `ConnectionsPage` / `aisaautopilot-connections` | هشت health check متوالی و نتیجهٔ واقعی | QA عملی لازم است |
| ۱۰ | Activity Log | `LogsPage` / `aisaautopilot-logs` | timeline یکپارچه، approval، export و empty state | ادغام history باید تأیید شود |

### قانون شمارش صفحات

- منوی اصلی باید همیشه همین ۱۰ آیتم را با همین ترتیب نمایش دهد.
- `AdminMenu.php` اکنون Store Copilot را بدون شرطِ WooCommerce در منو نگه می‌دارد؛ در نبود WooCommerce همان صفحه state «WooCommerce is not active» و کنترل‌های غیرعملیاتی نشان می‌دهد.
- aliasهای قدیمی (`settings`، `forms`، `history`، `chat`، `efb`، `woocommerce`، `widget` و `handoffs`) باید حفظ ولی از منوی visible پنهان بمانند.
- سه سطح تخصصی زیر، صفحهٔ اضافه در منو نیستند: Form Composer، EFB copilot داخل canvas و front-end Chat Widget.

## ۳. خلاصهٔ مهندسی موکاپ‌ها

### AutoPilot Admin

- shell: header سفید، chip اتصال provider، action متناسب با صفحه، sub-tab زیرخطی، banner، toast، job bar پایدار، config drawer و quick-preview widget.
- design tokenها: Plus Jakarta Sans، JetBrains Mono برای دادهٔ فنی، navy `#202a8d`، coral `#ff455f`، کارت‌های تخت با radius حدود ۱۰–۱۲px و مرزهای نازک.
- Setup: Connection → AI service → Key & model → Integrations. مرحلهٔ key یک حالت اولیه، transition `checking` و نتیجه‌های Connected، 401، 402/no-credit، network/timeout، 404، 429 و no-models دارد؛ همه باید با response واقعی نمایش داده شوند. تنظیمات Custom endpoint و Azure deployment که موکاپ صریحاً رسم نکرده است، در Advanced/configuration حفظ می‌شود.
- Site Knowledge: coverage، collections، sourceهای واقعی و gapهای قابل-dismiss در UI Phase 1؛ صف پایدار با busy/failed/resume/retry/skip و bridge ساخت draft برای gapها به backend آینده نیاز دارد.
- Answer Desk: گفتگو، preview و تنظیم widget، handoff کانال‌به‌کانال، و تنظیمات voice. مکالمهٔ visitor، source و fallback انسانی جزو هسته‌اند.
- Store: flow امن prompt → plan → immutable preview با TTL → explicit approval → **Woo draft**. همهٔ قابلیت‌های Phase 2 locked باقی می‌مانند.
- Content Studio: Write، Templates، History و approval-first؛ هیچ draft نباید خودکار publish شود.
- Usage: layout موکاپ دلاری است، اما plugin فعلی usage توکنی دارد؛ تا زمان داشتن price map واقعی، token باید واحد اصلی باشد.
- Health: هشت check sequential با spinner و outcome واقعی.
- Activity Log: log عملیاتی و history approval در یک timeline قابل‌فیلتر.

### Front Chat Widget

launcher → panel docked → fullscreen، سوال‌های پیشنهادی، پاسخ sourced، کارت handoff و overlay صوتی full-screen با چهار phase: connecting/listening/thinking/speaking. LTR و RTL هر دو بخشی از پذیرش هستند.

### Form Composer

فرم native با پیش‌نیاز email delivery، ساخت فرم توسط AI، انتخاب title/field/button و ارسال آن به chat به‌شکل reference chip، review/approval، shortcode بعد از ایجاد و هدایت درخواست‌های advanced به EFB. سناریوهای welcome/form/selecting/advanced/no-email/created باید پوشش داده شوند.

### EFB Builder Chat

FAB جمع‌شونده، modal شناور، بازشدن خودکار با انتخاب field در canvas، banner «Working on …»، chat scoped، help غیرتغییردهنده، upsell برای Pro field بدون mutation، voice overlay و LTR/RTL. chrome خود EFB آبی `#0d6efd` می‌ماند؛ copilot navy است.

## ۴. قابلیت‌های فعلی که موکاپ برایشان UI صریح ندارد

این فهرست جلوی حذف قابلیت‌های مفید را می‌گیرد. هر مورد یا در سطح درست جذب می‌شود یا به‌وضوح پشت مسیر عمیق می‌ماند.

| قابلیت فعلی | وضعیت در موکاپ | تصمیم UI/UX پیشنهادی | شرط فنی |
|---|---|---|---|
| Admin AI Chat (`ChatPage` و `/chat/*`) | صفحهٔ مستقل ندارد و موکاپ Site Knowledge فقط ۴ tab دارد | Chat فقط از alias/deep link «Ask your KB» قابل‌دسترسی بماند؛ tab پنجم visible ساخته نشود | history/create/get/delete و voice حفظ شوند |
| جدول entryهای KB، search/filter/delete و index picker | در موکاپ UI صریح ندارد | از canonical چهار-tab پنهان و فقط در alias عمیق/مسیر عملیاتی حفظ شود | API list/delete و انتخاب type/category نباید حذف شوند |
| Native Forms list و submissions | فقط ضمنی است | از Form Composer مسیر «Your forms»/deep link، نه آیتم جدید منو | اگر entry store واقعی نداریم، صادقانه email-only نمایش داده شود |
| Form Composer native | در موکاپ جداگانه است، نه tab رسمی Content Studio | از CTA «Create a form» به سطح تخصصی موکاپ Form Composer هدایت شود؛ منوی اصلی ۱۰تایی نماند | `/forms/plan` و `/forms/execute` حفظ شوند |
| تنظیمات عمیق appearance widget | موکاپ خلاصه‌تر است | داخل Widget با accordion «More appearance settings» | تمام کنترل‌های قدیمی و preview باقی بمانند |
| privacy/logging مکالمه‌ها | در موکاپ صریح نیست | در بخش Behaviour widget، با notice حریم خصوصی | opt-in logging و inbox واقعی حفظ شود |
| تنظیمات پیشرفتهٔ Setup: delivery ایمیلی/test، تنظیمات کامل embedding (provider/base/model/key)، public assistant، مدل voice، حالت پیش‌فرض فرم و جدول WordPress Connectors | موکاپ برایشان UI صریح ندارد | در alias پنهان `aisaautopilot-settings` با عنوان Advanced Settings حفظ شده و از wizard لینک دارد | گزینه‌های قدیمی نباید هنگام ذخیرهٔ wizard حذف یا overwrite شوند |
| providerهای بیشتر از ۱۵ مورد موکاپ | موکاپ فقط نمونهٔ ۱۵تایی دارد | providerهای پشتیبانی‌شده پنهان نشوند؛ metadata عمومی داشته باشند | `/provider/models` و test واقعی |
| token budget در برابر هزینهٔ دلار | موکاپ دلار نشان می‌دهد | token-first؛ USD فقط با price map قابل‌ردیابی | هرگز رقم هزینهٔ ساختگی ندهید |
| EFB admin deep link | موکاپ آن را منوی جدا نمی‌داند | hidden alias و CTA مرتبط؛ سطح اصلی داخل EFB canvas است | EFB نباید در صورت عدم نصب، UI شکسته بدهد |
| Key vault، redaction، rate-limit، migration، VectorSearch، auto-index | سرویس‌های داخلی‌اند، نه feature صفحه‌ای | UI مستقل لازم ندارند | test و log امن لازم دارند |

## ۵. فاصله‌های واقعی با موکاپ و اولویت رفع

| اولویت | شکاف | علت و اقدام لازم |
|---|---|---|
| رفع‌شده | PHPCS کامل شکست می‌خورد | baseline شامل خطاها و هشدارهای formatting بود. اکنون PHPCS کامل روی ۱۱۶ فایل با **۰ error / ۰ warning** پاس می‌شود. |
| رفع‌شده | Store Copilot در WooCommerce غیرفعال ناپدید می‌شود | اکنون visible و locked است تا شمار منوی موکاپ همیشه ۱۰ بماند. |
| رفع‌شده برای A2.2 | Setup بدون اثبات واقعی اتصال می‌توانست ذخیره شود | ذخیرهٔ wizard اکنون receipt تستِ موفقِ دقیق، user-bound، HMAC، ۱۵ دقیقه‌ای و one-time می‌خواهد. تست ناموفق وضعیت تأییدشدهٔ قبلی را حذف نمی‌کند؛ test با key/base/model صریحاً خالی هم به تنظیم ذخیره‌شده fallback نمی‌کند. |
| رفع‌شده برای A2.3 | Integrations فرم/محتوا را ادغام و toggleهای بی‌اثر نشان می‌داد | اکنون ۴ گروه و ۶ کارت موکاپ، gateهای واقعی، dependency state، CTA صحیح، dialog accessible و persistence nonce-protected دارد. |
| رفع‌شده برای A2.4 | خاموش‌کردن auto-index، hookهای cleanup KB را هم خاموش می‌کرد | ContentHooks اکنون همیشه ثبت می‌شود؛ فقط schedule publish/update پشت setting است و unpublish/trash/delete در حالت off نیز KB را پاک می‌کند. |
| رفع‌شده برای A2.5.1 | toggle Integrations renderer بدون logging/handoff را فعال می‌کرد، در حالی‌که widget قابل‌تنظیم با خاموش‌بودن آن toggle visible می‌ماند | `public_assistant_enabled` و `widget.enabled` اکنون gate واحد دارند؛ renderer canonical configurable widget است و endpointهای public نیز همان gate را رعایت می‌کنند. handoff فقط با channel کامل‌شده offer می‌شود. |
| رفع‌شده برای A2.5.2 | Conversations موکاپ می‌توانست confidence/location/live-status یا source فرضی را نشان دهد و asset frontend widget settingهای persist‌شده را به fallback تبدیل کند | صفحه فقط چهار state و دادهٔ `VisitorConversationStore` را render می‌کند؛ citation واقعی و transcript محدود دارد. `enqueue_widget_assets()` اکنون settings را پیش از localization می‌خواند و integration test انتقال مقدارهای persist‌شده را پوشش می‌دهد. |
| رفع‌شده برای A2.5.3 | UI کوتاه Widget اگر full-form save می‌فرستاد، checkboxها/credentialهای خارج از موکاپ را reset می‌کرد و Widget alias از IA جدا می‌ماند | route canonical `conversations&tab=widget` و endpoint partial با nonce/capability فقط `enabled`/`color` را به‌روز می‌کند؛ دیگر capabilityها compatibility/locked گزارش شده‌اند و custom palette swatch را صادقانه قفل می‌کند. |
| رفع‌شده برای A2.5.5 | موکاپ streaming realtime، barge-in، voiceهای provider-fixed و language buttonهای ذخیره‌شونده را نمایش می‌داد، اما plugin فقط clip→STT و SpeechSynthesis browser داشت | route canonical `conversations&tab=voice` و endpoint partial فقط `voice_enabled`/`voice_auto` را می‌نویسد؛ realtime/barge-in قفل‌اند، Browser default/Auto-detect واقعی نمایش داده می‌شوند و test phrase local است. |
| P1 | کنترل Reply length موکاپ backend ندارد | کنترل در wizard disabled است و توضیح صادقانه دارد؛ تا افزودن option و اعمال واقعی در `ChatEngine`، نباید مقدار ساختگی ذخیره یا نمایش داده شود. |
| P1 | banner تداخل با AI plugin دیگر در موکاپ detector واقعی ندارد | تا داشتن detector قابل‌اعتماد، این banner عمداً نمایش داده نمی‌شود؛ شبیه‌سازی هشدار false-positive مجاز نیست. |
| P1 | browser visual QA نهایی برای کل worktree کامل نشده | Playwright Core، Chrome و credential محلی آماده شد و smoke واقعی ۱۸ route/alias، Form Composer و widget را پاس کرد. Overview، Setup و Integrations اکنون desktop/RTL/390px و screenshot بازبینی‌شده دارند؛ مقایسهٔ pixel/interaction سایر صفحه‌ها و QA نهایی هنوز باز است. |
| رفع‌شده | local WordPress smoke به worktree فعلی متصل نیست | junction `ai-site-assistant-autopilot` به همین source تأیید شد و افزونهٔ قدیمی `sitepilot-ai` deactivate شد؛ smoke محلی بدون warning اجرا می‌شود. |
| P0 | بستهٔ `dist` با source فعلی همگام نیست | ZIP فعلی از نسخهٔ UI جدید قدیمی‌تر است (فایل‌های کلیدی آن timestamp قدیمی‌تر دارند). ZIP فقط پس از سبزشدن QA دوباره ساخته و validate می‌شود. |
| رفع‌شده | checklist دستی با قرارداد privacy ناسازگار است | `MANUAL-TESTING.md` با قرارداد delivery ایمیلی و عدم ذخیرهٔ PII اصلاح شد و تست یکپارچهٔ ارسال نیز پاس است. |
| P1 | KB queue پایدارِ pause/resume/stop/retry/skip | موکاپ آن را فعال نشان می‌دهد؛ اگر persistence/recovery واقعی نداریم، باید backend تکمیل یا کنترل locked شود. |
| P1 | stateهای Failed/Resume/Skip موکاپ برای KB | indexer خطا را فقط در response همان batch برمی‌گرداند و job store ندارد؛ banner و actionهای resume/skip/retry نباید پیش از persistence واقعی شبیه‌سازی شوند. |
| P1 | collection toggle/priority و sourceهای PDF/URL/Woo در موکاپ | قرارداد فعلی فقط post/page و CPTهای صراحتاً انتخاب‌شده را می‌خواند؛ collectionها read-only derived هستند و source غیرپشتیبانی باید locked/در گزارش بماند. |
| P1 | Real-time voice streaming | transcription/browser speech پیاده شده، اما realtime session و barge-in هنوز transport ندارد؛ گزینه‌ها عمداً Phase 2/locked هستند. |
| P1 | transportهای Handoff | Email/Telegram/Twilio را فقط با اتصال واقعی connected کنید؛ WhatsApp/Slack/Discord/webhook بدون transport فعال فقط قابل‌پیکربندی و صادقانه Phase 2 باشند. |
| رفع‌شده برای A2.5 | IA Answer Desk یکپارچه نبود | Conversations، Widget، Handoff و Voice اکنون header، entry و URL/tab canonical مشترک دارند؛ فقط Handoff inbox به‌دلیل نبود UI موکاپ، alias محافظت‌شده باقی مانده است. |
| رفع‌شده برای A2.6 | Store Products/Orders | Products فقط read-model واقعی و marker مالکیتی را نشان می‌دهد؛ publish/edit/bulk و order mutation به‌طور کامل قفل و مستند هستند. |
| رفع‌شده برای A2.6 | Woo legacy alias copy گمراه‌کننده داشت | `aisaautopilot-woocommerce` اکنون alias محافظت‌شدهٔ Store Copilot است و به `What it can do` می‌رود؛ copy create/edit/search/bulk قدیمی دیگر render نمی‌شود. |
| P1 | Content Studio و Forms در IA | زیرصفحه‌های رسمی موکاپ و flow تخصصی Form Composer باید جدا و بدون اضافه‌کردن آیتم visible جدید تثبیت شوند. |
| P1 | IA فعلی Content Studio ناهماهنگ است | Write/Templates سطح جدید دارند، ولی History alias قدیمی و Forms یک mode قدیمی‌تر است؛ header/subtab/URL و read model history باید یکپارچه شوند. |
| P1 | i18n/RTL/a11y | متن‌های جدید، focus، keyboard، contrast و mobile 390px هنوز نیازمند مرور واقعی دارند. |
| P1 | breakpointهای سطوح split-pane | موکاپ breakpoint دقیق برای Form/EFB/fullscreen widget نمی‌دهد؛ رفتار ≤768px باید به‌عنوان تصمیم UX و test پذیرش ثبت شود. |

## ۶. شواهد فنی به‌دست‌آمده در این ارزیابی

| بررسی | نتیجه | تفسیر |
|---|---|---|
| PHP | `C:\xampp\php\php.exe`، نسخهٔ 8.2.12 | مناسب با نیاز افزونه (`>=8.1`) |
| PHPUnit | **PASS** — 131 test، 429 assertion | سلامت بخشی از قراردادهای موجود؛ اثبات تطبیق موکاپ نیست |
| syntax scan | 116 PHP و 18 JS بدون خطای syntax | baseline قابل‌ادامه است |
| PHPCS کامل | **PASS** — 116/116، ۰ error، ۰ warning | گیت کیفیت کد باز است |
| PHPStan | **PASS** — ۰ error | روی همهٔ ۱۱۴ فایل تحلیل‌شده اجرا شد |
| `local_admin_render_smoke.php` | **PASS** — ۱۴ render route بدون warning/notice/deprecation | junction به source فعلی تأیید شده است |
| `browser_release_check.cjs` | **PASS** برای ۱۸ route/alias؛ QA اختصاصی Setup، Integrations و Site Knowledge نیز پاس | login موقت و پاک‌سازی‌شونده، HTTP 200، shell، console/pageerror و overflow را می‌سنجد |
| browser coverage موجود | ۱۸ route/alias را می‌سنجد؛ Overview، Setup، Integrations و Site Knowledge screenshot/interaction/RTL/390px اختصاصی دارند | برای صفحه‌های باقی‌مانده باید stateهای موکاپ و visual comparison افزوده شود |
| بستهٔ release فعلی | ZIP v1.0.4 ساختار سالم دارد، اما با source UI جاری همگام نیست | پس از تغییرات دوباره build و check می‌شود |
| visual diff، RTL، keyboard، contrast، mobile | برای Overview، Setup، Integrations و Site Knowledge: RTL/390px/overflow و screenshot پاس؛ برای بقیه در انتظار | جزء گیت اجباری خروجی است |

## ۷. برنامهٔ اجرایی پیشنهادی

### فاز ۰ — تثبیت مبنا و قرارداد (۰٫۵ تا ۱ روز)

1. ثبت snapshot از worktree فعلی و تعیین اینکه کدام تغییرها باید حفظ شوند.
2. وصل‌کردن نصب محلی WordPress به همین source tree یا deployment کنترل‌شدهٔ یکسان.
3. اصلاح PHPCS و line ending بدون refactor نامرتبط.
4. تأیید slugهای alias و تثبیت تصمیم‌های §۴.

**خروجی:** baseline سبز و قابل‌تکرار؛ نه فقط یک UI ظاهراً جدید.

### فاز ۱ — Shell و navigation (۱ تا ۱٫۵ روز)

1. اعمال/یکسان‌سازی tokenها، typography، card/border، toast، banner، page header و job bar.
2. حفظ دقیق ۱۰ آیتم visible؛ نمایش locked Store Copilot در نبود WooCommerce.
3. aliasهای قدیمی و deep linkها را بدون تغییر identifier تست کنید.

**خروجی:** تمام صفحات با brand و IA صحیح باز می‌شوند.

### فاز ۲ — صفحات admin مطابق موکاپ (۳ تا ۴٫۵ روز)

1. Overview، Setup، Integrations، Usage، Health و Activity Log.
2. Site Knowledge و Ask your KB با داده و state واقعی.
3. Answer Desk به‌شکل چهار tab یکپارچه؛ نه surfaceهای جدا و گیج‌کننده.
4. Store و Content Studio با capability gateهای صادقانه و approval-first.

**خروجی:** هر ۱۰ صفحه در 1440px و 1024px با stateهای واقعی قابل‌مقایسه‌اند.

### فاز ۳ — سطوح تخصصی (۲ تا ۳ روز)

1. Form Composer: reference chip، delivery prerequisite، review/create/shortcode و EFB handoff.
2. EFB: FAB/modal، selected-field contract، scoped command، help/Pro guard و voice fallback.
3. Front Widget: launcher/panel/fullscreen/handoff/voice، source chip و RTL/LTR.

**خروجی:** سه موکاپ غیرمنویی به‌صورت مستقل و عملیاتی تأیید می‌شوند.

### فاز ۴ — تکمیل backendهای لازم یا gate صادقانه (۲٫۵ تا ۴ روز)

1. صف KB و stateهای recovery.
2. read modelهای واقعی Store و Activity/Content history.
3. transportهای موجود handoff و حالت‌های واضح برای transportهای Phase 2.
4. تصمیم voice: streaming واقعی یا کنترل locked با voice input فعلی.

**خروجی:** هیچ دکمهٔ فعال بدون رفتار واقعی به کاربر عرضه نمی‌شود.

### فاز ۵ — QA و پذیرش (۲ تا ۳ روز)

1. PHPUnit، PHPStan، PHPCS و syntax check سبز.
2. browser release check با login موقت، پوشش همهٔ ۱۸ slug/alias و screenshot در 1440، 1024 و 390px.
3. مقایسهٔ side-by-side همهٔ stateهای موکاپ: empty/loading/error/success/edge.
4. RTL `fa_IR`، keyboard-only، focus، aria، contrast و console errors.
5. تست عملی provider، KB index، form delivery بدون ذخیرهٔ PII، widget/handoff، Woo draft و EFB در محیطی که افزونه‌های وابسته فعال‌اند.

**خروجی:** گزارش اختلاف‌های عمدی Phase 1 و evidence قابل‌تکرار برای تحویل.

## ۸. معیار پایان کار

کار فقط وقتی «آماده» است که همهٔ موارد زیر هم‌زمان برقرار باشند:

- منوی visible دقیقاً ۱۰ صفحه و به ترتیب موکاپ باشد.
- هر alias قدیمی به سطح درست برسد و هیچ REST/option/slug فنی تغییر نکرده باشد.
- همهٔ کنترل‌های فعال به backend واقعی وصل باشند؛ سایر کنترل‌ها locked/Phase 2 باشند.
- تمام stateهای مهم موکاپ با دادهٔ واقعی یا حالت empty صادقانه قابل‌نمایش باشند.
- PHPUnit، PHPStan و PHPCS کامل پاس شوند؛ console error و PHP warning صفر باشد.
- RTL/LTR، 390px، keyboard/focus و contrast تأیید شوند.
- تفاوت‌های عمدی با موکاپ فقط در همین فایل و به دلیل قرارداد امنیتی/Phase 1 ثبت شده باشند.

## ۹. فرض‌های برآورد

برآورد ۱۰ تا ۱۴ روز بر این مبنا است که یک توسعه‌دهنده به source و محیط محلی WordPress دسترسی دارد، WooCommerce و Easy Form Builder برای test قابل‌فعال‌سازی‌اند، یک provider key آزمایشی و یک کلید نامعتبر برای stateهای خطا موجود است، و در طول کار feature جدید خارج از موکاپ اضافه نمی‌شود. نبود هر کدام از این موارد، زمان QA را افزایش می‌دهد، نه اینکه با mock data جایگزین شود.
