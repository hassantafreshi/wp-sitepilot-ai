# ممیزی و برنامهٔ اجرای S3.3 — Front Chat Widget

تاریخ ممیزی: ۲۰۲۶-۰۹-۱۷ (T07)  
مرجع بصری: `mockups/design_handoff_admin_redesign/Front Chat Widget.dc.html` با پنج حالت Bubble، Panel، Full screen، Voice mode و Handoff، در LTR و RTL  
سطح واقعی: ویجت بازدیدکننده روی سایت عمومی، `includes/Frontend/ChatWidget.php` با `assets/frontend/widget.js` و `widget.css`  
شواهد: `test-artifacts/front-chat-widget/mockup/` (28 قاب)، `test-artifacts/front-chat-widget/baseline/<locale>/` (18 تصویر)، `test-artifacts/front-chat-widget/20260917-t07/`

## تصمیم معماری

موکاپ یک فروشگاه نمایشی (Northwind) با گفت‌وگوی از پیش نوشته، popular questions ثابت و چرخهٔ خودکار voice می‌سازد. این‌ها ابزار prototype هستند و وارد محصول نمی‌شوند. سطح canonical همان renderer واقعی است:

- `Core/Plugin::render_widget()` در `wp_footer`، فقط وقتی `Settings::answer_desk_enabled()` و `ChatWidget::should_render_widget()` هر دو درست‌اند؛
- `Core/Plugin::enqueue_widget_assets()` با شیء محلی `AISAAutoPilotWidget`؛
- shortcode `[aisaautopilot_chat]` از `ChatWidget::render_standalone()`.

S3.3 renderer تازه نمی‌سازد و markup را از نو نمی‌نویسد. بازطراحی موکاپ از پیش روی همین renderer انجام شده است (شواهد تصویری ۲۰۲۶-۰۹-۱۵ در `test-artifacts/mockup-parity/widget-after/`) و baseline امروز آن را تأیید می‌کند. پس کار S3.3 **بستن شکاف‌های اندازه‌گیری‌شده، صداقت رابط و اثبات با browser matrix** است، نه ساختن دوباره.

`includes/Frontend/WidgetRenderer.php` و `assets/frontend/chat-widget.js|css` به هیچ hook وصل نیستند (تنها ارجاع، خود فایل و assertion آزمون lifecycle است). حذف نمی‌شوند و فقط در گزارش اجرا ثبت می‌شوند.

## تأیید دوبارهٔ واقعیت فنی ۲۰۲۶-۰۹-۱۴

| ادعای رودمپ | نتیجهٔ وارسی T07 | شاهد |
|---|---|---|
| renderer canonical `ChatWidget` در `wp_footer` با دو دروازه | درست | `includes/Core/Plugin.php:65`، `:179`–`:186` |
| assetها `widget.js` و `widget.css`؛ shortcode `[aisaautopilot_chat]` | درست | `Plugin.php:63`، `:125`–`:126` |
| `WidgetRenderer` و `chat-widget.*` بی‌hook | درست؛ در `includes/` هیچ ارجاعی جز خود کلاس نیست | grep؛ `tests/wordpress_answer_desk_lifecycle_integration.php` |
| ویجت expand، handoff، ضبط صدا، گفتار مرورگر، suggestion، citation و typing دارد | درست | `widget.js` |
| «`aria-live` در JS ندارد» | **کهنه.** `#spw-msgs` در PHP `role="log"` و `aria-live="polite"` دارد و وضعیت کارت انتقال `role="status"` و `aria-live="polite"`. آنچه واقعاً نیست: اعلام وضعیت ضبط، transcribe و خطای میکروفن برای صفحه‌خوان (فقط placeholder عوض می‌شود) | `ChatWidget.php:124`، `:228`؛ `widget.js:440`–`:451` |
| قراردادهای A2.5: logging فقط opt-in با notice، handoff فقط با کانال آماده، `voice_enabled` و `voice_auto`، accent، source فقط با citation واقعی | درست | `ChatWidget.php:43`–`:47`، `:166`؛ `Settings::answer_desk_handoff_available()`؛ `widget.js:194` |
| زبان front-end از `WPLANG` | درست؛ baseline با fixture زبان سایت `html[lang]=fa-IR` و `direction: rtl` داد | `20260917-t07/capture-plugin.txt` |

## قرارداد REST ویجت

| مسیر | روش | مجوز | مصرف در `widget.js` | نکته |
|---|---|---|---|---|
| `/widget/settings` | GET | عمومی | T07: `getSettings()` در `init`، **روی هر بار بارگذاری صفحه**. از T10: **استفاده نمی‌شود**؛ همان داده‌ها در `AISAAutoPilotWidget` هستند (F-15) | اعتبارنامه یا قاعدهٔ استثنا برنمی‌گرداند. route حفظ می‌شود |
| `/widget/ask` | POST | عمومی، 20 در ساعت (`RateLimiter` با `widget_text`) | `send()` | 403 `widget_disabled`، 400 `empty` و `question_too_long`، 429 `rate_limited` و `usage_cap_reached`، 503 `assistant_unavailable`. پاسخ موفق همان پوشهٔ `ChatEngine::result()` است (`answer`، `sources`، `status`، `clarify_options`، `handoff_offer`) به‌اضافهٔ `conversation_id` وقتی logging روشن است. از T10 هر رد `message` دارد و هیچ ردی `answer` ندارد (F-14) |
| `/widget/conversation` | POST | عمومی | استفاده نمی‌شود | همیشه `messages: []` برمی‌گرداند. ثبت نوبت‌ها در خود `/widget/ask` انجام می‌شود |
| `/widget/handoff` | POST | عمومی، 5 در ساعت | `requestHandoff()` | ارسال هم‌زمان به همهٔ کانال‌های آماده؛ قرارداد کامل در `docs/technical/ANSWER_DESK_FA.md` |
| `/widget/handoff/status` | GET | عمومی | استفاده نمی‌شود | فقط `enabled` |
| `/public/voice` | POST | عمومی، سقف `public_voice` | `sendAudio()` | فقط `answer_desk_enabled()` را می‌سنجد، **نه `widget.voice_enabled`** (یافتهٔ F-09) |
| `/public/chat` | POST | عمومی | استفاده نمی‌شود | دستیار عمومی قدیمی‌تر؛ حفظ می‌شود |
| `/widget/test/telegram`، `/widget/test/twilio` | GET | `can_manage` | استفادهٔ مدیر | همیشه در browser mock می‌شوند |

## نگاشت موکاپ به قرارداد واقعی

### حالت Bubble (launcher)

| کنترل موکاپ | در کد | تصمیم |
|---|---|---|
| حباب 62px navy با حلقهٔ `fw-ring` | `#spw-toggle` با `.spw-toggle__ring`، 62×62 در baseline | حفظ؛ حلقه در `prefers-reduced-motion` خاموش است |
| nudge «Need a hand?» با متن | `#spw-nudge` با عنوان ثابت و متن greeting؛ یک‌بار در session با `sessionStorage` | حفظ. **اما** با پیش‌فرض `auto_open=5` هرگز دیده نمی‌شود (F-01، D-25) |
| نشان قرمز «1» روی حباب | ساخته نشده | **ساخته نمی‌شود.** شمار پیام خوانده‌نشدهٔ واقعی وجود ندارد؛ عدد ثابت دادهٔ جعلی است |
| جای حباب در RTL آینه می‌شود | جای فیزیکی ثابت (X-02) | اختلاف عمدی: جایی که مدیر انتخاب کرده مقدم است |

### حالت Panel (docked)

| کنترل موکاپ | در کد | تصمیم |
|---|---|---|
| پنل 392×624، گوشهٔ 20px | CSS پیش‌فرض 392×624 و 20px، اما `style_vars()` با پیش‌فرض‌های `window_width=380`، `window_height=550` و `panel_radius=16` آن را بازنویسی می‌کند؛ baseline 380×550 | F-02 → T08 |
| آواتار با نقطهٔ سبز و «Online · replies instantly» | نقطهٔ سبز همیشه روشن (`aria-hidden`)؛ زیرعنوان از تنظیم `subtitle` | F-03 → T08: نقطهٔ سبز ادعای «آنلاین» بدون دادهٔ واقعی است (provider ممکن است تنظیم‌نشده یا در سقف مصرف باشد). پیشنهاد: حذف نقطه، اختلاف عمدی مستند |
| دکمه‌های Voice mode، Full screen و Minimise در header | `#spw-voice` (فقط وقتی `voice_enabled`)، `#spw-expand`، `#spw-close`؛ 34×34 | hit target زیر 44px (F-04 → T08) |
| «Today» | `.spw-day` ثابت | حفظ؛ گفت‌وگو فقط در session است، پس «امروز» درست است |
| پیام خوشامد | `#spw-welcome` از `greeting` | حفظ؛ پیش‌فرض انگلیسی روی سایت فارسی (F-05 → T08) |
| حباب‌های کاربر و AI با source «From …» | `addMessage()`؛ source فقط از `sources` واقعی، پیوند با پروتکل http/https | حفظ. متن لاتین در سایت RTL نقطه‌گذاری را جابه‌جا نشان می‌داد (F-06)؛ از T10 هر حباب `dir="auto"` دارد و پاسخ Markdown به عنصر واقعی رندر می‌شود (F-13) |
| typing سه‌نقطه | `showTyping()` فقط در طول درخواست واقعی | حفظ؛ T10 در مرورگر ثابت کرد پس از رسیدن پاسخ و پس از Stop ناپدید می‌شود |
| chipهای پرسش | `#spw-chips` از `suggestions` (حداکثر 4، هر کدام 90 نویسه)؛ در busy پنهان | **با کلیک بی‌درنگ ارسال می‌شود**، مانند موکاپ (`c.ask`). D-24 تأیید شد و قاعدهٔ `docs/technical/ARCHITECTURE_FA.md` در T10 به متن ساختهٔ AI محدود شد؛ گزینه‌های `clarify_options` همان ویجت فقط composer را پر می‌کنند |
| composer با mic و send | `textarea#spw-input` (Enter ارسال، Shift+Enter خط تازه)، `#spw-mic`، `#spw-send` که در busy به Stop تبدیل می‌شود؛ 36×36 | hit target (F-04)؛ Stop فقط درخواست مرورگر را قطع می‌کند (F-07). از T10 برچسب «Stop waiting for the reply» و یادداشت صادقانه پس از توقف |
| «Answers come from this store. Talk to a human» | «Answers come from this site and can be wrong.» (X-03) و دکمهٔ `data-spw-handoff` فقط وقتی کانالی آماده است | حفظ؛ اختلاف عمدی X-03 |
| — | `.spw-recording-notice` زیر composer وقتی logging روشن و متن ناخالی است | قابلیت بدون UI موکاپ؛ حفظ |
| — | دکمهٔ «پرش به آخرین پیام» `#spw-jump` | قابلیت بدون UI موکاپ؛ حفظ |

### حالت Full screen

| کنترل موکاپ | در کد | تصمیم |
|---|---|---|
| قاب 940×760 با scrim و blur سایت | `.spw-panel--full` با `min(940px, 100vw-52px)` و `min(760px, 100dvh-52px)`؛ `#spw-scrim` با `backdrop-filter: blur(2px)` | هم‌خوان در baseline 1440 |
| دکمهٔ «Voice» برچسب‌دار و Exit full screen | `.spw-hbtn--voice .spw-hbtn__label` در full؛ آیکن expand به shrink عوض می‌شود؛ Minimise پنهان | هم‌خوان |
| ستون Popular questions | `.spw-side` از **همان `suggestions` واقعی**؛ اگر suggestion نباشد ستون فقط بخش انسان را دارد | حفظ؛ هیچ پرسش نمونه‌ای ساخته نمی‌شود |
| «Can't find it? … Talk to a human» | `.spw-side__human` فقط وقتی handoff آماده است | هم‌خوان |
| بستن با کلیک scrim | `scrim.click → setFull(false)` | هم‌خوان |
| — | `aria-modal` در full برابر `true`، focus trap در `trapFocus()`، Escape اول از full و بعد از panel خارج می‌شود | قابلیت بدون UI موکاپ؛ baseline: Escape از full خارج شد. از T09 اثبات‌شده با browser: focus هنگام ورود داخل پنل می‌ماند، ۲۴ بار Tab و یک Shift+Tab از خود پنل بیرون نمی‌رود، و هنگام خروج **فقط وقتی** کنترل focus‌دار ناپدید شده باشد (ستون پرسش‌ها) یا focus روی صفحه رها شده باشد (کلیک scrim) به دکمهٔ expand بازمی‌گردد؛ composer نیمه‌نوشته focus را نگه می‌دارد |
| — | قفل scroll بدنه در full | T07: **وجود ندارد** (F-08). از T09: `lockPageScroll()` مقدار inline `overflow` را روی `html` و `body` snapshot می‌کند، `hidden` می‌گذارد و هنگام خروج یا بسته شدن پنل دقیقاً همان را بازمی‌گرداند |

### حالت Voice mode

| عنصر موکاپ | backend واقعی | تصمیم |
|---|---|---|
| overlay تمام‌صفحه با orb و phaseهای Connecting، Listening، Thinking، Speaking | فقط ضبط `MediaRecorder` تا 45 ثانیه، آپلود یک‌جا به `/public/voice`، متن در composer؛ گفتار با `speechSynthesis` مرورگر وقتی `voice_auto` | **D-02**؛ پیش‌فرض: ساخته نمی‌شود |
| زیرنویس زنده «…and can I pick it up in store instead?» | transcript فقط پس از پایان ضبط برمی‌گردد | ساخته نمی‌شود؛ متن realtime وجود ندارد |
| «Tap the mic to interrupt any time» (barge-in) | وجود ندارد | ساخته نمی‌شود؛ `AnswerDeskVoicePage.php:57`–`:75` همین را قفل صادقانه کرده است |
| چیپ زبان «English · auto-detected» | زبان از `document.documentElement.lang` فرستاده می‌شود، تشخیص خودکار نیست | ساخته نمی‌شود؛ ادعای نادرست |
| «Not recorded» با قفل | صدا برای transcribe به provider فرستاده می‌شود | ساخته نمی‌شود؛ بدون سیاست نگهداری provider ادعای «ضبط نمی‌شود» اثبات‌پذیر نیست |
| Mute | جریان پیوسته‌ای برای بی‌صدا کردن وجود ندارد | ساخته نمی‌شود |
| End (قطع تماس) | تماسی وجود ندارد؛ معادل واقعی «توقف ضبط» است | فقط اگر D-02 بله: «Stop recording» |
| Type | بازگشت به composer | فقط اگر D-02 بله |
| جایگزین فعلی | `#spw-voice` و `#spw-mic` هر دو `toggleRecording()`؛ `aria-pressed`؛ هنگام ضبط هر دو به دکمهٔ توقف (مربع) با برچسب و tooltip «Stop recording» بدل می‌شوند، کادر پیام قاب coral با نقطهٔ زنده و زمان m:ss می‌گیرد و placeholder یک‌خطی «Listening…» است (پیگیری T13 به درخواست مالک)؛ جملهٔ کامل «Listening… tap the mic again to stop» فقط در ناحیهٔ status برای صفحه‌خوان | W-01، جایگزین صادقانه. T12 اعلام `aria-live` وضعیت ضبط و transcribe را اضافه کرد |

