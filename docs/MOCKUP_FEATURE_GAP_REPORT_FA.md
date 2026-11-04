# گزارش شکاف موکاپ و کد — AI Site Assistant AutoPilot

- تاریخ: ۲۰۲۶-۰۹-۱۵
- موکاپ‌ها: `mockups/design_handoff_admin_redesign/` شامل `AutoPilot Admin.dc.html`، `Front Chat Widget.dc.html`، `Form Composer.dc.html` و `EFB Builder Chat.dc.html`.
- مسیرهای کد نسبت به ریشهٔ افزونه (`apps/wordpress-plugin/ai-site-assistant-autopilot`) است و شمارهٔ خط‌ها مربوط به همین تاریخ. مرجع موکاپ به شکل `نام فایل:خط` آمده است.
- شواهد تصویری: فریم‌های موکاپ در `test-artifacts/mockup-parity/mockup/`؛ تصاویر افزونه در `test-artifacts/mockup-parity/plugin/`، `setup/`، `widget-after/` و `alias-check/` (en_US و fa_IR، عرض 1440 و 390). گیت نهایی: `test-artifacts/gates/20260915-070523-release` با 53/53.

## راهنمای وضعیت

| برچسب | معنا |
|---|---|
| ناموجود | در کد نیست؛ نه UI و نه backend. |
| قفل صادقانه | UI هست ولی غیرفعال و با توضیح، چون backend واقعی ندارد (قاعدهٔ No fake UI). |
| جایگزین صادقانه | همان نیاز با رفتار واقعی دیگری پوشش داده شده و متن UI همان رفتار را می‌گوید. |
| اختلاف عمدی | اجرا شده ولی عمداً متفاوت از موکاپ؛ دلیل در همان ردیف آمده. |

---

## ۱. در موکاپ هست، در کد نیست

### ۱.۱ `AutoPilot Admin.dc.html`

