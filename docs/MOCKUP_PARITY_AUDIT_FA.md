# گزارش تطبیق واقعی موکاپ و افزونه — AutoPilot AI

وضعیت: بازبینی مرحله‌ای در حال اجرا  
تاریخ: 2026-08-30  
مبنای قضاوت: `mockups/design_handoff_admin_redesign/AutoPilot Admin.dc.html` و پیاده‌سازی runtime افزونه؛ نه صرفاً وجود فایل یا test واحد.

## حکم فعلی

بازطراحی انجام‌شده است، اما **هنوز تطبیق ۱:۱ و تأیید بصری/رفتاری همهٔ سطح‌ها محسوب نمی‌شود**. تست‌های PHPUnit/PHPCS فقط قراردادهای کد را تأیید می‌کنند؛ معادل بازبینی در WordPress فعال یا مقایسهٔ صفحه‌به‌صفحه با mockup نیستند.

## ماتریس اولیه

| سطح موکاپ | وضعیت واقعی فعلی | حکم audit | اقدام لازم |
|---|---|---|---|
| Overview / Setup / Integrations / Usage / Health / Activity | صفحه و نام‌گذاری جدید وجود دارد؛ Activity از read model واحد استفاده می‌کند. | نیازمند بازبینی بصری live | screenshot و smoke روی WordPress فعال |
| Site Knowledge: Overview / Collections / Indexing Queue / Sync Sources | چهار sub-tab موکاپ، Overview data-driven، sourceهای واقعی و routeهای deep alias پیاده شده‌اند؛ queue پایدار هنوز وجود ندارد. | تکمیل UI Phase 1 | backend BE-05 برای recovery و sourceهای گسترده‌تر |
| Answer Desk: Conversations / Widget / Human handoff / Real-time voice | runtime renderer واحدِ configurable و gateهای Integration/Widget/Handoff هم‌معنا دارد؛ هر چهار tab روی entry canonical با data/persistence واقعی و QA صفحه‌ای هستند. | تکمیل صفحه‌ای | streaming realtime و barge-in تا ساخت transport Phase 2، قفل و گزارش‌شده می‌مانند |
| Store Copilot: New product / Capabilities / Products / Orders | چهار tab canonical، draft-only proposal، read-only product list و قرارداد واقعی Orders پیاده‌سازی شده‌اند. | تکمیل (A2.6) | PHPCS/PHPStan/WordPress/browser QA پاس؛ گزارش اختلاف و شواهد: `STORE_COPILOT_A2_6_EXECUTION_REPORT_FA.md` |
| Content Studio: Write / Templates / History | قرارداد proposal/cap امن، shell سه‌تب canonical، Write داده‌محور، Templates با hand-off امن و History marker-only با CSV مستقل پیاده شده‌اند. History فقط خروجی durable post/page را نشان می‌دهد و template/usage ثبت‌نشده را صریح می‌گوید؛ log عمومی با آن مخلوط نمی‌شود. | تکمیل (A2.7) | اختلاف‌های عمدی: approval-first/draft-only، بدون editor/usage template جعلی، و بدون cost per-draft؛ شواهد در `CONTENT_STUDIO_A2_7_AUDIT_AND_PLAN_FA.md` |
| EFB copilot و front widget | اجزای عملی موجودند؛ تطبیق visual/live و selected-field contract کامل تأیید نشده است. | نیازمند تکمیل | BE-09 و browser smoke |

## Site Knowledge — نتیجهٔ اجرای A2.4