### حالت Handoff

| کنترل موکاپ | در کد | تصمیم |
|---|---|---|
| کارت زرد «I'll bring in a person» در thread | `#spw-handoff-template` کلون در `#spw-msgs`؛ فقط وقتی `Settings::answer_desk_handoff_available()` | هم‌خوان |
| دو دکمهٔ «Email me a reply» و «Chat on Telegram» | یک دکمهٔ «Send this chat to our team» به همهٔ کانال‌های آماده، به‌اضافهٔ «Not now» | W-02، اختلاف عمدی؛ ترتیب و انتخاب کانال در T61 و T62 (بستهٔ S7، جایگزین T38 و T37 — `docs/HUMAN_SUPPORT_S7_AUDIT_AND_PLAN_FA.md`) |
| — | فیلدهای تماس Off/Optional/Required (D-20)، خطای زیر فیلد، Enter برای ارسال | قابلیت بدون UI موکاپ؛ حفظ |
| — | پیشنهاد خودکار کارت وقتی `handoff_offer` درست است | قابلیت بدون UI موکاپ؛ حفظ |
| chipها هنگام کارت پنهان‌اند | chipها زیر کارت می‌مانند | F-10 → T11 |
| — | پیام تأیید «Sent — we will be in touch.» | صادقانه؛ ادعای «اپراتور وصل شد» یا زمان پاسخ ندارد |

### ابزارهای prototype موکاپ

نوار «Front-end widget» با دکمه‌های حالت، کلید Direction، نوار مرورگر `northwind.store`، اسکلت کارت‌های سایت، داده‌های `CONV`، `CHIPS`، `POPULAR` و `VOICE` و پاسخ ساختگی «Good question — here’s what I found on that.» فقط برای نمایش‌اند و ساخته نمی‌شوند.

## تنظیمات ویجت: نویسنده و مصرف‌کننده

| کلید | نویسندهٔ مدیر | مصرف‌کننده | یافته |
|---|---|---|---|
| `enabled`، `color` | tab Widget (`/widget/admin-settings`) | `ChatWidget`، `/widget/settings` | سالم |
| `position`، `theme`، `custom_colors`، `colors`، `icons`، `title`، `subtitle`، `greeting`، `suggestions`، گوشه‌ها، `handoff_text`، `handoff_hook`، logging و notice | `ChatWidgetSettings::save()` | `ChatWidget` | سالم |
| `voice_enabled`، `voice_auto` | `AdminVoiceSettingsController` | `ChatWidget`، `/widget/settings`، `widget.js` | پیش‌فرض `voice_auto` از T08 برابر `false` (D-25) |
| `handoff_*`، `handoff_fields` | tab Human handoff | `Settings::answer_desk_handoff_available()`، `handoff_asks()` | سالم |
| `auto_open` (پیش‌فرض T07: 5؛ از T08: 0) | T07: **هیچ**؛ از T08: کنترل «Open the chat by itself» در `ChatWidgetSettings` | `ChatWidget.php`، `widget.js` | F-01، D-25؛ رفع در T08 با مهاجرت یک‌بارهٔ 5 به 0 |
| `window_width`، `window_height` (پیش‌فرض T07: 380 و 550؛ از T08: 392 و 624) | **هیچ** | `style_vars()` | F-02؛ رفع در T08 با مهاجرت یک‌بارهٔ مقدار قدیمی |
| `defaults_revision` (از T08) | `Settings::migrate_widget_defaults()` و هر `Settings::update()` | همان مهاجرت | نشان اجرای یک‌بارهٔ مهاجرت |
| `widget_visibility`، `exclude_types`، `exclude_cats`، `exclude_ids` | کارت `#answer-desk-visibility` در `ChatWidgetSettings` (از T73) | `should_render_widget()` از راه `Settings::widget_exclusions()` | F-11 — **حل شد در T73**؛ پیش از آن هیچ نویسنده‌ای نداشت |
| `exclude_pages`، `widget_exclude_ids` | فقط خالی می‌شوند وقتی کارت ذخیره می‌شود | `Settings::widget_exclusions()` همراه `exclude_ids` | F-11 — تا T73 خواننده `widget_exclude_ids` را به‌جای `exclude_ids` می‌خواند و `exclude_pages` را به کار نمی‌برد |
| `voice_name` | **هیچ** | `widget.js:269` | بی‌اثر مگر با نوشتن مستقیم option |
| `bg` | **هیچ** | `/widget/settings` | فقط در پاسخ REST |
| `btn_size`، `btn_shape`، `btn_color`، `btn_icon`، `btn_animation`، `btn_offset_x`، `btn_offset_y`، `window_border_radius`، `window_accent_color`، `window_header_color`، `window_font_size`، `window_shadow` | **هیچ** | **هیچ** | F-12: دوازده کلید بازنشستهٔ طرح نخست، در `Settings::WIDGET_RETIRED_DEFAULTS`. **تعریف شد در T73**: کنترل زندهٔ جانشین هر کدام در `docs/technical/DATA_MODEL_FA.md`؛ حذف نمی‌شوند (قاعدهٔ حفظ) و با option در uninstall می‌روند |

## قابلیت‌های افزونه که موکاپ برایشان UI ندارد

- **متن گفت‌وگو برای انتقال:** `PdfExporter::export_text()` متن ساده را برای ایمیل و Telegram می‌سازد. «PDF transcript» برای بازدیدکننده وجود ندارد: `export_html()` و `export_word_by_word()` هیچ فراخوانی ندارند و `HandoffService::handoff()` همیشه `pdf_url` خالی به Twilio می‌دهد. در UI چیزی ساخته نمی‌شود.
- **notice ثبت گفت‌وگو** زیر composer، فقط با opt-in.
- **برجسته‌سازی کلمه در گفتار:** پاسخ به `span.spw-word` تقسیم و با `onboundary` برجسته می‌شود.
- **Stop و Try again:** دکمهٔ send در busy به Stop تبدیل می‌شود؛ خطا دکمهٔ «Try again» دارد که همان پرسش را بدون تکرار حباب کاربر دوباره می‌فرستد. از T10: برچسب «Stop waiting for the reply» با یادداشت `[data-spw-note="stopped"]`، و «Try again» فقط برای خطای گذرا.
- **Markdown امن و گزینه‌های clarify (T10):** پاسخ Markdown با DOM رندر می‌شود؛ `clarify_options` به‌صورت دکمه‌هایی زیر پاسخ که فقط composer را پر می‌کنند. موکاپ پاسخ قالب‌بندی‌شده یا پرسش روشن‌کننده ندارد.
- **rate limit:** 20 پرسش و 5 انتقال در ساعت برای هر شناسهٔ بازدیدکننده؛ پیام سرور با retry نمایش داده می‌شود.
- **shortcode** `[aisaautopilot_chat]`.
- **تنظیمات عمیق ظاهر:** تم تیره، دوازده رنگ بخش، چهار آیکون، چهار جایگاه، گوشه‌ها، `prefers-contrast: more` و `@media print`.
- **پرش به آخرین پیام**، **nudge یک‌بار در session**، **باز شدن با `#aisaautopilot-chat`** و **`auto_open`**.

## تصمیم breakpoint

| عرض | پنل docked | full screen | ستون Popular questions |
|---|---|---|---|
| بیشتر از 768px | قاب شناور 392px | قاب مرکزی 940×760 با scrim | دیده می‌شود |
| 641 تا 768px | قاب شناور | **تصمیم T07: تمام viewport، بدون scrim**؛ امروز قاب مرکزی با ستون 270px که ستون پیام را حدود 450px می‌کند | پنهان؛ chipها در thread برمی‌گردند |
| 640px و کمتر | sheet تمام‌صفحه (موجود) | همان sheet؛ دکمهٔ expand پنهان (موجود) | پنهان (موجود) |

دلیل: در 641 تا 768px ستون 270px بیش از یک‌سوم عرض را می‌گیرد و قاب مرکزی با حاشیهٔ 26px فایده‌ای بر تمام viewport ندارد. آستانهٔ `matchMedia('(max-width: 900px)')` در `widget.js:735` هم با CSS هم‌خوان نیست و در T09 به همان 768px تبدیل شد.

**اجراشده در T09:** بلوک `@media (min-width: 641px) and (max-width: 768px)` در `widget.css`؛ اندازه‌گیری مرورگر در 768×900: پنل 768×900 با گوشهٔ `0px`، `scrim` با `display: none`، ستون `display: none` و chipهای thread دوباره دیده می‌شوند. در موکاپ 390px پنل docked با حاشیه است؛ sheet تمام‌صفحه در 640px و کمتر اختلاف عمدی است، چون کیبورد گوشی جای قاب شناور را می‌گیرد.

## یافته‌های ممیزی

