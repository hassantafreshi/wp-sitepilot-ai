# گزارش اجرای S3.3 — Front Chat Widget

تاریخ پایان: ۲۰۲۶-۰۹-۱۸  
مرجع تصویری: `mockups/design_handoff_admin_redesign/Front Chat Widget.dc.html` با پنج حالت Bubble، Panel، Full screen، Voice mode و Handoff در LTR و RTL  
سطح واقعی: ویجت بازدیدکنندهٔ سایت عمومی — `includes/Frontend/ChatWidget.php` با `assets/frontend/widget.js` و `widget.css`  
ممیزی و ریزتسک‌ها: `FRONT_CHAT_WIDGET_S3_3_AUDIT_AND_PLAN_FA.md`  
taskهای رودمپ: T07، T08، T09، T10، T11، T12، T13 و T14

## نتیجه

ویجت بازدیدکننده پیش از این بسته روی renderer واقعی به موکاپ نزدیک شده بود؛ کار S3.3 ساختن دوبارهٔ آن نبود، **بستن ۲۵ یافتهٔ اندازه‌گیری‌شده (F-01 تا F-25)، صادقانه کردن رابط و اثبات همه‌چیز در مرورگر** بود. هر ۲۵ یافته بسته شد: ۲۲ تا با اصلاح کد و سه تا با تصمیم مستند (F-11 و F-12 در همین گزارش، F-17 با تصمیم «قفل نمی‌شود»).

داده‌های نمایشی موکاپ وارد محصول نشدند: فروشگاه Northwind، گفت‌وگوی از پیش نوشته، «Popular questions» نمونه، نشان قرمز «1» روی حباب و چرخهٔ خودکار voice همه ابزار prototype ماندند. ویجت فقط پرسش‌های پیشنهادی واقعی مدیر، منبع واقعی citation و کانال‌های واقعاً آمادهٔ انتقال را نشان می‌دهد.

سه دستاورد مهم‌تر از هم‌خوانی بصری‌اند:

1. **ویجت دیگر روی هر بارگذاری هر صفحه خودش باز نمی‌شود** و هیچ درخواست REST‌ی پیش از باز کردن پنل نمی‌فرستد (F-01 و F-15).
2. **`POST /public/voice` دروازهٔ `widget.voice_enabled` گرفت، پیش از rate limiter** — سایتی که میکروفن را خاموش کرده بود پیش از این هم سهمیهٔ بازدیدکننده و هم فراخوان provider را خرج می‌کرد (F-09).
3. **کل رابط در مرورگر اثبات شد**، نه در ادعا: سه فایل آزمون دائمی با ۳۸۵ سنجه در دو زبان، سه سناریو و دو عرض.

S3.3 بسته شد. هیچ گیتی نسبت به خط مبنا افت نکرده است و منوی اصلی همچنان هشت آیتم D-23 است.

## تغییرات فنی

