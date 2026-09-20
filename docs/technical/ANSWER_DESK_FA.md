# Answer Desk

Answer Desk مجموعهٔ پشتیبانی بازدیدکننده است: ویجت روی سایت، گفت‌وگوهای ثبت‌شده، انتقال به انسان و صدا. مدیر پاسخ‌ها را در کنسول «Ask your knowledge base» می‌آزماید.

## دروازه‌ها

| شرط | منبع |
|---|---|
| Answer Desk فعال است | `Settings::answer_desk_enabled()`: هر دو `public_assistant_enabled` و `widget.enabled` روشن‌اند. |
| ویجت روی این صفحه نمایش داده می‌شود | `ChatWidget::should_render_widget()`: دروازهٔ بالا و `widget_visibility` (`all`، یا `exclude` با قاعده‌های `Settings::widget_exclusions()`: مورد تکی، نوع محتوا و بایگانی آن، دستهٔ نوشته و صفحهٔ آن دسته). کد کوتاه `[aisaautopilot_chat]` هم از همین دروازه می‌گذرد. |
| انتقال به انسان در دسترس است | `Settings::answer_desk_handoff_available()`: Answer Desk فعال، `handoff_enabled` روشن و دست‌کم یک کانال فعال و پیکربندی‌شده |

hookهای ویجت همیشه ثبت می‌شوند و این دروازه‌ها در زمان اجرا سنجیده می‌شوند. پس روشن یا خاموش کردن در تنظیمات بدون بارگذاری دوبارهٔ افزونه اثر می‌کند.

## ویجت بازدیدکننده

- **رندر و assetها:**
  - `ChatWidget::render()` markup ویجت را در `wp_footer` چاپ می‌کند.
  - `Plugin::enqueue_widget_assets()` فایل‌های `assets/frontend/widget.css` و `widget.js` را بار می‌کند.
  - شیء محلی `AISAAutoPilotWidget` این‌ها را دارد: `restUrl`، `nonce`، `greeting`، `voiceEnabled`، `voiceName`، `voiceAuto` و `i18n` (از `Plugin::widget_strings()`). از T11 چهار کلید `handoffText`، `handoffHook`، `handoffSending` و `handoffSent` حذف شدند: دو تای نخست را `widget.js` هرگز نمی‌خواند (برچسب و متن قلاب در markup کارت رندر می‌شوند) و دو تای دوم نسخهٔ تکراری `i18n.sending` و `i18n.sent` بودند. `wp_localize_script` مقدار بولی سطح بالا را رشته می‌کند (`true` به `"1"` و `false` به `""`)، پس `widget.js` آن‌ها را با truthiness می‌خواند. `voiceName` از T10 همان مقدار ذخیره‌شده است (پیش‌فرض رشتهٔ خالی، نه نام یک صدای ثابت).
- **shortcode:** `[aisaautopilot_chat]` همان ویجت را با `ChatWidget::render_standalone()` در محتوا قرار می‌دهد.
- **جریان پاسخ:**
  - **بدون درخواست هنگام بارگذاری (T10، F-15):** `widget.js` هیچ درخواستی تا نخستین پرسش نمی‌فرستد. تنظیمات زمان اجرا (`voiceEnabled`، `voiceAuto`، `voiceName`) از `AISAAutoPilotWidget` خوانده می‌شوند، یعنی از همان صفحه‌ای که markup پنل از آن رندر شده است. پیش از T10 هر بازدیدکننده روی هر بارگذاری هر صفحه یک `GET /widget/settings` می‌فرستاد. route حفظ شده و همچنان قرارداد عمومی و بدون اسرار دارد، ولی ویجت از آن استفاده نمی‌کند. پیامد عمدی: با cache تمام‌صفحه، تنظیمات صدا همان‌قدر کهنه‌اند که خود markup ویجت (عنوان، پرسش‌ها، دکمهٔ انتقال) و nonce.
  - `auto_open` ثانیه‌های پیش از باز شدن خودکار پنل است و روی `data-spw-auto-open` می‌نشیند. پیش‌فرض از T08 برابر 0 است (D-25): پنل خودش باز نمی‌شود و `widget.js` پس از 4 ثانیه nudge «Need a hand?» را یک‌بار در هر session نشان می‌دهد. مدیر این مقدار را در سفارشی‌سازی پیشرفته با کنترل «Open the chat by itself» تعیین می‌کند (0، 5، 10، 20، 30 یا 60 ثانیه؛ سقف ذخیره 120).
  - `window_width` و `window_height` (پیش‌فرض 392 و 624، با بازهٔ 320 تا 560 و 380 تا 820) و `voice_name` در renderer خوانده می‌شوند ولی هیچ صفحهٔ مدیریتی آن‌ها را نمی‌نویسد.
  - **صفحه‌هایی که گفت‌وگو در آن‌ها دیده می‌شود (F-11، ۲۰۲۶-۰۹-۱۸):** کارت `#answer-desk-visibility` در سفارشی‌سازی پیشرفته `widget_visibility` و `exclude_types`، `exclude_cats` و `exclude_ids` را می‌نویسد. مدیر «Every page» یا «Every page except the ones below» را برمی‌گزیند؛ در حالت دوم نوع‌های عمومی (بدون media)، دسته‌های نوشته و موردهای تکی را انتخاب می‌کند. مورد تکی با جست‌وجوی عنوان از route هسته `GET /wp/v2/search?type=post&subtype=any` (فقط منتشرشده‌ها، با nonce `wp_rest` موجود در `AISAAutoPilotAI`) پیدا می‌شود و به‌صورت ردیف قابل‌حذف با `input[type=hidden]` می‌نشیند؛ عنوان نتیجه با `DOMParser` به متن تبدیل و با `textContent` نوشته می‌شود. تایپ در کادر جست‌وجو نوار «Unsaved changes» را روشن نمی‌کند و Enter فرم را ارسال نمی‌کند. هر فهرست پیش از چک‌باکس‌ها یک مقدار خالی می‌فرستد تا خالی کردن آن ذخیره شود. برگهٔ Widget با ردیف `[data-sp-widget-where]` خلاصه‌ای می‌دهد (`everywhere` یا `limited`) که فقط قاعده‌های هنوز معتبر را می‌شمارد. پیش از این، خواننده `widget_exclude_ids` را می‌خواند در حالی که پیش‌فرض `exclude_ids` بود، `exclude_pages` را خوانده ولی به کار نمی‌برد، شناسهٔ صفحهٔ دسته را با شناسهٔ نوشته مقایسه می‌کرد، و هیچ فرمی این کلیدها را نمی‌نوشت. جزئیات کلیدها در `DATA_MODEL_FA.md`؛ آزمون: `tests/wordpress_widget_visibility_integration.php`.
  - **پیش‌فرض‌های نسخهٔ 2 و مهاجرت:** `Settings::update()` با هر ذخیره کل `widget_defaults()` را می‌نویسد، پس مقدار ذخیره‌شده لزوماً انتخاب مدیر نیست. `Settings::migrate_widget_defaults()` در `Plugin::boot()` یک‌بار اجرا می‌شود: اگر `widget.defaults_revision` کمتر از `Settings::WIDGET_DEFAULTS_REVISION` (2) باشد، فقط مقدارهای دقیق قدیمیِ کلیدهای بدون نویسندهٔ UI جابه‌جا می‌شوند (`auto_open` 5 به 0، `window_width` 380 به 392، `window_height` 550 به 624) و نشان بازبینی نوشته می‌شود. `voice_auto` و `panel_radius` نویسندهٔ UI دارند و مهاجرت داده نمی‌شوند؛ فقط پیش‌فرضشان عوض شد (`false` و 20). سایتی که هرگز تنظیمات ذخیره نکرده option تازه‌ای نمی‌گیرد.
  - **متن پیش‌فرض به زبان سایت:** `Settings::widget_copy()` عنوان، زیرعنوان، greeting و `conversation_notice` را برمی‌گرداند. مقدار ذخیره‌شده‌ای که برابر پیش‌فرض انگلیسی `widget_defaults()` است با ترجمهٔ `Settings::widget_default_copy()` جایگزین می‌شود و متنی که مدیر نوشته دست نمی‌خورد. renderer، `AISAAutoPilotWidget.greeting`، `GET /widget/settings` و پیش‌نمایش‌های مدیر همین را می‌خوانند. customiser پیش‌فرض دست‌نخورده را ترجمه‌شده نشان می‌دهد و اگر همان متن ترجمه‌شده ذخیره شود، پیش‌فرض انگلیسی را ذخیره می‌کند تا با زبان سایت عوض شود (F-05).
  - پرسش به `POST /widget/ask` می‌رود، با 20 درخواست در ساعت، و از `ChatEngine` عبور می‌کند.
  - پاسخ منبع واقعی را زیر خود نشان می‌دهد؛ خط «From …» فقط از `sources` پاسخ ساخته می‌شود و پیوندش فقط `http` یا `https` است.