| # | یافته | شاهد | task |
|---|---|---|---|
| F-01 | `auto_open` پیش‌فرض 5 است و هیچ UI مدیر آن را نمی‌نویسد، پس ویجت روی **هر بار بارگذاری هر صفحه** خودش باز می‌شود (در 640px و کمتر تمام صفحه را می‌پوشاند) و nudge هرگز دیده نمی‌شود | baseline: `data-spw-auto-open="5"`؛ پنل در 3 از 4 اجرا داخل پنجرهٔ 9 ثانیه‌ای خودش باز شد (چهارمی دیرتر از پنجرهٔ اندازه‌گیری) | D-25 → T08. **رفع شد:** پیش‌فرض 0، کنترل «Open the chat by itself» و مهاجرت یک‌بارهٔ 5 به 0. browser: nudge در هر چهار ترکیب زبان و عرض خودش آمد و پنل تا حدود 11 ثانیه بسته ماند |
| F-02 | ابعاد پیش‌فرض 380×550 و گوشهٔ 16px به‌جای 392×624 و 20px موکاپ، چون پیش‌فرض‌های `Settings` روی CSS می‌نشینند | baseline `panel {"width":380,"height":550}` | T08. **رفع شد:** پیش‌فرض 392×624 و 20px، مهاجرت 380×550. `panel_radius` نویسندهٔ UI دارد و مهاجرت داده نمی‌شود. browser در 1440: `{"width":392,"height":624,"radius":"20px"}` |
| F-03 | نقطهٔ سبز «آنلاین» همیشه روشن است، بدون دادهٔ واقعی | `widget.css:197` | T08. **رفع شد:** از markup، CSS ویجت و پیش‌نمایش customiser (`.sp-wpanel__sub::before`) حذف شد |
| F-04 | hit target دکمه‌های header 34×34 و composer 36×36؛ گیت T08 دست‌کم 44px است | baseline `hit` | T08: ناحیهٔ کلیک با pseudo-element بزرگ می‌شود و اندازهٔ دیداری موکاپ می‌ماند. **رفع شد:** اندازهٔ کلیک‌پذیر اندازه‌گیری‌شده با `elementFromPoint` بین 44×44 و 45×45 برای voice، expand، close، mic، send و بستن nudge؛ اندازهٔ دیداری 34 و 36 و 24 ماند. chip، پیوند footnote و دکمه‌های کارت انتقال متن‌دارند و در T17 سنجیده می‌شوند |
| F-05 | پیش‌فرض‌های `title`، `subtitle` و `greeting` در `Settings::widget_defaults()` رشتهٔ انگلیسی ثابت‌اند، پس سایت فارسی بدون ویرایش مدیر «Hi! How can I help you?» می‌بیند | `baseline/fa_IR/widget-panel-390.png` | T08: وقتی مقدار ذخیره‌شده همان پیش‌فرض است، رشتهٔ ترجمه‌شده نمایش داده شود. **رفع شد** با `Settings::widget_copy()` برای عنوان، زیرعنوان، greeting و `conversation_notice`، در renderer، script data، REST، customiser و دو پیش‌نمایش مدیر. عنوان پیش‌فرض نام محصول است و در PO هم «AI Site Assistant AutoPilot» مانده |
| F-06 | حباب پیام، chip و پرسش ستون `dir` ندارند؛ متن لاتین در سایت RTL نقطه‌گذاری را جابه‌جا نشان می‌دهد (همان یافتهٔ T04 در EFB) | `baseline/fa_IR/widget-fullscreen-1440.png` | T10. **رفع شد:** `dir="auto"` روی حباب هر نوبت، گزینهٔ clarify و یادداشت در JS، و greeting، chip، پرسش ستون، عنوان و زیرعنوان header و متن nudge در PHP. browser در fa_IR: حباب پاسخ لاتین `direction: ltr` داخل پنل RTL |
| F-07 | Stop فقط `fetch` را abort می‌کند؛ سرور پاسخ را کامل و مصرف و (با logging) نوبت را ثبت می‌کند. برای 429 هم دکمهٔ «Try again» بی‌درنگ نمایش داده می‌شود | `widget.js:251`، `:324`؛ `ChatWidgetController.php:78`–`:111` | T10. **رفع شد:** برچسب «Stop waiting for the reply» (فارسی «توقف انتظار برای پاسخ»)، یادداشت `[data-spw-note="stopped"]` که می‌گوید سایت ممکن است پاسخ را تمام کند ولی اینجا نشان داده نمی‌شود، و `NO_RETRY` برای `rate_limited`، `usage_cap_reached`، `question_too_long`، `empty` و `widget_disabled`. browser: 503 با «Try again»، هر دو 429 و 400 بدون آن؛ پاسخ دیرِ درخواستِ متوقف‌شده در thread نمی‌نشیند. سمت سرور تغییر نکرد: درخواستی که رسیده لغوپذیر نیست |
| F-08 | full screen scroll بدنه را قفل نمی‌کند | `setFull()` | T09. **رفع شد:** `lockPageScroll()` با snapshot دقیق inline `overflow` روی `html` و `body`؛ بدون snapshot، باز کردن قفل بی‌اثر است. browser در 1440 و 768، دو زبان: `overflow` هر دو `hidden`، تلاش برای scroll صفر پیکسل، و پس از Escape، کلیک scrim و بستن پنل مقدار دقیقاً به رشتهٔ خالی اولیه برگشت |
| F-17 (تازه در T09) | در 640px و کمتر، sheet باز است ولی صفحهٔ پشت آن همچنان scroll می‌شود؛ قفل فقط برای full screen گذاشته شد چون گیت T09 همان را می‌خواست | `t09/…/widget-panel-390.png`، check «the docked sheet leaves the page scroll untouched» | T13. **بسته با تصمیم «قفل نمی‌شود»:** sheet در 640px و کمتر `aria-modal="false"` است و دکمهٔ expand ندارد، پس modal نیست و قفل scroll صفحه با آن نمی‌خواند؛ قفل `overflow` روی بدنه در iOS هم قابل اعتماد نیست و جای scroll را هنگام بستن جابه‌جا می‌کند. آنچه واقعاً آزار می‌دهد، زنجیر شدن scroll thread به صفحهٔ پشت، با `overscroll-behavior: contain` روی `#spw-msgs` بسته است و matrix آن را در هر دو زبان در 390px می‌سنجد. **بازگشایی و رفع به درخواست مالک (۲۰۲۶-۰۹-۱۸، پیگیری T13):** تصمیم «قفل نمی‌شود» کاربرپسند نبود: sheet کل صفحه را می‌پوشاند، پس scroll صفحهٔ پنهان فقط نوار آدرس مرورگر را جابه‌جا و sheet را کوچک و بزرگ می‌کرد، کشیدن روی header صفحهٔ پشت را می‌برد، و Tab و صفحه‌خوان به صفحهٔ نادیده می‌رسیدند. اکنون `syncModal()` در `widget.js` پنل باز را در 640px و کمتر (همان آستانهٔ CSS، با `matchMedia` و شنوندهٔ `change` برای چرخش گوشی) مثل full screen modal می‌کند: `aria-modal="true"`، `trapFocus()` و همان `lockPageScroll()` با snapshot. قفل `overflow` جای scroll را نگه می‌دارد و بستن sheet صفحه را همان‌جا برمی‌گرداند. پنل docked در 641px و بیشتر شناور و غیر modal ماند. matrix در 390px دو زبان: `aria-modal` برابر `true`، `html`/`body` هر دو `hidden`، ۱۴ Tab داخل sheet، wheel روی header صفحه را از 600px تکان نمی‌دهد، پس از بستن همان 600px و `overflow` دقیقاً `""`، و همان wheel با sheet بسته صفحه را می‌برد (control). iOS پیش از 16 `overflow: hidden` را برای لمس نادیده می‌گیرد؛ آنجا همان `overscroll-behavior: contain` روی thread می‌ماند |
| F-18 (تازه در T09) | قاعدهٔ `.spw-panel--full:has(.spw-side__list) .spw-chips { display: none }` با specificity ‏(0,4,0) بر قاعدهٔ بازگردانی chip در بلوک 640px با ‏(0,3,0) غلبه می‌کرد، پس در ≤640px هم ستون پنهان بود و هم chipها: پرسش‌های ذخیره‌شده در full screen اصلاً دیده نمی‌شدند | `widget.css:482` و `:558` پیش از T09 | T09. **رفع شد:** هر دو بلوک کوچک همان الگوی `:has` را دارند و integration شمار هر سه قاعده را می‌سنجد |
| F-09 | `/public/voice` فقط Answer Desk را می‌سنجد، نه `widget.voice_enabled`؛ با voice خاموش هم transcribe عمومی پذیرفته می‌شود | `PublicChatController.php:42`–`:48` | T12. **رفع شد:** `is_voice_enabled()` تازه **پیش از rate limiter** سنجیده می‌شود و 403 `voice_disabled` برمی‌گرداند، پس سایتی که میکروفن را خاموش کرده نه سهمیهٔ بازدیدکننده خرج می‌کند و نه فراخوان provider. integration: با voice خاموش 403 و شمارندهٔ rate دست‌نخورده، با voice روشن همان فراخوان فقط به اعتبارسنجی صدا می‌رسد (400 `invalid_audio`). مرورگر: یک POST واقعی روی سایت با voice خاموش 403 گرفت |
| F-10 | chipها هنگام کارت انتقال زیر آن می‌مانند؛ موکاپ پنهانشان می‌کند | `baseline/en_US/widget-handoff-1440.png` | T11. **رفع شد:** `syncChips()` تنها نویسندهٔ `#spw-chips.hidden` است و آن را با «در حال نوشتن» یا «کارت پاسخ‌نداده در thread» پنهان می‌کند؛ با «Not now» و پس از ارسال برمی‌گردند. browser در هر چهار ترکیب: chip دیده‌شده پیش از کارت، پنهان هنگام کارت، دیده‌شده پس از dismiss و پس از `is-sent` |
| F-11 | استثنای صفحه، دسته، نوع و شناسه UI ندارد و کلید شناسه ناهم‌نام است (`exclude_ids` در برابر `widget_exclude_ids`) | جدول تنظیمات | در T14 به‌عنوان محدودیت باز ثبت شد. **حل شد در T73 (۲۰۲۶-۰۹-۱۸، به دستور مالک):** کارت «Pages where the chat appears» در سفارشی‌سازی پیشرفته و ردیف خلاصه در برگهٔ Widget؛ خواننده اکنون `exclude_ids`، `exclude_pages` و `widget_exclude_ids` را یکی می‌کند و شناسهٔ صفحهٔ دسته را با شناسهٔ نوشته مقایسه نمی‌کند. integration 27/0 |
| F-12 | دوازده کلید پیش‌فرض بدون نویسنده و خواننده | جدول تنظیمات | در T14 برای T21 ثبت شد. **تعریف شد در T73:** کلیدهای بازنشستهٔ طرح نخست، جمع‌شده در `Settings::WIDGET_RETIRED_DEFAULTS`، با کنترل زندهٔ جانشین هر کدام در `docs/technical/DATA_MODEL_FA.md`؛ حذف نمی‌شوند و T21 تصمیمی دربارهٔ آن‌ها ندارد |
| F-13 | `clarify_options` پاسخ `ChatEngine` در ویجت رندر نمی‌شود و `answer` که Markdown است به‌صورت متن خام نمایش داده می‌شود | `ChatEngine.php:351`–`:353`، `widget.js:182` | T10. **رفع شد:** `renderAnswer()` پاراگراف، فهرست، عنوان، bold، italic، کد درون‌خطی و پیوند http/https را فقط با DOM API می‌سازد؛ `addClarifyOptions()` حداکثر 5 گزینه که فقط composer را پر می‌کنند (D-24). browser: `strong` 1، `ul` 1 با 2 `li`، `code` 1، پیوند امن با `noopener`، بدون نشانهٔ خام؛ کلیک گزینه composer را پر کرد و درخواستی نفرستاد. کد fenced و جدول پشتیبانی نمی‌شوند |
| F-14 | پاسخ 400 `empty` کلید `answer` دارد و `widget.js` هر `data.answer` را موفق می‌خواند. کلاینت پرسش خالی نمی‌فرستد، پس فقط با درخواست دستی رخ می‌دهد | `ChatWidgetController.php:61`–`:68` | T10. **رفع شد در دو سو:** سرور برای `empty` کلید `message` می‌فرستد و `widget.js` فقط `ok && answer` را نوبت دستیار می‌داند. integration: 400 `empty` و `question_too_long` هر دو بدون `answer` |
| F-15 | `GET /widget/settings` روی هر بارگذاری هر صفحه برای هر بازدیدکننده فرستاده می‌شود، حتی اگر پنل هرگز باز نشود | baseline `plugin requests before first send: ["GET widget/settings"]` | T10. **رفع شد با گزینهٔ دوم:** `getSettings()` حذف شد و `voiceEnabled` (تازه)، `voiceAuto` و `voiceName` از `AISAAutoPilotWidget` خوانده می‌شوند؛ بنابراین حتی پس از باز شدن پنل هم درخواست settings نیست. `voiceName` پیش‌فرض ثابت «Google UK English Female» را از دست داد، چون پیش‌تر ویجت مقدار REST (رشتهٔ خالی) را می‌خواند. browser: صفر درخواست افزونه پس از 3 ثانیه و پس از باز شدن پنل، در هر چهار ترکیب |
| F-19 (تازه در T10) | برجسته‌سازی کلمه هنگام خواندن با صدا فقط کلمهٔ اول را روشن می‌کرد: `onboundary` هر span را با `charIdx` ثابت صفر مقایسه می‌کرد | `widget.js` پیش از T10، `var charIdx = 0` بدون افزایش | T10. **رفع شد:** هر `.spw-word` `start` و `end` خود را در متن سادهٔ خوانده‌شده نگه می‌دارد و `speakText()` همان متن ساده را می‌خواند. T10 آن را با بازخوانی کد بست. **در T12 در مرورگر اثبات شد:** با stub کردن `SpeechSynthesisUtterance` و `speechSynthesis` و شلیک رویدادهای واقعی `onboundary` روی همان متنی که به موتور گفتار داده می‌شود، کلمهٔ اول، چهارم و آخر هرکدام **دقیقاً یک** span را روشن کردند، مرز غیرکلمه (`sentence`) چیزی را تغییر نداد و `onend` همه را پاک کرد — در هر دو زبان |
| F-16 | وضعیت ضبط، transcribe و خطای میکروفن برای صفحه‌خوان اعلام نمی‌شود | `setRecordingUi()` | T12. **رفع شد:** ناحیهٔ `role="status" aria-live="polite"` با قلاب `data-spw-voice-status` تنها وقتی رندر می‌شود که `voice_enabled` روشن باشد، و `setVoiceStatus()` تنها نویسندهٔ آن است: فاز ضبط (همان جملهٔ placeholder، پس دیده و شنیده یکی است)، فاز `transcribing`، و پیام پایانی که می‌گوید متن در کادر پیام است و **هنوز چیزی ارسال نشده**. هر شش مسیر خطا ناحیه را خالی می‌کنند (خودِ خطا در `role="log"` اعلام می‌شود) و ارسال عمدی هم آن را پاک می‌کند. کلاس `.spw-live` بصری پنهان است ولی `display:none` نیست، چون ناحیهٔ پنهان‌شده اصلاً خوانده نمی‌شود |
| F-20 (تازه در T11) | رونوشتی که کارت انتقال می‌فرستد `textContent` حباب را می‌خواند. از T10 پاسخ با `renderAnswer()` به پاراگراف و فهرست تبدیل می‌شود و `textContent` مرز بلوک‌ها را ندارد، پس تیم پشتیبانی «… find a clear answer to that yet.Would you like me to …» را می‌گرفت | بدنهٔ mockشدهٔ `/widget/handoff` پیش از T11 | T11. **رفع شد:** `addMessage()` همان خوانش سادهٔ پاسخ (که برای خواندن با صدا ساخته می‌شود) را روی گره نگه می‌دارد و `requestHandoff()` آن را می‌فرستد؛ `textContent` جایگزین است اگر نبود. browser: نقش‌ها `user,assistant`، بدون `yet.Would` و دست‌کم دو خط |
| F-21 (تازه در T11) | پیش‌فرض برچسب دکمهٔ انتقال در فرم مدیر `Talk to human` بود و در renderer `Talk to a human`؛ `handoff_text` کلید `widget_defaults()` نیست، پس نخستین ذخیرهٔ tab Widget متن دیگری از آنچه بازدیدکننده دیده بود ذخیره می‌کرد | `ChatWidgetSettings.php:305` و `:477` در برابر `ChatWidget.php:42` | T11. **رفع شد:** هر دو `Talk to a human`. msgid قدیمی `Talk to human` در PO می‌ماند تا بازتولید POT در T18 |
| F-22 (تازه در T11) | چهار کلید handoff در دادهٔ localize: `handoffText` و `handoffHook` را `widget.js` هرگز نمی‌خواند (هر دو در markup رندر می‌شوند) و `handoffSending`/`handoffSent` نسخهٔ تکراری `i18n.sending` و `i18n.sent` بودند؛ یعنی متن نوشتهٔ مدیر روی هر بارگذاری هر صفحه بی‌دلیل فرستاده می‌شد | `Plugin.php:141`–`:144` پیش از T11 | T11. **رفع شد:** هر چهار کلید حذف شدند و `requestHandoff()` از `str('sending')` و `str('sent')` می‌خواند. assertion آزمون lifecycle که وجود آن‌ها را می‌خواست، دقیقاً به قرارداد تازه بازنویسی شد: greeting و voiceName در دادهٔ اسکریپت، برچسب و قلاب در markup |
| F-23 (تازه در T13) | متن کمکی کوچک کنتراست کافی نداشت: برچسب «Today» و placeholder ‏2.55:1، footnote ‏2.26:1، خط منبع 3.62:1 و برچسب ستون پرسش‌ها 2.66:1 (کمینهٔ WCAG AA برای متن کوچک 4.5:1) | اجرای نخست matrix، `20260918-t13/run1-en-main.txt` | T13. **رفع شد:** `--spw-faint` از `#9a9db8` به `#686b8a` (دست‌کم 4.56:1 روی همهٔ سطوح روشن تا `#eef0fb`) و در تم تیره از `#6b7280` به `#9098a6`؛ footnote (`#a9abc4`) و خط منبع (`#7e81a3`) رنگ ثابت خود را از دست دادند و همان توکن را می‌خوانند. اندازه‌گیری مرورگر: 4.95 تا 5.17 به 1. همین رنگ‌های کم‌رنگ در `aisaautopilot-ui.css` مدیر هم هست (برای T17) |
| F-24 (تازه در T13) | عنوان پیش‌فرض لاتین («AI Site Assistant AutoPilot») با `dir="auto"` (F-06) در سایت فارسی چپ‌چین می‌شد و زیرعنوان فارسی راست‌چین، پس دو سطر header از هم جدا بودند؛ همین برای متن nudge و پرسش ستون با متن لاتین | بازبینی تصویری `t13/fa_IR/widget-fullscreen-1440.png` و `widget-panel-390.png` پیش از اصلاح | T13. **رفع شد:** `text-align` عنوان، زیرعنوان، متن nudge و پرسش ستون از `dir` ریشهٔ ویجت می‌آید. `text-align: match-parent` نخست امتحان شد و در fa_IR 390 شکست خورد: Chrome آن را نمی‌شناسد (`CSS.supports` برابر false) و فقط `-webkit-match-parent` را دارد. check لبهٔ متن عنوان و زیرعنوان را با Range می‌سنجد |
| F-25 (تازه در T13) | هنگام ضبط، placeholder «Listening… tap the mic again to stop» در composer یک‌خطی بریده می‌شد؛ در فارسی «… برای توقف دوباره روی» بدون «میکروفن بزنید» | بازبینی تصویری `t13/*/widget-voice-recording-390.png` پیش از اصلاح | T13. **رفع شد:** `autoGrow()` کادر خالی را به اندازهٔ placeholder می‌گیرد و `setRecordingUi()` پس از عوض کردن placeholder آن را صدا می‌زند. check ارتفاع لازم متن placeholder را با ارتفاع کادر مقایسه می‌کند. **بازنگری به درخواست مالک (۲۰۲۶-۰۹-۱۸، پیگیری T13):** کادر دوخطی هنگام ضبط کاربرپسند نبود (پرش ارتفاع composer و راهنمای متنی به‌جای نشانهٔ دیداری). اکنون placeholder ضبط `i18n.listening` یک‌خطی («Listening…» / «در حال گوش دادن...»، msgid موجود در PO) است، کادر قاب coral با نقطهٔ زنده و زمان m:ss به رقم زبان صفحه (`Intl.NumberFormat`؛ فارسی «۰:۰۱») می‌گیرد، و هر دو دکمهٔ میکروفن به دکمهٔ توقف (مربع `ICON_STOP`) با `aria-label` و `title` «Stop recording» بدل می‌شوند؛ جملهٔ کامل «… tap the mic again to stop» در ناحیهٔ status برای صفحه‌خوان ماند. matrix در 1440 و 390 دو زبان: ارتفاع کادر هنگام ضبط برابر حالت عادی (50px و 53px)، placeholder کامل دیده می‌شود، زمان جلو می‌رود، کنتراست زمان 6.01:1، و توقف همه را برمی‌گرداند. `autoGrow()` برای placeholderهای بلندتر از کادر ماند. **یافتهٔ جانبی:** دکمهٔ میکروفن composer transition نداشت و `transition: all` دکمه‌های قالب hello-elementor به آن نشت می‌کرد؛ رنگ coral آهسته می‌آمد و تصویرهای T13 کمرنگ گرفته شده بودند. `.spw-mic` اکنون transition خودش را دارد (برابر دکمه‌های header) |
| F-26 (تازه در پیگیری T13) | دکمهٔ «آخرین پیام» (`#spw-jump`) وقتی focus داشت خودش را پنهان می‌کرد و focus به `<body>` می‌افتاد: بیرون از پنل و بیرون از trap حالت modal. این ریشهٔ شکست گذرای «Tab از full screen بیرون رفت» در T13 است: Tab پس از آخرین فیلد thread به دکمه می‌رسید، در حالی که scroll نرم thread هنوز به انتها نرسیده بود. با کلیک روی دکمه هم رخ می‌داد | اجرای دوبارهٔ matrix در ۲۰۲۶-۰۹-۱۸ (`20260918-t13-followup/matrix-rerun.txt`: `fa_IR main 1440 … name → email → <body>`)؛ probe قطعی: `activeAfter: "BODY"` | پیگیری T13. **رفع شد:** `syncJump()` پیش از `jump.hidden = true`، اگر دکمه focus داشته باشد، focus را با `preventScroll` به `#spw-input` می‌دهد. probe پس از رفع `spw-input` برگرداند. check تازه با ردیف‌های پرکنندهٔ موقت آن را در هر چهار ترکیب قطعی می‌سنجد، و render integration قرارداد کد را دارد |