| # | صفحه | قابلیت موکاپ (مرجع) | وضعیت در کد (مرجع) | دلیل و کار لازم |
|---|---|---|---|---|
| A-01 | Setup › مرحلهٔ ۳ › Advanced | «Reply length» و timeout (`:331`، `:347`) | Reply length قفل صادقانه: `includes/Admin/SettingsPage.php:908`. timeout ناموجود. | تنظیم سراسری طول پاسخ و timeout در قرارداد provider نیست؛ گزینهٔ تنظیمات و اعمال آن در لایهٔ provider لازم است. **T29 (۲۰۲۶-۱۱-۰۴):** قفل اکنون در `SettingsPage.php:912`. |
| A-02 | Site Knowledge › Collections | روشن و خاموش کردن هر مجموعه و «Priority» (`:727`، `:1614`) | فقط‌خواندنی با برچسب «Managed by index scope»: `includes/Admin/KnowledgeBasePage.php:242` | backend اولویت‌بندی بازیابی و scope قابل‌ویرایش لازم است. **T29:** قفل در `KnowledgeBasePage.php:266`. بازیابی گفتگو اکنون `KnowledgeSearch::retrieve` است. |
| A-03 | Site Knowledge › Indexing Queue | صف پایدار با Retry the failed، Skip و ادامه (`:647`، `:740`، `:755`) | جایگزین صادقانه: اجرای batch در همان صفحه با متن صریح «صف پایدار، فهرست retry و cursor ادامه ندارد»: `KnowledgeBasePage.php:266` | صف پایدار در دیتابیس و اجرای پس‌زمینه (cron یا Action Scheduler). **T29:** متن در `KnowledgeBasePage.php:290`. cron بازسازی پایدار پس از تغییر قالب ایندکس از پیش هست (`ContentHooks.php:59`). |
| A-04 | Site Knowledge › Sync Sources | «Uploaded PDFs» و «External URL» (`:2373`، `:2374`) | ناموجود؛ فقط post typeهای وردپرس با متن صریح: `KnowledgeBasePage.php:309` | parser PDF و خزندهٔ URL با سیاست امنیتی و محدودیت حجم. **T29:** متن در `KnowledgeBasePage.php:333`. |
| A-05 | Site Knowledge › Overview | دکمهٔ «Write it» روی هر پرسش بی‌پاسخ که پیش‌نویس می‌سازد و بعد «Open draft» (`:2366`) | **اجرا شد ۲۰۲۶-۰۹-۱۶ (T30)** — به بخش ۴ منتقل شد. | — |
| A-06 | Answer Desk › Conversations | آمار «94% answered without you» (`:794`) و مکان بازدیدکننده «Visitor · Berlin» (`:2436`) | ناموجود؛ فقط شمار رشته‌ها: `includes/Admin/AnswerConversationsPage.php:154` | نرخ خودکار به برچسب نتیجهٔ هر گفتگو نیاز دارد. مکان به GeoIP و سیاست حریم خصوصی نیاز دارد؛ پیشنهاد: ساخته نشود. |
| A-07 | Answer Desk › Human handoff | کانال‌های WhatsApp، Slack، Discord و Webhook با «Send a test post» (`:1729` تا `:1732`) | قفل صادقانه: `includes/Admin/AnswerDeskHandoffPage.php:206` تا `:230` | transport واقعی هر کانال؛ بک‌لاگ D-07 نقشهٔ راه. **T29 (۲۰۲۶-۱۱-۰۴): بیشتر منسوخ.** WhatsApp Business و Slack ساخته شده‌اند و Signal افزوده شده است (`AnswerDeskHandoffPage.php:307` تا `:441`). فقط Discord و Custom webhook قفل مانده‌اند (`:457`، `:458`). دکمهٔ آزمون «Check connection» است. T46 به همین دو کانال کوچک شد. |
| A-08 | Answer Desk › Human handoff | ترتیب fallback: «از بالا به پایین تا یکی بپذیرد؛ Email همیشه fallback» (`:1721`، `:2405`) | اختلاف عمدی: ارسال هم‌زمان به همهٔ کانال‌های فعال و پیکربندی‌شده، با همین متن در UI: `AnswerDeskHandoffPage.php:57` | ترتیب fallback به گزارش پذیرش از هر transport نیاز دارد. **T29:** متن در `AnswerDeskHandoffPage.php:69`. fan-out اکنون ۶ کانال دارد. |
| A-09 | Answer Desk › Real-time voice | موتور realtime، قطع صحبت (barge-in) و صداهای نام‌دار Aria، Verse، Sol و Nova (`:1754`، `:2420`) | realtime و barge-in قفل صادقانه: `includes/Admin/AnswerDeskVoicePage.php:57` تا `:75` و `:102` تا `:104`. انتخاب صدای نام‌دار ناموجود (گفتار مرورگر). | تصمیم D-02 و BE-04. **T29:** مرجع‌ها همچنان درست‌اند (`AnswerDeskVoicePage.php:57` تا `:76`، `:102` تا `:105`). |
| A-10 | Store Copilot › What it can do | انتشار، ویرایش محصول موجود، تغییر گروهی، تغییر سفارش و بازپرداخت | قفل صادقانه Phase 2: `includes/Admin/StoreCopilotPage.php:259` تا `:269`، `:299`، `:407` | قرارداد draft-only فاز ۱. **T29 (۲۰۲۶-۱۱-۰۴): بخشی منسوخ.** تب Descriptions توضیح بلند محصول موجود را با بازبینی، اعمال و Undo تغییر می‌دهد. ردیف‌های Phase 2 در `StoreCopilotPage.php:298` تا `:300` و `:307`، `:308` هستند. متن تب Products (`:418`، `:436`) با این قابلیت تناقض دارد (F-1 در بخش ۸.۷ plan S5). |
| A-11 | Store Copilot | «Read order status for the customer who asks» و پاسخ سفارش در ویجت (منبع «Store Copilot · order lookup» در `:2436`) | جایگزین صادقانه: «Read a recent order summary as a store manager»: `StoreCopilotPage.php:267` | lookup سفارش برای بازدیدکننده به احراز مالکیت سفارش نیاز دارد. **T29:** متن در `StoreCopilotPage.php:306`. route در `RestRoutes.php:326`. |
| A-12 | Store Copilot › Settings | «Smart product search» که بازدیدکننده با توصیف محصول جست‌وجو کند | جایگزین صادقانه: فقط endpoint محافظت‌شده، بدون UI بازدیدکننده، با همین متن: `StoreCopilotPage.php:298` | UI جست‌وجو در ویجت. **T29:** متن در `StoreCopilotPage.php:337`. |
| A-13 | Content Studio › Write | وضعیت «Published automatically» (`:2527`) | ناموجود، عمداً؛ قرارداد approval-first | تغییر قرارداد است؛ فقط با تصمیم مالک. |
| A-14 | Content Studio › Write | لحن «Formal» و «Plain» | قفل صادقانه: `includes/Admin/CopilotPage.php:290` و `:291` | گزینهٔ لحن در prompt پیش‌نویس. **T29:** چیپ‌ها در `CopilotPage.php:349`، `:350`. |
| A-15 | Content Studio › Templates | شمار استفاده مانند «Used 38 times» (`:1699`) | ناموجود: `CopilotPage.php:344` | شمارندهٔ استفاده از الگو. **T29:** الگوها در `CopilotPage.php:403` تا `:425`. |
| A-16 | Usage & Cost | مبالغ دلاری و سقف هزینه؛ «در سقف همهٔ قابلیت‌ها متوقف و ایمیل می‌شود» (`:1295`) | جایگزین صادقانه: توکن‌محور (`includes/Admin/UsagePage.php:9`). ایمیل هنگام رسیدن به سقف ناموجود. | نقشهٔ قیمت هر مدل و اعلان ایمیلی. **T29:** Setup سقف ماهانهٔ توکنی پیشنهاد می‌کند و Overview هنگام رسیدن به سقف بنر نشان می‌دهد. ایمیل و مبلغ دلاری هنوز نیستند. |
| A-17 | Activity Log | Approve، Approve all، Undo و Restore روی ردیف‌ها (`:1363`، `:2605`) | ناموجود؛ لاگ فقط‌خواندنی با خروجی CSV: `includes/Admin/LogsPage.php:44` | مسیر بازگردانی برای هر نوع عمل. |
| A-18 | Integrations | بنر «Another AI plugin is active» (`:1543`، `:1998`) | **اجرا شد ۲۰۲۶-۱۱-۰۴ (T31)** — به بخش ۴ منتقل شد. | — |
| A-19 | Answer Desk › Widget | «Match my theme colours» (`:2691`) | قفل صادقانه با برچسب «Manual palette»: `includes/Admin/AnswerDeskWidgetPage.php` (`data-sp-widget-theme-locked`). **تا ۲۰۲۶-۰۹-۱۸ در این گزارش نیامده بود؛ در T73 پیدا شد.** | هیچ کدی پالت قالب را نمی‌خواند. راه کم‌هزینه: خواندن `theme.json` پالت (`wp_get_global_settings( array( 'color', 'palette' ) )`) و پیشنهاد آن رنگ‌ها کنار رنگ‌های برند؛ قالب بدون پالت صادقانه گفته شود. پس از انتشار، در T29 (D-13 ب). **T29:** task آن T76 است. |

