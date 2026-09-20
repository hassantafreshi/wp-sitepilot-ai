# ردیاب اجرایی بازطراحی موکاپ

این ردیاب مکمل `MOCKUP_REDESIGN_AUDIT_AND_PLAN_FA.md` است. قانون اجرا: **هیچ بسته‌ای قبل از گذراندن گیت تست خودش بسته یا دنبال نمی‌شود.**

از ۲۰۲۶-۰۹-۱۴ صف ریزکارهای باقی‌مانده تا انتشار (T00 تا T25)، تصمیم‌های مالک و ژورنال جلسه‌ها در `PRODUCT_COMPLETION_ROADMAP_FA.md` نگهداری می‌شود. وضعیت سطح بسته و لاگ تاریخ‌دار همچنان همین‌جا ثبت می‌شود.

## گیت‌های مشترک هر بسته

- PHP syntax برای فایل‌های تغییرکرده.
- PHPUnit کامل در انتهای هر بستهٔ دارای رفتار PHP/REST.
- PHPCS برای فایل‌های تغییرکرده و PHPCS کامل پیش از رفتن به بستهٔ بعدی.
- PHPStan برای تغییرهای `includes/`.
- در تغییرهای UI: render smoke، desktop، 390px، RTL، keyboard/focus و console check متناسب با سطح.
- در تغییرهای مسیر/alias: همهٔ slugهای قدیمی و جدید باید به سطح درست برسند.

## فاز ۰ — baseline و محیط

| ID | کار کوچک | وضعیت | گیت پذیرش |
|---|---|---|---|
| B0.1 | رفع خطاها و هشدارهای PHPCS بدون تغییر رفتار | تکمیل | PHPCS کامل = ۰ error / ۰ warning |
| B0.2 | بررسی و هم‌راستاسازی WordPress محلی با همین source | تکمیل | live smoke بدون duplicate-plugin/PHP warning |
| B0.3 | اجرای baseline static suite | تکمیل | PHPUnit + PHPStan + syntax سبز |
| B0.4 | اصلاح تست/مستند privacy فرم‌ها | تکمیل | تست دستی با عدم ذخیرهٔ PII همسو باشد |

## فاز ۱ — shell و navigation

| ID | کار کوچک | وضعیت | گیت پذیرش |
|---|---|---|---|
| N1.1 | design tokens، header، banner، toast و job bar | تکمیل | desktop/RTL shell smoke |
| N1.2 | منوی ثابت ۱۰تایی و Store locked بدون WooCommerce | تکمیل | ۱۰ آیتم + alias route smoke |
| N1.3 | asset enqueue، font fallback و console safety | تکمیل | همهٔ صفحه‌ها بدون JS error |

## فاز ۲ — صفحات wp-admin

| ID | کار کوچک | وضعیت | گیت پذیرش |
|---|---|---|---|
| A2.1 | Overview | تکمیل | دادهٔ واقعی + empty/error + RTL/390px |
| A2.2 | Setup & Connection | تکمیل | wizard چهارمرحله‌ای، آزمونِ دقیقِ provider و RTL/390px |
| A2.3 | Integrations | تکمیل | feature gate، persistence، dialog و QA واقعی |
| A2.4 | Site Knowledge | تکمیل (UI Phase 1) | چهار tab، دادهٔ واقعی، deep alias و QA واقعی |
| A2.5 | Answer Desk | تکمیل (صفحه‌ای) | Conversations، Widget، Human handoff و Real-time voice canonical هستند؛ QA متمرکز چهار tab پاس شده است |
| A2.6 | Store Copilot | تکمیل | چهار tab canonical، contract واقعی و QA محلی/visual کامل؛ گزارش: `STORE_COPILOT_A2_6_EXECUTION_REPORT_FA.md` |
| A2.7 | Content Studio | تکمیل | A2.7.0 تا A2.7.5 (ایمنی، shell، Write، Templates، History و QA نهایی) با دادهٔ واقعی و visual QA تکمیل شد |
| A2.8 | Usage & Cost | تکمیل | token-first، cap واقعی، daily aggregate، keyboard/RTL/390px و visual QA؛ گزارش: `USAGE_A2_8_EXECUTION_REPORT_FA.md` |
| A2.9 | Health | تکمیل | کارت canonical هشت‌ردیفی، diagnostics ذخیره‌شدهٔ امن، state ترتیبی، RTL/390px و visual QA؛ گزارش: `HEALTH_A2_9_EXECUTION_REPORT_FA.md` |
| A2.10 | Activity Log | تکمیل در قرارداد واقعی | timeline/empty موکاپ، CSV امن، permission، native RTL/390px؛ approval/rollback فاقد backend مستند شدند؛ گزارش: `ACTIVITY_LOG_A2_10_EXECUTION_REPORT_FA.md` |

### A2.3 — تقسیم ریزِ Integrations

| ID | کار کوچک | وضعیت | گیت پذیرش |
|---|---|---|---|
| A2.3.1 | نگاشت قرارداد هر شش integration موکاپ به setting/connector واقعی و بستن toggleهای بی‌اثر | تکمیل | `wordpress_integrations_hub_integration.php` = ۱۴/۰؛ REST، UI destination و auto-index واقعی گیت شدند |
| A2.3.2 | بازسازی hub: چهار گروه، شش کارت، stateهای Always on/On/Off/Needs dependency و CTA صحیح | تکمیل | render contract، دقیقاً ۴ گروه/۶ کارت و screenshot desktop پاس |
| A2.3.3 | drawer پیکربندیِ accessible با فقط کنترل‌های واقعی و توضیح صادقانهٔ کنترل‌های Phase 2 | تکمیل | nonce/capability، Escape/focus، حفظ widget و تنظیم‌های نامرتبط پاس |
| A2.3.4 | QA عملکرد، RTL و responsive | تکمیل | browser flow، 390px، RTL، console/pageerror، PHPUnit/PHPStan/PHPCS و Phase 1 پاس |

### A2.4 — تقسیم ریزِ Site Knowledge

| ID | کار کوچک | وضعیت | گیت پذیرش |
|---|---|---|---|
| A2.4.1 | نگاشت stateهای موکاپ به دادهٔ واقعی coverage/source/gap و ثبت محدودیت queue/collection | تکمیل | قرارداد data و گزارش gap: فقط source/type واقعی؛ queue/failure/collection غیرپایدار قفل/مستند |
| A2.4.2 | بازسازی Overview: empty، coverage، sources و content gaps مطابق موکاپ | تکمیل | empty/populated state، source picker واقعی و gap dismiss با دادهٔ KB |
| A2.4.3 | بازسازی Collections، Indexing Queue و Sync Sources؛ routeهای خارج موکاپ hidden alias | تکمیل | four tabs دقیق، routeهای ask/entries پنهان، queue/collection بدون کنترل جعلی |
| A2.4.4 | QA عملکرد، RTL و responsive | تکمیل | lifecycle 7/0، KB hub 8/0، Phase 1 45/0، browser exit 0 در desktop/RTL/390px |

### A2.5 — تقسیم ریزِ Answer Desk

| ID | کار کوچک | وضعیت | گیت پذیرش |
|---|---|---|---|
| A2.5.1 | انتخاب renderer canonical، هم‌معناکردن Integration/Widget gate و حذف handoff جعلیِ channel ناقص | تکمیل | lifecycle **5/0**، Phase 1 **45/0**، بدون provider/delivery خارجی |
| A2.5.2 | بازسازی Conversations: logging-off، empty و conversation/source واقعی بدون live/location/rate جعلی | تکمیل | Conversations **5/0**، lifecycle **6/0**، Phase 1 **45/0**، desktop/RTL/390px browser و screenshot review پاس |
| A2.5.3 | بازسازی Widget با preview و persistence واقعی؛ کنترل‌های عمیق در مسیر کم‌ازدحام | تکمیل | Widget settings **4/0**، Widget page **4/0**، autosave/persistence/nonce + desktop/RTL/390px browser و screenshot review پاس |
| A2.5.4 | بازسازی Human handoff: Email/Telegram/Twilio و stateهای honest/locked | تکمیل | endpoint **5/0**، صفحه **4/0**، lifecycle **6/0**، REST nonce/persistence و browser desktop/RTL/390px با screenshot review پاس؛ inbox/status خارج از UI موکاپ مستند و در alias حفظ شد |
| A2.5.5 | بازسازی Real-time voice با voice input/TTS واقعی و lock صادقانهٔ streaming | تکمیل | endpoint **5/0**، صفحه **4/0**، persistence/nonce و browser desktop/RTL/390px پاس؛ streaming و barge-in بدون backend قفل و مستند شدند |
| A2.5.6 | QA صفحه، RTL، responsive، keyboard/focus و visual comparison | تکمیل (Answer Desk) | regression focused هر چهار tab با browser exit **0**، بدون console/pageerror؛ screenshotهای Voice در desktop/RTL/mobile بازبینی شدند |