- **گفت‌وگو (T10):**
  - **موفقیت:** فقط پاسخی با `ok` درست و `answer` ناخالی نوبت دستیار است. هر پاسخ دیگر حباب خطا با `message` سرور (یا پیام عمومی) است. تا T10، پاسخ 400 `empty` کلید `answer` داشت و به‌عنوان سخن دستیار نمایش داده می‌شد (F-14)؛ اکنون `message` دارد.
  - **Markdown امن:** `renderAnswer()` زیرمجموعه‌ای را که engine تولید می‌کند به عنصر واقعی تبدیل می‌کند: پاراگراف (`.spw-md-p`)، فهرست نقطه‌ای و شماره‌دار (`ul|ol.spw-md-list`)، عنوان `#` (`.spw-md-h`)، `**bold**`، `*italic*`، `` `code` `` (`.spw-code`) و `[label](url)`. هر گره با DOM API ساخته می‌شود و `innerHTML` استفاده نمی‌شود؛ پیوندی که `http` یا `https` نیست همان متن خام می‌ماند. بلوک کد fenced و جدول پشتیبانی نمی‌شوند و متن خام می‌مانند (F-13).
  - **خواندن با صدا:** همان پیمایش، متن سادهٔ پاسخ را به ترتیب خواندن می‌سازد و هر `.spw-word` جای شروع و پایان خود را در آن رشته نگه می‌دارد؛ `speakText()` همان متن ساده را می‌خواند و `onboundary` کلمهٔ درست را برجسته می‌کند. پیش از T10 همهٔ spanها با offset صفر مقایسه می‌شدند و فقط کلمهٔ اول برجسته می‌شد.
  - **`clarify_options`:** وقتی engine چند موضوع ممکن را نام می‌برد، زیر پاسخ گروه `[data-spw-clarify]` با دکمه‌های `[data-spw-clarify-option]` (حداکثر 5، هر کدام 120 نویسه) ساخته می‌شود. این برچسب‌ها را مدل نوشته، پس کلیک فقط composer را پر و focus می‌کند و چیزی ارسال نمی‌شود (D-24).
  - **chipها و پرسش‌های ستون:** متنی است که مدیر نوشته و با کلیک بی‌درنگ ارسال می‌شود (D-24).
  - **typing:** `#spw-typing` فقط در طول درخواست واقعی وجود دارد.
  - **Stop:** در حالت busy دکمهٔ ارسال برچسب «Stop waiting for the reply» دارد. کلیک فقط `fetch` را abort می‌کند؛ سرور پرسش را دارد، پاسخ را کامل می‌کند، مصرف را می‌شمارد و با logging روشن نوبت را ثبت می‌کند. برای همین یادداشت `p.spw-note[data-spw-note="stopped"]` در thread می‌گوید که سایت ممکن است پاسخ را تمام کند ولی اینجا نشان داده نمی‌شود. بستن پنل هنگام انتظار هم abort می‌کند ولی یادداشتی نمی‌گذارد. یادداشت‌ها `.spw-msg` نیستند و در متن گفت‌وگوی انتقال فرستاده نمی‌شوند (F-07).
  - **Try again:** فقط برای خطای گذرا (قطع شبکه، 503 `assistant_unavailable`، کدهای ناشناخته). برای `rate_limited`، `usage_cap_reached`، `question_too_long`، `empty` و `widget_disabled` دکمه‌ای نیست، چون همان درخواست دوباره همان پاسخ را می‌گیرد (`NO_RETRY` در `widget.js`، F-07).
  - **جهت متن:** حباب هر نوبت، گزینهٔ clarify و یادداشت در JS، و greeting، chip، پرسش ستون، عنوان و زیرعنوان header و متن nudge در PHP `dir="auto"` دارند، پس متن لاتین در سایت فارسی (و برعکس) با جهت خودش خوانده می‌شود (F-06). عنوان، زیرعنوان، متن nudge و پرسش ستون با وجود `dir="auto"` هم‌ترازی را از `dir` ریشهٔ ویجت می‌گیرند (`.spw-root[dir="rtl"] … { text-align: right }`)، تا عنوان لاتین در سایت فارسی کنار آواتار و هم‌لبه با زیرعنوان بماند؛ `text-align: match-parent` در Chrome پشتیبانی نمی‌شود (F-24، T13).
  - **composer (T13):** در markup `dir` ندارد. `autoGrow()` در `widget.js`، که هر نوشتن در کادر (تایپ، گزینهٔ clarify، transcript، خالی شدن پس از ارسال و عوض شدن placeholder هنگام ضبط) از آن می‌گذرد، `dir="auto"` را فقط وقتی متن هست می‌گذارد و با خالی شدن برمی‌دارد، چون Chrome کادر خالی `dir="auto"` را ltr حل می‌کند و placeholder فارسی چپ‌چین می‌شد. کادر خالی به اندازهٔ placeholder بلند می‌شود، پس placeholder بلندتر از کادر (گوشی باریک، ترجمهٔ بلند) کامل دیده می‌شود.
  - **حالت ضبط (F-25، بازنگری به درخواست مالک پس از T13):** `setRecordingUi()` کلاس `spw-composer__box--recording` را می‌گذارد (قاب و حلقهٔ coral)، `#spw-rec` را نشان می‌دهد (نقطهٔ چشمک‌زن و `#spw-rec-time` با زمان m:ss، هر 250ms، به رقم `document.documentElement.lang` از راه `Intl.NumberFormat`؛ `aria-hidden`)، و روی هر دو دکمهٔ میکروفن `.spw-mic__ico` را با مربع `.spw-mic__stop` عوض می‌کند و `aria-label` و `title` را «Stop recording» می‌گذارد. placeholder ضبط `i18n.listening` یک‌خطی است («Listening…»)، پس کادر در موبایل و دسکتاپ هم‌ارتفاع می‌ماند. جملهٔ کامل `i18n.recording` («… tap the mic again to stop») فقط در ناحیهٔ status برای صفحه‌خوان است. `.spw-mic` transition خودش را دارد تا `transition: all` دکمه‌های قالب coral را آهسته نکند.