### ۱.۲ `Front Chat Widget.dc.html`

| # | بخش | قابلیت موکاپ (مرجع) | وضعیت در کد (مرجع) | دلیل و کار لازم |
|---|---|---|---|---|
| W-01 | حالت صوتی | overlay تمام‌صفحه با orb، وضعیت «Listening» و قطع صحبت (`:267`، `:350` تا `:352`؛ سناریوی `voice` در `:396`) | جایگزین صادقانه transcript-first: دکمه‌های `#spw-voice` و `#spw-mic` (`includes/Frontend/ChatWidget.php:103`) و ضبط در `assets/frontend/widget.js`. متن در composer می‌نشیند و خودکار ارسال نمی‌شود. **T12 (۲۰۲۶-۰۹-۱۸):** D-02 اجرا شد و overlay ساخته نشد؛ به‌جای آن سه فاز واقعی با `role="status"` اعلام می‌شوند و `/public/voice` با میکروفن خاموش 403 `voice_disabled` می‌دهد. در مرورگر اثبات شد که حین تبدیل هیچ متن زنده‌ای نمایش داده نمی‌شود و کل جریان تنها یک درخواست می‌سازد. | تصمیم D-02 — **بسته**. **T29:** دکمه‌ها در `ChatWidget.php:106` و `:159`. |
| W-02 | کارت انتقال به انسان | انتخاب کانال Email یا Telegram در خود کارت (`:159`، `:160`) | اختلاف عمدی: یک دکمهٔ ارسال به تیم که به همهٔ کانال‌های پیکربندی‌شده می‌رود: `ChatWidget.php:193`، `widget.js:538` و `:561` | انتخاب کانال توسط بازدیدکننده به فهرست عمومی کانال‌ها بدون افشای جزئیات نیاز دارد. **T29 (۲۰۲۶-۱۱-۰۴): بخشی منسوخ.** هدر Reply-To ساخته شده است (`HandoffEmail.php:81` تا `:83`). fan-out ۶ کانال دارد و فیلدهای تماس قابل تنظیم‌اند. انتخاب کانال هنوز نیست. کارت در `ChatWidget.php:214` تا `:251`. |

سناریوهای دیگر ویجت اجرا شده‌اند: launcher و nudge، panel، full screen با ستون پرسش‌های پرتکرار و کارت انتقال (سناریوهای `launcher`، `panel`، `fullscreen` و `handoff` در `:395` و `:396`).

### ۱.۳ `Form Composer.dc.html`