### A2.6 — تقسیم ریز Store Copilot

| ID | کار کوچک | وضعیت | گیت پذیرش |
|---|---|---|---|
| A2.6.1 | renderer canonical، چهار tab، aliasهای قدیمی و حذف enqueue dashboard قدیمی | تکمیل | PHPCS **124/124**، PHPStan **0**، render/assets smoke و هر دو alias پاس |
| A2.6.2 | New product: proposal-before-draft، TTL و approve/discard | تکمیل | draft smoke **3/0**، page integration و browser proposal state پاس |
| A2.6.3 | What it can do: capability/status/settings و paused gate واقعی | تکمیل | settings integration **7/0**، persistence و permission/read-write gate پاس |
| A2.6.4 | Products و Orders: read-model واقعی، empty state و قرارداد order صادقانه | تکمیل | read-model integration **3/0**، marker/fixture cleanup و render assertion پاس |
| A2.6.5 | browser و visual QA Store | تکمیل | desktop، RTL واقعی `fa_IR`، 390px، overflow، console/pageerror و screenshot review با **exit 0** |

گزارش قرارداد، اختلاف‌ها و شواهد کامل Store در `STORE_COPILOT_A2_6_EXECUTION_REPORT_FA.md` نگهداری می‌شود. همهٔ گیت‌های بالا سبز هستند و A2.6 تکمیل علامت خورده است.

### A2.7 — تقسیم ریز Content Studio

| ID | کار کوچک | وضعیت | گیت پذیرش |
|---|---|---|---|
| A2.7.0 | قرارداد ایمنی proposal و usage cap پیش از UI | تکمیل | integration WordPress **3/0**، PHPUnit **133/439**، PHPStan **0** و PHPCS **124/124** |
| A2.7.1 | canonical shell، سه tab موکاپ، alias و deep link Forms | تکمیل | navigation integration **5/0**، asset smoke **19/0**، browser `fa_IR`/390px **exit 0**، PHPUnit **133/439**، Phase-1 **45/0**، PHPStan **0** و PHPCS **124/124** |
| A2.7.2 | Write: brief، proposal/approval و recent drafts واقعی | تکمیل | read-model **2/0**، renderer **3/0**، browser `fa_IR`/390px **exit 0**، Phase-1 **45/0**، PHPUnit **133/439**، PHPStan **0** و PHPCS **125/125** |
| A2.7.3 | Templates: چهار کارت و prefill واقعی Write | تکمیل | templates integration **5/0**، keyboard hand-off بدون REST خودکار، browser `fa_IR`/390px **exit 0**، visual review، PHPUnit **133/439**، PHPStan **0** و PHPCS **125/125** |
| A2.7.4 | History: rows واقعی، usage صادقانه و export contract | تکمیل | read-model **3/0**، export **3/0**، page **5/0**، `admin-post` nonce/capability، CSV formula guard، empty state و fixture cleanup |
| A2.7.5 | browser و visual QA Content Studio | تکمیل | browser `fa_IR` desktop/RTL/390px **exit 0**، keyboard CSV download، zero REST خودکار، console/pageerror صفر و screenshot review |

گزارش ممیزی، قراردادها، قابلیت‌های فاقد UI موکاپ و برآورد در `CONTENT_STUDIO_A2_7_AUDIT_AND_PLAN_FA.md` نگهداری می‌شود.

## فاز ۳ — سطوح تخصصی

### A2.10 — گیت بسته‌شده

ممیزی/قرارداد، export امن، timeline، renderer و browser/regression به‌ترتیب تکمیل شدند. Timeline **25/0**، page **3/0**، export **5/0**، lifecycle fixture **4 PASS**، browser matrix چهارحالته + تکرار فارسی **exit 0**، PHPCS **130/130 بدون warning**، PHPStan **0**، PHPUnit **133/439** و Phase 1 **45/0**. رخداد ازدست‌رفتن لاگ محلیِ helper قدیمی و عدم بازیابی آن جداگانه در `ACTIVITY_LOG_QA_INCIDENT_FA.md` ثبت شده است.

| ID | کار کوچک | وضعیت | گیت پذیرش |
|---|---|---|---|
| S3.1 | Form Composer | تکمیل | context ثابت، shell سه‌حالته، approval/publish confirm، shortcode، RTL/390 و regression طبق گزارش اجرا پاس |
| S3.2 | EFB copilot | تکمیل | selected-field سمت سرور، `/efb/log` با callback `client_log`، voice transcript-first، help/Pro، سبک step و progress، لاگ اتمی، fa_IR/RTL/390px و regression طبق `EFB_COPILOT_S3_2_EXECUTION_REPORT_FA.md` پاس |
| S3.3 | Front Chat Widget | تکمیل | ۲۶ یافتهٔ F-01 تا F-26 بسته؛ matrix دائمی **429/0** در en_US و fa_IR × 1440/390 × سه سناریو (T14: 385/0؛ پیگیری T13 به درخواست مالک: حالت ضبط یک‌خطی، sheet modal و F-26 focus دکمهٔ «آخرین پیام»)، render integration **97/0**، full **9/9** در T14، smoke 8/8 پس از پیگیری؛ F-11 و F-12 پس از بستن در T73 حل شدند (widget visibility integration 27/0، widget customise matrix 85/0)؛ گزارش: `FRONT_CHAT_WIDGET_S3_3_EXECUTION_REPORT_FA.md` |

## فاز ۴ — QA نهایی و release

| ID | کار کوچک | وضعیت | گیت پذیرش |
|---|---|---|---|
| Q4.1 | browser smoke برای هر ۱۸ slug/alias | منتظر | HTTP/render/console/screenshot |
| Q4.2 | visual side-by-side با ۴ موکاپ | منتظر | اختلاف‌های عمدی مستند |
| Q4.3 | accessibility، RTL و responsive کامل | منتظر | keyboard/focus/contrast/overflow پاس |
| Q4.4 | build ZIP و release validation | منتظر | ZIP همگام و سالم |

## فاز ۵ — بازآرایی IA (S6، دستور مستقیم مالک ۲۰۲۶-۰۹-۱۷)

| ID | کار کوچک | وضعیت | گیت پذیرش |
|---|---|---|---|
| S6.1 | تب‌های Overview (Usage & Cost، Health، Activity Log) + ترویج Form Composer به Form Studio | تکمیل | full **9/9** + هفت integration (۶۱ چک، ۰ شکست)؛ menu-smoke 5/0، assets-smoke 20/0 |
| S6.2 | سازگاری JSON فرم‌ها با Easy Form Builder — سند | تکمیل | بخش قرارداد JSON در `docs/technical/FORMS_CONTENT_STORE_FA.md` نوشته شد؛ `EfbFormConverter` از پیش قرارداد `form_structer` را دقیق پیاده می‌کند |
| S6.3 | browser matrix واقعی روی IA تازه (en_US/fa_IR، دسکتاپ/۳۹۰px) | تکمیل | `admin_ia_browser_matrix.cjs` **652 PASS / 0 FAIL** (en_US 316، fa_IR 336)، صفر console/pageerror/HTTP≥400؛ smoke 8/8؛ گزارش `OVERVIEW_FORM_STUDIO_S6_EXECUTION_REPORT_FA.md` |

جزئیات، گزینه‌های نام‌گذاری بررسی‌شده و checklist ریزکار در `OVERVIEW_FORM_STUDIO_S6_AUDIT_AND_PLAN_FA.md`؛ صف و وابستگی در `PRODUCT_COMPLETION_ROADMAP_FA.md` (T54، T55، D-23).

## فاز ۶ — پشتیبانی انسانی (S7، درخواست مالک ۲۰۲۶-۰۹-۱۸)