- **ظاهر:** از `widget` در تنظیمات می‌آید. متغیرهای CSS `--spw-radius`، `--spw-msg-radius` و `--spw-float-radius` از `panel_radius`، `msg_radius` و `bubble_radius` ساخته می‌شوند و `--spw-w` و `--spw-h` از `window_width` و `window_height`. پنل پیش‌فرض 392×624 با گوشهٔ 20px است، مانند موکاپ.
  - header نقطهٔ سبز «آنلاین» ندارد، چون هیچ دادهٔ واقعی آماده بودن دستیار را نمی‌سنجد (F-03).
  - دکمه‌های آیکنی اندازهٔ دیداری موکاپ را نگه می‌دارند (34px در header و دکمهٔ پرش، 36px در composer، 24px برای بستن nudge) و یک `::before` شفاف ناحیهٔ کلیک را به دست‌کم 44×44px می‌رساند، بدون هم‌پوشانی با دکمهٔ کناری. هیچ نگه‌دارندهٔ این دکمه‌ها نباید `overflow: hidden` داشته باشد (F-04).
  - **کنتراست (T13):** متن کمکی کوچک (برچسب روز، placeholder، خط منبع، footnote، برچسب ستون، دکمهٔ بستن nudge) یک توکن دارد: `--spw-faint` برابر `#686b8a` در تم روشن و `#9098a6` در تم تیره، که روی هر سطح دست‌کم 4.5:1 است (F-23). رنگ ثابت کم‌رنگ تازه به این متن‌ها ندهید؛ matrix مرورگر 22 جفت متن و آیکون را اندازه می‌گیرد.
  - زیر `prefers-reduced-motion: reduce` همهٔ animation و transitionها خاموش‌اند، حلقهٔ تپندهٔ launcher پنهان است و hover آن جابه‌جا نمی‌شود.
- **حالت full screen (T09):**
  - دکمهٔ `#spw-expand` کلاس `spw-panel--full` را می‌گذارد، `aria-modal` را `true` می‌کند، `#spw-scrim` را نشان می‌دهد و برچسب و `title` دکمه را به «Exit full screen» عوض می‌کند.
  - سه بازهٔ عرض: بیشتر از 768px قاب مرکزی `min(940px, 100vw - 52px)` در `min(760px, 100dvh - 52px)` با scrim و ستون «پرسش‌های پرتکرار»؛ 641 تا 768px تمام viewport بدون scrim و بدون ستون، با بازگشت chipها به thread؛ 640px و کمتر پنل از پیش sheet تمام‌صفحه است و دکمهٔ expand پنهان می‌شود. آستانهٔ `matchMedia` در `widget.js` همان 768px است.
  - ستون پرسش‌ها فقط `widget.suggestions` ذخیره‌شده را نشان می‌دهد و بخش «Can’t find it?» فقط وقتی دست‌کم یک کانال انتقال آماده است؛ هیچ پرسش نمونه‌ای ساخته نمی‌شود.
  - **قفل scroll:** `lockPageScroll()` مقدار inline `overflow` روی `html` و `body` را پیش از قفل snapshot می‌کند، `hidden` می‌گذارد و هنگام خروج از full screen، کلیک scrim یا بسته شدن پنل دقیقاً همان را بازمی‌گرداند. بدون snapshot، باز کردن قفل کاری نمی‌کند. قفل `overflow` جای scroll را نگه می‌دارد، پس بستن پنل صفحه را همان‌جا برمی‌گرداند.
  - **پنل modal (F-17، برگشت تصمیم T13 به درخواست مالک):** `syncModal()` تنها نویسندهٔ `aria-modal` و تنها فراخوانندهٔ `lockPageScroll()` است. `isModal()` یعنی پنل باز است و یا full screen است یا `sheetQuery` (`matchMedia('(max-width: 640px)')`، همان آستانهٔ CSS) برقرار است. `setOpen()`، `setFull()` و شنوندهٔ `change` همان query (برای چرخش گوشی) آن را صدا می‌زنند. پس sheet موبایل هم مثل full screen `aria-modal="true"` است، Tab در آن می‌پیچد و صفحهٔ پشت قفل است؛ پنل docked در 641px و بیشتر غیر modal است و صفحه را آزاد می‌گذارد. `#spw-msgs` همچنان `overscroll-behavior: contain` دارد، که در iOS پیش از 16 (که `overflow: hidden` را برای لمس نادیده می‌گیرد) تنها سد است.
  - **focus:** هنگام ورود، اگر focus بیرون از پنل باشد به خود پنل (`tabindex="-1"`) می‌رود؛ `trapFocus()` (هر وقت `isModal()`: full screen یا sheet موبایل) دکمه، `textarea`، `input`، `select`، پیوند و `[tabindex="0"]` دیدنی را می‌شمارد و Tab و Shift+Tab (از جمله از خود پنل) داخل پنل می‌پیچد. هنگام خروج، focus **فقط وقتی** به `#spw-expand` برمی‌گردد که کنترل focus‌دار ناپدید شده باشد (پرسش ستون) یا focus روی صفحه رها شده باشد (کلیک scrim)؛ composer نیمه‌نوشته focus را نگه می‌دارد. Escape اول از full screen و بعد از پنل خارج می‌شود و در پایان focus به launcher برمی‌گردد.
  - **دکمهٔ «آخرین پیام» (`#spw-jump`، F-26):** `syncJump()` در هر scroll گفت‌وگو، در frame بعد، دکمه را فقط وقتی thread در انتهاست پنهان می‌کند. اگر دکمه در همان لحظه focus داشته باشد، focus **پیش از** پنهان شدن به composer (`#spw-input`، کنترل بعدی در ترتیب Tab) می‌رود. بدون این کار، دکمهٔ پنهان‌شده focus را به `<body>` می‌انداخت: بیرون از پنل و بیرون از trap حالت modal. این ریشهٔ شکست گذرای «Tab از full screen بیرون رفت» در T13 بود: Tab به دکمه می‌رسید، در حالی که scroll نرم thread هنوز به انتها نرسیده بود. matrix آن را با ردیف‌های پرکنندهٔ موقت، قطعی بازتولید می‌کند.