## تصمیم‌های مالک

هر سه ردیف در ۲۰۲۶-۰۹-۱۷ با پیام مالک «پیشنهادت خوودت را انجام بده فعلا» طبق پیشنهاد تأیید شدند: D-25 گزینهٔ الف با `voice_auto` پیش‌فرض خاموش (اجرا در T08)، D-24 ارسال با کلیک (اجرا در T10)، D-02 خیر (اجرا در T12).

| ID | task | پرسش | پیشنهاد (تأییدشده) |
|---|---|---|---|
| D-02 | T12 | overlay تمام‌صفحهٔ voice موکاپ ساخته شود؟ | خیر. کد هیچ session یا transport realtime ندارد و `AnswerDeskVoicePage` همین را به مدیر می‌گوید. اگر بله: فقط phaseهای واقعی «در حال ضبط»، «در حال تبدیل به متن» و «در حال خواندن پاسخ»، بدون زیرنویس زنده، barge-in، Mute، چیپ «auto-detected» و «Not recorded» |
| D-24 | T10 | پرسش‌های پیشنهادی ویجت با کلیک ارسال شوند (موکاپ و کد فعلی) یا فقط composer را پر کنند (قاعدهٔ `ARCHITECTURE_FA.md` برای chat، Form Composer و EFB)؟ | ارسال با کلیک بماند و قاعده صریحاً دامنه‌دار شود. متن را مدیر نوشته، نه AI؛ هیچ عمل مخربی ندارد؛ و قاعدهٔ «انسان پیش از اجرا می‌بیند» دربارهٔ پیشنهاد AI است. هزینه: هر کلیک یک پرسش از سقف 20 در ساعت و مصرف provider است |
| D-25 | T08 | با `auto_open` چه شود؟ | (الف، پیشنهاد) پیش‌فرض 0 و کنترل «باز شدن خودکار پس از N ثانیه» در سفارشی‌سازی پیشرفته؛ مقدار ذخیره‌شدهٔ صریح دست نمی‌خورد (امروز هیچ UI آن را ننوشته، پس عملاً همهٔ سایت‌ها تغییر می‌کنند و nudge دیده می‌شود). (ب) پیش‌فرض 5 بماند و فقط کنترل اضافه شود. (ج) تغییری نکند و فقط مستند شود. پرسش فرعی: پیش‌فرض `voice_auto=true` (خواندن خودکار هر پاسخ با صدا) هم `false` شود؟ پیشنهاد: بله، چون مدیر در tab Real-time voice می‌تواند روشنش کند |

## visual QA پایهٔ T07

روش: Playwright روی `file://` موکاپ در 1440×960 و 390×844، هر دو جهت، چهار حالت ثابت و سه phase voice (28 قاب)؛ و ویجت واقعی روی صفحهٔ اول قالب فعال با fixture موقت که تنظیمات و `WPLANG` را snapshot و بازگرداند، در `en_US` و `fa_IR` (18 تصویر). `/widget/ask` با شکل پاسخ واقعی `ChatEngine` mock شد و `/public/*` و `/widget/handoff` مسدود بودند؛ هیچ provider یا کانالی تماس نگرفت.

| جفت | نتیجه |
|---|---|
| Panel 1440 | ساختار، رنگ navy، header، حباب‌ها، source، chipها و composer هم‌خوان. اختلاف: ابعاد و گوشه (F-02)، عنوان طولانی پیش‌فرض با ellipsis، فونت (پایین) |
| Full screen 1440 RTL | هم‌خوان، شامل ستون پرسش‌ها و بخش انسان. اختلاف: جهت متن لاتین (F-06)، پیوند source زیرخط‌دار |
| Handoff 1440 | کارت زرد هم‌خوان. اختلاف: یک دکمهٔ ارسال (W-02)، فیلد ایمیل اختیاری (D-20)، chipها زیر کارت (F-10) |
| Launcher 1440 | حباب هم‌خوان؛ نشان «1» و nudge نیست (اولی عمدی، دومی F-01) |
| Panel 390 | sheet تمام‌صفحه به‌جای قاب با حاشیه (تصمیم breakpoint)؛ expand پنهان؛ بدون overflow افقی |
| Voice | در افزونه overlay وجود ندارد (D-02). جایگزین صادقانه: میکروفن در جای خود، placeholder و ناحیهٔ `role="status"` برای سه فاز واقعی، و متن در composer. زیرنویس زنده، barge-in، Mute، End، چیپ «auto-detected» و «Not recorded» ساخته نشدند چون هیچ‌کدام پشتوانهٔ backend ندارند — در T12 در مرورگر هم اثبات شد که هیچ متن جزئی حین transcribe ظاهر نمی‌شود |

اختلاف عمدی فونت: موکاپ Plus Jakarta Sans از Google Fonts بار می‌کند. ویجت روی سایت بازدیدکننده فونت بیرونی بار نمی‌کند (حریم خصوصی و سرعت) و stack سیستمی با Vazirmatn دارد.

| سنجهٔ baseline | en_US | fa_IR |
|---|---|---|
| `html[lang]` و `direction` پنل | `en-US`، `ltr` | `fa-IR`، `rtl` |
| متن فارسی واقعی | — | placeholder «پیامی بنویسید…» و footnote فارسی؛ عنوان، greeting و chipها انگلیسی (مقدار fixture و F-05) |
| درخواست افزونه پیش از نخستین ارسال | فقط `GET widget/settings` | فقط `GET widget/settings` |
| console، pageerror و HTTP ≥ 400 | 0 | 0 |
| overflow افقی سند در 1440 و 390 | 0 | 0 |
| Escape از full screen | خارج شد | خارج شد |

## شواهد T07

| مورد | نتیجه | مسیر |
|---|---|---|
| قاب‌های موکاپ | 28 تصویر: `launcher`، `panel`، `fullscreen`، `handoff` و `voice-listening|thinking|speaking` × `ltr|rtl` × `1440|390` | `test-artifacts/front-chat-widget/mockup/` |
| baseline ویجت | 9 تصویر در هر locale | `test-artifacts/front-chat-widget/baseline/` |
| لاگ capture | exit 0 در هر دو اجرا | `20260917-t07/capture-mockup.txt`، `capture-plugin.txt` |
| اسکریپت‌ها | fixture و capture موقت، بیرون از repo | `20260917-t07/t07_widget_fixture.php`، `t07_capture.cjs` |
| پاک‌سازی | «T07 widget fixture removed.» پس از هر locale و در `finally`؛ `aisaautopilot_settings` همچنان نبود، `WPLANG` همان رشتهٔ خالی و option snapshot حذف شد؛ preflight با WARNINGS 0 و BLOCKERS 0 | `20260917-t07/preflight-after-capture.txt` |
| بدون تغییر کد | mtime و اندازهٔ 279 فایل `includes`، `assets`، `tests`، `languages`، `scripts` و دو فایل ریشه پیش و پس از task مقایسه شد | `20260917-t07/code-mtime-before.txt` و `code-mtime-after.txt` |

## ریزتسک‌ها و گیت‌ها

| ID | رودمپ | کار | وضعیت | گیت پذیرش |
|---|---|---|---|---|
| S3.3.0 | T07 | ممیزی، نگاشت موکاپ به قرارداد، baseline تصویری و این برنامه | تکمیل (۲۰۲۶-۰۹-۱۷) | سند کامل، همهٔ کنترل‌های موکاپ نگاشت‌شده، D-02 مطرح، بدون تغییر کد |
| S3.3.1 | T08 | launcher و panel: ابعاد و گوشهٔ موکاپ (F-02)، حذف نقطهٔ «آنلاین» (F-03)، hit target 44px (F-04)، پیش‌فرض‌های ترجمه‌پذیر (F-05)، `auto_open` طبق D-25 (F-01)، `prefers-reduced-motion` | تکمیل (۲۰۲۶-۰۹-۱۷): شواهد در بخش «شواهد T08» | `tests/wordpress_front_chat_widget_render_integration.php` با N/0 (دروازهٔ روشن و خاموش، `dir`، متغیرهای accent و ابعاد، config محلی‌شده، نبود secret در markup و `AISAAutoPilotWidget`، `data-spw-auto-open`)؛ `node --check`؛ PHPCS و PHPStan متمرکز؛ مقایسهٔ screenshot دسکتاپ با `mockup/widget-panel-ltr-1440.png` |
| S3.3.2 | T09 | full screen: breakpoint 768px، قفل scroll بدنه (F-08)، focus trap و بازگشت focus، هم‌ترازی آستانهٔ JS، و رفع F-18 | تکمیل (۲۰۲۶-۰۹-۱۷): شواهد در بخش «شواهد T09» | browser: expand، collapse با دکمه، scrim و Escape، بازگشت focus به opener، Tab داخل پنل می‌ماند؛ overflow افقی صفر در 390 و 768؛ screenshot دسکتاپ و موبایل در LTR و RTL |
| S3.3.3 | T10 | گفت‌وگو: رفتار chip طبق D-24، `dir="auto"` (F-06)، Stop صادقانه و 429 بدون retry فوری (F-07)، `clarify_options` و Markdown امن (F-13)، F-14، درخواست settings فقط پس از باز شدن (F-15) | تکمیل (۲۰۲۶-۰۹-۱۷): شواهد در بخش «شواهد T10»؛ F-19 تازه هم رفع شد | browser با `page.route` برای پاسخ با source، بدون source، clarify، 500، 429 `rate_limited` و `usage_cap_reached`؛ صفر درخواست افزونه پیش از باز شدن پنل؛ integration اگر قرارداد PHP تغییر کند؛ به‌روزرسانی `ARCHITECTURE_FA.md` |
| S3.3.4 | T11 | کارت handoff: پنهان شدن chipها (F-10)، حالت ready و not-ready، پیام تأیید صادقانه؛ F-20، F-21 و F-22 تازه هم رفع شدند | تکمیل (۲۰۲۶-۰۹-۱۷): شواهد در بخش «شواهد T11» | integration برای offer در حالت ready و not-ready (با `pre_wp_mail` و `pre_http_request` بسته)؛ browser با `/widget/handoff` mock و بدون دکمه در not-ready |
| S3.3.5 | T12 | voice صادقانه: transcript-first، `aria-live` وضعیت (F-16)، دروازهٔ `voice_enabled` روی `/public/voice` (F-09)، overlay طبق D-02 | تکمیل (۲۰۲۶-۰۹-۱۸): F-09، F-16 و F-19 بسته؛ overlay طبق D-02 ساخته نشد؛ شواهد در بخش «شواهد T12» | browser با mock `getUserMedia` و `MediaRecorder`: ارسال خودکار صفر، transcript در composer، اعلام وضعیت؛ integration 403 با voice خاموش؛ screenshot حالت ضبط |
| S3.3.6 | T13 | browser matrix: `tests/front_chat_widget_browser_matrix.cjs`، `tests/front_chat_widget_browser_check.cjs` و `tests/local_front_chat_widget_browser_fixture.php` (snapshot تنظیمات و `WPLANG`، مثل fixture موقت T07) | تکمیل (۲۰۲۶-۰۹-۱۸): 385/0؛ F-17 بسته، F-23 تا F-25 تازه رفع شدند؛ شواهد در بخش «شواهد T13» | exit 0 در `en_US` و `fa_IR`، 1440×960 و 390×844، keyboard، focus، contrast، overflow و صفر console و pageerror و HTTP؛ جفت screenshot با قاب‌های `mockup/`؛ preflight بدون باقی‌مانده. fixture همچنین DG-01 راهنمای کاربر را می‌بندد |
| S3.3.7 | T14 | regression و بستن اسناد | تکمیل (۲۰۲۶-۰۹-۱۸): full 9/9 در دو اجرا، matrix 385/0، render integration 92/0؛ گزارش اجرا نوشته شد؛ شواهد در بخش «شواهد T14» | `run-gates.ps1 -Tier full`؛ `docs/FRONT_CHAT_WIDGET_S3_3_EXECUTION_REPORT_FA.md` با F-11 و F-12 و فهرست اختلاف‌های عمدی؛ tracker S3.3 ← تکمیل؛ شمارهای یکسان در همهٔ اسناد |