| ID | کار کوچک | وضعیت | گیت پذیرش |
|---|---|---|---|
| S7.0 | ممیزی، قرارداد کانال و ماتریس قابلیت هر سرویس | نیازمند تصمیم (D-26، D-27) | هر ردیف ماتریس به سند رسمی سرویس یا اندازه‌گیری واقعی ارجاع دارد |
| S7.1 | هستهٔ داده: schema v3 صندوق و چهار جدول تازه | منتظر | مهاجرت بدون از دست رفتن ردیف + PHPUnit/PHPStan |
| S7.2 | نقش و سطح دسترسی نیروی پشتیبانی | منتظر | نیرو فقط پنل را می‌بیند؛ منوی ادمین همچنان هشت آیتم |
| S7.3 | پنل پاسخگو در wp-admin | منتظر | integration دو نقش + browser با دو کاربر واقعی |
| S7.4 | تجربهٔ بازدیدکننده (ماشین حالت و «X پیوست») | منتظر | نبود کنترل برای قابلیتی که کانال ندارد، en/fa و ۱۴۴۰/۳۹۰ |
| S7.5 | مسیریابی و جدول زمان‌بندی | منتظر | تست واحد شیفت شب‌گذر، تعطیلی و «بدون شیفت = بدون وعده» |
| S7.6 | اعلان‌ها (ایمیل، پیامک، مرورگر) | منتظر | صفر ارسال واقعی در تست |
| S7.7 | بستر ایمیل با پیوند امضاشده به thread | منتظر | متن fa_IR و صراحت یک‌طرفه بودن ایمیل |
| S7.8 تا S7.11 | Telegram، Slack، Discord و WhatsApp | مسدود (محیط عمومی D-29) | امضا، replay، پاسخ زیر ۳ ثانیه و یک اجرای واقعی |
| S7.12 | webhook امضاشده و سرویس‌های عمومی | منتظر | امضا + سیاست SSRF |
| S7.13 | امنیت، حریم خصوصی، نگهداشت و uninstall | منتظر | بازبینی امنیتی مکتوب |
| S7.14 | browser matrix S7 | منتظر | exit 0 هر دو locale، صفر console/pageerror، cleanup |
| S7.15 | regression، گزارش اجرا و بستن بسته | منتظر | full gate سبز و شمارهای یکسان در اسناد |

سند کامل: `HUMAN_SUPPORT_S7_AUDIT_AND_PLAN_FA.md`؛ صف، وابستگی و تصمیم‌ها در `PRODUCT_COMPLETION_ROADMAP_FA.md` (T57 تا T72، D-26 تا D-32).

## لاگ اجرا

- ۲۰۲۶-۰۹-۱۸ — اجرای دوبارهٔ matrix ویجت به درخواست مالک، پس از تغییر هم‌زمان T73. جلسه‌های T15 و T73 برای این اجرا مکث کردند. اجرای اول نقص **F-26** را نشان داد: دکمهٔ «آخرین پیام» وقتی focus داشت پنهان می‌شد و focus از پنل بیرون می‌افتاد. این ریشهٔ شکست گذرای T13 بود و رفع شد. نتیجهٔ نهایی: matrix **429/0**، render integration **97/0**، smoke 8/8، preflight تمیز. جزئیات در بخش «اجرای دوبارهٔ matrix» فایل plan S3.3.
- ۲۰۲۶-۰۹-۱۸ — **پیگیری S3.3 به دستور مالک (T73): دو محدودیت باز گزارش S3.3 حل شد.** F-11 مشکل UI بود: کارت «Pages where the chat appears» در سفارشی‌سازی ویجت («Every page» یا «Every page except the ones below»، نوع محتوا، دستهٔ نوشته، جست‌وجوی برگه و نوشته و محصول با عنوان) و ردیف خلاصه در برگهٔ Widget؛ خوانندهٔ استثنا سه نقص داشت (کلید `widget_exclude_ids` به‌جای `exclude_ids`، `exclude_pages` بی‌اثر، و مقایسهٔ شناسهٔ دسته با شناسهٔ نوشته) که رفع شد. F-12 مشکل مستندات بود: دوازده کلید بازنشسته با کنترل زندهٔ جانشین هرکدام در `docs/technical/DATA_MODEL_FA.md` تعریف شدند و حذف نشدند. integration تازه **27/0**، widget customise browser matrix **85/0** (en_US 41، fa_IR 44)، 31 msgid فارسی. دو اجرای مرورگر نخست با matrixهای T15 و T56 هم‌زمان شد و بی‌اعتبار است؛ اجرای معتبر پس از واگذاری نوبتی پنجره (21:27 تا 21:32) است. جزئیات در ردیف T73 رودمپ.
- ۲۰۲۶-۰۹-۱۸ — **S6 تکمیل شد (T56).** IA هشت‌صفحه‌ای D-23 نخستین بار در مرورگر سنجیده شد: منوی ۸ آیتمی به ترتیب D-23، هر ۸ صفحه و ۳ تب پنهان Overview در en_US و fa_IR × 1440 و 390، نوار تب با یک `aria-current`، Tab و Enter، و لینک عمیق قدیمی Form Studio. matrix دائمی تازه `tests/admin_ia_browser_matrix.cjs` با **652 PASS / 0 FAIL**. سه نقص رفع شد: «Form Studio» و پنج جملهٔ آن و «Overview sections» در fa_IR ترجمه نداشتند (اکنون «استودیوی فرم»)، ردیف تب Overview به کارت اول چسبیده بود، و تب‌ها در 390px 3 + 1 می‌شکستند. یک برخورد با matrix نشست دیگر روی حساب QA مشترک افشا و با نگهبان exit 2 مهار شد. smoke 8/8 (`test-artifacts/gates/20260918-202017-smoke`). گزارش: `OVERVIEW_FORM_STUDIO_S6_EXECUTION_REPORT_FA.md`.

- ۲۰۲۶-۰۹-۱۸ — پیگیری S3.3 به درخواست مالک (پس از T14). دو اختلاف عمدی T13 کاربرپسند نبودند و رفع شدند. هنگام ضبط، کادر پیام دیگر دوخطی نمی‌شود: قاب coral، نقطهٔ زنده، زمان m:ss، دکمهٔ توقف و placeholder یک‌خطی «Listening…»، در موبایل و دسکتاپ (F-25). sheet موبایل مثل full screen modal است و صفحهٔ پشت را قفل می‌کند (F-17 برگشت). matrix **425/0**، render integration **96/0**، smoke 8/8. جزئیات در بخش «پیگیری T13» فایل plan S3.3.