چهار تب visible دقیقاً مطابق handoff هستند: \`Overview\`، \`Collections\`، \`Indexing Queue\` و \`Sync Sources\`. Overview برای KB خالی، CTA واقعی first pass را نشان می‌دهد و در حالت داده‌دار coverage، source و content-gap را فقط از WordPress/KB واقعی می‌سازد. اعداد coverage به‌شکل \`indexed / published\` از همان post type واقعی می‌آیند؛ sourceها فقط post/page/CPTهای قابل‌خواندن backend هستند.

\`kb_auto_index\` اکنون فقط schedule شدن index در publish/update را کنترل می‌کند. hookهای lifecycle همیشه ثبت می‌شوند تا محتوا با auto-index خاموش نیز در unpublish/trash/delete از KB حذف شود. لایهٔ خواندن Site Knowledge و دادهٔ قبلی عمداً «Always on» باقی می‌ماند.

قابلیت‌هایی که در افزونه وجود دارند اما موکاپ UI برایشان ندارد، حذف نشده‌اند:

| قابلیت حفظ‌شده | محل دسترسی | دلیل پنهان‌بودن از چهار tab |
|---|---|---|
| Ask your KB / Admin Chat | \`?page=aisaautopilot-kb&tab=ask\` و Chat alias | موکاپ tab پنجم ندارد |
| جدول entry، search/filter/delete | \`?page=aisaautopilot-kb&tab=entries\` | موکاپ مدیریت سطح entry رسم نکرده است |
| type/category picker برای index | modal واقعیِ CTAهای Index | برای انتخاب scope عملیاتی لازم است، اما صفحهٔ مستقل موکاپ نیست |

موارد زیر **عمداً شبیه‌سازی نشده‌اند** و تا backend واقعی به گزارش gap باقی می‌مانند:

| اختلاف با موکاپ | علت فنی و رفتار فعلی |
|---|---|
| queue پایدار، Pause/Stop، Retry/Skip/Resume و failed banner | batch فقط تا وقتی همان tab مرورگر باز است اجرا می‌شود؛ job store/cursor/recovery ندارد |
| وعدهٔ «nothing is lost» برای full re-index | اجرای full re-index فعلی ابتدا KB را truncate می‌کند |
| collection toggle، priority و Add collection | collectionها از typeهای واقعی index مشتق می‌شوند و persistence مستقل ندارند |
| WooCommerce/PDF/URL به‌عنوان sync source | backend فعلی فقط post/page/CPTهای مجاز را می‌خواند |
| CTA \`Write it\` / \`Open draft\` برای gap | backend gap فقط list/dismiss/clear دارد و draft bridge ندارد |

شواهد A2.4: \`wordpress_content_hooks_lifecycle_integration.php\` **7/0**، \`wordpress_knowledge_hub_integration.php\` **8/0**، regression \`wordpress_phase1_integration.php\` **45/0** و browser QA اختصاصی با \`BROWSER_EXIT=0\` (desktop، RTL، 390px، Escape/focus picker، console/pageerror). حساب مرورگر موقت بعد از هر اجرا حذف شده است.

## Answer Desk — نتیجهٔ A2.5.1: قرارداد runtime واحد

پیش از تغییر UI، یک نقص رفتاری رفع شد: toggle کارت Answer Desk در Integrations مقدار `public_assistant_enabled` را روشن می‌کرد، اما آن مسیر renderer دیگری را فعال می‌کرد که logging، citation و handoff قابل‌تنظیم را نداشت. در مقابل، widget قابل‌تنظیم پیشین با خاموش‌بودن همان Integration هم قابل‌نمایش بود. بنابراین تب‌های Conversations و Human handoff می‌توانستند دربارهٔ widgetی حرف بزنند که بازدیدکننده واقعاً نمی‌دید.

اکنون predicate مشترک `Settings::answer_desk_enabled()` قرارداد واحد زیر را اجرا می‌کند:

| setting | معنای واقعی |
|---|---|
| `public_assistant_enabled` | روشن/خاموش‌بودن قابلیت Answer Desk از Integrations |
| `widget.enabled` | visible بودن launcher برای بازدیدکننده |
| هر دو روشن | دقیقاً یک configurable ChatWidget با citation، opt-in logging، handoff و voice input render می‌شود |
| هر کدام خاموش | launcher render نمی‌شود و endpointهای public مربوط نیز درخواست را رد می‌کنند |

همچنین `Settings::answer_desk_handoff_available()` میان renderer، ChatEngine و endpoint handoff مشترک شد. checkbox handoff بدون Email/Telegram/Twilio واقعاً پیکربندی‌شده دیگر دکمهٔ کاذب یا offer کاذب نشان نمی‌دهد. ارسال هر کانالِ فعال همچنان مستقل است؛ مدل fallback ترتیبیِ موکاپ جعل نشده است.

شواهد A2.5.1: `wordpress_answer_desk_lifecycle_integration.php` **5/0** (چهار حالت gate/runtime و handoff ناقص/آماده)، `wordpress_phase1_integration.php` **45/0**، PHP syntax و PHPCS روی فایل‌های production تغییرکرده **سبز**.

## Answer Desk — نتیجهٔ A2.5.2: Conversations صادقانه و داده‌محور

تب نخستِ Answer Desk اکنون shell و ترتیب چهار تب handoff را حفظ می‌کند، اما فقط stateهایی را render می‌کند که backend واقعاً دارد: `widget-hidden`، `logging-off`، `empty` و `ready`. حالت `ready` از `VisitorConversationStore` می‌خواند و threadهای opt-in ثبت‌شده را همراه با زمان واقعی، آخرین سؤال/پاسخ، citationهای ذخیره‌شده و transcript حداکثر ۱۲ پیام نشان می‌دهد. متن کاربر با `dir="auto"`، زمان عددی با `dir="ltr"` و source/ID در markup ایمن قرار گرفته‌اند.

نسخهٔ موکاپیِ «94% answered»، نام/کشور visitor، live typing، page-origin و status ساختگی handoff عمداً پیاده نشده‌اند، زیرا در Store وجود ندارند. preview فشردهٔ Widget تنها settingهای واقعی را نشان می‌دهد و CTAهای Widget settings، Content gaps و Handoff به routeهای واقعی می‌روند. در جریان QA همچنین `Plugin::enqueue_widget_assets()` اصلاح شد تا پیش از localize کردن frontend، `Settings::all()` را بخواند؛ بنابراین greeting، voice و handoff persist‌شده به fallback ناخواسته تبدیل نمی‌شوند.

شواهد A2.5.2: `wordpress_answer_conversations_integration.php` **5/0**، `wordpress_answer_desk_lifecycle_integration.php` **6/0**، regression `wordpress_phase1_integration.php` **45/0**، PHPUnit **131/429**، PHPStan **0** و PHPCS بدون error/warning. browser QA با fixture صرفاً موقت در desktop، RTL و 390px اجرا شد؛ overflow، console error و pageerror نداشت، screenshotها بازبینی و fixture/account موقت پاک شدند.

## Answer Desk — نتیجهٔ A2.5.3: Widget با ذخیرهٔ جزئیِ واقعی

تب Widget اکنون روی URL canonical `admin.php?page=aisaautopilot-conversations&tab=widget` render می‌شود و همان header/چهار tab Answer Desk را دارد. بدنهٔ دوکارت موکاپ شامل Appearance & behaviour و Live preview است. visibility و accent colour تنها کنترل‌هایی هستند که در این UI کوتاه هم قرارداد persistence دقیق دارند: `POST /aisaautopilot/v1/widget/admin-settings` با REST nonce و `manage_options` فقط `widget.enabled` و `widget.color` را به `Settings::update()` می‌سپارد. بنابراین POST ناقص، checkboxها، logging، متن‌ها، voice، handoff، channelها یا credentialهای قدیمی را reset نمی‌کند.

swatchهای `#202a8d`، `#ff455f`، `#198754` و `#14152b` واقعی‌اند؛ coral پس از autosave و reload به preview بازمی‌گردد. visibility نیز بدون full-form submit خاموش و دوباره روشن می‌شود. source page settingِ جداگانه ندارد: فقط هنگام citation واقعاً ذخیره‌شده خودکار نمایش داده می‌شود. automatic theme detection وجود ندارد و صادقانه locked است. اگر custom palette قدیمی فعال باشد، swatchها disabled می‌شوند تا preview یا widget دربارهٔ رنگ واقعی دروغ نگوید.