- **صدا:** transcript-first است. متن پیاده‌شده در کادر پیام قرار می‌گیرد و بازدیدکننده ارسال می‌کند. خواندن پاسخ با صدای مرورگر فقط وقتی `voice_auto` روشن است؛ پیش‌فرض از T08 خاموش است (D-25) و مدیر آن را در tab Voice روشن می‌کند.
- **صدا (T12):** جریان کامل و صادقانه چنین است، و overlay تمام‌صفحهٔ موکاپ طبق D-02 ساخته **نشده** است.
  - **دروازهٔ سرور:** `POST /public/voice` علاوه بر Answer Desk، `widget.voice_enabled` را هم می‌سنجد و با میکروفن خاموش 403 `voice_disabled` می‌دهد — پیش از rate limiter و پیش از هر جست‌وجوی کلید (`PublicChatController::is_voice_enabled()`). پیش از T12 سایتی که voice را خاموش کرده بود همچنان transcribe عمومی می‌پذیرفت.
  - **دروازهٔ markup:** وقتی `voice_enabled` خاموش است نه `#spw-mic` رندر می‌شود، نه `#spw-voice` و نه ناحیهٔ وضعیت.
  - **اعلام وضعیت:** `<p class="spw-live" id="spw-voice-status" role="status" aria-live="polite" data-spw-voice-status>` در composer، تنها وقتی voice روشن است. `setVoiceStatus()` در `widget.js` تنها نویسندهٔ آن است و سه فاز واقعی را اعلام می‌کند: ضبط (جملهٔ کامل `recording`؛ placeholder از پیگیری T13 نسخهٔ کوتاه `listening` است)، `transcribing`، و `transcriptReady` که صریح می‌گوید متن در کادر پیام است و **هنوز چیزی ارسال نشده**. هر شش مسیر خطا (مرورگر بدون پشتیبانی، شکست ساخت `MediaRecorder`، رد دسترسی میکروفن، نبود صدا، رد سرور و خطای شبکه) ناحیه را خالی می‌کنند — خود خطا در `role="log"` اعلام می‌شود — و ارسال عمدی هم آن را پاک می‌کند. کلاس `.spw-live` بصری پنهان است ولی `display:none` نیست، چون ناحیهٔ پنهان‌شده خوانده نمی‌شود.
  - **چیزی خودکار ارسال نمی‌شود:** `sendAudio()` فقط `input.value` را پر می‌کند و focus را به کادر می‌برد؛ `send()` تنها از دکمهٔ Send، کلید Enter یا chip فراخوانده می‌شود. در مرورگر اثبات شد که کل جریان صوتی فقط یک `POST public/voice` می‌سازد و thread تا فشردن Send هیچ نوبت بازدیدکننده‌ای ندارد.
  - **بدون متن realtime:** حین transcribe کادر پیام خالی می‌ماند؛ زیرنویس زنده، barge-in، Mute و End ساخته نشده‌اند چون هیچ transport پیوسته‌ای در کد نیست.
  - **خواندن پاسخ با صدا:** `speakText()` همان خوانش سادهٔ پاسخ را می‌خواند و هر `.spw-word` جای خود (`start` و `end`) را در آن متن دارد، پس رویداد `onboundary` دقیقاً کلمهٔ در حال خواندن را روشن می‌کند (F-19، در T12 در مرورگر اثبات شد). فاز «در حال خواندن» عمداً اعلام نمی‌شود تا با خود موتور گفتار هم‌زمان روی صفحه‌خوان نیفتد.
  - **بدون تغییر:** سقف 45 ثانیه‌ای ضبط، آپلود یک‌جا، و مسیرهای مدیریتی `/chat/voice/transcribe`، `/forms/voice/transcribe` و `/efb/voice/transcribe` که به تنظیم ویجت بازدیدکننده ربطی ندارند و دروازهٔ تازه را نگرفتند.