- ۲۰۲۶-۰۹-۱۸ — **S3.3 تکمیل شد (T14).** ویجت بازدیدکننده بسته شد: ۲۵ یافتهٔ اندازه‌گیری‌شده (F-01 تا F-25) بسته شدند — ۲۲ با کد و سه با تصمیم مستند (F-11، F-12 و F-17). مهم‌ترین‌ها: ویجت دیگر روی هر بارگذاری هر صفحه خودش باز نمی‌شود و پیش از باز شدن پنل هیچ درخواست RESTی نمی‌فرستد؛ `POST /public/voice` دروازهٔ `widget.voice_enabled` را **پیش از rate limiter** گرفت؛ پاسخ Markdown با DOM امن رندر می‌شود؛ کارت انتقال بدون ادعای اپراتور یا زمان پاسخ است و در نبود کانال آماده اصلاً وجود ندارد؛ صدا transcript-first ماند (D-02: overlay ساخته نشد)؛ و کنتراست متن کمکی از 2.26:1 به دست‌کم 4.95:1 رسید. **گیت‌های بستن:** full **9/9** در دو اجرای مستقل با شمارهای یکسان (PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit 149 / 514، Phase 1 45/0) — `test-artifacts/gates/20260918-181045-full`؛ browser matrix دائمی **385 PASS / 0 FAIL** (en_US 193، fa_IR 192) — `test-artifacts/front-chat-widget/20260918-t14/matrix-t14.txt`؛ render integration **92/0**؛ preflight پایانی WARNINGS 0 و BLOCKERS 0 بدون باقی‌ماندهٔ fixture. T14 کد را تغییر نداد. گزارش کامل: `FRONT_CHAT_WIDGET_S3_3_EXECUTION_REPORT_FA.md`. بعدی: T56 (S6.3) و سپس T29 (S5.0).
- ۲۰۲۶-۰۹-۱۸ — S7 باز شد (برنامه‌ریزی، بدون تغییر کد). درخواست مالک: پشتیبانی انسانی از اعلان یک‌طرفه به میز پشتیبانی کامل تبدیل شود — شش بستر قابل انتخاب، دپارتمان‌بندی، چند نیرو با جدول زمان‌بندی، پذیرش و ارجاع و پیوستن چند نفر، پنل پاسخگو در داشبورد وردپرس با سطح دسترسی جدا، و اعلام «چه کسی وارد گفتگو شد» به بازدیدکننده. سند اجرا (`HUMAN_SUPPORT_S7_AUDIT_AND_PLAN_FA.md`) با ممیزی `file:line` وضعیت امروز، قرارداد `SupportChannelInterface` با دوازده capability، ماتریس واقعی نه سرویس، ماشین حالت چهارده‌حالتهٔ بازدیدکننده، مدل داده، قرارداد REST، امنیت هر webhook و شش قفل صادقانه نوشته شد. شانزده task (T57 تا T72) و هفت تصمیم (D-26 تا D-32) در رودمپ ثبت شد؛ D-29 همان‌جا با مالک بسته شد (محیط QA عمومی: ZIP اپ Node.js روی Hostinger برای دامنهٔ موقت، سپس به‌روزرسانی با SSH). هیچ کد، تست یا تنظیمی تغییر نکرد. بعدی: پاسخ مالک به D-26 و D-27.
- ۲۰۲۶-۰۹-۱۸ — S3.3.6 تکمیل (T13). browser matrix دائمی ویجت: `tests/front_chat_widget_browser_matrix.cjs` با fixture و check هم‌نام، **385 PASS / 0 FAIL** در en_US و fa_IR × 1440 و 390 × سه سناریو. matrix سه نقص تازه پیدا کرد که رفع شدند: کنتراست متن کمکی (F-23)، هم‌ترازی عنوان لاتین در سایت فارسی (F-24) و بریده شدن جملهٔ «در حال شنیدن…» (F-25)؛ F-17 با تصمیم «قفل نمی‌شود» بسته شد. render integration 92/0، smoke 8/8. بعدی: S3.3.7 (T14).
- ۲۰۲۶-۰۹-۱۸ — S3.3.5 تکمیل (T12). صدای صادقانه در ویجت: `POST /public/voice` حالا دروازهٔ `widget.voice_enabled` دارد و با میکروفن خاموش 403 `voice_disabled` می‌دهد — پیش از rate limiter و پیش از هر فراخوان provider (F-09)؛ ناحیهٔ تازهٔ `role="status"` سه فاز واقعی (شنیدن، تبدیل به متن، «متن در کادر پیام است و هنوز چیزی ارسال نشده») را برای صفحه‌خوان اعلام می‌کند و پس از هر خطا و هنگام ارسال پاک می‌شود (F-16)؛ و برجسته‌سازی کلمه هنگام خواندن با صدا این بار در مرورگر اثبات شد (F-19). D-02 اجرا شد: overlay تمام‌صفحهٔ voice ساخته نشد و زیرنویس زنده، barge-in، Mute و End پشتوانه ندارند. شواهد: check مرورگر **126/0** در en_US و fa_IR × (voice-on 1440/390، read-aloud 1440، voice-off 1440) با stub کردن میکروفن، ضبط‌کننده و موتور گفتار؛ کل جریان صوتی فقط یک `POST public/voice` ساخت و تا فشردن Send هیچ پرسشی نرفت. render integration 88/0، نه integration مرتبط سبز، static 5/5 (`test-artifacts/gates/20260918-022224-static`) و smoke 8/8، MO 2286 از 2427. هیچ صدایی از مرورگر بیرون نرفت و هیچ provider یا بودجه‌ای لمس نشد. بعدی: T13 (browser matrix رسمی؛ هر سه وابستگی T09، T11 و T12 تکمیل).
- ۲۰۲۶-۰۹-۱۷ — S3.3.4 تکمیل (T11). کارت انتقال به انسان: تا وقتی کارت منتظر پاسخ بازدیدکننده است پرسش‌های پیشنهادی کنار می‌روند و با «Not now» یا پس از ارسال برمی‌گردند (F-10)؛ کارت ارسال‌شده در thread می‌ماند، هر دو دکمه را می‌اندازد و فقط «Sent — we will be in touch.» را می‌گوید، بدون ادعای اپراتور یا زمان پاسخ؛ در حالت not-ready نه دکمه، نه قالب و نه کارتی وجود دارد، حتی وقتی پاسخ `handoff_offer` دارد. سه یافتهٔ تازه هم رفع شد: رونوشتی که به تیم می‌رفت مرز پاراگراف و فهرست پاسخ رندرشده را از دست می‌داد (F-20)، برچسب پیش‌فرض دکمه در فرم مدیر با renderer یکی نبود (F-21) و چهار کلید handoff در دادهٔ localize خوانده نمی‌شدند یا تکراری بودند (F-22). شواهد: check مرورگر 140/0 در en_US و fa_IR × 1440/390 × (ready، not-ready)، render integration 76/0، یازده integration مرتبط سبز، static 5/5 (`test-artifacts/gates/20260917-225056-static`) و smoke 8/8. هیچ ارسال واقعی رخ نداد. بعدی: T12.
- ۲۰۲۶-۰۹-۱۷ — S3.3.3 تکمیل (T10). گفت‌وگوی ویجت: بازدیدکننده‌ای که گفت‌وگو را باز نمی‌کند دیگر هیچ درخواستی نمی‌فرستد (تنظیمات صدا از دادهٔ محلی صفحه، F-15)؛ پاسخ Markdown با DOM امن رندر می‌شود و گزینه‌های `clarify_options` فقط composer را پر می‌کنند (F-13، مرز D-24)؛ Stop برچسب «Stop waiting for the reply» و یادداشت صادقانه دارد و سقف‌ها (`rate_limited`، `usage_cap_reached`) دکمهٔ «Try again» ندارند (F-07)؛ 400 `empty` دیگر `answer` نمی‌فرستد و ویجت فقط `ok` را موفق می‌داند (F-14)؛ `dir="auto"` روی حباب‌ها و متن‌های مدیر (F-06). یافتهٔ تازه F-19 همین‌جا رفع شد: برجسته‌سازی کلمه هنگام خواندن با صدا فقط کلمهٔ اول را روشن می‌کرد (در مرورگر سنجیده نشده؛ T12). شواهد: check مرورگر 106/0 در en_US و fa_IR × 1440/390، render integration 62/0، هشت integration مرتبط سبز، static 5/5 (`test-artifacts/gates/20260917-193637-static`)، MO 2283 از 2424. بعدی: T11 یا T12.
- ۲۰۲۶-۰۹-۱۷ — S3.3.2 تکمیل (T09). حالت full screen: صفحهٔ پشت پنل دیگر scroll نمی‌شود (قفل با snapshot دقیق و بازگردانی کامل)، focus داخل پنل می‌ماند و هنگام خروج فقط وقتی کنترلش ناپدید شده یا روی صفحه رها شده به دکمهٔ expand برمی‌گردد، و در 641 تا 768px تمام viewport بدون scrim و بدون ستون است (تصمیم breakpoint T07) با بازگشت chipها. یافتهٔ تازه F-18 همین‌جا رفع شد: قاعدهٔ `:has(.spw-side__list)` با specificity بالاتر، chipها را در ≤640px هم پنهان می‌کرد و پرسش‌های ذخیره‌شده در full screen موبایل اصلاً دیده نمی‌شدند. شواهد: check مرورگر 114/0 در en_US و fa_IR × 1440/768/390، render integration 49/0، static 5/5 (`test-artifacts/gates/20260917-135629-static`). باز برای T13: F-17 (sheet موبایل قفل scroll ندارد). بعدی: T10.
- ۲۰۲۶-۰۹-۱۷ — S3.3.1 تکمیل (T08). مالک D-25، D-24 و D-02 را طبق پیشنهاد تأیید کرد. ویجت دیگر خودش باز نمی‌شود و nudge دیده می‌شود؛ کنترل «Open the chat by itself» در customiser افزوده شد؛ `voice_auto` پیش‌فرض خاموش است؛ پنل 392×624 با گوشهٔ 20px است؛ نقطهٔ «آنلاین» از ویجت و پیش‌نمایش حذف شد؛ ناحیهٔ کلیک دکمه‌های آیکنی 44px است؛ متن پیش‌فرض دست‌نخورده به زبان سایت نمایش داده می‌شود. مهاجرت یک‌باره فقط مقدارهای قدیمیِ بدون نویسندهٔ UI را جابه‌جا می‌کند. شواهد: render integration 41/0، check مرورگر 83/0 (en_US و fa_IR، 1440 و 390)، customise matrix 26/0 و 29/0، full 9/9 (`test-artifacts/gates/20260917-122714-full`). آزمون menu badge به شمار 8 آیتم D-23 اصلاح شد (6/0). بعدی: T09 یا T10.