- **پیش‌فرض‌ها و مهاجرت تنظیمات** (`includes/Core/Settings.php`، T08، D-25): پیش‌فرض‌های نسخهٔ ۲ (`auto_open` صفر، `voice_auto` خاموش، پنل 392×624، گوشهٔ 20px) به‌همراه `defaults_revision` و `Settings::migrate_widget_defaults()` در boot. مهاجرت فقط کلیدهایی را جابه‌جا می‌کند که **هیچ نویسندهٔ UI ندارند** و مقدارشان دقیقاً همان پیش‌فرض قدیمی است؛ انتخاب صریح مدیر دست نمی‌خورد. علت وجود مهاجرت: `Settings::update()` با هر ذخیره کل `widget_defaults()` را در option می‌نویسد، پس تغییر پیش‌فرض به‌تنهایی روی سایت‌هایی که یک بار ذخیره کرده‌اند بی‌اثر است.
- **متن پیش‌فرض ترجمه‌پذیر** (`Settings::widget_copy()`، T08، F-05): وقتی عنوان، زیرعنوان، greeting یا متن اطلاع‌رسانی دقیقاً همان پیش‌فرض دست‌نخورده است، رشتهٔ ترجمه‌شدهٔ زبان جاری نشان داده می‌شود. renderer، دادهٔ localize، `GET /widget/settings`، customiser و هر دو پیش‌نمایش مدیر از همین یک منبع می‌خوانند.
- **دروازهٔ صدا پیش از سهمیه** (`includes/Api/PublicChatController.php`، T12، F-09): `is_voice_enabled()` تازه در `transcribe()` و 403 `voice_disabled`، **پیش از `RateLimiter::allow`**. قاعدهٔ «ترتیب دروازه‌ها» از همین‌جا وارد `docs/technical/SECURITY_FA.md` شد: بررسی «این قابلیت اصلاً روشن است؟» نباید سهمیهٔ بازدیدکننده را بسوزاند.
- **تغییر قرارداد REST** (`includes/Api/ChatWidgetController.php`، T10، F-14): پاسخ 400 `empty` دیگر کلید `answer` ندارد و `message` می‌فرستد؛ سمت کلاینت فقط `ok && answer` نوبت دستیار شمرده می‌شود. ثبت در `REST_API_FA.md`.
- **صفر درخواست پیش از باز شدن پنل** (`assets/frontend/widget.js`، `includes/Core/Plugin.php`، T10، F-15): `getSettings()` حذف شد و `voiceEnabled`، `voiceAuto` و `voiceName` از شیء محلی `AISAAutoPilotWidget` می‌آیند. route `GET /widget/settings` برای سازگاری حفظ شد ولی ویجت آن را صدا نمی‌زند.
- **پاک‌سازی دادهٔ localize** (`includes/Core/Plugin.php`، T11، F-22): چهار کلید handoff حذف شدند — دو تا را JS هرگز نمی‌خواند (در markup رندر می‌شوند) و دو تا تکرار `i18n.sending` و `i18n.sent` بودند. یعنی متن نوشتهٔ مدیر دیگر روی هر بارگذاری هر صفحه بی‌دلیل به بازدیدکننده فرستاده نمی‌شود.
- **Markdown امن با DOM** (`widget.js`، T10، F-13): `renderAnswer()` پاراگراف، فهرست، عنوان، bold، italic، کد درون‌خطی و پیوند را فقط با DOM API می‌سازد؛ هیچ `innerHTML` با دادهٔ مدل نیست و پیوند از `safeLink()` (فقط `http`/`https`، با `noopener noreferrer nofollow`) می‌گذرد.
- **قفل scroll قابل بازگردانی** (`widget.js`، T09، F-08): `lockPageScroll()` مقدار inline `overflow` روی `html` و `body` را **پیش از** قفل snapshot می‌کند و در هر سه راه خروج (Escape، کلیک scrim، بستن پنل) دقیقاً همان را برمی‌گرداند؛ بدون snapshot، باز کردن قفل بی‌اثر است.
- **focus trap کامل‌تر** (`widget.js`، T09): `trapFocus()` حالا `input` و `select` را هم می‌شمارد (کارت انتقال فیلد دارد) و Shift+Tab از خود پنل به آخرین کنترل می‌پیچد. آستانهٔ `matchMedia` از 900px به 768px با CSS هم‌تراز شد.
- **رونوشت درست برای تیم پشتیبانی** (`widget.js`، T11، F-20): `addMessage()` همان خوانش سادهٔ پاسخ رندرشده را روی گره نگه می‌دارد و `requestHandoff()` آن را می‌فرستد؛ پیش از این `textContent` مرز پاراگراف و فهرست را از دست می‌داد و تیم «… yet.Would you like me to …» می‌گرفت.
- **اعلام وضعیت صدا** (`ChatWidget.php`، `widget.js`، T12، F-16): ناحیهٔ `role="status" aria-live="polite"` با قلاب `data-spw-voice-status` که **فقط وقتی `voice_enabled` روشن است** رندر می‌شود؛ `setVoiceStatus()` تنها نویسندهٔ آن است و هر شش مسیر خطا و هر ارسال عمدی پاکش می‌کند.
- **کنتراست و جهت** (`widget.css`، T13، F-23 و F-24): سه رنگ کم‌رنگ پراکنده به یک توکن `--spw-faint` تبدیل شدند (روشن `#686b8a`، تیره `#9098a6`)؛ کمترین کنتراست اندازه‌گیری‌شده از 2.26:1 به 4.95:1 رسید. هم‌ترازی عنوان، زیرعنوان، متن nudge و پرسش ستون از `dir` ریشهٔ ویجت می‌آید، چون `dir="auto"` هر بلوک را با جهت متن خودش می‌چیند و Chrome `text-align: match-parent` را نمی‌شناسد.
- **لاگ اتمی و بقیهٔ سرور دست‌نخورده‌اند:** `ChatEngine`، `HandoffService`، `VoiceTranscriber`، `RateLimiter`، صندوق انتقال و سقف ۴۵ ثانیه‌ای ضبط در این بسته تغییر نکردند.
- **پیگیری پس از بستن، به درخواست مالک (۲۰۲۶-۰۹-۱۸):** دو اختلاف عمدی T13 کاربرپسند نبودند و رفع شدند. (۱) حالت ضبط: `setRecordingUi()` قاب coral، نقطهٔ زنده و زمان m:ss (`#spw-rec`) نشان می‌دهد و هر دو دکمهٔ میکروفن را به دکمهٔ توقف بدل می‌کند، پس placeholder یک‌خطی `i18n.listening` کافی است و کادر در موبایل و دسکتاپ هم‌ارتفاع می‌ماند (F-25). (۲) sheet موبایل: `syncModal()` پنل باز در 640px و کمتر را مثل full screen modal می‌کند، با `aria-modal`، نگه داشتن Tab و قفل صفحهٔ پشت، و جای scroll را نگه می‌دارد (F-17). جزئیات در بخش «پیگیری T13» فایل plan.
- **آزمون‌های تازه:** `tests/wordpress_front_chat_widget_render_integration.php` (تازه در T08، از 41/0 تا 92/0 و پس از پیگیری **96/0**) و سه فایل دائمی مرورگر در T13: `tests/local_front_chat_widget_browser_fixture.php`، `tests/front_chat_widget_browser_check.cjs` و `tests/front_chat_widget_browser_matrix.cjs`.