| # | بخش | قابلیت موکاپ (مرجع) | وضعیت در کد (مرجع) | دلیل و کار لازم |
|---|---|---|---|---|
| C-01 | انتخاب فیلد | کلیک روی فیلدهای فرم و پیوستن آن‌ها به گفتگو (چیپ‌های «Editing»، «2 fields referenced» و پیشنهادهایی مثل «Make both required»)؛ سناریوی `selecting` (`:367`) | ناموجود. جریان کد «توصیف، طرح قابل‌بازبینی، تأیید، ساخت» است و اصلاح با «Back and edit» انجام می‌شود: `assets/admin/form-builder.js:215` و `:390` | مسیر REST برای «اصلاح طرح» که فیلدهای انتخاب‌شده را همراه دستور بفرستد. بدون آن، UI ساخته نشد تا وعدهٔ دروغ ندهد. **T29 (۲۰۲۶-۱۱-۰۴): بخشی ساخته شده.** بازبینی طرح با پیام روی `/forms/plan` (`form-builder.js:370` تا `:372`) جای «Back and edit» را گرفته است. کلیک روی فیلد، چیپ «Editing» و `/forms/refine` هنوز نیستند. |
| C-02 | بوم فرم | فرم زنده روی بوم پس از ساخت با ویرایش در جا؛ سناریوی `form` (`:366`) | جایگزین: پیش‌نمایش طرح با تب‌های Preview و Structure و حالت Desktop و Mobile، پیش از ساخت: `form-builder.js:236` | وابسته به C-01. **T29:** تب‌های Preview و Structure حذف شدند. فهرست فیلدها کنار پیش‌نمایش فقط‌خواندنی است (`form-builder.js:193` تا `:257`). |
| C-03 | Advanced → EFB | کارت درون گفتگو «That needs Easy Form Builder» با چیپ‌های «Keep it simple» و «What can native forms do?»؛ سناریوی `advanced` (`:367`، `:287` تا `:289`) | جایگزین: نوار ثابت «Need file uploads, payments, multi-step or logic?» با «Open EFB»: `includes/Admin/CopilotPage.php:108` | پاسخ ساخت‌یافتهٔ «نیازمند EFB» از validator طرح. **T29:** بلوک EFB اکنون فقط در حالت شروع است (`CopilotPage.php:115` تا `:123`) و متنش «dates» را هم دارد. |

سناریوهای `welcome`، `needemail` (قفل تا تنظیم ایمیل دریافت) و `created` (شورت‌کد، `form-builder.js:429`) اجرا شده‌اند.

### ۱.۴ `EFB Builder Chat.dc.html`

| # | بخش | قابلیت موکاپ (مرجع) | وضعیت در کد (مرجع) | دلیل و کار لازم |
|---|---|---|---|---|
| E-01 | Live voice | صفحهٔ تماس تمام‌صفحه با Mute، End و Chat و چیپ «English · auto-detected»؛ سناریوی `voice` (`:390`، `:221` تا `:224`) | جایگزین صادقانه: دکمهٔ `aisaautopilot-efb-live-voice` (`includes/Efb/EfbBuilderButton.php:129`) متن زندهٔ تشخیص گفتار را در composer می‌گذارد و هرگز خودکار ارسال نمی‌کند: `assets/admin/efb-copilot.js:533` | تصمیم D-02. **T29 (۲۰۲۶-۱۱-۰۴): مرجع منسوخ.** دکمهٔ جدا حذف شد. یک دکمهٔ ضبط `#aisaautopilot-efb-voice` (`EfbBuilderButton.php:353`) متن زنده و سپس transcript سرور را در composer می‌گذارد، بدون ارسال خودکار (`efb-copilot.js:909` تا `:947`). |

سناریوهای `fab` (`EfbBuilderButton.php:116`)، `selected` (`efb-copilot.js:158`)، `help` (`efb-copilot.js:482`) و `pro` (`efb-copilot.js:388`، `includes/Efb/EfbLicense.php`) اجرا شده‌اند.

---

## ۲. در کد هست، در موکاپ نیست