- ۲۰۲۶-۰۹-۱۷ — S6.1 و S6.2 تکمیل شدند (دستور مستقیم مالک، D-23؛ T54/T55). Usage & Cost، Health و Activity Log از آیتم مستقل منو به تب زیر Overview منتقل شدند (`OverviewNavigation` تازه)؛ Form Composer به «Form Studio» تغییر نام داد و از deep link زیر Content Studio به هشتمین صفحهٔ اصلی مستقل ترویج یافت (`CopilotPage::render_forms()`، لینک قدیمی همچنان کار می‌کند). شناسه‌های داخلی (`form_composer_enabled`، id ماشینی `form-composer`) دست‌نخورده ماندند. S6.2: قرارداد JSON `form_structer` مستند شد؛ سازگاری با EFB از پیش در `EfbFormConverter` پیاده بود. گیت full **9/9** (PHPCS 134/134 با 0/0، PHPUnit 149/514، menu-smoke 5/0، assets-smoke **20/0**) + هفت integration مستقیم (۶۱ چک، ۰ شکست). جزئیات: `docs/OVERVIEW_FORM_STUDIO_S6_AUDIT_AND_PLAN_FA.md`. باقی‌مانده: S6.3 (browser matrix واقعی، T56). بعدی: T56 یا ادامهٔ صف اصلی (T08…).
- ۲۰۲۶-۰۹-۱۷ — S3.3 آغاز شد؛ S3.3.0 تکمیل (T07). ممیزی و برنامه در `FRONT_CHAT_WIDGET_S3_3_AUDIT_AND_PLAN_FA.md`: بازطراحی موکاپ از پیش روی renderer واقعی `ChatWidget` است، پس باقی بسته بستن ۱۶ یافتهٔ اندازه‌گیری‌شده و browser matrix است. شواهد: 28 قاب موکاپ و 18 تصویر baseline (en_US و fa_IR، 1440 و 390) با صفر console، pageerror و HTTP ≥ 400؛ کد تغییر نکرد. تصمیم‌های لازم: D-02 (T12)، D-24 (T10)، D-25 (T08). بعدی: T08.
- ۲۰۲۶-۰۹-۱۷ — S3.2 تکمیل شد (T06). EFB Copilot روی ویرایشگر واقعی EFB 4.2.0 با selected-field اعتبارسنجی‌شده در سرور، رفع callback `/efb/log`، voice transcript-first بدون ارسال خودکار، کارت how-to و Pro واقعی، operationهای سبک step و progress، لاگ اتمی (T28) و ترجمهٔ کامل فارسی بسته شد.
  - **گیت‌ها:** full **9/9** — php-lint 229/229، node-check 37/37، PHPCS **133/133** با 0/0، PHPStan **0**، PHPUnit **149 / 514**، menu 5/0، assets 19/0، render 18/0، Phase 1 **45/0** (`test-artifacts/gates/20260916-214733-full`)؛ release **60/60** با EFB Copilot contract 15/0 و Logger concurrency 6/0 (`test-artifacts/gates/20260916-215043-release`)؛ browser matrix `en_US` و `fa_IR` هر کدام **76/0** (`test-artifacts/efb-copilot/20260916-t04/`).
  - **اختلاف‌های عمدی:** voice realtime تمام‌صفحه (D-02 و T47)، chip که فقط composer را پر می‌کند، خط زمینهٔ هدر بدون نام فرم و شمار فیلد، کارت how-to نثری و ظاهر کارت Pro.
  - گزارش: `EFB_COPILOT_S3_2_EXECUTION_REPORT_FA.md`. بعدی: S3.3 (T07).

- ۲۰۲۶-۰۹-۱۶ — دستور مالک برای انتقال و پرسش‌های بی‌پاسخ اجرا شد (D-20، D-21، D-22).
  - **ساخته شد:** اطلاعات تماس قابل تنظیم در کارت انتقال (Off، Optional، Required)؛ بایگانی صندوق؛ پاسخ ذخیره‌شده و «Write it» برای پرسش‌های بی‌پاسخ (T30 بسته شد).
  - **گیت‌ها:** static 5/5 (PHPUnit 149 / 514)؛ release 60/60 (`test-artifacts/gates/20260916-204545-release`)؛ سه browser matrix در en_US و fa_IR بدون شکست.
  - جزئیات در handoff و ژورنال رودمپ.

- ۲۰۲۶-۰۹-۱۶ — انتقال به انسان سرتاسری وارسی شد.
  - آزمون تازهٔ `wordpress_widget_handoff_flow_integration` با 16/0.
  - دو خطای escape رفع شد: متن Telegram دوبار escape می‌شد، صندوق entity ذخیره می‌کرد، و `wp_strip_all_tags` متن پس از `<` را می‌بُرد. متن اکنون خام ذخیره و در هر خروجی escape می‌شود.
  - گیت release 59/59 (`test-artifacts/gates/20260916-162559-release`)؛ matrix صندوق در en_US و fa_IR بدون شکست.
  - جزئیات در handoff و ژورنال رودمپ.

- ۲۰۲۶-۰۹-۱۶ — نسخهٔ Claude Design سطح‌های بیرون از موکاپ بررسی شد و تغییرهای تأییدشدهٔ آن اجرا شد: نوار صندوق در انتقال به انسان، حالت قفل و کارت خطای کنسول پرسش، بارگذاری و خطای محتوای ایندکس‌شده، برچسب فقط‌خواندنی اتصال و نوار ذخیرهٔ روشن. گیت release 58/58 (`test-artifacts/gates/20260916-003727-release`). مرورگر en_US و fa_IR بدون شکست. جزئیات در handoff و ژورنال رودمپ.

- ۲۰۲۶-۰۹-۱۵ — قابلیت‌های کد بدون موکاپ در Claude Design طراحی (`design/claude-design-v2`) و مطابق آن ساخته شدند: کنسول پرسش، مدخل‌های دانش سایت، صندوق انتقال، فرم‌های بومی، reset مصرف، سفارشی‌سازی ویجت و حریم خصوصی، تنظیمات پیشرفته و جزئیات EFB، هر کدام با integration و browser matrix. گیت release **58/58** (PHPCS **130/130 بدون error/warning**، PHPStan **0**، PHPUnit **137/470**، Phase 1 **45/0** و 49 integration suite). browser regression **90/0** در `en_US/fa_IR`. مستندات فنی `docs/technical/` و agent راهنمای کاربر (`/aisa-docs`) افزوده شدند. جزئیات در handoff و ژورنال رودمپ.

- ۲۰۲۶-۰۹-۱۵ — بازبینی بصری و هم‌ترازی با چهار موکاپ (بخشی از Q4.2 و Q4.3) انجام شد. پوستهٔ مشترک، Setup wizard، Overview، Integrations، Usage، Activity Log، ویجت بازدیدکننده، aliasهای پنهان، Store Copilot، Site Knowledge و Form Composer با موکاپ مقایسه و اصلاح شدند؛ منوی wp-admin روی aliasها باز می‌ماند و نشان شمار انتقال‌های new از دادهٔ واقعی می‌آید. شکاف‌های دوطرفه با مرجع دقیق در `MOCKUP_FEATURE_GAP_REPORT_FA.md` ثبت شد. گیت release **53/53** (PHPCS **130/130 بدون error/warning**، PHPStan **0**، PHPUnit **137/470**، Phase 1 **45/0** و 44 integration suite) و browser در `en_US/fa_IR`، 1440 و 390 بدون overflow، console/pageerror یا HTTP خطا (صفحه‌ها 118 OK، ویجت 54/0، Setup 106/0، alias/منو/گوشی 42/0)؛ حساب QA و fixture پاک شدند.