## تغییرات UI

- **launcher و nudge:** نقطهٔ سبز «آنلاین» حذف شد؛ ناحیهٔ کلیک همهٔ دکمه‌های آیکنی با pseudo-element به 44px رسید بی‌آنکه اندازهٔ دیداری موکاپ (34 و 36 و 24) عوض شود؛ `prefers-reduced-motion` حلقهٔ launcher و انیمیشن ورود پنل را خاموش می‌کند. با پیش‌فرض تازه، nudge واقعاً دیده می‌شود.
- **پنل:** 392×624 با گوشهٔ 20px مطابق موکاپ؛ در 641 تا 768px full screen کل viewport را می‌گیرد (بدون scrim و بدون ستون، با بازگشت chipها)؛ در 640px و کمتر همان sheet تمام‌صفحه.
- **گفت‌وگو:** هر حباب `dir="auto"` دارد؛ پاسخ به عنصر واقعی رندر می‌شود؛ خط منبع فقط با citation واقعی؛ گزینه‌های `clarify_options` زیر پاسخ فقط composer را پر می‌کنند (مرز D-24)؛ دکمهٔ انتظار «Stop waiting for the reply» است و پس از توقف یادداشت صادقانه می‌گوید سایت ممکن است پاسخ را تمام کند ولی اینجا نشان داده نمی‌شود؛ «Try again» فقط برای خطای گذرا می‌آید، نه برای سقف نرخ و سقف مصرف.
- **کارت انتقال:** تا وقتی کارت منتظر تصمیم بازدیدکننده است پرسش‌های پیشنهادی کنار می‌روند و با «Not now» یا پس از ارسال برمی‌گردند؛ کارت ارسال‌شده سبز می‌شود، هر دو دکمه را می‌اندازد و فقط «Sent — we will be in touch.» می‌گوید؛ در نبود کانال آماده نه دکمه، نه قالب و نه کارتی وجود دارد — حتی اگر پاسخ `handoff_offer` داشته باشد.
- **صدا:** transcript-first ماند. میکروفن هیچ درخواستی نمی‌سازد، کل جریان صوتی یک `POST /public/voice` است، متن دست‌نخورده در کادر پیام می‌نشیند و تا فشردن Send چیزی ارسال نمی‌شود. هنگام ضبط کادر پیام قاب coral، نقطهٔ زنده و زمان m:ss (به رقم زبان سایت) می‌گیرد، هر دو دکمهٔ میکروفن به دکمهٔ توقف بدل می‌شوند و placeholder یک‌خطی «Listening…» است، پس کادر در موبایل و دسکتاپ هم‌ارتفاع می‌ماند؛ جملهٔ کامل «… tap the mic again to stop» را صفحه‌خوان می‌شنود.
- **قلاب‌های آزمون:** `data-spw-voice-status`، `data-spw-note`، `data-spw-handoff`، `data-spw-handoff-field`، `data-spw-handoff-error` و `data-spw-auto-open` پایدارند و matrix از همین‌ها می‌سنجد، نه از متن انگلیسی.