| # | قابلیت | مرجع کد | جای آن در UI |
|---|---|---|---|
| B-01 | کنسول چت مدیر «Ask your knowledge base» با گفتگوهای ذخیره‌شده و ورودی صوتی | `includes/Admin/ChatPage.php`، `assets/admin/chat.js`، REST در `includes/Api/RestRoutes.php:292` | alias پنهان `aisaautopilot-chat`، لینک‌شده از Site Knowledge (`KnowledgeBasePage.php:313`)، با header مشترک |
| B-02 | جدول مدخل‌های دانش و کارت قدیمی پرسش‌های بی‌پاسخ | `KnowledgeBasePage.php:321` (`tab=entries`) | تب پنهان |
| B-03 | فهرست فرم‌های بومی با وضعیت، شورت‌کد و ویرایش | `includes/Admin/NativeFormsPage.php` | alias `aisaautopilot-forms` زیر Content Studio |
| B-04 | صندوق انتقال‌ها با وضعیت‌های new، in_progress، resolved و failed | `includes/Support/HandoffInbox.php:125`، `includes/Admin/HandoffInboxPage.php`، `includes/Frontend/ChatWidgetSettings.php:349` | alias `aisaautopilot-handoffs` زیر Answer Desk |
| B-05 | PDF رونوشت گفتگو برای انتقال | `includes/Support/PdfExporter.php`، `includes/Support/HandoffService.php:9` | پشت صحنه |
| B-06 | سفارشی‌ساز عمیق ویجت: موقعیت، تم تیره، رنگ‌ها، شعاع‌ها، آیکون‌ها، اندازه و شکل و انیمیشن و جابه‌جایی دکمه، ابعاد و سایه و فونت پنجره، باز شدن خودکار، استثنای صفحه و دسته و نوع و شناسه، و visibility. **اصلاح T07 (۲۰۲۶-۰۹-۱۷):** فرم فقط موقعیت، تم، رنگ‌ها، آیکون‌ها، متن‌ها و گوشه‌ها را می‌نویسد. باز شدن خودکار، ابعاد پنجره، visibility و استثناها در renderer خوانده می‌شوند ولی UI ندارند، و کلیدهای دکمه (`btn_*`) و سایه و فونت پنجره (`window_*` جز عرض و ارتفاع) نه نوشته و نه خوانده می‌شوند: F-01، F-11 و F-12 در `FRONT_CHAT_WIDGET_S3_3_AUDIT_AND_PLAN_FA.md`. **T08 (۲۰۲۶-۰۹-۱۷):** باز شدن خودکار اکنون کنترل «Open the chat by itself» در همین فرم دارد و پیش‌فرضش «هرگز» است (D-25). **T73 (۲۰۲۶-۰۹-۱۸):** visibility و استثناها کارت «Pages where the chat appears» گرفتند (F-11) و کلیدهای `btn_*` و `window_*` بی‌اثر به‌عنوان کلید بازنشسته تعریف شدند (F-12). فقط ابعاد پنجره همچنان UI ندارد | کلیدها در `includes/Core/Settings.php:405`؛ فرم در `includes/Frontend/ChatWidgetSettings.php` | alias `aisaautopilot-widget` و تب Widget |
| B-07 | ثبت گفتگو و متن اطلاع‌رسانی حریم خصوصی | `ChatWidgetSettings.php:100` (کلیدهای `conversation_logging_enabled` و `conversation_notice`) | تب Widget |
| B-08 | تنظیمات پیشرفته مانند مدل تبدیل صوت و ترجیح EFB برای فرم‌ها (`prefer_efb`) | `includes/Admin/SettingsPage.php:453` و `:526` | alias `aisaautopilot-settings` |
| B-09 | ثبت در Connectors وردپرس 7.x با نوع `ai_assistant` و ثابت یا متغیر محیطی `AISAAUTOPILOT_API_KEY` | `includes/Core/WpConnectors.php:85` | کارت «WordPress Connector» در مرحلهٔ ۱ Setup |
| B-10 | صفحهٔ سازگاری و عملیات مجاز EFB | `includes/Admin/EasyFormBuilderPage.php` | alias `aisaautopilot-efb` زیر Integrations |
| B-11 | صفرکردن مصرف ماه جاری | `includes/Admin/UsagePage.php:94`، REST در `RestRoutes.php:478` | دکمهٔ Reset در Usage & Cost |
| B-12 | لایهٔ امنیت: KeyVault، Redactor و RateLimiter | `includes/Security/KeyVault.php`، `includes/Security/Redactor.php`، `includes/Security/RateLimiter.php` | نامرئی |
| B-13 | aliasهای قدیمی برای bookmarkها، با باز ماندن منو و برجسته شدن آیتم مالک | `includes/Admin/AdminMenu.php:77` و `:120` | هشت مسیر پنهان |
| B-14 | جمع‌شدن روزها و رویدادهای قدیمی در Activity Log برای لاگ‌های طولانی | `includes/Admin/LogsPage.php:18` و `:62` | «Show earlier activity» |
| B-15 | پیام صریح نمودار خالی مصرف | `UsagePage.php:104` | Usage & Cost |

**وضعیت ۲۰۲۶-۰۹-۱۵:** سطح‌های B-01، B-02، B-03، B-04، B-06، B-07، B-08، B-10 و B-11 در طرح Claude Design طراحی و در افزونه بازسازی شدند. فایل طرح `design/claude-design-v2/AutoPilot Admin Extended.dc.html` است و `EXTENDED_SURFACES_BRIEF.md` جدول وضعیت اجرا و اختلاف‌های عمدی را دارد.