### Widget — قابلیت‌های افزونه خارج از UI موکاپ

| قابلیت موجود | تصمیم UI/UX در این مرحله | علت |
|---|---|---|
| چهار position (bottom/top × left/right) | در `aisaautopilot-widget` compatibility/Advanced نگه داشته شد؛ tab کوتاه فقط position فعلی را truthful نشان می‌دهد | موکاپ فقط ادعای bottom-right دارد و toggle دوحالته برای چهار مقدار گمراه‌کننده است |
| light/dark، custom per-part palette، radius، icon و floating button | Advanced compatibility surface | موکاپ فقط swatch accent دارد؛ نمایش half-config یا reset در UI کوتاه خطرناک است |
| title/subtitle/greeting/suggestions، auto-open و exclusion rules | Advanced compatibility surface | موکاپ برایشان UI ندارد؛ حفظ داده بدون ساخت control جعلی ضروری است |
| opt-in conversation logging و notice | Conversations/Advanced | setting حریم خصوصی است، نه یک toggle ظاهریِ موکاپ |
| voice input/TTS/voice name | tab Real-time voice آینده + Advanced تا آن زمان | موکاپ tab اختصاصی دارد؛ Widget نباید duplicate configuration بدهد |
| Email/Telegram/Twilio و secretهای handoff | tab Human handoff + Advanced compatibility | credentialها و channel status اکنون در tab مستقل و truthful قرار دارند |
| «فقط adminها» و automatic theme matching | locked/documented، نه implementation ظاهری | backend چنین capabilityهایی ندارد |