- **آزمون قرارداد:** `tests/wordpress_front_chat_widget_render_integration.php` دروازه‌ها، `dir`، متغیرهای رنگ و ابعاد، پیش‌فرض‌های نسخهٔ 2 و مهاجرت، متن پیش‌فرض ترجمه‌شده در markup و `AISAAutoPilotWidget` و `GET /widget/settings`، کنترل «Open the chat by itself» و نبودن گیرندهٔ خصوصی یا اعتبارنامه در سطح بازدیدکننده را می‌سنجد. از T09 قرارداد ستون پرسش‌ها (سه حالت: با پرسش، بدون پرسش و با کانال آماده، و بدون هیچ‌کدام)، بلوک breakpoint 641 تا 768px، قاعدهٔ `:has` که chipها را برمی‌گرداند، آستانهٔ 768px در JS و قرارداد قفل scroll هم در همین فایل سنجیده می‌شوند. از T10 قرارداد گفت‌وگو هم: `dir="auto"` در markup، `voiceEnabled` و رشته‌های Stop و clarify در `AISAAutoPilotWidget`، پاسخ `message` بدون `answer` برای 400 `empty` و `question_too_long`، نبود درخواست settings در `widget.js`، فهرست `NO_RETRY`، یادداشت Stop، دروازهٔ `ok`، `renderAnswer` و اینکه گزینهٔ clarify فقط composer را پر می‌کند، و نبود `innerHTML` جز آیکون ثابت. از T11 قرارداد کارت انتقال هم: نبود دکمه، قالب و کارت در حالت not-ready، رد شدن `/widget/handoff` با `handoff_disabled` بدون هیچ ارسال، کانال تیک‌خوردهٔ ناتمام، یک قالب و دقیقاً دو دکمه در حالت ready، ناحیهٔ `role="status"`، نبود ادعای اپراتور و زمان پاسخ در متن کارت و در پیام تأیید، یکی بودن برچسب پیش‌فرض در renderer و فرم مدیر، نبود کلیدهای handoff در دادهٔ localize، قاعدهٔ `syncChips()` و متن رونوشت با مرزهای پاراگراف. از T12 قرارداد صدا هم: یک ناحیهٔ وضعیت polite وقتی voice روشن است و هیچ وقتی خاموش است، بصری پنهان و نه `display:none`، دو رشتهٔ فاز در دادهٔ localize، `setVoiceStatus()` به‌عنوان تنها نویسنده، هفت بار پاک شدن ناحیه، نبود فراخوان `send()` در `sendAudio()`، نبود overlay و `SpeechRecognition` و barge-in، قرارداد offset در `onboundary`، و دو حالت 403 `voice_disabled` و 400 `invalid_audio` روی `/public/voice` از T13 جهت composer، اندازهٔ placeholder، توکن کنتراست و هم‌ترازی متن مدیر، و از پیگیری T13 قرارداد `syncModal()` و sheet modal، markup `#spw-rec` و مربع توقف، رشتهٔ `listening` در دو زبان و قواعد CSS حالت ضبط، و قرارداد `syncJump()` که focus را پیش از پنهان کردن دکمهٔ «آخرین پیام» به composer می‌دهد (F-26) (**97 check**).
- **آزمون مرورگر (T13):** `tests/front_chat_widget_browser_matrix.cjs` با fixture `tests/local_front_chat_widget_browser_fixture.php` و check `tests/front_chat_widget_browser_check.cjs` جانشین checkهای موقت T08 تا T12 شد: `en_US` و `fa_IR` × سناریوهای `main`، `auto` و `quiet` × 1440 و 390، بازدیدکنندهٔ خارج‌شده روی صفحهٔ اول قالب فعال، با mock کامل `/widget/ask`، `/widget/handoff` و `/public/voice` و stub میکروفن و گفتار (**385 PASS / 0 FAIL**). جزئیات: `docs/QUALITY_AND_RELEASE_FA.md` و بخش «شواهد T13» در `docs/FRONT_CHAT_WIDGET_S3_3_AUDIT_AND_PLAN_FA.md`. در T14 همین matrix دوباره با 385/0 اجرا شد. پس از پیگیری T13 به درخواست مالک: 425/0، با سنجش حالت ضبط یک‌خطی و sheet modal (قفل صفحه، Tab، بازگشت جای scroll). پس از رفع F-26: **429 PASS / 0 FAIL**. `settle()` در check تا پایان انیمیشن‌های محدودِ در جریان صبر می‌کند، نه یک زمان ثابت.
- **بستهٔ S3.3 بسته شده است (T14، ۲۰۲۶-۰۹-۱۸):** قرارداد کامل ویجت، اختلاف‌های عمدی با موکاپ و محدودیت‌های باز — از جمله بی‌hook بودن `includes/Frontend/WidgetRenderer.php` و `assets/frontend/chat-widget.js|css` — در `docs/FRONT_CHAT_WIDGET_S3_3_EXECUTION_REPORT_FA.md` ثبت است. دو محدودیت آن گزارش پس از بستن حل شدند (T73): F-11 با کارت «Pages where the chat appears» و خوانندهٔ اصلاح‌شده، و F-12 با تعریف دوازده کلید بازنشسته و کنترل زندهٔ جانشین هرکدام در `DATA_MODEL_FA.md`.

### دو سطح تنظیم

| سطح | slug | چه چیزی را تغییر می‌دهد |
|---|---|---|
| tab Widget در Answer Desk | `aisaautopilot-conversations&tab=widget` | نمایش ویجت و رنگ تأکیدی، با ذخیرهٔ فوری از `/widget/admin-settings`؛ خلاصهٔ فقط‌خواندنی «Pages where the chat appears» با پیوند «Choose pages» |
| سفارشی‌سازی پیشرفته | `aisaautopilot-widget` | جایگاه، تم، **صفحه‌هایی که گفت‌وگو در آن‌ها دیده می‌شود** (`widget_visibility` و `exclude_types`، `exclude_cats`، `exclude_ids`)، رنگ برند و دوازده رنگ بخش‌ها، عنوان و زیرعنوان، چهار آیکون PNG، پیام آغاز و پرسش‌های پیشنهادی، باز شدن خودکار (`auto_open`)، گوشه‌های پنل و پیام، شکل دکمهٔ شناور، متن دکمه و متن بالای دکمهٔ انتقال، ثبت گفت‌وگو و متن اطلاع‌رسانی. `ChatWidgetSettings::save()` فقط همین کلیدها را می‌نویسد. |

## ثبت گفت‌وگوها

وقتی `widget.conversation_logging_enabled` روشن است:

- خود `POST /widget/ask` پس از هر پاسخ نوبت را با `session_token` در `aisaautopilot_widget_chats` ثبت می‌کند (`VisitorConversationStore::append_turn()`) و `conversation_id` را برمی‌گرداند. `POST /widget/conversation` چیزی ثبت نمی‌کند، همیشه `messages: []` برمی‌گرداند و `widget.js` آن را صدا نمی‌زند (وارسی T07).
- متن `conversation_notice`، که حداکثر 240 نویسه است، زیر کادر پیام و footnote به بازدیدکننده نمایش داده می‌شود (`.spw-recording-notice`).
- مدیر گفت‌وگوها را در tab Conversations و با `/widget/conversations` می‌بیند.

وقتی ثبت خاموش است، گفت‌وگو ذخیره نمی‌شود، اما پرسش بی‌پاسخ همچنان بدون شناسهٔ پرسنده در `KbGaps` ثبت می‌شود.

## انتقال به انسان

1. **درخواست:** بازدیدکننده دکمهٔ انتقال را می‌زند و `POST /widget/handoff` فرستاده می‌شود.
   - `widget.js` (`requestHandoff`) این‌ها را می‌فرستد: `messages` (نقش و متن هر نوبت)، `conversation_id`، و اطلاعات تماسی که کارت انتقال پرسیده است.
   - **متن هر نوبت (از T11):** پاسخ دستیار از T10 با `renderAnswer()` به عنصرهای واقعی تبدیل می‌شود، پس `textContent` حباب مرز پاراگراف و آیتم فهرست را از دست می‌داد («… yet.Would you like …»). `addMessage()` همان «خوانش سادهٔ» پاسخ (که برای خواندن با صدا هم ساخته می‌شود) را روی خود گره نگه می‌دارد و رونوشت آن را می‌فرستد؛ اگر نبود، `textContent` جایگزین است. حباب خوش‌آمد، حباب‌های خطا و یادداشت‌های صادقانه (`[data-spw-note]`) در رونوشت نیستند، چون کسی آن‌ها را نگفته است.
   - **کارت فقط با کانال آماده:** `#spw-handoff-template` و هر دو دکمهٔ `data-spw-handoff` (زیرنویس composer و ستون full screen) فقط وقتی رندر می‌شوند که `Settings::answer_desk_handoff_available()` درست باشد. اگر پاسخ موتور `handoff_offer` بفرستد ولی قالب نباشد، `showHandoffCard()` بی‌صدا برمی‌گردد، پس در حالت not-ready هیچ دکمه‌، قالب و کارتی وجود ندارد.
   - **chipها هنگام کارت (T11، F-10):** `syncChips()` تنها نویسندهٔ `#spw-chips.hidden` است و آن را با «دستیار در حال نوشتن است» یا «کارت انتقالِ پاسخ‌نداده در thread است» پنهان می‌کند. با «Not now» یا پس از ارسال (`.is-sent`) chipها برمی‌گردند، چون دستیار همچنان کار می‌کند. موکاپ هم در حالت Handoff هیچ chipی نشان نمی‌دهد.
   - **تأیید صادقانه:** پس از پاسخ `ok` کارت سبز می‌شود، هر دو دکمه می‌روند و ناحیهٔ `role="status"` فقط «Sent — we will be in touch.» را می‌گوید: بدون ادعای «اپراتور وصل شد»، بدون نشان حضور و بدون زمان پاسخ.
2. **اطلاعات تماس (از ۲۰۲۶-۰۹-۱۶):** مدیر در tab Human handoff، بخش «Details to ask for»، برای هر یک از نام، ایمیل، شمارهٔ موبایل و نشانی وب‌سایت یکی از «Off»، «Optional» یا «Required» را انتخاب می‌کند.
   - **ذخیره:** `widget.handoff_fields` با `POST /handoff/visitor-fields`. هر چهار مورد باید فرستاده شوند و پیش‌فرض همه `off` است. `Settings::handoff_fields()` مقدار نامعتبر را `off` می‌خواند.
   - **کارت ویجت:** `ChatWidget::handoff_asks()` فقط موردهای روشن را در قالب `#spw-handoff-template` رندر می‌کند، با `type`، `autocomplete` و `inputmode` درست. مورد اختیاری برچسب «(optional)» دارد و مورد الزامی `required` و `aria-required`. وقتی همه خاموش‌اند، متن قدیمی کارت («Add your email or phone in a message first») می‌ماند.
   - **برچسب پیش‌فرض دکمه:** `handoff_text` کلید پیش‌فرض `widget_defaults()` نیست. اگر ذخیره نشده یا خالی باشد، renderer و فرم tab Widget هر دو از T11 همان `Talk to a human` ترجمه‌شده را نشان می‌دهند (پیش از آن فرم مدیر `Talk to human` را pre-fill می‌کرد و همان را ذخیره می‌کرد).
   - **بررسی در مرورگر:** `fieldProblem()` پیش از ارسال خطا را زیر همان فیلد نشان می‌دهد (`aria-invalid` و `aria-describedby`) و فوکوس را روی نخستین فیلد نادرست می‌گذارد. Enter در فیلد ارسال می‌کند. اگر سرور فیلدی را رد کند، همان فیلد علامت می‌خورد.
   - **سرور:** `HandoffContact::collect()` فقط موردهای روشن را از درخواست می‌خواند؛ مقدار موردهای خاموش نادیده گرفته و ذخیره نمی‌شود.
     - نام: `sanitize_text_field`، حداکثر 120 نویسه.
     - ایمیل: `is_email`.
     - موبایل: رقم‌های فارسی و عربی به لاتین تبدیل می‌شوند؛ فقط `+` آغازین، رقم، فاصله، نقطه، خط تیره و پرانتز، و 6 تا 15 رقم.
     - وب‌سایت: بدون scheme با `https://` کامل می‌شود؛ فقط `http` و `https`، بدون user و password، host با دامنهٔ سطح بالا (IDN هم پذیرفته می‌شود).