| مورد | آنچه ساخته شد | آزمون |
|---|---|---|
| B-01 | کنسول با حالت آماده یا قفل همراه دلیل، منبع‌ها، «Not in your content» با پیوند به پرسش‌های بی‌پاسخ و حذف گفت‌وگو با تأیید | `tests/wordpress_chat_console_integration.php`، `tests/chat_console_browser_matrix.cjs` |
| B-02 | مدخل‌ها با حذف تکی و ایندکس دوبارهٔ کامل، هر دو با تأیید | `tests/browser_release_check.cjs` (Site Knowledge) |
| B-03 | فرم‌های بومی با کپی shortcode و آزمون تحویل | `tests/wordpress_phase1_integration.php` |
| B-04 | صندوق انتقال با فیلتر، کشوی گفت‌وگو و تغییر وضعیت، و نوار «Open inbox» روی tab Human handoff | `tests/wordpress_handoff_inbox_integration.php`، `tests/handoff_inbox_browser_matrix.cjs`، `tests/wordpress_answer_handoff_page_integration.php` |
| B-06، B-07 | سفارشی‌سازی ویجت و ثبت گفت‌وگو و حریم خصوصی، با نوار تغییرات ذخیره‌نشده و ذخیره فقط کلیدهای همان صفحه | `tests/wordpress_widget_customise_integration.php`، `tests/widget_customise_browser_matrix.cjs` |
| B-08 | تنظیمات پیشرفته در بخش‌های جست‌وجو، صدا، فرم‌ها و دستیار عمومی. بخش اتصال فقط‌خواندنی است. | `tests/wordpress_advanced_settings_integration.php`، `tests/advanced_settings_browser_matrix.cjs` |
| B-10 | جزئیات دستیار EFB: عملیات مجاز از `EfbCustomizeSkill::OPS`، حوزه‌های ممنوع از کاتالوگ، آخرین تغییر از لاگ | `tests/wordpress_efb_details_integration.php`، `tests/efb_details_browser_matrix.cjs` |
| B-11 | reset دورهٔ جاری در منطقهٔ خطر با پنجرهٔ تأیید مشترک | `tests/browser_release_check.cjs` (Usage) |

- **ساخته‌نشده:** سطح‌های برنامه‌ریزی‌شدهٔ همان طرح ساخته نشده‌اند: ترتیب تحویل (T37)، ویرایشگر الگو (T33 و T34) و کارت محصول (T39).
- **مستند فنی:** `docs/technical/ADMIN_SURFACES_FA.md`.

---

## ۳. اختلاف‌های عمدی در بخش‌های اجراشده