شواهد A2.5.3: `wordpress_answer_widget_settings_integration.php` **4/0**، `wordpress_answer_widget_page_integration.php` **4/0**، lifecycle **6/0**، Phase 1 **45/0**، PHPUnit **131/429**، PHPStan **0**، PHPCS **118/118** بدون error/warning و Node **19/19**. browser QA با request واقعی REST nonce، autosave/reload، desktop، RTL و 390px اجرا شد؛ grid و header در RTL کامل visible ماندند، console/pageerror نداشت و screenshotها بازبینی شدند. fixture و account موقت بعد از QA حذف شده‌اند.

## Answer Desk — نتیجهٔ A2.5.4: Human handoff با کانال‌های واقعی

تب Human handoff اکنون روی URL canonical `admin.php?page=aisaautopilot-conversations&tab=handoff` است و همان header/چهار tab Answer Desk را دارد. بدنهٔ ۷۶۰px موکاپ شامل کارت‌های عمودی Email، Telegram و WhatsAppِ قفل‌شده است؛ پنل dashed `Add another channel` فقط transport واقعی SMS/Twilio را باز می‌کند و Slack، Discord و Custom webhook را به‌صراحت unavailable نگه می‌دارد.

`POST /aisaautopilot/v1/handoff/admin-settings` با REST nonce و capability `manage_options` یک channel را به‌صورت scoped ذخیره می‌کند. Email recipient، Telegram chat و شماره‌های Twilio در همان settingهای واقعی ذخیره می‌شوند؛ Telegram token و Twilio SID/token فقط در secret store رمزنگاری‌شده باقی می‌مانند و response یا DOM هرگز مقدارشان را بازنمی‌گرداند. empty Email به‌طور صادقانه از WordPress administration email استفاده می‌کند. Badgeها نیز `Ready`، `Needs setup`، `Paused` و `Not configured` هستند، نه ادعای غیرقابل‌آزمون `Connected`.

کنترل `Check connection` فقط بعد از click و confirm صریح مدیر قابل‌اجراست، با provider تماس می‌گیرد و handoff بازدیدکننده ارسال نمی‌کند؛ QA آن را کلیک نکرد. visitor handoff تنها هنگامی offer می‌شود که Integration، Widget و دست‌کم یک channel آماده هم‌زمان برقرار باشند.

### Human handoff — قابلیت‌های افزونه خارج از UI موکاپ یا اختلاف عمدی

| قابلیت/اختلاف | تصمیم UI/UX و دلیل |
|---|---|
| inbox و statusهای `new`/`in_progress`/`resolved`/`failed` | موکاپ UI برای inbox ندارد؛ در alias محافظت‌شدهٔ `aisaautopilot-handoffs` حفظ شد و وارد tab کانال‌ها نشد |
| متن button handoff، hook و global flag legacy | در Advanced compatibility باقی مانده‌اند؛ UI موکاپ برایشان کنترل ندارد. در tab کوتاه، global gate از آماده‌بودن واقعی channelها مشتق می‌شود تا دکمهٔ کاذب ساخته نشود |
| مدل delivery | هستهٔ فعلی هر channel آماده را مستقل fan-out می‌کند؛ ترتیب fallback موکاپ یا badge «Email fallback» جعل نشده است |
| WhatsApp، Slack، Discord و Custom webhook | transport backend ندارند؛ lock/Planned هستند و هیچ credential یا Connect جعلی نمی‌گیرند |
| test موکاپ با عبارت «Send a test message» | endpointهای موجود فقط connection check می‌کنند و call آن‌ها نیازمند confirm مدیر است؛ UI نام و رفتار را صادقانه نشان می‌دهد |
| state `Connected` موکاپ | صرف وجود credential اتصال خارجی را اثبات نمی‌کند؛ بنابراین UI از `Ready` استفاده می‌کند و test اختیاری را جدا نگه می‌دارد |