## اختلاف‌های آگاهانه با موکاپ

| موکاپ | افزونه | دلیل |
|---|---|---|
| نشان «1» روی حباب | ساخته نشد | شمار پیام خوانده‌نشدهٔ واقعی وجود ندارد |
| نقطهٔ سبز آواتار و «Online · replies instantly» | بدون نقطه؛ زیرعنوان همان متن مدیر | هیچ دادهٔ واقعی آماده بودن دستیار را نمی‌سنجد (provider ممکن است تنظیم‌نشده یا در سقف باشد) |
| overlay تمام‌صفحهٔ voice با orb، زیرنویس زنده، barge-in، Mute، End و «Not recorded» | transcript-first با سه فاز واقعی در `role="status"` | D-02: هیچ session یا transport realtime در کد نیست (W-01، بسته) |
| دو دکمهٔ کانال در کارت انتقال | یک دکمهٔ «Send this chat to our team» به همهٔ کانال‌های آماده | W-02؛ انتخاب کانال و ماشین حالت واقعی در T61 (S7.4) |
| موکاپ «Not now» و فیلد تماس ندارد | دکمهٔ «Not now» و فیلدهای Off/Optional/Required (D-20) | بازدیدکننده باید بتواند بدون تعهد برگردد و تیم باید راه پاسخ داشته باشد |
| موکاپ حالت «ارسال‌شده» ندارد | کارت سبز بدون دکمه با یک سطر تأیید | بازدیدکننده باید ببیند چه چیزی فرستاده شد؛ بدون ادعای اپراتور، حضور یا زمان پاسخ |
| «Answers come from this store.» | «… and can be wrong.» (X-03) | صراحت دربارهٔ خطای ممکن |
| جای حباب در RTL آینه می‌شود | جای فیزیکی انتخاب‌شدهٔ مدیر (X-02) | انتخاب مدیر مقدم است |
| Popular questions نمونه | فقط پرسش‌های واقعی ذخیره‌شدهٔ مدیر | بدون دادهٔ نمونه |
| متن nudge دربارهٔ فروشگاه نمونه | متن greeting مدیر (ترجمه‌شده وقتی دست‌نخورده است) | متن موکاپ مال Northwind است |
| chip با کلیک بی‌درنگ ارسال می‌شود | همان (D-24) | متن را مدیر نوشته، نه AI؛ در مقابل، گزینهٔ ساختهٔ مدل فقط composer را پر می‌کند |
| قاب مرکزی full screen در هر عرضی | در 641 تا 768px تمام viewport، بدون scrim و ستون | ستون 270px بیش از یک‌سوم عرض را می‌گرفت (تصمیم breakpoint T07) |
| پنل شناور با حاشیه در 390px | sheet تمام‌صفحه در 640px و کمتر | کیبورد گوشی جای قاب شناور را می‌گیرد |
| پنل شناور غیر modal | sheet در 640px و کمتر modal است: `aria-modal="true"`، Tab داخل آن و صفحهٔ پشت قفل؛ بستن، صفحه را همان‌جا برمی‌گرداند | sheet کل صفحه را می‌پوشاند؛ scroll صفحهٔ پنهان فقط sheet را با نوار آدرس جابه‌جا می‌کرد (F-17، بازگشایی به درخواست مالک) |
| خروج از full screen همیشه focus را به دکمهٔ expand برمی‌گرداند | فقط وقتی کنترل focus‌دار ناپدید شده یا focus روی صفحه رها شده باشد | composer نیمه‌نوشته نباید focus را از دست بدهد |
| رنگ متن کمکی `#9a9db8`، `#a9abc4` و `#7e81a3` | یک توکن `--spw-faint` | کنتراست 4.5:1 برای متن کوچک (F-23) |
| حالت ضبط در overlay (D-02) | در composer: قاب coral، نقطهٔ زنده، زمان m:ss، دکمهٔ توقف و placeholder یک‌خطی «Listening…» | کادر در موبایل و دسکتاپ هم‌ارتفاع می‌ماند و فرمان توقف با خود دکمه دیده می‌شود (F-25، بازنگری به درخواست مالک) |
| Plus Jakarta Sans از Google Fonts | stack سیستمی با Vazirmatn | روی سایت بازدیدکننده فونت بیرونی بار نمی‌شود (حریم خصوصی و سرعت) |
| نوار حالت‌ها، کلید Direction، نوار مرورگر و اسکلت سایت | ساخته نشد | ابزار prototype موکاپ است |