- ۲۰۲۶-۰۹-۱۰ — S3.1 تکمیل شد. Form Composer در هر سه وضعیت compose/review/result پوستهٔ ثابت header + canvas + Assistant موکاپ را دارد؛ مقصد email مدیر، preview، expiry/risk، approval، تأیید دوم publish، shortcode و EFB handoff به قراردادهای واقعی متصل‌اند. status تأییدشده در proposal freeze و context خراب fail-closed شد. تخمین ثابت ۱۲۰۰ token و chrome قدیمی حذف، ترجمهٔ فارسی تکمیل و MO با ۱۱۹۴ entry کامپایل شد. renderer **4/0**، proposal context **3/0**، delivery **PASS**، Phase 1 **45/0**، PHPUnit **133/439**، PHPStan **0** و PHPCS **130/130** پاس شدند. browser matrix در `en_US/fa_IR`، desktop/RTL/390px شامل draft و publish-confirm با **0 failure** تمام شد؛ console/pageerror و overflow صفر و حساب QA پاک شد. گزارش: `FORM_COMPOSER_S3_1_EXECUTION_REPORT_FA.md`.

- ۲۰۲۶-۰۹-۰۶ — A2.10 تکمیل شد: timeline و empty state مطابق ساختار موکاپ، فقط لینک واقعی به draft، CSV ایمن و حذف کنترل‌های legacy از surface canonical. چهار اجرای مرورگر انگلیسی/فارسی و خالی/پر بدون شکست، با screenshot review؛ تراز عنوان RTL و focus Export نیز اصلاح و دوباره تست شدند. رگرسیون کامل سبز است. رخداد helper قدیمیِ حذف لاگ محلی پنهان نشده و گزارش مستقل دارد؛ دادهٔ تاریخی بازیابی نشده است.

- ۲۰۲۶-۰۹-۰۵ — A2.9 تکمیل شد. Health از ۱۰ dependency گروه‌بندی‌شده، summary و sidebar قدیمی به کارت دقیق موکاپ با **۸** check ثابت تبدیل شد: Provider reachable، API key، Key storage، Embeddings، WP-Cron، Index freshness، Rate limit headroom و PHP/WordPress. `POST /health/diagnostics` فقط config ذخیره‌شده را یک‌بار بررسی و snapshot HMAC بدون key/endpoint/model/raw response نگه می‌دارد؛ test draft Settings آن را تغییر نمی‌دهد. نرخ quota provider قابل‌اثبات نیست، پس `Not measured` است. گیت نهایی: diagnostics **7/0**، render **3/0**، Phase 1 **45/0**، PHPCS **129/129**، PHPStan **0**، PHPUnit **133/439** و browser واقعی `fa_IR` با **0 failure** در desktop/RTL/390px. screenshotهای visual QA بازبینی شدند و حساب QA موقت پاک شد.

- ۲۰۲۶-۰۹-۰۵ — A2.8 پس از بازبینی correctness بسته شد: endpoint سقف Usage strict و محدوده‌دار شد، storage خراب و period آینده بدون تحریف داده مدیریت می‌شوند، و KB/Store/EFB در cap قرارداد صریح `429 usage_cap_reached` و transport صفر دارند. برای جلوگیری از گم‌شدن cursor در Site Knowledge، کنترل `Resume indexing` فقط در همان tab پس از pause ظاهر می‌شود؛ این اختلاف لازم با stateهای موکاپ در گزارش ثبت شده است. گیت نهایی: PHPCS **127/127** بدون error/warning، PHPStan **0**، PHPUnit **133/439**، Usage tracker **11/0**، budget API **15/0**، KB cap **3/0**، Store cap **2/0**، EFB cap **2/0**، Phase 1 **45/0** و browser واقعی `fa_IR` در desktop/RTL/390px بدون console/page error سبز.

- ۲۰۲۶-۰۹-۰۳ — A2.7.4 و A2.7.5 تکمیل شدند. History از Activity Log جدا و به کارت موکاپیِ Content Studio تبدیل شد: فقط post/pageهای markerدار Studio، زمان ایجاد واقعی (با fallback تاریخ محلی برای draftهای تازه)، مقصد/حالت WordPress و attribution صریح `Template not recorded`/`Usage not recorded` نشان داده می‌شوند. چون price map و usage per-draft نداریم، copy کارت آگاهانه «Recent saved output, usage when recorded…» است؛ USD یا usage جعلی نمایش داده نمی‌شود. `ContentStudioHistoryExport` از `admin-post`، nonce و `manage_options` استفاده می‌کند، فقط schema محدودِ ۱۰۰ row markerدار را صادر و formula injection را خنثی می‌کند. browser QA با fixture موقت در desktop/RTL/390px marker-only row، نبود row unmarked، Enter→CSV و صفر REST را با **exit 0** تأیید کرد؛ screenshotهای History/Write/Templates بازبینی شدند. رگرسیون نهایی: PHPStan **0**، PHPCS **126/126**، PHPUnit **133/439**، Node syntax سبز، contract **3/0**، navigation **5/0**، Write **3/0**، Templates **5/0**، History read/export/page **3/0 / 3/0 / 5/0**، assets **19/0**، render smoke **18 route**، Phase 1 **8/0** و **45/0**. کاربر QA و fixtureهای موقت پاک شدند.

- ۲۰۲۶-۰۹-۰۲ — A2.7.3 تکمیل شد. Templates اکنون دقیقاً چهار کارت موکاپی با preview مونو و CTA واقعی `Use template` دارد. CTA با keyboard به Write می‌رود، brief editable را بدون اجرای خودکار `plan/execute` پر می‌کند و percent-encoding پیش از `esc_url()` placeholderهای `{product}` و مشابه آن را حفظ می‌کند. چون backend editor، persistence و شمارش استفادهٔ قابل‌اعتماد ندارد، `Built-in` و `Use template` آگاهانه جای `Used N times` و `Edit template` موکاپ را گرفته‌اند و این اختلاف در گزارش ثبت شده است. شواهد: Templates integration **5/0**، browser واقعی `fa_IR` در desktop/RTL/390px با **exit 0**، keyboard hand-off و بدون REST خودکار، screenshot review؛ رگرسیون PHPStan **0**، PHPCS **125/125**، PHPUnit **133/439**، navigation **5/0**، read-model **2/0**، Write **3/0**، contract **3/0**، assets **19/0**، render smoke **18 route**، Phase 1 **8/0** و **45/0**. کاربر QA موقت و fixtureهای تست پاک شدند.

- ۲۰۲۶-۰۹-۰۲ — A2.7.2 تکمیل شد. Write Content Studio اکنون empty-state، composer ۷۸px، سه chip صادقانهٔ Voice، CTA `Write a draft` و فهرست دقیقاً سه‌تاییِ draftهای واقعی markerدار را مطابق موکاپ دارد. read-model فقط post/page تولیدشده توسط Studio را می‌خواند؛ پیش‌نمایش و تأیید صریحِ draft-only عمداً برای حفظ ایمنی باقی ماندند و auto-publish عملیاتی نشده است. browser QA با REST mock مسیر plan → preview → approval → result را بدون provider/draft واقعی تأیید کرد و cap response را در UI قفل نمود. شواهد: read-model **2/0**، Write renderer **3/0**، contract **3/0**، navigation **5/0**، assets **19/0**، render smoke **18 route**، Phase 1 **8/0** و **45/0**، PHPUnit **133/439**، PHPStan **0**، PHPCS **125/125** و browser `fa_IR` desktop/RTL/390px با **exit 0** و بدون console/pageerror. screenshotهای desktop و موبایل نیز بازبینی شدند؛ fixture و حساب QA موقت پاک شدند.

- ۲۰۲۶-۰۹-۰۱ — A2.6 تکمیل شد. Store Copilot اکنون فقط چهار tab موکاپ (`New product`، `What it can do`، `Products` و `Orders`) دارد. New product قبل از ایجاد draft، proposal پانزده‌دقیقه‌ای و تأیید صریح می‌خواهد؛ Phase 1 همیشه draft-only است. Capabilities فقط دو preference واقعی را ذخیره می‌کند و connector paused همهٔ routeهای محافظت‌شدهٔ Store را می‌بندد. Products از read-model واقعی WooCommerce با marker مالکیتی استفاده می‌کند و Orders قرارداد read-only/manager-only را بدون action جعلی توضیح می‌دهد. گیت‌ها: settings **7/0**، page **6/0**، draft **3/0**، read-model **3/0**، Phase 1 **45/0**، PHPUnit **131/429**، PHPStan **0 error** و PHPCS **124/124 بدون error/warning**. browser QA هر دو alias با **exit 0**، desktop/390px و screenshot review را پاس کرد؛ RTL هم علاوه بر تست کنترل‌شده، در پوستهٔ واقعی WordPress با `fa_IR` اجرا شد و title، description و پنل‌ها بدون clipping باقی ماندند. fixture و حساب QA موقت پس از هر اجرا حذف شدند.