شواهد A2.5.4: `wordpress_answer_handoff_settings_integration.php` **5/0**، `wordpress_answer_handoff_page_integration.php` **4/0**، Conversations **5/0**، Widget **4/0**، lifecycle **6/0**، render/assets smoke محلی و browser focused Handoff با REST nonce، persistence، enable/pause/restore، desktop، RTL و 390px همگی پاس شدند. در QA هیچ provider یا delivery خارجی فراخوانی نشد؛ fixture و account موقت حذف شدند. regression کامل: PHPUnit **131/429**، PHPStan **0 error**، PHPCS **120/120 بدون error/warning** و Node **20/20**.

## Answer Desk — نتیجهٔ A2.5.5: Real-time voice با قابلیت‌های واقعی

تب Voice اکنون روی URL canonical `admin.php?page=aisaautopilot-conversations&tab=voice` و زیر همان header/چهار tab Answer Desk است. ساختار سه‌کارت موکاپ حفظ شده است: کارت mode، انتخاب Engine و تنظیمات Reply voice/Language/toggleها. اما تنها مسیر واقعی این نسخه `Transcribe + speak` است: widget یک clip کوتاه را یک‌بار برای STT می‌فرستد، آن را نگه‌داری نمی‌کند، transcript را در message field می‌گذارد و در صورت فعال‌بودن `voice_auto` پاسخ تکمیل‌شده را با SpeechSynthesis مرورگر بازدیدکننده می‌خواند.

`POST /aisaautopilot/v1/voice/admin-settings` با REST nonce و capability `manage_options` فقط `widget.voice_enabled` و `widget.voice_auto` را تغییر می‌دهد. بنابراین رنگ، visibility، privacy/logging، handoff و credentialها در autosave صوتی reset نمی‌شوند. `Play a test phrase` نیز فقط از API گفتاری همان browser مدیر استفاده می‌کند و نه ضبط می‌کند و نه به provider درخواست می‌فرستد.

### Voice — قابلیت‌های موکاپ که در افزونه UI عملی ندارند یا عمداً متفاوت‌اند

| قابلیت/اختلاف | تصمیم UI/UX و دلیل |
|---|---|
| `Realtime stream` و مکالمهٔ صوتی دوطرفهٔ پیوسته | transport/session backend و provider realtime در افزونه نیست؛ switch و engine به‌صورت `Not available` قفل‌اند، نه control نمایشی |
| `Let visitors interrupt` / barge-in | فقط با stream پیوسته معنا دارد؛ مسیر clip→transcript نمی‌تواند جواب درحال‌پخش را مانند تماس قطع کند، پس قفل است |
| voiceهای ثابت موکاپ (`Aria`، `Verse`، `Sol`، `Nova`) | نام voice به browser/device بازدیدکننده وابسته است و تضمین provider ندارد؛ UI `Browser default` را صادقانه نشان می‌دهد. مقدار legacy `voice_name` در compatibility settings حفظ شده ولی در موکاپ کنترل معتبر ندارد |
| انتخاب زبان ثابت `English`/`فارسی` | `VoiceTranscriber` عمداً زبان clip را به locale سایت pin نمی‌کند؛ فقط `Auto-detect` واقعی نمایش داده می‌شود |
| `voice_model` و `voice_responses_enabled` در تنظیمات عمیق | مدل STT به‌صورت read-only در tab دیده می‌شود؛ settingهای legacy/advanced خارج از UI موکاپ حفظ شده‌اند تا toggle صوری یا gate متناقض ساخته نشود |
| عبارت موکاپ «uses your connected provider» برای test | test فعلی local browser speech است و هیچ provider request ندارد؛ copy نیز همین واقعیت را می‌گوید |

شواهد A2.5.5: `wordpress_answer_voice_settings_integration.php` **5/0**، `wordpress_answer_voice_page_integration.php` **4/0**، و regression تمام هشت suite Answer Desk **37/0**. browser focused Voice و سپس regression چهار tab با nonce/persistence/pause-resume/desktop/RTL/390px، بدون overflow، console/pageerror و با screenshot review پاس شدند. در هیچ‌یک provider، delivery یا audio recording واقعی فراخوانی نشد؛ fixture و account موقت بعد از QA حذف شدند. regression کامل: PHPUnit **131/429**، PHPStan **0 error**، PHPCS **122/122 بدون error/warning** و Node **21/21**.

## قاعدهٔ ادامه

هیچ سطحی با عبارت «کامل» بسته نمی‌شود مگر این‌که: route واقعی داشته باشد، حالت‌های empty/loading/error/success آن تست شود، و در WordPress فعال با mockup مقایسهٔ بصری شود.