## اختلاف‌های عمدی ثبت‌شده تا اینجا

| موکاپ | افزونه | دلیل |
|---|---|---|
| نشان «1» روی حباب | ساخته نمی‌شود | شمار خوانده‌نشدهٔ واقعی نیست |
| جای حباب در RTL آینه می‌شود | فیزیکی (X-02) | انتخاب مدیر مقدم است |
| «Answers come from this store.» | «… and can be wrong.» (X-03) | صراحت دربارهٔ خطا |
| دو دکمهٔ کانال در کارت انتقال | یک دکمهٔ ارسال به همهٔ کانال‌های آماده (W-02) | **بسته می‌شود در T61 (S7.4):** کارت چندگامه با انتخاب دپارتمان و راه ارتباط، فقط از کانال‌هایی که واقعاً آماده‌اند. T38 در همان‌جا ادغام می‌شود |
| موکاپ حالت «ارسال‌شده» ندارد | کارت ارسال‌شده در thread می‌ماند، سبز می‌شود، هر دو دکمه را می‌اندازد و فقط «Sent — we will be in touch.» می‌گوید؛ chipها برمی‌گردند | بازدیدکننده باید ببیند چه چیزی فرستاده شده و دستیار همچنان کار می‌کند؛ هیچ ادعای اپراتور، حضور یا زمان پاسخ (T11). **در T61 به ماشین حالت واقعی تبدیل می‌شود:** `queued` → `notified` → `claimed` («علی از تیم پشتیبانی به گفتگو پیوست») → `active` → `resolved`، هر حالت از رویداد واقعی |
| موکاپ فیلد تماس ندارد | فیلدهای Off/Optional/Required با اعتبارسنجی مرورگر و سرور (D-20) | قابلیت افزونه بدون UI موکاپ؛ حذف نمی‌شود. در T61 به گام اول کارت با چیدمان تمیزتر منتقل می‌شود |
| موکاپ «Not now» ندارد | دکمهٔ «Not now» کنار ارسال | بازدیدکننده باید بتواند بدون تعهد برگردد؛ می‌ماند |
| overlay صوتی realtime | transcript-first (W-01) | D-02 |
| قاب مرکزی full screen در هر عرضی | در 641 تا 768px تمام viewport، بدون scrim و بدون ستون | تصمیم breakpoint T07؛ ستون 270px بیش از یک‌سوم عرض را می‌گرفت (اجرا در T09) |
| خروج از full screen focus را به دکمهٔ expand برمی‌گرداند | فقط وقتی کنترل focus‌دار ناپدید شده یا focus روی صفحه رها شده باشد | جای بازدیدکننده در composer نیمه‌نوشته دزدیده نمی‌شود (اندازه‌گیری‌شده در T09) |
| Plus Jakarta Sans | stack سیستمی با Vazirmatn | بدون فونت بیرونی روی سایت بازدیدکننده |
| پنل با حاشیه در 390px | sheet تمام‌صفحه در 640px و کمتر | کیبورد گوشی |
| Popular questions نمونه | فقط suggestionهای واقعی مدیر | بدون دادهٔ نمونه |
| نقطهٔ سبز روی آواتار و «Online · replies instantly» | بدون نقطه؛ زیرعنوان همان متن مدیر (T08) | هیچ دادهٔ واقعی آماده بودن دستیار را نمی‌سنجد |
| متن nudge «Ask about products, shipping or your order — day or night.» | متن greeting مدیر (T08: ترجمه‌شده وقتی دست‌نخورده است) | متن موکاپ دربارهٔ فروشگاه نمونه است |
| رنگ متن کمکی `#9a9db8`، `#a9abc4` و `#7e81a3` | یک توکن `--spw-faint` برابر `#686b8a` | کنتراست 4.5:1 برای متن کوچک (F-23، T13) |
| حالت ضبط در overlay (D-02) | در composer: قاب coral، نقطهٔ زنده، زمان m:ss، دکمه‌های میکروفن به دکمهٔ توقف بدل می‌شوند و placeholder یک‌خطی «Listening…»؛ کادر ارتفاع خود را نگه می‌دارد | در موبایل و دسکتاپ یک‌خطی؛ فرمان توقف با خود دکمه دیده می‌شود (F-25، بازنگری به درخواست مالک) |
| پنل شناور غیر modal در 390px | sheet در 640px و کمتر modal است: `aria-modal="true"`، Tab داخل آن و صفحهٔ پشت قفل | sheet کل صفحه را می‌پوشاند؛ scroll صفحهٔ پنهان فقط sheet را با نوار آدرس جابه‌جا می‌کرد (F-17، بازگشایی به درخواست مالک) |

## شواهد T08

روش: fixture و check موقت بیرون از repo (`20260917-t08/t08_widget_fixture.php` و `t08_capture.cjs`) روی صفحهٔ اول قالب فعال، با snapshot و بازگرداندن `aisaautopilot_settings` و `WPLANG`. fixture عمداً `auto_open`، `voice_auto` و ابعاد را تنظیم نمی‌کند تا پیش‌فرض‌های نسخهٔ 2 دیده شوند. `/widget/ask`، `/public/*` و `/widget/handoff` مسدود بودند.

| گیت | نتیجه | مسیر |
|---|---|---|
| `tests/wordpress_front_chat_widget_render_integration.php` | 41/0 در دو اجرای پشت‌سرهم؛ option تنظیمات پس از هر اجرا همچنان وجود نداشت | `20260917-t08/render-integration-run1.txt`، `run2.txt` |
| integrationهای مرتبط | 12 suite سبز: conversations 5/0، lifecycle 6/0، handoff page 7/0، handoff settings 10/0، voice page 4/0، voice settings 5/0، widget page 4/0، widget settings 4/0، handoff inbox 19/0، widget customise 12/0، handoff flow 21/0؛ menu badge پس از اصلاح شمار D-23 برابر 6/0 | `20260917-t08/related-integrations.txt`، `menu-badge-integration.txt` |
| check مرورگر T08 | 83 PASS، 0 FAIL: en_US و fa_IR در 1440×960 و 390×844 | `20260917-t08/capture-t08.txt`، تصویرها در `t08/<locale>/` |
| `widget_customise_browser_matrix.cjs` | en_US 26/0 و fa_IR 29/0، با سه check تازه (کنترل باز شدن خودکار، ذخیرهٔ 10 ثانیه، نبود نقطه در پیش‌نمایش) و check فارسی کنترل و greeting | `20260917-t08/widget-customise-matrix.txt` |
| PHPCS متمرکز | 7 فایل، 0 error و 0 warning | `20260917-t08/phpcs-focused.txt` |
| static | 5/5: php-lint 231/231، node-check 37/37، PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit 149 / 514 | `test-artifacts/gates/20260917-120906-static` |
| full | 9/9: همان static، menu 5/0، assets 20/0، render 18/0، Phase 1 45/0، PHP diagnostic صفر | `test-artifacts/gates/20260917-122714-full` |
| ترجمه | 6 msgid تازه؛ `msgfmt --check` سالم؛ MO با 2280 ترجمه از 2421 ورودی (141 بی‌ترجمه) | خروجی `scripts/compile-mo.php` |
| پاک‌سازی | «T08 widget fixture removed.» و «Local browser QA account removed.»؛ probe: 0 option fixture یا کاربر QA، 0 کاربر QA، تنظیمات ذخیره‌نشده، `WPLANG` خالی؛ preflight WARNINGS 0 و BLOCKERS 0 | `20260917-t08/preflight-after-browser.txt` |

| سنجهٔ browser | en_US 1440 | en_US 390 | fa_IR 1440 | fa_IR 390 |
|---|---|---|---|---|
| `data-spw-auto-open` | 0 | 0 | 0 | 0 |
| nudge بدون باز شدن پنل | بله | بله | بله | بله |
| پنل | 392×624، گوشهٔ 20px | sheet 390×844 | 392×624، گوشهٔ 20px | sheet 390×844 |
| direction | ltr | ltr | rtl | rtl |
| ناحیهٔ کلیک دکمه‌های آیکنی | 44 تا 45px | 44 تا 45px | 44 تا 45px | 44 تا 45px |
| Escape و بازگشت focus به launcher | بله | بله | بله | بله |
| درخواست افزونه | فقط `GET widget/settings` (F-15 برای T10) | همان | همان | همان |
| console، pageerror و HTTP ≥ 400 | 0 | 0 | 0 | 0 |

`prefers-reduced-motion: reduce` در en_US 1440: حلقهٔ launcher `display: none` و animation پنل `none`.

بازبینی تصویری: `t08/en_US/widget-panel-1440.png` با `mockup/widget-panel-ltr-1440.png` در جای پنل، ابعاد، گوشه، header بدون نقطه، chipها و composer هم‌خوان است؛ ریشهٔ هر دو 24px بالای لبهٔ پایین است. `t08/en_US/widget-launcher-1440.png` با `mockup/widget-launcher-ltr-1440.png` هم‌خوان است، جز نشان «1» و متن nudge که عمدی‌اند. در `t08/fa_IR/` جهت پنل، ترتیب دکمه‌های header و composer آینه شده و متن‌های پیش‌فرض فارسی‌اند؛ جای launcher فیزیکی می‌ماند (X-02).

## شواهد T09

روش: fixture و check موقت بیرون از repo (`20260917-t09/t09_widget_fixture.php` و `t09_capture.cjs`)، با همان الگوی T08: snapshot و بازگرداندن دقیق `aisaautopilot_settings` و `WPLANG`، و restore بدون snapshot بی‌اثر. fixture سه پرسش پیشنهادی واقعی و کانال ایمیل آماده می‌گذارد تا ستون «پرسش‌های پرتکرار» و بخش انسانی آن داده داشته باشند. `/widget/ask`، `/public/*` و `/widget/handoff` مسدود بودند، پس هیچ provider یا کانالی تماس نگرفت. سه عرض: 1440×960 (قاب مرکزی)، 768×900 (مرز تصمیم breakpoint) و 390×844.

| گیت | نتیجه | مسیر |
|---|---|---|
| check مرورگر T09 | **114 PASS، 0 FAIL** در en_US و fa_IR × سه عرض | `20260917-t09/capture-t09.txt`، تصویرها در `t09/<locale>/` |
| `tests/wordpress_front_chat_widget_render_integration.php` | از 41/0 به **49/0** رسید (هشت check تازهٔ T09) و در دو اجرای پشت‌سرهم سبز ماند | `20260917-t09/render-integration-run2.txt` |
| static | 5/5: php-lint 231/231، node-check 37/37، PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit 149 / 514 (بدون افت از خط مبنا) | `test-artifacts/gates/20260917-135629-static` |
| پاک‌سازی | «T09 widget fixture removed.» پس از هر locale و دوباره در `finally`؛ probe: option تنظیمات همچنان ذخیره‌نشده، `WPLANG` همان رشتهٔ خالی، option fixture غایب؛ preflight WARNINGS 0 و BLOCKERS 0 | `20260917-t09/preflight-after-browser.txt` |

| سنجهٔ browser | 1440 | 768 | 390 |
|---|---|---|---|
| پنل در full screen | 940×760، گوشهٔ 22px | 768×900، گوشهٔ 0px (تمام viewport) | دکمهٔ expand `display: none`؛ sheet تمام‌صفحه |
| scrim | `block` و کلیک‌پذیر | `display: none` | — |
| ستون پرسش‌ها | `flex` با 3 پرسش ذخیره‌شده و بخش انسانی | `display: none` | — |
| chipهای thread | `display: none` (پرسش‌ها یک‌بار دیده می‌شوند) | `flex` با 3 chip | `flex` با 3 chip |
| `aria-modal` | `false` ← `true` ← `false` | همان | همیشه `false` |
| قفل scroll | `html`/`body` هر دو `hidden`، تلاش scroll صفر پیکسل | همان | بدون قفل (F-17) |
| بازگرداندن قفل | پس از Escape، کلیک scrim و بستن پنل: دقیقاً `""` | همان | — |
| focus | ورود: داخل پنل؛ ۲۴ Tab و Shift+Tab بیرون نمی‌رود | همان | — |
| بازگشت focus | از پرسش ستون → `#spw-expand`؛ از scrim → `#spw-expand`؛ از composer → composer می‌ماند؛ بستن → launcher | composer می‌ماند؛ بستن → launcher | بستن → launcher |
| برچسب دکمه | «Full screen» ↔ «Exit full screen»؛ در fa_IR «تمام‌صفحه» ↔ «خروج از تمام‌صفحه» | همان | — |
| overflow افقی، console، pageerror، HTTP ≥ 400 | 0 | 0 | 0 |
| درخواست افزونه | فقط `GET widget/settings` (F-15 برای T10) | همان | همان |

بازبینی تصویری: `t09/en_US/widget-fullscreen-1440.png` در برابر `mockup/widget-fullscreen-ltr-1440.png` هم‌خوان است — همان قاب مرکزی با scrim، header با دکمهٔ برچسب‌دار Voice و دکمهٔ shrink، ستون راست با عنوان «POPULAR QUESTIONS»، کارت‌های پرسش، جداکنندهٔ افقی و بخش «Can’t find it?» با دکمهٔ حاشیه‌دار. اختلاف‌ها همه از پیش ثبت‌شده‌اند: گفت‌وگوی نمونهٔ موکاپ و زیرعنوان «Online · answers from this store» (نقطه و ادعای آنلاین در T08 حذف شد)، فونت Plus Jakarta Sans در برابر stack سیستمی، و متن‌های پیش‌فرض محصول به‌جای Northwind. `t09/fa_IR/widget-fullscreen-1440.png` کاملاً آینه است: ستون در سمت چپ، header و composer معکوس و همهٔ متن‌ها فارسی. در 768 هر دو زبان تمام viewport را می‌گیرند، ستون حذف و chipها زیر خوشامد برمی‌گردند و composer روی 660px وسط‌چین می‌ماند؛ موکاپ برای این عرض قابی ندارد و این اختلاف عمدی تصمیم breakpoint T07 است.

## شواهد T10

روش: fixture و check موقت بیرون از repo (`20260917-t10/t10_widget_fixture.php` و `t10_capture.cjs`) با همان الگوی T08 و T09: snapshot و بازگرداندن دقیق `aisaautopilot_settings` و `WPLANG`، و restore بدون snapshot بی‌اثر. fixture دو پرسش پیشنهادی واقعی و voice روشن (`voice_auto` خاموش) می‌گذارد و همهٔ کانال‌های انتقال را خاموش نگه می‌دارد تا کارت انتقال (دامنهٔ T11) وسط check نیاید. هر پاسخ `/widget/ask` با `page.route` و شکل واقعی `ChatEngine::result()` mock شد؛ `/public/*` و `/widget/handoff` مسدود بودند، پس هیچ provider، کانال یا بودجه‌ای لمس نشد. دو عرض: 1440×960 و 390×844.