3. **دروازه‌ها:**
   - `handoff_disabled` برمی‌گردد اگر انتقال در دسترس نباشد.
   - rate limit پنج درخواست در ساعت است.
   - جزئیات نامعتبر 400 با کد `invalid_email`، `invalid_phone`، `invalid_website` یا `invalid_name` و فهرست `fields` می‌دهد. نبود مورد الزامی 400 با `missing_contact` و `fields` می‌دهد.
   - نبود پیام `no_messages` برمی‌گرداند.
   - `ChatWidgetController::clean_handoff_messages()` فقط 12 پیام آخر و از هر پیام 1200 نویسه را نگه می‌دارد.
   - **قرارداد متن ساده:** متن همان‌طور که تایپ شده ذخیره می‌شود. UTF-8 نامعتبر و نویسه‌های کنترلی حذف می‌شوند و `\r\n` به `\n` تبدیل می‌شود. `sanitize_textarea_field` به کار نمی‌رود، چون `3<4` را به `3&lt;4` تبدیل می‌کند. هر خروجی متن را خودش escape می‌کند:

     | خروجی | escape |
     |---|---|
     | صندوق مدیر | `esc_html` در PHP و `textContent` در کشو |
     | ایمیل | `nl2br( esc_html() )` در `HandoffEmail::build_messages_html()` |
     | Telegram | `htmlspecialchars` با `double_encode=false` برای parse mode `HTML` |
     | پیامک | متن ساده |

     تا 2026-09-16 متن پیش از ذخیره encode می‌شد. به همین دلیل Telegram آن را دوبار escape می‌کرد (`&amp;lt;`) و `wp_strip_all_tags` در ایمیل بقیهٔ متن بعد از `<` را حذف می‌کرد. ردیف‌های قدیمی همان entityها را دارند و در صندوق به‌صورت `&lt;` دیده می‌شوند. مهاجرتی برایشان نوشته نشده است.
4. **ارسال:** `HandoffService::handoff( $messages, $contact )` پیام را به **همهٔ** کانال‌های فعال و پیکربندی‌شده می‌فرستد. صف ترتیبی هنوز وجود ندارد (T37).
   - ایمیل بخش اطلاعات تماس را با `HandoffEmail::build_contact_html()` نشان می‌دهد. وقتی بازدیدکننده ایمیل گذاشته باشد، سرآیند `Reply-To` دارد تا پاسخ به همان ایمیل مستقیم به او برسد.
   - Telegram و پیامک خط‌های «Name»، «Email»، «Mobile» و «Website» را فقط برای موردهای پرشده دارند؛ در غیر این صورت «Contact: not provided».

   | کانال | روش ارسال |
   |---|---|
   | ایمیل | `HandoffEmail` با `wp_mail` به `handoff_email_to` یا ایمیل مدیریت |
   | Telegram | `HandoffTelegram` با Bot API و تقسیم پیام بلند |
   | پیامک | `HandoffTwilio` با Twilio |

5. **ثبت:** نتیجه در `HandoffInbox::record()` (همراه `visitor_phone` و `visitor_website`) با وضعیت `new` (دست‌کم یک کانال پذیرفت) یا `failed` ذخیره می‌شود. پاسخ به بازدیدکننده شناسهٔ `reference` دارد.
6. **صندوق:** مدیر درخواست را در `aisaautopilot-handoffs` یا tab انتقال صفحهٔ پیشرفتهٔ ویجت می‌بیند:
   - فیلترهای All، چهار وضعیت و Archived؛
   - ردیف: ایمیل، و اگر نبود موبایل یا وب‌سایت؛ وگرنه «No contact details left»؛
   - کشوی گفت‌وگو با 12 پیام آخر و فهرست اطلاعات تماس: ایمیل (`mailto:`)، موبایل (`tel:`)، وب‌سایت (پیوند فقط برای `http` و `https`، با `target="_blank"` و `rel="noopener noreferrer nofollow"`)؛
   - تغییر وضعیت با `/handoffs/{id}/status`؛
   - پاسخ ایمیلی با mailto فقط وقتی ایمیل معتبر است.

   وضعیت فقط برای تیم است و به بازدیدکننده نمایش داده نمی‌شود.

7. **بایگانی (از ۲۰۲۶-۰۹-۱۶):** بایگانی جدا از وضعیت است و ستون `archived_at` (schema نسخهٔ 2) آن را نگه می‌دارد.
   - **یک گفت‌وگو:** دکمهٔ «Archive» یا «Move back to inbox» در کشو، با `POST /handoffs/{id}/archive` و `archived`. وضعیت و تاریخ آخرین تغییر عوض نمی‌شوند، پس گفت‌وگو پس از برگشت در همان جای فهرست است. کشو یادداشت «Archived … It no longer counts as waiting and keeps its status» را نشان می‌دهد.
   - **همه یک‌جا:** دکمهٔ «Archive resolved» در پایین کارت، فقط وقتی گفت‌وگوی حل‌شده‌ای در صندوق هست. پس از تأیید در dialog مشترک، `POST /handoffs/archive-resolved` می‌فرستد. هیچ چیزی حذف نمی‌شود.
   - **شمارش:** `count_new()` و `count_failed()` گفت‌وگوهای بایگانی‌شده را نمی‌شمارند، پس نوار «Waiting for a person»، حباب منو و نمای کلی هم آن‌ها را منتظر نمی‌دانند. صندوق تا 100 گفت‌وگوی فعال و جدا تا 100 گفت‌وگوی بایگانی‌شده بار می‌کند.
   - **گفت‌وگوهای قدیمی:** ردیف‌هایی که پیش از قرارداد متن ساده ثبت شده‌اند و `&lt;` نشان می‌دهند، به‌جای مهاجرت با همین بایگانی از فهرست بیرون می‌روند (تصمیم مالک).
   - **toast:** تا کشو باز است، toast بالاتر از دکمه‌های پایین کشو (در صفحهٔ کوچک) یا در وسط بخش آزاد صفحه (در صفحهٔ بزرگ) نمایش داده می‌شود تا «Move back to inbox» را نپوشاند.

- **آزمون‌ها:**
  - `tests/wordpress_handoff_inbox_integration.php` (19 بررسی): فیلترها، اطلاعات تماس و رد نشانی غیر وب، بایگانی و برگشت، شمارش‌ها و «Archive resolved» (گفت‌وگوهای حل‌شدهٔ دیگر سایت پس از آزمون برگردانده می‌شوند).
  - `tests/wordpress_answer_handoff_settings_integration.php` (10 بررسی): مسیر `handoff/visitor-fields`، مجوز، رد مقدار نامعتبر، مجموعهٔ ناقص و کلید ناشناخته، و رندر بخش «Details to ask for».
  - `tests/Unit/HandoffContactTest.php`: قواعد موبایل، وب‌سایت و `collect()`.
  - `handoff_inbox_browser_matrix.cjs` و `answer_handoff_browser_matrix.cjs` در `en_US` و `fa_IR`، 1440 و 390 پیکسل؛ دومی کارت ویجت را هم با پاسخ ساختگی `/widget/handoff` می‌آزماید.