## قابلیت‌های افزونه که موکاپ برایشان UI ندارد

- کد کوتاه `[aisaautopilot_chat]` از `ChatWidget::render_standalone()`.
- notice ثبت گفت‌وگو زیر composer، فقط با opt-in مدیر.
- برجسته‌سازی کلمه هنگام خواندن پاسخ با صدای مرورگر (`onboundary`) و کنترل `voice_auto`.
- Stop و Try again، سقف واقعی ۲۰ پرسش و ۵ انتقال در ساعت با پیام سرور.
- دکمهٔ «پرش به آخرین پیام»، nudge یک‌بار در هر session، باز شدن با `#aisaautopilot-chat` و `auto_open` قابل تنظیم.
- تنظیمات عمیق ظاهر: تم تیره، دوازده رنگ بخش، چهار آیکون، چهار جایگاه، گوشه‌ها، `prefers-contrast: more` و `@media print`.
- `aria-modal`، focus trap، Escape دومرحله‌ای (اول از full screen، بعد از پنل) و ناحیهٔ `role="log"` برای پیام‌ها.

## محدودیت‌های باز و صادقانه

- ~~**F-11 — استثنای نمایش ویجت UI ندارد**~~ — **حل شد پس از بستن بسته (T73، ۲۰۲۶-۰۹-۱۸، به دستور مالک).** مشکل نبودِ UI بود و خواننده هم سه نقص داشت: کلید `widget_exclude_ids` را می‌خواند در حالی که پیش‌فرض `exclude_ids` بود، `exclude_pages` را خوانده ولی به کار نمی‌برد، و شناسهٔ صفحهٔ دسته را با شناسهٔ نوشته مقایسه می‌کرد. اکنون کارت «Pages where the chat appears» در سفارشی‌سازی پیشرفته هست («Every page» یا «Every page except the ones below»، نوع‌های محتوا، دسته‌های نوشته و جست‌وجوی برگه و نوشته و محصول با عنوان)، برگهٔ Widget خلاصهٔ آن را نشان می‌دهد، و `Settings::widget_exclusions()` هر سه کلید شناسه را یکی می‌کند. هیچ کلیدی حذف نشد. آزمون: `tests/wordpress_widget_visibility_integration.php`.
- ~~**F-12 — دوازده کلید پیش‌فرض مرده**~~ — **تعریف شد (T73).** مشکل مستندات بود، نه UI: این کلیدها از طرح نخست ویجت مانده‌اند. شش تا کار یک کنترل زنده را تکرار می‌کنند (`btn_color`، `btn_icon`، `btn_shape`، `window_border_radius`، `window_accent_color`، `window_header_color`) و شش تا هرگز ساخته نشدند (`btn_size`، `btn_animation`، `btn_offset_x`، `btn_offset_y`، `window_font_size`، `window_shadow`). در کد زیر `Settings::WIDGET_RETIRED_DEFAULTS` جمع شده‌اند و جدول «کلید بازنشسته ← کنترل زنده» در `docs/technical/DATA_MODEL_FA.md` است. طبق قاعدهٔ حفظ در پیش‌فرض‌ها می‌مانند و با خود option در uninstall حذف می‌شوند؛ پس T21 دیگر تصمیمی دربارهٔ آن‌ها ندارد.
- **`voice_name` و `bg`:** اولی فقط با نوشتن مستقیم option اثر دارد و دومی تنها در پاسخ REST دیده می‌شود؛ هیچ‌کدام کنترل مدیر ندارند.
- **`includes/Frontend/WidgetRenderer.php` و `assets/frontend/chat-widget.js|css` به هیچ hook وصل نیستند** (تنها ارجاع، assertion آزمون lifecycle است). حذف نشدند تا سازگاری حفظ شود؛ renderer canonical `ChatWidget` است.
- **انتخاب و ترتیب کانال انتقال وجود ندارد:** `HandoffService` هم‌زمان به همهٔ کانال‌های آماده می‌فرستد. ماشین حالت واقعی («علی به گفتگو پیوست»)، پذیرش، ارجاع و انتخاب راه تماس در T61 و T62 (بستهٔ S7) ساخته می‌شود؛ تا آن زمان کارت هیچ ادعای اپراتور یا زمان پاسخ نمی‌کند.
- **کارت انتقال ارسال‌شده پاک نمی‌شود** و بازدیدکننده می‌تواند کارت تازه بخواهد؛ سقف واقعی همان ۵ انتقال در ساعت است.
- **Markdown عمداً محدود** به زیرمجموعهٔ تولیدی engine است؛ بلوک کد fenced و جدول متن خام می‌مانند.
- **قفل sheet موبایل با `overflow: hidden` است:** Safari در iOS پیش از 16 آن را برای scroll لمسی نادیده می‌گیرد. آنجا فقط `overscroll-behavior: contain` روی thread جلوی زنجیر شدن scroll را می‌گیرد. قفل `position: fixed` روی بدنه عمداً به کار نرفت، چون جای scroll را هنگام بستن جابه‌جا می‌کند. matrix قفل را در Chrome اثبات می‌کند، نه در Safari واقعی.
- **فاز «در حال خواندن پاسخ با صدا» اعلام نمی‌شود:** هم‌زمان با حرف زدن موتور گفتار، یک اعلام صفحه‌خوان روی آن می‌افتد.
- **`/chat/voice/transcribe`، `/forms/voice/transcribe` و `/efb/voice/transcribe`** دروازهٔ `voice_enabled` ویجت را نگرفتند: مسیر مدیرند و به تنظیم ویجت بازدیدکننده ربطی ندارند.
- **پیام‌های خطای mockشده در اجرای فارسی انگلیسی‌اند**، چون در مرورگر شکل پاسخ mock می‌شود؛ ترجمهٔ پیام سرور را integrationهای PHP می‌سنجند.
- ~~**یک شکست گذرای بازتولیدنشده**~~ — **ریشه پیدا و رفع شد (F-26، پیگیری T13، ۲۰۲۶-۰۹-۱۸).** در یکی از اجراهای T13 (en_US، 1440، full screen) یک بار Tab از پنل بیرون رفت. در اجرای دوبارهٔ matrix پس از پیگیری، همین شکست در fa_IR 1440 تکرار شد و مسیر focus آن را نشان داد: `… → name → email → <body>`. پس از آخرین فیلد thread، Tab به دکمهٔ «آخرین پیام» (`#spw-jump`) می‌رسید. scroll نرم thread که هنوز در جریان بود به انتها می‌رسید، `syncJump()` دکمه را در حالی که focus داشت پنهان می‌کرد، و focus به `<body>` می‌افتاد: بیرون از پنل و بیرون از trap حالت modal. این نقص محصول بود، نه آزمون، و با کلیک روی همان دکمه هم رخ می‌داد. اکنون `syncJump()` پیش از پنهان کردن، focus را به composer می‌دهد، که کنترل بعدی در ترتیب Tab است. probe قطعی پیش از رفع `BODY` و پس از آن `spw-input` برگرداند، و matrix این را در هر چهار ترکیب زبان و عرض می‌سنجد. render integration هم قرارداد آن را دارد.
- **رنگ‌های کم‌رنگ مشابه در CSS مدیر** (`assets/admin/aisaautopilot-ui.css`: `#a9abc4`، `#9a9db8`، `#7e81a3`) در این بسته لمس نشدند و کنتراست‌شان سنجیده نشده است؛ دامنهٔ T17.