| گیت | نتیجه | مسیر |
|---|---|---|
| check مرورگر T10 | **106 PASS، 0 FAIL** در en_US و fa_IR × 1440 و 390. سه اجرا: اجرای نخست 102 PASS و 4 FAIL داد و هر چهار شکست، سنجهٔ console در هر ترکیب بود که سطرهای «Failed to load resource» خودِ Chrome برای پاسخ‌های 503، 429 و 400 عمداً mockشده را شمرده بود؛ فیلتر فقط برای سطرهایی افزوده شد که `location().url` آن‌ها `/widget/ask` است. اجرای دوم 106/0. در اجرای سوم فقط مکث 400ms پیش از هر capture افزوده شد (تصویر اول وسط انیمیشن 0.28s ورود پیام گرفته شده بود) و باز 106/0 | `20260917-t10/capture-t10.txt`، تصویرها در `t10/<locale>/` |
| `tests/wordpress_front_chat_widget_render_integration.php` | از 49/0 به **62/0** (سیزده check تازهٔ T10) و در دو اجرا سبز | `20260917-t10/render-integration-run2.txt` |
| integrationهای مرتبط | usage cap response 6/0، lifecycle 6/0، widget settings 4/0، widget page 4/0، handoff flow 21/0، voice settings 5/0، conversations 5/0، widget customise 12/0 | `20260917-t10/related-integrations.txt` |
| static | 5/5: php-lint 231/231، node-check 37/37، PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit 149 / 514 (بدون افت) | `test-artifacts/gates/20260917-193637-static` |
| ترجمه | 3 msgid تازه (`Stop waiting for the reply`، یادداشت توقف، `Choose what you mean`)؛ `msgfmt --check` سالم؛ MO با 2283 ترجمه از 2424 ورودی PO (141 بی‌ترجمهٔ کهنه) | خروجی `scripts/compile-mo.php` |
| پاک‌سازی | «T10 widget fixture removed.» پس از هر locale و دوباره در `finally`؛ preflight: `settings_stored=False`، WARNINGS 0 و BLOCKERS 0؛ `debug.log` امروز بدون PHP warning افزونه | `20260917-t10/preflight-after-browser.txt` |

| سنجهٔ browser | en_US 1440 | en_US 390 | fa_IR 1440 | fa_IR 390 |
|---|---|---|---|---|
| درخواست افزونه پس از 3 ثانیه و پس از باز شدن پنل | 0 | 0 | 0 | 0 |
| `AISAAutoPilotWidget.voiceEnabled` / `voiceAuto` | `"1"` / `""` | همان | همان | همان |
| پاسخ Markdown: `strong`، `ul`، `li`، `code`، `p` | 1، 1، 2، 1، 3 | همان | همان | همان |
| نشانهٔ خام (`**`، `](`، backtick) | 0 | 0 | 0 | 0 |
| پیوند درون پاسخ | `https`، `_blank`، `noopener noreferrer nofollow` | همان | همان | همان |
| خط منبع | فقط با citation؛ پاسخ بدون citation بدون خط | همان | همان | همان |
| جهت حباب پاسخ لاتین | `dir="auto"` | همان | `dir="auto"` و `direction: ltr` | همان |
| گزینهٔ clarify | 2 گزینه؛ کلیک composer را پر و focus کرد؛ 0 درخواست تازه | همان | همان | همان |
| 503 / 429 `rate_limited` / 429 `usage_cap_reached` / 400 `empty` | retry 1 / 0 / 0 / 0 | همان | همان | همان |
| برچسب دکمه هنگام انتظار | «Stop waiting for the reply» | همان | «توقف انتظار برای پاسخ» | همان |
| پس از Stop | 1 یادداشت، 0 نوبت تازه، typing حذف، دکمه «Send»؛ پاسخ دیر 3.5 ثانیه بعد هم نیامد | همان | یادداشت فارسی، دکمه «ارسال» | همان |
| overflow افقی، console، pageerror، HTTP ≥ 400 غیر mock | 0 | 0 | 0 | 0 |

بازبینی تصویری: `t10/en_US/widget-answer-1440.png` در برابر `mockup/widget-panel-ltr-1440.png` هم‌خوان است — حباب سفید پاسخ با حاشیهٔ نازک و گوشهٔ پایینی کوچک، خط «From …» با آیکون کتاب زیر آن و chipها در انتهای thread؛ پاسخ اکنون عنوان پررنگ، فهرست نقطه‌دار، پیوند زیرخط‌دار و کد درون‌خطی با پس‌زمینهٔ کم‌رنگ دارد که موکاپ نشان نمی‌دهد. `t10/fa_IR/widget-clarify-1440.png`: پنل آینه شده، حباب‌های لاتین از چپ به راست خوانده می‌شوند و دو گزینهٔ clarify زیر پرسش ابهام قرار دارند. `t10/fa_IR/widget-stopped-390.png`: حباب‌های خطا بدون «تلاش دوباره» برای سقف‌ها و با آن برای 503، و یادداشت فارسی وسط‌چین پس از توقف.

باز و عمدی:
- composer `dir="auto"` ندارد؛ متن لاتین پرشده در composer سایت فارسی راست‌چین دیده می‌شود (`t10/fa_IR/widget-clarify-1440.png`). رفتار `dir="auto"` روی `textarea` خالی در مرورگرها یکسان نیست و placeholder فارسی ممکن است چپ‌چین شود؛ تصمیم در بازبینی تصویری T13. **بسته در T13:** Chrome کادر خالی با `dir="auto"` را ltr حل کرد (probe)، پس `dir` ایستا گذاشته نشد؛ `autoGrow()` که همهٔ نوشتن‌ها در composer از آن می‌گذرد، `dir="auto"` را فقط وقتی متن هست می‌گذارد و با خالی شدن برمی‌دارد.
- پشتیبانی Markdown عمداً محدود به زیرمجموعهٔ تولیدی engine است؛ بلوک کد fenced و جدول متن خام می‌مانند.
- F-19 (برجسته‌سازی کلمه) فقط با بازخوانی کد و قرارداد offset ثابت شده، نه در مرورگر؛ وارسی در T12.

## شواهد T11

روش: fixture و check موقت بیرون از repo (`20260917-t11/t11_widget_fixture.php` و `t11_capture.cjs`) با همان الگوی T08 تا T10: snapshot و بازگرداندن دقیق `aisaautopilot_settings` و `WPLANG` پیش از هر تغییر، و restore بدون snapshot بی‌اثر. fixture دو حالت دارد: `ready` با یک کانال ایمیل کامل به نشانی `.invalid` و «نام اختیاری، ایمیل الزامی»، و `off` با همهٔ کانال‌ها خاموش. هر دو مسیر `/widget/ask` و `/widget/handoff` با `page.route` و شکل واقعی `ChatEngine::result()` و `ChatWidgetController::handoff()` پاسخ داده شدند و `/public/*` مسدود بود، پس هیچ provider، ایمیل، پیامک، پیام Telegram، ردیف صندوق یا بودجه‌ای لمس نشد. چهار ترکیب زبان و عرض در هر دو حالت: en_US و fa_IR × 1440×960 و 390×844.

| گیت | نتیجه | مسیر |
|---|---|---|
| check مرورگر T11 | **140 PASS، 0 FAIL** در en_US و fa_IR × 1440 و 390 × (ready، not-ready). اجرای نخست 16 PASS و 1 FAIL داد و شکست در خودِ check بود نه در محصول: انتظار برای «خطای فیلد» نخستین `[data-spw-handoff-error]` را می‌گرفت که جعبهٔ خطای فیلد **نام** است و هرگز پر نمی‌شود؛ selector به فیلدی که سرور نام می‌برد دقیق شد (`[data-spw-handoff-field="email"][aria-invalid="true"]`)، بدون تضعیف هیچ سنجه‌ای | `20260917-t11/capture-t11.txt`، تصویرها در `t11/<locale>/` |
| `tests/wordpress_front_chat_widget_render_integration.php` | از 62/0 به **76/0** (چهارده check تازهٔ T11) | `20260917-t11/render-integration.txt` |
| integrationهای مرتبط | usage cap 6/0، lifecycle 6/0 (پس از بازنویسی assertion به قرارداد تازهٔ F-22)، widget settings 4/0، widget page 4/0، handoff flow 21/0، voice settings 5/0، conversations 5/0، widget customise 12/0، handoff settings 10/0، handoff page 7/0، handoff inbox 19/0 | `20260917-t11/related-integrations.txt` |
| static | 5/5: php-lint 231/231، node-check 37/37، PHPCS 134/134 با **0 خطا و 0 هشدار**، PHPStan 0، PHPUnit 149 / 514 (بدون افت). اجرای نخست 4/5 بود: حذف بلندترین کلیدهای آرایهٔ localize هم‌ترازی `=>` هفت سطر را به هم زد و `phpcbf` روی همان یک فایل آن را برگرداند | `test-artifacts/gates/20260917-225056-static` |
| smoke | 8/8: menu-smoke 5/0، assets-smoke 20/0، render-smoke 18/0 | `test-artifacts/gates/20260917-225233-smoke` |
| ترجمه | هیچ رشتهٔ نمایشی تازه‌ای افزوده نشد؛ `Talk to a human` از پیش در PO ترجمه داشت، پس PO و MO تغییر نکردند. msgid بی‌استفادهٔ `Talk to human` تا بازتولید POT در T18 می‌ماند | — |
| پاک‌سازی | «T11 widget fixture removed.» پس از هر چهار آماده‌سازی و دوباره در `finally`؛ preflight: `settings_stored=False`، WARNINGS 0 و BLOCKERS 0 | `20260917-t11/preflight-after-browser.txt` |

| سنجهٔ browser (حالت ready) | en_US 1440 | en_US 390 | fa_IR 1440 | fa_IR 390 |
|---|---|---|---|---|
| chip پیش از کارت / هنگام کارت / پس از «Not now» / پس از ارسال | دیده / پنهان / دیده / دیده | همان | همان | همان |
| دکمه‌های `data-spw-handoff` و قالب کارت | 2 و موجود | همان | همان | همان |
| فیلدهای کارت | `name`، `email*` | همان | همان | همان |
| ناحیهٔ نتیجه | `status/polite` و خالی در آغاز | همان | همان | همان |
| جهت کارت / جهت فیلد ایمیل | `ltr` / `ltr` | همان | `rtl` / `ltr` | همان |
| «Not now» | کارت حذف، focus در composer، 0 درخواست | همان | همان | همان |
| ارسال بدون ایمیل الزامی | 0 ارسال، `aria-invalid`، `aria-describedby`، focus روی فیلد | همان | همان | همان |
| ایمیل نامعتبر در مرورگر | 0 ارسال | همان | همان | همان |
| رد شدن از سوی سرور (400 `invalid_email`) | کارت زنده، دکمه فعال، خطا روی همان فیلد، focus روی آن | همان | همان | همان |
| ارسال با Enter داخل فیلد | 1 درخواست، `is-sent`، هر دو دکمه پنهان | همان | همان | همان |
| متن تأیید | «Sent — we will be in touch.» | همان | «ارسال شد — با شما تماس می‌گیریم.» | همان |
| کلیدهای بدنهٔ درخواست | `conversation_id`، `name`، `email` (بدون `phone` و `website`) | همان | همان | همان |
| رونوشت | دو نوبت `user,assistant`، بدون greeting، با مرز پاراگراف | همان | همان | همان |
| overflow افقی، console، pageerror، HTTP ≥ 400 | 0 | 0 | 0 | 0 |
| routeهای لمس‌شده | فقط `POST widget/ask` و `POST widget/handoff` | همان | همان | همان |

| سنجهٔ browser (حالت not-ready) | en_US 1440 | en_US 390 | fa_IR 1440 | fa_IR 390 |
|---|---|---|---|---|
| دکمهٔ `data-spw-handoff` و قالب کارت | 0 و غایب | همان | همان | همان |
| پاسخ با `handoff_offer: true` | 0 کارت | همان | همان | همان |
| chipها | دیده می‌شوند | همان | همان | همان |
| routeهای لمس‌شده | فقط `POST widget/ask` | همان | همان | همان |

بازبینی تصویری: `t11/en_US/widget-handoff-1440.png` در برابر `mockup/widget-handoff-ltr-1440.png` هم‌خوان است — همان کارت زرد کم‌رنگ با حاشیهٔ طلایی، آیکون شخص و عنوان «I’ll bring in a person»، متن قلاب دو سطری، دکمهٔ پررنگ navy و دکمهٔ حاشیه‌دار کنارش، و نبود chip در thread. اختلاف‌ها همه ثبت‌شده‌اند: یک دکمهٔ «Send this chat to our team» به‌جای «Email me a reply» و «Chat on Telegram» (W-02؛ انتخاب کانال در T37 و T38)، دکمهٔ «Not now» که موکاپ ندارد، دو فیلد تماس (D-20)، دادهٔ نمونهٔ Northwind و زیرعنوان «Online · replies instantly» که در T08 حذف شد، و فونت. `t11/fa_IR/widget-handoff-390.png` کاملاً آینه است و فیلد ایمیل در همان کارت RTL چپ‌چین می‌ماند. `t11/<locale>/widget-handoff-sent-*.png` حالتی است که موکاپ ندارد: کارت سبز، بدون دکمه، با یک سطر تأیید، و chipها زیر آن برگشته‌اند.

باز و عمدی:
- کارت ارسال‌شده پاک نمی‌شود و بازدیدکننده می‌تواند با دکمهٔ زیرنویس کارت تازه‌ای بخواهد؛ سقف واقعی پنج انتقال در ساعت است و پیام سرور همان را می‌گوید.
- رونوشت از DOM خوانده می‌شود (نه از یک آرایهٔ جداگانه) تا نوبتی که بازدیدکننده با «Try again» برداشته است فرستاده نشود.
- ترتیب و انتخاب کانال هنوز وجود ندارد: `HandoffService` به همهٔ کانال‌های آماده می‌فرستد (T37 و T38).


## شواهد T12

روش: fixture و check موقت بیرون از repo (`20260918-t12/t12_widget_fixture.php` و `t12_capture.cjs`) با همان الگوی T08 تا T11: snapshot و بازگرداندن دقیق `aisaautopilot_settings` و `WPLANG`، و restore بدون snapshot بی‌اثر. fixture سه حالت دارد — `on` (voice روشن، خواندن خودکار خاموش، یعنی پیش‌فرض نسخهٔ 2)، `auto` (هر دو روشن، برای رانندگی F-19) و `off` (voice خاموش) — و در هر سه حالت همهٔ کانال‌های انتقال خاموش‌اند تا کارت T11 وسط جریان صوتی نیاید.