| # | اختلاف | مرجع | دلیل |
|---|---|---|---|
| X-01 | ترتیب checklist آزمون کلید در Setup: ابتدا تست اتصال، سپس بارگذاری مدل‌ها | `assets/admin/setup-wizard.js` | مراحل از پاسخ واقعی درخواست‌ها پر می‌شوند، نه انیمیشن ساختگی. |
| X-02 | جای ویجت در RTL فیزیکی می‌ماند؛ موکاپ آن را آینه می‌کند | `assets/frontend/widget.css` | موقعیتی که مدیر انتخاب کرده مقدم است. |
| X-03 | متن زیر composer ویجت: «Answers come from this site and can be wrong.» | `includes/Frontend/ChatWidget.php` | صراحت دربارهٔ خطای ممکن. |
| X-04 | زیرعنوان Form Composer «review every field, then create it» به‌جای «click any field to fine-tune it» | `CopilotPage.php` | تا C-01 ساخته نشود، وعدهٔ کلیک روی فیلد داده نمی‌شود. **T29:** زیرعنوان امروز `CopilotPage.php:81`: «Simple forms, emailed to you. Describe one, check the plan, then create it.» |
| X-05 | برچسب وضعیت صفحهٔ EFB: «No change proposed yet» یا «Last change applied» به‌جای «Waiting to apply» ثابت | `includes/Admin/EasyFormBuilderPage.php` | صفحه هیچ پیشنهاد معلقی نگه نمی‌دارد؛ پیشنهادها در ویرایشگر EFB است. |
| X-06 | مرحلهٔ ۳ Setup: کارت Model پیش از آزمون اتصال هم دیده می‌شود، با فیلد «Model name» و «Refresh from provider» (T16) | `SettingsPage.php`، `setup-wizard.js` | آزمون با همان مدل انتخاب‌شده فرستاده می‌شود؛ شناسهٔ مدل سفارشی فیلد آزاد لازم دارد. |
| X-07 | checklist آزمون کلید فقط مرحلهٔ شکست‌خورده را «failed» نشان می‌دهد؛ فریم 401 موکاپ هر چهار مرحله را تیک زده (T16) | `setup-wizard.js` (`FAILED_STAGE`) | تیک همهٔ مراحل کنار «کلید رد شد» ادعای نادرست است. |
| X-08 | «No models returned» با «Connected» و Next فعال؛ موکاپ هشدار می‌دهد و Next را می‌بندد (T16) | `setup-wizard.js` (`refreshModels`) | آزمون با مدل انتخاب‌شده موفق بوده؛ راهنمای رفع همچنان نشان داده می‌شود. |
| X-09 | Health بنر خلاصهٔ علت ریشه ندارد؛ هر ردیف وضعیت و جزئیات واقعی خودش را دارد (T16) | `connections.js` | گزارش diagnostics علت ریشه برنمی‌گرداند؛ حدس زدن آن متن ساختگی است. |
| X-10 | نتیجهٔ ساخت پیش‌نویس Store Copilot یک ردیف با پیام سرور و «Review product draft»، بدون «Propose another» (T16) | `woocommerce.js` (`showResult`) | composer همان بالا آماده است؛ پیوند مستقیم به همان پیش‌نویس دقیق‌تر است. |
| X-11 | Usage & Cost، Health و Activity Log زیر نوار tab «Overview»، با برجسته شدن «Overview» در منو (T16) | `AdminMenu.php` | تصمیم بازآرایی IA در S6. |
| X-12 | «Widget settings» در سربرگ Answer Desk به tab Widget می‌رود، نه modal چهار-کلیدی (T16) | `AnswerDeskWidgetPage.php`، B-06 | سفارشی‌ساز کامل یک منبع حقیقت است. |
| X-13 | «Speak replies aloud» تا روشن شدن «transcribe + speak» غیرفعال است (T16) | `answer-voice.js` | پخش پاسخ بدون ورودی صوتی معنا ندارد؛ یادداشت دلیل را می‌گوید. |
| X-14 | هشدار افزونهٔ هم‌پوشان (T31): برای افزونهٔ چت عنوان «Another chat plugin is active» و «may overlap» به‌جای «will overlap»؛ هشدار چت فقط وقتی دکمهٔ چت Answer Desk روشن است؛ پنهان کردن برای هر مدیر و هر سایت ماندگار است | `IntegrationsPage.php` (`render_conflicts`)، `ConflictDetector.php` | Tawk.to یا Chaty افزونهٔ AI نیستند. هم‌پوشانی فقط وقتی ممکن است که هر دو دکمه روشن باشند. ادعای قطعی بدون دیدن قالب و جای دکمه درست نیست. |

---

## ۴. بسته‌شده در همین نوبت (UI/UX)