## شواهد تست

| گیت | نتیجه | artifact |
|---|---|---|
| گیت full (T14) | **9/9**، exit 0، در دو اجرای مستقل با شمارهای یکسان | `test-artifacts/gates/20260918-181045-full`، `test-artifacts/gates/20260918-180938-full` |
| php-lint و node-check | 232/232 و 39/39 | همان |
| PHPCS کامل | 134/134، **0 error، 0 warning** | همان |
| PHPStan | 0 error | همان |
| PHPUnit | 149 test / 514 assertion (بدون افت از خط مبنا) | همان |
| menu، assets و render smoke | 5/0، 20/0 و 18/0؛ PHP diagnostic افزونه صفر | همان |
| Phase 1 integration | 45/0 | همان |
| render integration ویجت | **92 check، 0 failed** (T08: 41، T09: 49، T10: 62، T11: 76، T12: 88) | `test-artifacts/front-chat-widget/20260918-t14/render-integration.txt` |
| پیگیری T13 به درخواست مالک | matrix **425 PASS / 0 FAIL** (en_US 213، fa_IR 212؛ ۴۰ check تازه برای حالت ضبط و sheet modal)، render integration **96/0**، هشت integration مرتبط سبز، smoke 8/8؛ preflight پایانی WARNINGS 0 و BLOCKERS 0 | `test-artifacts/front-chat-widget/20260918-t13-followup/`، `test-artifacts/gates/20260918-185323-smoke` |
| اجرای دوبارهٔ پیگیری (پس از تغییر هم‌زمان T73) | اجرای اول 423/2 نقص F-26 را نشان داد و رفع شد. نتیجهٔ نهایی: matrix **429 PASS / 0 FAIL** (en_US 215، fa_IR 214)، render integration **97/0**، smoke 8/8 (PHPUnit 153 / 526)، preflight پایانی WARNINGS 0 و BLOCKERS 0 | `…/20260918-t13-followup/matrix-final.txt`، `test-artifacts/gates/20260918-230345-smoke` |
| browser matrix ویجت (T14) | **385 PASS / 0 FAIL**، exit 0 و `MATRIX RESULT PASS`؛ دقیقاً همان تفکیک T13: en_US 193 (main 173، auto 4، quiet 16) و fa_IR 192 (main 172، auto 4، quiet 16). شکست گذرای focus تکرار نشد | `test-artifacts/front-chat-widget/20260918-t14/matrix-t14.txt` |
| browser matrix ویجت (T13) | **385 PASS / 0 FAIL**: en_US 193 و fa_IR 192؛ به‌علاوهٔ سه اجرای تکرار سناریوی `main` با 345/0 | `test-artifacts/front-chat-widget/20260918-t13/matrix-run6.txt`، `flake-probe-1..3.txt` |
| check‌های موقت هر ریزتسک | T08 83/0، T09 114/0، T10 106/0، T11 140/0، T12 126/0 | `test-artifacts/front-chat-widget/20260917-t08` تا `20260918-t12` |
| ترجمه | MO با **2286** ترجمه از 2427 ورودی PO (141 بی‌ترجمهٔ کهنه، کار T18)؛ ۱۲ msgid تازه در کل بسته؛ `msgfmt --check` سالم | خروجی `scripts/compile-mo.php` |
| پاک‌سازی | fixture پس از هر ترکیب و دوباره در `finally` برداشته شد؛ preflight پایانی با `settings_stored=False`، locale `en_US`، WARNINGS 0 و BLOCKERS 0 | `test-artifacts/front-chat-widget/20260918-t14/preflight-final.txt` |