- ۲۰۲۶-۰۸-۳۰ — A2.4 تکمیل شد. Site Knowledge اکنون دقیقاً چهار tab موکاپ دارد: Overview، Collections، Indexing Queue و Sync Sources. Overview هم empty-state و هم coverage/source/gap واقعی را رندر می‌کند؛ source picker واقعی است و با Escape focus را به CTA برمی‌گرداند. Collections فقط typeهای مشتق‌شدهٔ واقعی را نشان می‌دهد و Queue صریحاً browser-session-only است؛ Ask your KB و جدول entryها در aliasهای عمیق ask و entries باقی مانده‌اند. ContentHooks همیشه ثبت می‌شود تا حتی با خاموش‌بودن auto-index، unpublish/trash/delete دادهٔ KB را پاک کند. queue پایدار، retry/resume، priority collection، PDF/URL/Woo source و gap→draft عمداً جعل نشده و در گزارش gap ثبت شده‌اند. گیت‌ها: lifecycle **7/0**، KB hub **8/0**، Phase 1 **45/0**، render smoke پاس و browser QA **exit 0** در desktop/RTL/390px، Escape/focus picker، بدون overflow، console/pageerror؛ کاربر QA پاک شد.
- ۲۰۲۶-۰۸-۳۰ — بازاعتبارسنجی نهایی A2.4: PHPUnit **131 test / 429 assertion**، PHPCS کامل **116/116 بدون error/warning**، PHPStan **0 error**، syntax تمام PHP/JS، و lifecycle/KB/Integrations/Phase 1/render/assets smoke محلی همگی پاس شدند.