- **آزمون سرتاسری:** `tests/wordpress_widget_handoff_flow_integration.php` (21 بررسی) مسیر واقعی REST را اجرا می‌کند:
  - ایمیل با `pre_wp_mail` گرفته می‌شود و Telegram با پاسخ ساختگی `pre_http_request` جواب می‌گیرد. هیچ ایمیل، پیامک یا درخواست شبکه‌ای واقعی فرستاده نمی‌شود.
  - می‌سنجد: هر دو کانال، کانال خاموش، شکست یک کانال (`new`)، شکست همهٔ کانال‌ها (`failed` و `handoff_failed`)، نادیده ماندن اطلاعات تماس وقتی همهٔ موردها خاموش‌اند، ذخیره و ارسال موردهای روشن (موبایل با رقم لاتین، وب‌سایت با `https`، `Reply-To`)، رندر فیلدهای کارت ویجت، متن دارای markup و متن فارسی با نیم‌فاصله در صندوق، ایمیل و Telegram، و رد شدن `missing_contact`، `invalid_email`، `invalid_phone`، `invalid_website`، `no_messages`، `handoff_disabled` و `rate_limited` بدون ثبت و بدون تماس با کانال.

- **پیکربندی کانال‌ها:** در tab Human handoff (`AnswerDeskHandoffPage`) با `/handoff/admin-settings` انجام می‌شود. اسرار رمزشده و جدا ذخیره می‌شوند.
- **بررسی اتصال:** دکمهٔ «Check connection» فقط اعتبار را می‌سنجد و پیام انتقال نمی‌فرستد.
- **کانال‌های قفل:** WhatsApp، Slack، Discord و webhook transport واقعی ندارند و قفل‌اند (T46؛ دامنهٔ تازه در T65 تا T69).

> **برنامه، نه وضعیت فعلی.** آنچه بالا آمد کل رفتار امروز است: یک اعلان یک‌طرفه بدون پذیرش، بدون نیرو، بدون شیفت و بدون پاسخ دوطرفه. تبدیل این بخش به یک میز پشتیبانی کامل (پنل پاسخگو در wp-admin با سطح دسترسی جدا، دپارتمان، جدول زمان‌بندی، پذیرش و ارجاع و پیوستن چند نفر، و اعلام «چه کسی وارد گفتگو شد» به بازدیدکننده) در بستهٔ S7 برنامه‌ریزی شده است: `docs/HUMAN_SUPPORT_S7_AUDIT_AND_PLAN_FA.md` و ردیف‌های T57 تا T72 رودمپ. هیچ‌کدام هنوز اجرا نشده‌اند و این سند تا اجرای هر task دربارهٔ آن ساکت می‌ماند.
- **نوار صندوق در tab انتقال:**
  - «Waiting for a person» یا «Nobody is waiting» را با شمار واقعی `new` و `failed` از `HandoffInbox` نشان می‌دهد، با پیوند «Open inbox».
  - وقتی `Settings::answer_desk_handoff_available()` نادرست است، می‌گوید به بازدیدکنندگان گزینهٔ گفت‌وگو با یک فرد داده نمی‌شود و درخواست‌های قبلی در صندوق می‌مانند.
  - در این حالت درخواست تازه‌ای ثبت نمی‌شود، چون `/widget/handoff` پیش از ثبت `handoff_disabled` برمی‌گرداند.

## کنسول «Ask your knowledge base»

- **صفحه و گفت‌وگوها:** `ChatPage` alias `aisaautopilot-chat` است. گفت‌وگوها مخصوص همان مدیرند (`ConversationManager` هر خواندن و حذف را با `user_id` محدود می‌کند) و بازدیدکننده آن‌ها را نمی‌بیند.
- **آماده بودن:** `data-sp-chat-ready` فقط وقتی `1` است که provider پیکربندی شده و دانش سایت مدخل داشته باشد.
  - در غیر این صورت نوار «Asking is locked» (`data-sp-chat-notice="provider"` یا `"knowledge"`) علت واقعی را می‌گوید. اگر محتوا ایندکس شده باشد، شمار آن را هم می‌آورد.
  - خوشامد عنوان و دکمهٔ مسیر حل را دارد: Setup & Connection یا Site Knowledge، و «See what is indexed» وقتی محتوا هست.
  - composer، میکروفون و پیشنهادها با آیکون قفل غیرفعال‌اند و زیر کادر «Nothing is sent while this is locked.» می‌آید.
- **کارت خطا (`chat.js`):** عنوان «No answer came back» دارد و متن پرسش به composer برمی‌گردد.

  | وضعیت | پیام | اقدام |
  |---|---|---|
  | `status: error` در پاسخ `ok` (`ChatEngine` خطای provider را گرفته) | «The AI service did not return an answer…» | «Ask again» و «Open Health» |
  | 429 `usage_cap_reached` | پیام سرور | «Open Usage & Cost»، بدون retry |
  | پاسخ ناموفق دیگر | پیام سرور | «Ask again» |
  | خطای شبکه | «Network error. Please try again.» | «Ask again» |

  - «Ask again» همان پرسش را فقط با کلیک دوباره می‌فرستد.
  - پاسخ ذخیره‌شده با `status: error` هنگام باز کردن دوبارهٔ گفت‌وگو هم کارت خطا نمایش داده می‌شود.
- **خط وضعیت:** واقعی است. یکی از این‌ها را نشان می‌دهد: «Answers from N indexed items · provider · model»، متن سقف مصرف، یا «Not connected».
- **پیام‌ها:**
  - پاسخ‌ها با Markdown ساده (پاراگراف، فهرست، عنوان، پیوند http یا https) رندر می‌شوند.
  - منبع‌ها با `target="_blank"` و `rel="noopener noreferrer"` باز می‌شوند.
  - `not_found` نشان «Not in your content» و پیوند به پرسش‌های بی‌پاسخ دارد.
  - اگر انتقال آماده باشد، یادداشت می‌دهد که بازدیدکننده در این نقطه پیشنهاد گفت‌وگو با یک فرد می‌گیرد. کنسول خودش درخواست انتقال نمی‌سازد.
- **عنوان گفت‌وگو:** از اولین پرسش ساخته می‌شود. placeholder ترجمه‌شده، مثلاً در fa_IR، هم جایگزین می‌شود.