هیچ provider، کلید، بودجه، ایمیل، پیامک یا پیام Telegram واقعی در کل بسته لمس نشد: `/widget/ask`، `/widget/handoff` و `/public/voice` در مرورگر با `page.route` پاسخ داده شدند، میکروفن و موتور گفتار با `addInitScript` جایگزین شدند، کانال fixture نشانی `.invalid` دارد و integrationها `pre_wp_mail` و `pre_http_request` را می‌بندند. تنها فراخوان واقعی شبکه یک `POST /public/voice` **بدون صدا** در سناریوی `quiet` است که دروازهٔ تازه پیش از rate limiter و پیش از هر جست‌وجوی کلید ردش می‌کند.

شکست‌های میانی پنهان نشده‌اند و هیچ assertionی برای سبز شدن سست نشد:

- T10: اجرای نخست 102/4 — هر چهار شکست سنجهٔ console بود که سطرهای «Failed to load resource» خودِ Chrome برای پاسخ‌های عمداً mockشده را می‌شمرد؛ فیلتر فقط برای URL همان route افزوده شد.
- T11: اجرای نخست 16/1 — check نخستین جعبهٔ خطا (متعلق به فیلد نام) را می‌پایید؛ selector به فیلدی که سرور نام می‌برد دقیق شد.
- T12: اجرای نخست 122/4 — کلیک وسط کادر متن مکان‌نما را وسط transcript می‌گذاشت؛ مکان‌نما صریحاً به انتهای متن رفت.
- T13: اجرای نخست ۹ شکست کنتراست واقعی (F-23) و سپس دو شکست دیگر (F-24 در fa_IR 390 و یک خروج گذرای focus) داد. F-23 تا F-25 نقص محصول بودند و در همان task رفع شدند.
- T11: حذف چهار کلید از آرایهٔ localize هم‌ترازی `=>` را به هم زد و PHPCS را amber کرد؛ `phpcbf` روی همان یک فایل آن را برگرداند.