میکروفن، ضبط‌کننده و موتور گفتار با `addInitScript` جایگزین شدند (`getUserMedia`، `MediaRecorder`، `SpeechSynthesisUtterance` و `speechSynthesis`)، و `/public/voice` و `/widget/ask` با `page.route` پاسخ داده شدند. پس **هیچ صدایی از مرورگر بیرون نرفت، هیچ provider فراخوانده نشد و هیچ بودجه‌ای خرج نشد.** تنها فراخوان واقعی شبکه یک `POST /public/voice` بدون صداست در حالت `off`، که دروازهٔ تازه پیش از rate limiter و پیش از هر جست‌وجوی کلید ردش می‌کند.

| گیت | نتیجه | شاهد |
|---|---|---|
| check مرورگر موقت T12 | **126 PASS و 0 FAIL** در en_US و fa_IR × (voice-on 1440×960 و 390×844، read-aloud 1440، voice-off 1440) | `20260918-t12/capture-t12.txt`، تصویرها در `test-artifacts/front-chat-widget/t12/` |
| render integration | 88/0 (از 76/0 در T11) | `20260918-t12/render-integration.txt` |
| integrationهای مرتبط | voice settings 5/0، voice page 4/0، lifecycle 6/0، widget settings 4/0، widget page 4/0، handoff flow 21/0، customise 12/0، usage cap 6/0، Phase 1 45/0 | `20260918-t12/related-integrations.txt` |
| static | 5/5: php-lint 231/231، node-check 37/37، PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit 149 / 514 | `test-artifacts/gates/20260918-022224-static` |
| smoke | 8/8: menu 5/0، assets 20/0، render 18/0 | `test-artifacts/gates/20260918-022344-smoke` |
| ترجمه | MO با 2286 ترجمه از 2427 ورودی PO؛ 3 msgid تازه؛ `msgfmt --check` سالم | خروجی `scripts/compile-mo.php` |
| پاک‌سازی | «T12 widget fixture removed.» پس از هر شش آماده‌سازی و دو بار در `finally`؛ preflight با `settings_stored=False`، WARNINGS 0 و BLOCKERS 0 | `20260918-t12/preflight-after-browser.txt` |

آنچه مرورگر در هر چهار ترکیب اثبات کرد:

| سنجه | نتیجه |
|---|---|
| فشردن میکروفن | یک بار `getUserMedia`، یک ضبط‌کننده، `aria-pressed="true"`، و **صفر درخواست شبکه** |
| اعلام فاز ضبط | متن ناحیهٔ status **دقیقاً** برابر placeholder دیده‌شده، به زبان سایت («Listening… tap the mic again to stop» / «در حال شنیدن… برای توقف دوباره روی میکروفن بزنید») |
| توقف | ضبط‌کننده بسته و track میکروفن آزاد شد؛ `aria-pressed` به `false` برگشت |
| حین transcribe | ناحیه «Transcribing your recording…» / «در حال تبدیل صدای شما به متن…» را اعلام کرد و کادر پیام **خالی** ماند: هیچ زیرنویس زنده یا متن جزئی وجود ندارد |
| رسیدن متن | transcript دست‌نخورده در کادر پیام، focus روی همان کادر، **صفر نوبت بازدیدکننده در thread**، و تنها route لمس‌شده `POST public/voice` |
| پیام پایانی | «Your words are in the message box. Check them, then press Send. Nothing was sent yet.» و معادل فارسی آن |
| ویرایش متن | تایپ کردن در کادر هیچ درخواستی نفرستاد |
| ارسال | فقط با فشردن Send یک `POST widget/ask` رفت، و ناحیهٔ status پاک شد |
| خواندن خودکار خاموش | پاسخ با صدا خوانده نشد (0 فراخوان `speak`) |
| F-19 (حالت `auto`) | متن داده‌شده به موتور گفتار دقیقاً همان خوانش پاسخ بود؛ مرز کلمهٔ اول، چهارم و آخر هرکدام **یک** span را روشن کرد؛ مرز `sentence` چیزی را تغییر نداد؛ `onend` همه را پاک کرد |
| voice خاموش | نه `#spw-mic`، نه `#spw-voice`، نه ناحیهٔ status؛ و `POST /public/voice` واقعی با **403 `voice_disabled`** و پیام به زبان سایت رد شد |
| console، pageerror و HTTP ≥ 400 | 0 در هر اجرا |

**اجرای نخست 122 PASS و 4 FAIL بود.** هر چهار شکست در خود check بود، نه در محصول: برای وارسی «متن قابل ویرایش است»، check وسط کادر متن کلیک می‌کرد و مکان‌نما وسط transcript می‌نشست، پس متن حاصل با انتظار جور درنمی‌آمد. مکان‌نما صریحاً به انتهای متن برده شد و هیچ سنجه‌ای ضعیف نشد.

بازبینی تصویری: `t12/en_US/widget-voice-recording-1440.png` حالت ضبط را نشان می‌دهد — هر دو دکمهٔ میکروفن (header و composer) به رنگ coral درمی‌آیند و placeholder «Listening… tap the mic again to stop» است؛ ناحیهٔ status عمداً دیده نمی‌شود چون فقط برای صفحه‌خوان است. در برابر قاب‌های `mockup/widget-voice-*.png` این **اختلاف عمدی D-02** است: موکاپ یک overlay تمام‌صفحه با orb و phaseهای Connecting/Listening/Thinking/Speaking دارد که هیچ‌کدام پشتوانهٔ backend ندارند. `t12/fa_IR/widget-voice-transcript-390.png` کاملاً آینه است و transcript فارسی در کادر پیام منتظر تصمیم بازدیدکننده می‌ماند.

باز و عمدی:
- فاز «در حال خواندن پاسخ با صدا» اعلام نمی‌شود: وقتی موتور گفتار خودش در حال حرف زدن است، یک اعلام هم‌زمان صفحه‌خوان روی آن می‌افتد. F-16 هم فقط ضبط، transcribe و خطای میکروفن را می‌خواست.
- سقف 45 ثانیه‌ای ضبط و پیام‌های خطای میکروفن همان‌اند که بودند؛ خطاها در `role="log"` اعلام می‌شوند و ناحیهٔ voice فقط خالی می‌شود تا فاز کهنه اعلام‌شده نماند.
- `/chat/voice/transcribe`، `/forms/voice/transcribe` و `/efb/voice/transcribe` مسیرهای مدیرند و دروازهٔ `voice_enabled` ویجت به آن‌ها اضافه نشد: آن‌ها به تنظیم ویجت بازدیدکننده ربطی ندارند.


## شواهد T13

روش: سه فایل **دائمی** در repo، جانشین fixture و check‌های موقت T07 تا T12:

- `tests/local_front_chat_widget_browser_fixture.php`: حالت‌های `prepare` و `restore`؛ پیش از هر تغییر کل `aisaautopilot_settings` و `WPLANG` (و اینکه وجود داشتند یا نه) را در `aisaautopilot_front_chat_widget_browser_fixture` snapshot می‌کند؛ `restore` بدون snapshot کاری نمی‌کند؛ `prepare` نخست snapshot اجرای ناقص قبلی را برمی‌گرداند. زبان از `AISAAUTOPILOT_BROWSER_LOCALE` و سناریو از `AISAAUTOPILOT_FRONT_WIDGET_SCENARIO`: `main` (کانال ایمیل آماده با گیرندهٔ `.invalid`، نام اختیاری و ایمیل الزامی، voice روشن و خواندن خودکار خاموش، سه پرسش پیشنهادی به زبان سایت)، `auto` (همان با خواندن خودکار روشن) و `quiet` (بدون کانال و بدون voice). هیچ post، ردیف، کاربر یا کلیدی نمی‌سازد.
- `tests/front_chat_widget_browser_check.cjs`: بازدیدکنندهٔ خارج‌شده روی صفحهٔ اول قالب فعال؛ `/widget/ask`، `/widget/handoff` و `/public/voice` با `page.route` و شکل واقعی کنترلرها؛ میکروفن، ضبط‌کننده و موتور گفتار با `addInitScript`. تنها فراخوان واقعی `POST /public/voice` بدون صدا در سناریوی `quiet` است که دروازهٔ voice پیش از rate limiter ردش می‌کند.
- `tests/front_chat_widget_browser_matrix.cjs`: `en_US,fa_IR` × `main,auto,quiet` (هر دو قابل تغییر با `AISAAUTOPILOT_FRONT_WIDGET_LOCALES` و `AISAAUTOPILOT_FRONT_WIDGET_SCENARIOS`)، restore پس از هر ترکیب و دوباره در `finally`. حساب QA نمی‌سازد، چون ویجت سطح بازدیدکننده است.

| گیت | نتیجه | شاهد |
|---|---|---|
| browser matrix | **385 PASS / 0 FAIL**، exit 0: en_US 193 (main 173، auto 4، quiet 16) و fa_IR 192 (main 172، auto 4، quiet 16) | `20260918-t13/matrix-run6.txt` |
| تکرار main | سه اجرای پشت‌سرهم در هر دو زبان، هرکدام 345 PASS و 0 FAIL | `20260918-t13/flake-probe-1..3.txt` |
| render integration | از 88/0 به **92/0** (جهت composer، اندازهٔ placeholder، توکن کنتراست، هم‌ترازی متن مدیر) | `20260918-t13/render-integration.txt` |
| integrationهای مرتبط | lifecycle 6/0، voice page 4/0، voice settings 5/0، widget page 4/0، widget settings 4/0، customise 12/0، handoff flow 21/0، usage cap response 6/0 | `20260918-t13/related-integrations.txt` |
| smoke (شامل static) | 8/8: php-lint 232/232، node-check 39/39، PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit 149 / 514، menu 5/0، assets 20/0، render 18/0 | `test-artifacts/gates/20260918-131657-smoke` |
| ترجمه | هیچ رشتهٔ نمایشی تازه‌ای افزوده نشد؛ PO و MO تغییر نکردند | — |
| پاک‌سازی | «Front chat widget browser fixture removed.» پس از هر شش ترکیب و در `finally`؛ preflight پس از browser و در پایان: `settings_stored=False`، locale `en_US`، WARNINGS 0 و BLOCKERS 0 | `20260918-t13/preflight-after-browser.txt`، `preflight-final.txt` |

مسیر اجرا، بدون پنهان کردن شکست‌ها: اجرای نخست en_US main ‏9 FAIL داد، همه کنتراست (F-23)؛ پس از اصلاح 0 FAIL. matrix کامل سوم 377/0 بود ولی بازبینی تصویری F-24 و F-25 را نشان داد. اجرای چهارم (main، هر دو زبان) دو FAIL داشت: F-24 در fa_IR 390، چون راه‌حل نخست (`match-parent`) در Chrome کار نمی‌کند، و یک خروج گذرای focus از full screen با Tab در en_US 1440. اولی با هم‌ترازی بر پایهٔ `dir` ریشه رفع شد. دومی در پنج اجرای بعدی (پنجم، ششم و سه تکرار) بازتولید نشد و ریشه‌اش پیدا نشد؛ check اکنون مسیر کامل focus و عنصری را که focus به آن رفت در پیام شکست می‌نویسد تا اگر دوباره رخ داد، T14 یا T17 علت را ببیند. هیچ assertionی تضعیف نشد.

آنچه matrix در هر زبان ثابت می‌کند:

| سنجه | 1440×960 | 390×844 |
|---|---|---|
| بدون درخواست افزونه تا باز شدن؛ nudge خودش می‌آید و پنل بسته می‌ماند | بله | بله |
| Shift+Tab از صفحه به launcher می‌رسد، حلقهٔ focus دیده می‌شود، Enter پنل را باز و caret را در composer می‌گذارد | بله | بله |
| Tab و Shift+Tab در پنل: mic، send، پیوند انتقال، سه chip، close، expand، voice؛ هرکدام حلقهٔ مرئی | بله | بله (بدون expand) |
| hit target دکمه‌های آیکنی | 44×44 تا 44×45 | همان |
| کنتراست (22 جفت متن یا آیکون) | همه دست‌کم 4.5 برای متن و 3 برای آیکون؛ کمترین 4.95 | همان |
| composer | خالی: جهت صفحه و بدون `dir`؛ لاتین ltr؛ فارسی rtl؛ Shift+Enter خط تازه بدون ارسال | همان |
| گفت‌وگو | chip یک درخواست (D-24)؛ Markdown امن؛ منبع فقط با citation؛ clarify فقط composer را پر می‌کند؛ 503 با retry و 429 بدون آن؛ Stop صادقانه | همان |
| کارت انتقال | در thread، chipها کنار می‌روند، فقط `name` و `email*`، «Not now»، خطای فیلد، ردّ سرور روی همان فیلد، Enter یک ارسال، تأیید بدون ادعای اپراتور | همان |
| voice | یک `getUserMedia`، اعلام فاز در `role="status"` برابر placeholder، placeholder کامل دیده می‌شود، transcript در composer بدون ارسال، فقط Send می‌پرسد | همان |
| full screen | 940×760، scrim، ستون 3 پرسش، قفل `html`/`body`، 24 Tab داخل پنل، Escape با حفظ focus composer و بازگرداندن دقیق قفل | دکمهٔ expand پنهان؛ `overscroll-behavior: contain` (F-17) |
| بستن | Escape پنل را می‌بندد و focus به launcher برمی‌گردد | همان |
| `auto` | کلمهٔ اول، چهارم و آخر هرکدام یک span روشن (F-19) | — |
| `quiet` | نه mic، نه ناحیهٔ status، نه دکمه یا قالب انتقال، و پاسخ با `handoff_offer` کارت نمی‌سازد؛ `POST /public/voice` واقعی 403 `voice_disabled` با پیام به زبان سایت | همان، بدون فراخوان واقعی |
| console، pageerror، HTTP ≥ 400 غیر mock، overflow افقی | 0 | 0 |
| `prefers-reduced-motion` (en_US) | حلقهٔ launcher و انیمیشن پنل `none` | — |

بازبینی تصویری (`test-artifacts/front-chat-widget/t13/<locale>/` در برابر `mockup/`):