- ۲۰۲۶-۰۸-۲۹ — شروع B0.1 و B0.2. PHPUnit baseline پیش از اصلاح: **131 test / 429 assertion پاس**. PHPCS baseline: **65 error**. local smoke: render شد اما به deployment قدیمی `sitepilot-ai` متصل و همراه warning بود؛ برای پذیرش معتبر نیست.
- ۲۰۲۶-۰۸-۲۹ — B0.2 تکمیل شد. junction `ai-site-assistant-autopilot` به source فعلی سالم بود؛ entrypoint قدیمی و هم‌زمانِ `sitepilot-ai` فقط deactivate شد. smoke فعلی: **exit 0، بدون PHP warning**. copy قدیمی برای ایمنی نگه داشته شده، اما دیگر active نیست.
- ۲۰۲۶-۰۸-۲۹ — B0.1 تکمیل شد. پنج فایل صرفاً formatting اصلاح شدند؛ PHPCS کامل: **116/116، 0 error**. PHPUnit: **131 test / 429 assertion پاس**. syntax و `git diff --check` در محدودهٔ اصلاح‌شده پاس شدند.
- ۲۰۲۶-۰۸-۲۹ — B0.3 تکمیل شد. PHPStan: **0 error**؛ PHPUnit: **131/429**؛ PHPCS: **116/116، 0 error**؛ WordPress local render smoke: **12 route، بدون warning/notice/deprecation**.
- ۲۰۲۶-۰۸-۲۹ — B0.4 تکمیل شد. checklist دستی از انتظار نادرستِ ذخیرهٔ submission به قرارداد email-only/عدم ذخیرهٔ PII اصلاح شد. `tests/wordpress_form_delivery_integration.php`: **PASS**؛ فرم موقت ایجاد و پاک شد و هیچ visitor value ذخیره نشد.
- ۲۰۲۶-۰۸-۲۹ — N1.1 تکمیل شد. shell در همهٔ صفحه‌ها translation مستقل گرفت، header از card گرد به header تخت موکاپ نزدیک شد و job bar دارای progress واقعی شد. `node --check`، PHP lint، PHPCS targeted و کامل (**116/116**)، PHPStan (**0 error**)، PHPUnit (**131/429**) و local render smoke (**12 route، بدون warning**) همگی پاس شدند.
- ۲۰۲۶-۰۸-۲۹ — بازاعتبارسنجی B0.1 انجام شد: formatter مکانیکی ۲۲۳ هشدار و سه اصلاح معنایی باقی‌مانده را حذف کرد. PHPCS کامل: **116/116، ۰ error، ۰ warning**؛ PHPStan: **۰ error**؛ PHPUnit: **131 test / 429 assertion**؛ و PHP lint سبز شدند.
- ۲۰۲۶-۰۸-۲۹ — N1.2 تکمیل شد. Store Copilot بدون شرطِ فعال‌بودن WooCommerce در منوی visible قرار گرفت و در نبود WooCommerce فقط state غیرعملیاتی و صادقانهٔ خود را نشان می‌دهد. `tests/local_admin_menu_smoke.php` ترتیب دقیق ۱۰ آیتم، ترجمه‌پذیری برچسب‌ها و پنهان‌ماندن ۸ alias قدیمی را پاس کرد. `tests/wordpress_phase1_integration.php` نیز با **42 checks, 0 failed** اجرای واقعی وردپرس محلی را تأیید کرد.
- ۲۰۲۶-۰۸-۲۹ — N1.3 تکمیل شد. aliasهای قدیمی اکنون به‌صورت direct hidden admin page ثبت می‌شوند، بنابراین bookmarkها HTTP 200 می‌گیرند بی‌آنکه وارد sidebar شوند. `tests/local_admin_assets_smoke.php` قرارداد enqueue همهٔ ۱۸ slug/alias، shell config و هر دو فونت mockup را پاس کرد؛ `node --check` همهٔ ۱۷ asset جاوااسکریپت نیز سبز شد. browser smoke واقعی با حساب موقت و پاک‌سازی‌شوندهٔ WordPress تمام ۱۸ مسیر را با **HTTP 200، shell حاضر، بدون fatal، console error یا pageerror** گذراند. Form Composer در desktop و ۳۹۰px، و ویجت عمومی (launcher، TTS، voice endpoint امن و overflow) نیز پاس شدند. suite نهایی این بسته: PHPUnit **131/429**، PHPStan **0 error**، PHPCS **116/116، 0 error/0 warning**، integration محلی **42/0** و form-delivery **PASS**.
- ۲۰۲۶-۰۸-۳۰ — A2.1 تکمیل شد. Overview اکنون layout دقیق موکاپ را با ۴ metric card، ستون Needs you و ۶ ردیف Running now (شامل Form Composer) نگه می‌دارد، اما عدد ساختگی نمایش نمی‌دهد: سؤال‌ها از turnهای واقعیِ ثبت‌شده و draftها فقط از post/product دارای marker داخلی `_aisaautopilot_generated` می‌آیند؛ draft معمولی WordPress وارد آمار نمی‌شود. Store Copilot برای draftهای جدید همان marker را می‌نویسد. واحد چهارم token باقی ماند، نه هزینهٔ دلاری ساختگی. حالت‌های first-day، provider-unconfigured و cap-reached با route واقعی CTAها تست شدند. `tests/wordpress_overview_metrics_integration.php` و `tests/wordpress_overview_states_integration.php`: **هرکدام 4/0**. browser QA نهایی: ۴ card، ۶ integration، desktop، RTL و ۳۹۰px بدون overflow، HTTP 200 و بدون console/pageerror؛ screenshotهای desktop/RTL/mobile نیز بازبینی شدند. گیت بازاعتبارسنجی: PHPUnit **131/429**، PHPStan **0**، PHPCS **116/116 بدون error/warning**، Phase 1 **42/0** و form-delivery **PASS**.
- ۲۰۲۶-۰۸-۳۰ — A2.2 تکمیل شد. Setup & Connection از فرم بلند قدیمی به wizard دقیق چهارمرحله‌ایِ موکاپ (Connection → AI service → Key & model → Integrations) تبدیل شد. فهرست ۱۵ سرویس، جست‌وجو، حالت no-result، مدل‌های قابل‌تازه‌سازی، outcomeهای اتصال و integration toggleهای واقعی پیاده‌سازی شدند. ذخیرهٔ wizard فقط پس از **تست موفقِ همان پیکربندی دقیق** مجاز است: نتیجهٔ موقت برای کاربر، HMAC شده، ۱۵ دقیقه‌ای و یک‌بارمصرف است؛ کلید API هرگز ذخیره یا به DOM/REST برگردانده نمی‌شود. تست ناموفق وضعیت تأییدشدهٔ قبلی را خراب نمی‌کند و مقدارهای صریحاً خالیِ endpoint/model دیگر به تنظیم قبلی fallback نمی‌کنند. قابلیت‌های پیشرفته‌ای که موکاپ UI ندارد در alias پنهان `aisaautopilot-settings` حفظ شده‌اند؛ کنترل Reply length چون backend ندارد disabled و صادقانه برچسب‌گذاری شده است. گیت‌ها: Node syntax **18/18**، PHPUnit **131/429**، PHPStan **0**، PHPCS **116/116 بدون error/warning**، render/menu/assets smoke پاس، setup integration **8/0**، Phase 1 **43/0** و form delivery **PASS**. browser QA واقعی نیز flow، خطای پیکربندی ناقص، جلوگیری از Save پیش از test، desktop، RTL و ۳۹۰px را بدون overflow، console/pageerror یا HTTP خطا پاس کرد؛ کاربر موقت پس از اجرا حذف شد.
- ۲۰۲۶-۰۸-۳۰ — A2.3 تکمیل شد. Integrations دقیقاً به چهار گروه و شش کارت موکاپ بازطراحی شد: Site Knowledge، Answer Desk، Form Composer، Easy Form Builder Copilot، Content Studio و Store Copilot. هر switch فقط setting واقعی خود را تغییر می‌دهد؛ REST Form Composer/Content Studio/EFB و auto-index نیز همان gateها را رعایت می‌کنند. dependency غایب با `Needs …` و CTA نصب/مسیر درست نشان داده می‌شود. dialogهای Configure فقط کنترل‌های persist‌شونده دارند؛ email-only/عدم ذخیرهٔ PII، draft-only publishing، theme/calendar متعلق به EFB و bulk crawl به‌عنوان محدودیت قفل‌شده و صادقانه ثبت شده‌اند. گیت‌ها: integration suite **14/0**، render/assets smoke پاس، Phase 1 **45/0**، PHPUnit **131/429**، PHPStan **0** و PHPCS **116/116 بدون error/warning**. browser QA اختصاصی desktop/RTL/390px، ترتیب ۶ کارت، dialog باز/بسته‌شدن با Escape و بازگشت focus، عدم overflow و نبود console/pageerror را با **exit 0** پاس کرد؛ کاربر موقت حذف شد.
- ۲۰۲۶-۰۸-۳۰ — A2.5.1 تکمیل شد. یک نقص contract بین toggle Integrations و widget قابل‌تنظیم رفع شد: اکنون `public_assistant_enabled` و `widget.enabled` gate واحد Answer Desk هستند و فقط یک widget قابل‌تنظیم با citation/logging/handoff برای بازدیدکننده render می‌شود. handoff فقط با دست‌کم یک Email/Telegram/Twilio واقعاً آماده visible/offer می‌شود؛ کانال ناقص دکمهٔ شکست‌خورده تولید نمی‌کند. `wordpress_answer_desk_lifecycle_integration.php` **5/0** و Phase 1 **45/0** پاس شدند؛ هیچ provider یا delivery خارجی در تست فراخوانی نشد.
- ۲۰۲۶-۰۸-۳۱ — A2.5.2 تکمیل شد. Conversations اکنون فقط چهار state واقعی `widget-hidden`، `logging-off`، `empty` و `ready` دارد؛ در حالت ready آخرین threadهای opt-in ذخیره‌شده، زمان واقعی، سؤال/پاسخ و citationهای واقعاً ذخیره‌شده را نشان می‌دهد و transcript به ۱۲ پیام آخر محدود است. هیچ confidence، location، live typing، page-origin یا handoff-status جعلی از موکاپ نمایش داده نشده است. preview فشردهٔ widget و CTAهای settings/content gaps/handoff همگی به مسیرهای واقعی متصل‌اند. یک نقص پیش‌پرواز در localize frontend نیز رفع شد تا greeting/voice/handoff persisted به‌جای fallback به widget برسند. گیت‌ها: Conversations **5/0**، lifecycle **6/0**، Phase 1 **45/0**، PHPUnit **131/429**، PHPStan **0**، PHPCS بدون error/warning، render/assets smoke پاس و browser QA desktop/RTL/390px با screenshot review و exit **0**؛ account و fixture موقت بعد از QA حذف شدند.
- ۲۰۲۶-۰۸-۳۱ — A2.5.3 تکمیل شد. Widget از alias بلند قدیمی به route canonical `aisaautopilot-conversations&tab=widget` منتقل شد و layout دقیق دوکارت موکاپ (Appearance & behaviour + Live preview) گرفت. فقط دو control قابل‌اتکا autosave می‌شوند: visibility و accent colour؛ REST مدیریتیِ nonce/capability-protected فقط همان دو field را partial update می‌کند، بنابراین privacy/logging، متن‌ها، voice، handoff و credentialها reset نمی‌شوند. coral `#ff455f` نیز واقعاً persist و preview را تغییر می‌دهد. source فقط با citation واقعی خودکار نمایش می‌یابد و theme-match/position/custom-palette بدون toggle جعلی، صادقانه قفل یا به Advanced compatibility route ارجاع شده‌اند. گیت‌ها: endpoint **4/0**، صفحه **4/0**، lifecycle **6/0**، Phase 1 **45/0**، PHPUnit **131/429**، PHPStan **0**، PHPCS **118/118 بدون error/warning**، Node **19/19**، render/assets smoke و browser autosave/reload/RTL/390px با screenshot review و exit **0**؛ fixture/account موقت حذف شد.
- ۲۰۲۶-۰۸-۳۱ — A2.5.4 تکمیل شد. Human handoff به route canonical `aisaautopilot-conversations&tab=handoff` منتقل شد و بدنهٔ ستونی ۷۶۰px موکاپ را با کارت‌های Email، Telegram، WhatsAppِ قفل‌شده و پنل Add another channel بازسازی کرد؛ SMS/Twilio فقط هنگام Add شدن باز می‌شود. `POST /aisaautopilot/v1/handoff/admin-settings` با REST nonce و `manage_options` فقط channel انتخاب‌شده را partial update می‌کند، credentialها را encrypted نگه می‌دارد و هرگز در پاسخ برنمی‌گرداند. Email/Telegram/Twilio فقط وقتی واقعاً آماده‌اند `Ready` می‌شوند؛ WhatsApp/Slack/Discord/webhook قفل‌اند. مدل واقعی افزونه fan-out است، نه fallback ترتیبی موکاپ؛ پس Email به‌دروغ Fallback یا Connected نام‌گذاری نشده است. گیت‌ها: endpoint **5/0**، صفحه **4/0**، Conversations **5/0**، Widget **4/0**، lifecycle **6/0**، render/assets smoke، PHPUnit **131/429**، PHPStan **0**، PHPCS **120/120 بدون error/warning**، Node **20/20** و browser focused Handoff با nonce/persistence/enable-pause-restore/desktop/RTL/390px، console/pageerror و screenshot review همگی پاس؛ account و fixture موقت حذف شدند.
- ۲۰۲۶-۰۸-۳۱ — A2.5.5 و A2.5.6 تکمیل شدند. Real-time voice به route canonical `aisaautopilot-conversations&tab=voice` منتقل شد. `POST /aisaautopilot/v1/voice/admin-settings` با nonce و `manage_options` فقط دو کنترل واقعاً مصرف‌شدهٔ ویجت (`voice_enabled` و `voice_auto`) را partial update می‌کند. «Realtime stream» و `Let visitors interrupt` چون transport/audio-session ندارند قفل و صادقانه برچسب‌گذاری شدند؛ `Transcribe + speak` ضبط کوتاهِ یک‌باره برای STT و پاسخ‌گویی با SpeechSynthesis مرورگر را کنترل می‌کند. `Play a test phrase` فقط در browser مدیر اجرا شد و هیچ audio/provider request نداشت. گیت‌ها: Voice endpoint **5/0**، Voice page **4/0**، هشت suite Answer Desk در مجموع **37/0**، render/assets smoke پاس، PHPUnit **131/429**، PHPStan **0 error**، PHPCS **122/122 بدون error/warning** و Node **21/21**. regression focused چهار tab و QA Voice در desktop/RTL/390px بدون overflow، console/pageerror و با screenshot review پاس شدند؛ fixture و account موقت حذف شدند.