- header مشترک full-bleed با خط زیرین، بدون اسکرول افقی در wp-admin: `.sp-app .sp-pagebar` در `assets/admin/aisaautopilot-ui.css`. فاصلهٔ زیر header مانند موکاپ 24px شد (پیش‌تر دوبرابر) و در عرض گوشی عنوان کل ردیف را می‌گیرد.
- منوی AutoPilot روی هر هشت alias پنهان باز می‌ماند و آیتم مالک برجسته می‌شود؛ پیش‌تر `get_admin_page_parent()` هسته آن را می‌بست: `AdminMenu.php` (`parent_file`).
- کارت‌های Human handoff در عرض گوشی: دکمه‌ها هم‌تراز متن کارت. متن «آخرین اعمال» صفحهٔ EFB در فارسی دیگر با فونت monospace (که اتصال حروف را می‌شکند) نمایش داده نمی‌شود. نوار چسبان «Save settings» در صفحهٔ قدیمی Widget زمینه گرفت تا روی فیلدها نیفتد.
- aliasهای Chat، Handoffs، Forms و EFB با `Ui::page_header` و منوی باز: `ChatPage.php`، `HandoffInboxPage.php`، `NativeFormsPage.php`، `EasyFormBuilderPage.php`.
- Store Copilot › What it can do: دو ستون برابر، فاصلهٔ درست عنوان گروه‌ها، آیکون tick و dash، عنوان کارت 14px و رنگ وضعیت مطابق موکاپ.
- **A-18 (T31، ۲۰۲۶-۱۱-۰۴):** Integrations با نام واقعی افزونه هشدار می‌دهد که افزونهٔ چت یا دستیار AI دیگری فعال است، همراه CTA «Open Plugins» و دکمهٔ پنهان کردن که با keyboard کار می‌کند. فهرست شامل ۱۴ slug تأییدشده در WordPress.org است و با filter `aisaautopilot_conflicting_plugins` گسترش می‌یابد. پنهان کردن در user option هر مدیر روی همان سایت ذخیره می‌شود و افزونهٔ دیگری از فهرست که بعداً فعال شود هشدار را برمی‌گرداند؛ افزونهٔ پنهان‌شده پنهان می‌ماند: `Core/ConflictDetector.php`، `Api/IntegrationsController.php`، `IntegrationsPage.php`، `integrations.js`. شاهد: `wordpress_conflict_detector_integration` 8/0، `ConflictDetectorTest` 4 آزمون، `conflict_browser_matrix` en/fa 19/0.
- **A-05 (T30، ۲۰۲۶-۰۹-۱۶):** پرسش بی‌پاسخ با دلیل، «Asked N times»، فیلتر «Last 7 days»، «Write it» به Content Studio › Write با brief و `source_gap`، و «Open draft» پس از تأیید پیش‌نویس. به دستور مالک، «Answer it» و کارت «Saved answers» هم ساخته شد که بدون درخواست AI پاسخ می‌دهد: `KbGaps.php`، `SavedAnswers.php`، `KnowledgeBasePage.php`، `knowledge-base.js`، `ContentController.php`، `ContentDraftExecutor.php`.
- Site Knowledge › Overview: قالب «x of y» و کارت «Questions your site could not answer» که با حالت خالی صادقانه همیشه دیده می‌شود: `KnowledgeBasePage.php` (`#sp-kb-gaps-empty`) و `knowledge-base.js:496`.
- Form Composer: چیپ‌های کوتاه موکاپ در بوم و چیپ‌های دستیار زیر خوشامد که فقط composer را پر می‌کنند و چیزی ارسال نمی‌کنند: `CopilotPage.php:67` و `:128`، `form-builder.js:660`.
- نشان شمار انتقال‌های «new» روی منوی Answer Desk از دادهٔ واقعی صندوق، با متن صفحه‌خوان و بدون افزودن آیتم منو: `AdminMenu.php` (`answer_desk_menu_label`)، `HandoffInbox.php` (`count_new`)؛ آزمون `tests/wordpress_answer_desk_menu_badge_integration.php` با 6 check و 0 شکست.
- Setup wizard چهارمرحله‌ای، ویجت بازدیدکننده، ثبت Connectors در WordPress 7.1، هم‌ترازی Integrations و Overview، و تکمیل ترجمهٔ fa_IR (بدون رشتهٔ ترجمه‌نشده در `includes/`).

---

## ۵. برنامهٔ اجرا

همهٔ ردیف‌های بخش ۱ به‌صورت task کامل برای ایجنت `aisa-finisher` تعریف شده‌اند: `docs/MOCKUP_GAP_S5_AUDIT_AND_PLAN_FA.md` و ردیف‌های T29 تا T53 رودمپ.

| شکاف | Task | بخش |
|---|---|---|
| A-05 | T30 | S5-A |
| A-18 | T31 | S5-A |
| A-19 | T76 (تعیین در T29، ۲۰۲۶-۱۱-۰۴) | S5-A |
| A-14 | T32 | S5-A |
| A-15 | T33، T34 | S5-A |
| A-01 | T35 | S5-A |
| A-02 | T36 | S5-A |
| A-08 | T37 | S5-A |
| W-02 | T38 | S5-A |
| A-12 | T39 | S5-A |
| C-01، C-02، C-03 | T40 | S5-A |
| A-17 | T41 | S5-A |
| A-06 (آمار) | T42 | S5-A |
| A-03 | T44 | S5-B |
| A-04 | T45 | S5-B |
| A-07 | T46 (پس از T29 فقط Discord و Custom webhook) | S5-B |
| A-09، W-01، E-01 | T47 | S5-B |
| A-10 | T48 | S5-B |
| A-11 | T49 | S5-B |
| A-16 | T50 | S5-B |
| A-06 (مکان) | T51 | S5-B |
| A-13 | T52 | S5-B |

## ۶. پیشنهاد برای تصمیم مالک

| ردیف | پیشنهاد |
|---|---|
| A-05، A-18 | کم‌هزینه و پرارزش؛ پیشنهاد برای نسخهٔ بعدی. |
| C-01، C-02، C-03 | یک بستهٔ «اصلاح طرح فرم» با مسیر REST جدید و آزمون integration. |
| A-07، A-09، W-01، E-01 | وابسته به D-02 و D-07 نقشهٔ راه (voice realtime و transportهای Phase 2). از A-07 فقط Discord و Custom webhook مانده است. |
| A-16 | نقشهٔ قیمت مدل‌ها پس از انتشار؛ تا آن زمان واحد توکن می‌ماند. |
| A-06 (مکان)، A-13 | پیشنهاد: ساخته نشوند؛ اولی به حریم خصوصی و دومی به قرارداد approval-first برمی‌خورد. |