| جفت | نتیجه |
|---|---|
| `widget-answer-1440` در برابر `mockup/widget-panel-ltr-1440` | هم‌خوان: header navy با سه دکمه، برچسب «Today»، حباب‌ها، خط «From …» با آیکون کتاب، chipها و composer. chipها زیر هم می‌شکنند چون متن‌شان بلندتر از نمونهٔ موکاپ است. اختلاف‌های ثبت‌شده: نقطهٔ «آنلاین» و زیرعنوان «Online · replies instantly»، فونت، و متن کمکی کمی تیره‌تر (F-23) |
| `widget-fullscreen-1440` (fa_IR) در برابر `mockup/widget-fullscreen-rtl-1440` | هم‌خوان و آینه: ستون پرسش‌ها در چپ، بخش «پیدا نکردید؟» با دکمهٔ حاشیه‌دار، header با دکمهٔ برچسب‌دار «صدا». پس از F-24 عنوان لاتین و زیرعنوان فارسی هر دو کنار آواتار راست‌چین‌اند. نقطه‌گذاری لاتین در افزونه درست است («Canada?») ولی در موکاپ جابه‌جاست («?Do you ship to Canada»)؛ این مزیت F-06 است |
| `widget-handoff-390` (fa_IR) در برابر `mockup/widget-handoff-rtl-390` | کارت زرد، عنوان با آیکون شخص، متن و دو دکمه هم‌خوان؛ اختلاف‌های ثبت‌شده W-02 (یک دکمه)، «Not now»، فیلدهای تماس (D-20)، و sheet تمام‌صفحه به‌جای پنل شناور |
| `widget-launcher-1440` در برابر `mockup/widget-launcher-ltr-1440` | هم‌خوان؛ نشان «1» و متن nudge نمونه عمداً ساخته نشده‌اند. تصویر پس از پایان انیمیشن 0.3s گرفته می‌شود |
| `widget-voice-recording-*` در برابر `mockup/widget-voice-*` | اختلاف عمدی D-02: بدون overlay. پس از F-25 placeholder دوخطی کامل است و هر دو دکمهٔ میکروفن coral می‌شوند |
| `widget-handoff-sent-*`، `widget-voice-transcript-*`، `widget-quiet-*` | حالت‌هایی که موکاپ ندارد؛ فقط یکپارچگی بصری بازبینی شد: بدون overflow، جهت درست، متن‌ها به زبان سایت |

باز و عمدی:
- پیام‌های خطای mockشدهٔ سرور در اجرای فارسی انگلیسی‌اند، چون شکل پاسخ است که mock می‌شود؛ ترجمهٔ پیام سرور را integrationهای PHP می‌سنجند.
- رنگ‌های کم‌رنگ مشابه در CSS مدیر (`aisaautopilot-ui.css`: `#a9abc4`، `#9a9db8`، `#7e81a3`) دست نخوردند؛ دامنهٔ T17 است.
- DG-01 راهنمای کاربر با همین fixture بسته شد: `docs-screenshot.cjs` پیش از `prepare` متغیر `AISAAUTOPILOT_BROWSER_LOCALE` را می‌گذارد و سناریوی پیش‌فرض `main` است.

## شواهد T14

روش: بستن بسته با رگرسیون کامل، بدون تغییر کد. هیچ فایل `includes/`، `assets/`، `tests/` یا `languages/` در این task عوض نشد؛ تنها خروجی، سند است.

| گیت | نتیجه | شاهد |
|---|---|---|
| `run-gates.ps1 -Tier full` | **9/9**، exit 0، در **دو اجرای مستقل** با شمارهای یکسان: php-lint 232/232، node-check 39/39، PHPCS 134/134 با 0 error و 0 warning، PHPStan 0، PHPUnit 149 / 514، menu-smoke 5/0، assets-smoke 20/0، render-smoke 18/0، Phase 1 45/0؛ PHP diagnostic افزونه صفر | `test-artifacts/gates/20260918-181045-full`، `test-artifacts/gates/20260918-180938-full` |
| browser matrix دائمی ویجت | **385 PASS / 0 FAIL**، exit 0 و `MATRIX RESULT PASS`؛ تفکیک دقیقاً برابر T13: en_US 193 (main 173، auto 4، quiet 16) و fa_IR 192 (main 172، auto 4، quiet 16) | `test-artifacts/front-chat-widget/20260918-t14/matrix-t14.txt`، تصویرها در `test-artifacts/front-chat-widget/t14/<locale>/` |
| render integration | **92 check، 0 failed** (برابر T13) | `test-artifacts/front-chat-widget/20260918-t14/render-integration.txt` |
| پاک‌سازی | «Front chat widget browser fixture removed.» پس از هر شش ترکیب و دوباره در `finally`؛ preflight پایانی `settings_stored=False`، locale `en_US`، WARNINGS 0 و BLOCKERS 0 | `test-artifacts/front-chat-widget/20260918-t14/preflight-final.txt` |

نکته‌های صادقانهٔ این اجرا:

- **دو اجرای full عمدی نبود:** نخستین فرمان از راه یک pipeline اشتباه (Bash به‌جای PowerShell) فرستاده شد؛ خودِ `run-gates.ps1` اجرا شد و تمام شد، ولی پوستهٔ فراخوان با کد 127 برگشت. فرمان با ابزار درست دوباره اجرا شد. بخش‌های سایت‌لمس‌کنندهٔ دو اجرا با هم هم‌پوشانی نداشتند (اجرای اول 18:11:53 تمام شد و اجرای دوم از 18:12:51 به بعد به سایت رسید) و هر دو خروجی عیناً یکسان است، پس نتیجه به‌عنوان یک تکرار سالم ثبت می‌شود.
- **شکست گذرای T13 (خروج focus با Tab از full screen) در این اجرا تکرار نشد**؛ مجموع اجراهای بدون تکرار به ده رسید. حذف نشده و همچنان در فهرست محدودیت‌های باز گزارش اجراست.
- F-11 و F-12 با تصمیم مستند بسته شدند، نه با کد: هیچ کلیدی حذف نشد (قاعدهٔ حفظ) و هر دو در بخش «محدودیت‌های باز و صادقانه» گزارش اجرا و برای T21 ثبت شدند.

گزارش اجرای بسته: `docs/FRONT_CHAT_WIDGET_S3_3_EXECUTION_REPORT_FA.md`.

## پیگیری T13 به درخواست مالک (۲۰۲۶-۰۹-۱۸): حالت ضبط و sheet موبایل

درخواست مالک: دو اختلاف عمدی گزارش T13 («کادر پیام هنگام ضبط دوخطی می‌شود» و «برگهٔ موبایل صفحهٔ پشت را قفل نمی‌کند») اگر کاربرپسند نیستند درست شوند، و حالت ضبط در دسکتاپ و موبایل هر دو درست باشد. هر دو کاربرپسند نبودند و رفع شدند (جزئیات در ردیف‌های F-17 و F-25 بالا).

| لایه | تغییر |
|---|---|
| `includes/Frontend/ChatWidget.php` | `#spw-rec` (نقطه و `#spw-rec-time`، `aria-hidden` و `hidden`) پیش از textarea، فقط با voice روشن؛ آیکون هر دو دکمهٔ میکروفن در `.spw-mic__ico` و مربع توقف در `.spw-mic__stop` |
| `includes/Core/Plugin.php` | کلید `i18n.listening` با msgid موجود `Listening…`؛ PO و MO تغییر نکردند |
| `assets/frontend/widget.js` | `setRecordingUi()`: کلاس `spw-composer__box--recording`، زمان‌سنج 250ms با `elapsed()` و `Intl.NumberFormat` زبان صفحه، `title` برابر `aria-label`، placeholder یک‌خطی. `sheetQuery` (640px)، `isModal()` و `syncModal()` تنها مسیر `aria-modal` و `lockPageScroll()`؛ `setOpen()` و `setFull()` آن را صدا می‌زنند؛ `trapFocus()` برای sheet هم کار می‌کند؛ شنوندهٔ `change` برای چرخش گوشی |
| `assets/frontend/widget.css` | قاب coral و حلقهٔ `rgba(255, 69, 95, .14)`، `.spw-rec` با `tabular-nums`، نقطهٔ چشمک‌زن `spwBlink` (با `prefers-reduced-motion` خاموش)، جابه‌جایی آیکون میکروفن و توقف، transition خود `.spw-mic` |

| گیت | نتیجه | شاهد |
|---|---|---|
| browser matrix | **425 PASS / 0 FAIL**، `MATRIX RESULT PASS`: en_US 213 (main 193، auto 4، quiet 16) و fa_IR 212 (main 192، auto 4، quiet 16)؛ ۴۰ check تازه | `test-artifacts/front-chat-widget/20260918-t13-followup/matrix-run3.txt`، تصویرها در `test-artifacts/front-chat-widget/t13-followup/<locale>/` |
| render integration | از 92/0 به **96/0** | `…/20260918-t13-followup/render-integration.txt` |
| هشت integration مرتبط | lifecycle 6/0، voice page 4/0، voice settings 5/0، widget page 4/0، widget settings 4/0، customise 12/0، handoff flow 21/0، usage cap response 6/0 | `…/20260918-t13-followup/related-integrations.txt` |
| smoke (با static) | **8/8**: php-lint 232/232، node-check 39/39، PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit 149 / 514، menu 5/0، assets 20/0، render 18/0 | `test-artifacts/gates/20260918-185323-smoke` |
| پاک‌سازی | «Front chat widget browser fixture removed.» پس از هر شش ترکیب و در `finally`؛ preflight پایانی `settings_stored=False`، WARNINGS 0 و BLOCKERS 0 | `…/20260918-t13-followup/preflight-final.txt` |

مسیر اجرا، بدون پنهان کردن شکست‌ها: اجرای نخست matrix ‏417/8 بود. چهار شکست پس‌زمینهٔ میکروفن composer را `rgba(255, 69, 95, 0.898)` خواند: transition نشت‌کرده از قالب هنوز در جریان بود. این همان رنگ کمرنگ تصویرهای T13 بود و با transition خود `.spw-mic` و انتظار check برای پایان transition رفع شد. چهار شکست دیگر از خود check بود: قالب `scroll-behavior: smooth` دارد، پس `scrollTo(0, 600)` پیش از باز شدن sheet به 600 نرسیده بود. check اکنون با `behavior: 'instant'` می‌پرد. اجرای دوم (فقط main) 385/0 و اجرای کامل سوم 425/0 بود. هیچ assertionی سست نشد.

بازبینی تصویری (`t13-followup/<locale>/widget-voice-recording-*` در برابر `mockup/widget-voice-listening-*`): اختلاف D-02 (بدون overlay) ماند. در هر چهار ترکیب کادر یک‌خطی است و قاب coral، نقطه، زمان («0:01» و «۰:۰۱») و مربع توقف در هر دو دکمه دیده می‌شوند. `widget-fullscreen-1440` و `widget-panel-390` بدون تغییر دیداری‌اند و دکمهٔ «صدا» در header آیکون و برچسبش را دارد.

### اجرای دوبارهٔ matrix پس از تغییرهای هم‌زمان (۲۰۲۶-۰۹-۱۸، شب)

به درخواست مالک، matrix پس از تغییر هم‌زمان T73 در `ChatWidget.php` (دروازهٔ `widget_visibility`) دوباره اجرا شد. سایت محلی میان چند جلسه مشترک است، پس با اجازهٔ مالک از دو جلسهٔ فعال (T15 و T73) خواسته شد browser job تازه‌ای شروع نکنند. هر دو پس از پایان اجرای خود و بازگرداندن fixtureها تأیید کردند، و preflight پیش از اجرا تمیز بود.

| اجرا | نتیجه | شاهد |
|---|---|---|
| اول | 423/2. (۱) `fa_IR main 1440` پنل 392×623: `settle()` ثابت 250ms کوتاه‌تر از انیمیشن 0.32s ورود پنل بود (نقص آزمون). (۲) `fa_IR main 1440` Tab از full screen بیرون رفت، با مسیر `… name → email → <body>`: **F-26، نقص محصول**. اجرا را `timeout 590` پیش از دو restore پایانی قطع کرد. fixture بی‌درنگ با حالت `restore` خودش برگشت (`WPLANG` خالی، `settings_stored=False`، option fixture غایب) | `20260918-t13-followup/matrix-rerun.txt` |
| probe قطعی F-26 | پیش از رفع `{"activeAfter":"BODY"}`، پس از رفع `{"activeAfter":"spw-input"}` | اسکریپت موقت بیرون از repo |
| دوم | 428/1. هر دو شکست قبلی رفع شد و check تازهٔ F-26 در هر چهار ترکیب سبز بود. شکست تازه در `fa_IR main 390`: زمان ضبط در 3 ثانیه از «۰:۰۰» عوض نشد | `…/matrix-rerun2.txt` |
| دو اجرای `fa_IR main` | هر دو 194/0. زمان‌سنج در هر چهار مورد از «۰:۰۰» به «۰:۰۱» رسید، با `aria-pressed="true"` و `#spw-rec` پیدا. check اکنون با فاصلهٔ 100ms poll می‌کند، نه با animation frame، و حالت را در پیام شکست چاپ می‌کند؛ مهلت همان 3 ثانیه ماند. ریشهٔ آن یک شکست ثابت نشد؛ محتمل‌ترین توضیح poll بر پایهٔ `requestAnimationFrame` زیر بار سیستم است | `…/timer-probe-1.txt`، `…/timer-probe-2.txt` |
| **نهایی** | **429 PASS / 0 FAIL**، `MATRIX RESULT PASS`: en_US 215 (main 195، auto 4، quiet 16) و fa_IR 214 (main 194، auto 4، quiet 16)؛ هر هفت restore اجرا شد | `…/matrix-final.txt`، تصویرها در `test-artifacts/front-chat-widget/t13-followup-rerun/<locale>/` |
| render integration | **97/0** (+1 برای قرارداد F-26) | `…/render-integration-final.txt` |
| smoke (با static) | **8/8**: php-lint 234/234، node-check 43/43، PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit 153 / 526 (افزایش از کار جلسه‌های دیگر)، menu 5/0، assets 20/0، render 18/0 | `test-artifacts/gates/20260918-230345-smoke` |
| پاک‌سازی | preflight 23:04: `settings_stored=False`، WARNINGS 0 و BLOCKERS 0؛ سپس به دو جلسه اطلاع داده شد | `…/preflight-final-rerun.txt` |

تغییرهای این دور: `syncJump()` در `widget.js` (F-26). در check: `settle()` اکنون تا پایان انیمیشن‌ها و transitionهای محدودِ در جریان صبر می‌کند (حلقه‌های بی‌پایان کنار گذاشته می‌شوند و سقف 3 ثانیه است)، check تازهٔ F-26 با ردیف‌های پرکنندهٔ موقت اضافه شد، و زمان‌سنج با فاصلهٔ ثابت poll می‌شود. هیچ assertionی سست نشد.

بازبینی تصویری: حالت ضبط در هر چهار ترکیب یک‌خطی است، با قاب coral، نقطه، «0:01» یا «۰:۰۱» و مربع توقف. در `fa_IR/widget-fullscreen-1440` دکمهٔ شناور «آخرین پیام» (↓) دیده می‌شود، چون thread در انتها نیست. این دکمه روی لبهٔ فیلد ایمیل کارت انتقال می‌افتد؛ رفتار شناور عمدی است، ولی هم‌پوشانی با فیلد برای بازبینی T17 ثبت شد.