## خروجی visual QA

قاب‌های موکاپ (۲۸ تصویر در `test-artifacts/front-chat-widget/mockup/`) با تصویرهای واقعی ویجت در هر ریزتسک جفت شدند؛ آخرین مجموعه در `test-artifacts/front-chat-widget/t13-followup/<locale>/` است (پیگیری T13 به درخواست مالک؛ مجموعهٔ پیش از آن در `t13/` و `t14/`).

| جفت | نتیجه |
|---|---|
| `widget-launcher-1440` ↔ `mockup/widget-launcher-ltr-1440` | هم‌خوان؛ نشان «1» و متن nudge نمونه عمداً ساخته نشده‌اند |
| `widget-answer-1440` ↔ `mockup/widget-panel-ltr-1440` | هم‌خوان: header، برچسب «Today»، حباب‌ها، خط «From …»، chipها و composer. chipها چون متن واقعی مدیر بلندتر است زیر هم می‌شکنند |
| `widget-fullscreen-1440` (fa_IR) ↔ `mockup/widget-fullscreen-rtl-1440` | آینهٔ کامل. نقطه‌گذاری لاتین در افزونه درست است («Canada?») و در موکاپ جابه‌جا — مزیت `dir="auto"` |
| `widget-handoff-390` (fa_IR) ↔ `mockup/widget-handoff-rtl-390` | کارت زرد هم‌خوان؛ اختلاف‌ها همان W-02، «Not now»، فیلد تماس و sheet تمام‌صفحه |
| `widget-voice-recording-*` ↔ `mockup/widget-voice-*` | اختلاف عمدی D-02: بدون overlay. پس از پیگیری T13 (`t13-followup/<locale>/`): کادر یک‌خطی با قاب coral، نقطه، زمان («0:01» و «۰:۰۱») و مربع توقف در هر دو دکمه. تصویرهای T13 میکروفن را کمرنگ نشان می‌دادند، چون transition قالب هنوز در جریان بود؛ `.spw-mic` اکنون transition خودش را دارد |
| `widget-handoff-sent-*`، `widget-voice-transcript-*`، `widget-quiet-*` | حالت‌هایی که موکاپ ندارد؛ فقط یکپارچگی بصری بازبینی شد: بدون overflow، جهت درست، متن به زبان سایت |

بازبینی تصویری دو نقصی را پیدا کرد که هیچ assertionی نگرفته بود (F-24 و F-25)؛ همین دلیل ماندن این مرحله در گیت هر ریزتسک است.
