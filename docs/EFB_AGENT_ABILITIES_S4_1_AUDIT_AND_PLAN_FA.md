# ممیزی و برنامه اجرای S4.1 — EFB Agent Abilities

تاریخ ممیزی: ۲۰۲۶-۰۹-۱۵
آخرین بازبینی: ۲۰۲۶-۰۹-۱۵ — وارسی ایجنت `aisa-finisher` روی source افزونه، source EFB و WordPress 7.1.1-RC1، به‌علاوهٔ تصمیم‌های مالک D-10 تا D-12. فهرست اصلاح‌ها در بخش «اصلاح‌های وارسی» آمده است.
مرجع: اعلام رسمی Gravity Forms (۲۰۲۶-۰۸-۲۶) دربارهٔ قابلیت agent-callable «AI Form Builder» که در **همهٔ** لایسنس‌های Gravity Forms (رایگان تا بالاترین سطح) عرضه شده: ساخت/ویرایش فرم، تنظیم conditional logic/اعلان/confirmation، جست‌وجو/خلاصه/به‌روزرسانی ورودی‌ها، مدیریت تنظیمات PayPal/Stripe/Mailchimp، با سطوح دسترسی جداگانهٔ read-only/write/destructive.
نگاشت مقابل: `docs/EFB-INTEGRATION-ROADMAP.md` بخش «Recorded but intentionally not AI-writable»، `includes/Efb/efb-capability-catalog.json`، و بستهٔ فعلی EFB Copilot (`docs/EFB_COPILOT_S3_2_AUDIT_AND_PLAN_FA.md`).
سطح واقعی: کانکتور/اسکیل‌های EFB در `includes/Connectors/EasyFormBuilderConnector.php`، `includes/Execution/ActionExecutor.php`، `includes/Efb/*` — این بسته گسترش همان زیرساخت است، نه سیستم موازی.

این بسته خارج از صف اجرای فعلی T00 تا T28 است و در بک‌لاگ فاز ۵ ثبت شده (ردیف B-09 در `PRODUCT_COMPLETION_ROADMAP_FA.md`). طبق D-10 در بک‌لاگ می‌ماند و فقط با تصمیم D-07 وارد صف اجرا می‌شود.

## تصمیم‌های مالک در رودمپ (۲۰۲۶-۰۹-۱۵)

| ID | پرسش | تصمیم | اثر روی این سند |
|---|---|---|---|
| D-10 | S4.1 پیش از انتشار ۱.۱.۰ یا بک‌لاگ؟ | بک‌لاگ فاز ۵ | هیچ ریزتسکی پیش از تصمیم D-07 آغاز نمی‌شود |
| D-11 | سطح پرداختی جدا در AISA لازم است یا گیت EFB کافی است؟ | سطح پرداختی جدا نه؛ (الف) abilityهای خواندنی با گیت EFB؛ (ب) abilityهای نوشتنی و حذفی فقط برای Pro با معیار خود EFB | گیت دو سطحی شد؛ بخش «زیرساخت» و «قرارداد گیت پلن» را ببینید |
| D-12 | `efb.entries.generate_test` بدون فلگ واقعی «آزمایشی» ساخته شود؟ | ساخته نمی‌شود تا EFB فلگ واقعی بیاورد | S4.1.3 از دامنه بیرون رفت |

## زمینه و تصمیم‌های ثبت‌شده در بازبینی خارجی

چهار تصمیم زیر در جلسهٔ بازبینی خارجی همین روز ثبت شده‌اند. در جدول تصمیم‌های رودمپ نیستند و ایجنت نمی‌تواند تأیید مالک را برای آن‌ها وارسی کند؛ هر کدام پیش از S4.1.0 باید در جدول D ثبت شود. بند ۲ با D-11 جایگزین شده است.

1. **محل ساخت**: این قابلیت در همین افزونهٔ AI Site Assistant AutoPilot ساخته می‌شود، **نه** با تغییر در سورس Easy Form Builder. EFB همچنان فقط منبع مرجع فقط‌خواندنی است (طبق `CLAUDE.md` ریشهٔ workspace).
2. **گیت لایسنس** (جایگزین‌شده با D-11): نسخهٔ بازبینی خارجی کل بسته را روی Free Plus یا Pro باز می‌کرد. اما Free Plus را هر مدیر EFB بدون کد فعال‌سازی و بدون پرداخت انتخاب می‌کند (`efb_save_plan_selection` در `includes/admin/class-Emsfb-admin.php:3584`)، پس دیوار پرداخت نیست. طبق D-11: خواندن برای Free Plus و Pro، نوشتن و حذف فقط برای Pro معتبر.
3. **حمل‌ونقل**: فقط از طریق **WordPress Abilities API** بومی (در صورت وجود روی نسخهٔ وردپرس سایت) عرضه می‌شود. ساخت یک MCP server adapter جدا در این فاز مطرح نیست.
4. **Mailchimp**: خارج از محدودهٔ این بسته. افزونهٔ Mailchimp امروز در EFB وجود ندارد (کار کاملاً از صفر در سمت EFB) و مالک گفته لازم نیست ساخته شود. اگر تیم EFB بعداً چنین افزونه‌ای ساخت، این بند دوباره باز می‌شود؛ تا آن زمان abilityهای تنظیمات پرداخت فقط Stripe و PayPal را پوشش می‌دهند.

## تصمیم معماری

قابلیت‌های تازه، توسعهٔ همان زیرساخت `ConnectorInterface` / `ConnectorRegistry` / `ActionExecutor` / `ActionStore` هستند که امروز فقط یک capability دارند: `form.create` در `EasyFormBuilderConnector::get_capabilities()` (خط ۵۸ و ۵۹) و در `NativeFormConnector::get_capabilities()`. `ActionExecutor::approve_and_execute()` (خط ۵۹) این رشته را hardcode کرده است. این بسته آن مسیر پایدار و تست‌شدهٔ فعلی را **دست‌نخورده نگه می‌دارد** و یک مسیر موازی و عمومی کنار آن اضافه می‌کند، نه یک بازنویسی، چون تغییر مسیر hardened فعلی بدون نیاز واقعی ریسک regression روی چیزی است که از قبل PHPUnit سبز دارد.

قواعد غیرقابل‌مذاکره (مستقیم از `CLAUDE.md` ریشهٔ workspace):

- **AI proposes, a human approves, the backend executes.** هر ability با سطح write یا destructive، در همان تماس اول چیزی را تغییر نمی‌دهد؛ فقط یک پیشنهاد (diff) در `ActionStore` می‌سازد. اجرای واقعی فقط از طریق تأیید صریح مدیر در wp-admin (همان الگوی `ActionExecutor::approve_and_execute()`) رخ می‌دهد. abilityهای read-only مستقیماً نتیجه برمی‌گردانند، چون ریسک تغییر وجود ندارد.
- **Secrets never reach the DOM, REST responses, logs or output.** هیچ ability هرگز مقدار خام `stripeSKey`، `paypalSKey`/`client_secret`، یا معادل SMTP را برنمی‌گرداند یا نمی‌نویسد — نه در خواندن، نه در نوشتن.
- بدون تغییر در `KeyVault::KEY_DERIVATION_SALT`، namespace، REST namespace یا هیچ شناسهٔ منجمد دیگر.

### زیرساخت جدید (S4.1.0)

- **وابستگی:** T26 (همگام‌سازی کاتالوگ با EFB 4.2.0، `efb_version_target: "4.2.x"`) باید تکمیل باشد. در ۲۰۲۶-۰۹-۱۵ تکمیل شد.
- `AbilityTier` (ثابت‌های `read`/`write`/`destructive`) و یک نگاشت استاتیک ability→tier در یک کلاس تازه (`includes/Efb/EfbAbilityRegistry.php` یا مشابه)، بدون تغییر امضای `ConnectorInterface` فعلی.
- **گیت پلن در `includes/Efb/EfbLicense.php` موجود**، نه کلاس تازه. `EfbLicense::status()` همین حالا `emsfb_pro` را می‌خواند و `has_pro_features()` برای هر 8 مقدار آزموده دقیقاً همان خروجی `allows_agent_abilities()` پیشنهادی نسخهٔ قبلی را دارد. گسترش پیشنهادی:
  - `allows_agent_read(): bool` — `has_pro_features()` (مقدار 1 یا 3، با cast به `int` چون option از DB رشته برمی‌گردد) **و** `EasyFormBuilderConnector::is_available()`. شرط دوم لازم است چون غیرفعال‌سازی EFB (`plugin_deactivation_cleanup_efb`، `includes/class-Emsfb.php:1186`) option را حذف نمی‌کند.
  - `allows_agent_write(): bool` — `is_available()` **و** `is_efb_pro()` خود EFB (`includes/functions.php:6855`). مرجع Pro در خود EFB همین تابع است که کد فعال‌سازی را با دامنه می‌سنجد، نه option خام (تست EFB `tests/test-addon-install-plan-gate.php:112`). این یعنی قید قبلی «بدون فراخوانی هیچ کلاس EFB» برای نوشتن کنار گذاشته می‌شود. `is_efb_pro()` ممکن است option را 0 کند یا حذف کند (خطوط 6882 و 6904)؛ این رفتار خود EFB است و فقط هنگام تأیید یا اجرای proposal صدا زده می‌شود، نه در هر خواندن.
- یک اجراکنندهٔ عمومی تازه (مثلاً `includes/Execution/AbilityActionExecutor.php`) با همان الگوی claim/validate/execute/log که `ActionExecutor` دارد. رکورد `ActionStore` امروز فقط `plan`، `connector_id` و `execution_context` دارد؛ برای خواندن capability از رکورد pinned، یک فیلد `capability` **به‌صورت افزودنی** به `ActionStore::create` اضافه می‌شود و رکوردهای قدیمی بدون آن همچنان مسیر `form.create` را می‌روند. `ActionExecutor` فعلی دست‌نخورده می‌ماند.
- تنظیمات مدیر: کلیدهای درون option موجود `aisaautopilot_settings` از راه `Core\Settings`، هم‌خانوادهٔ `efb_copilot_enabled`، نه optionهای جدا: `efb_agent_enabled` (master، پیش‌فرض خاموش)، `efb_agent_allow_write`، `efb_agent_allow_destructive` و `efb_agent_allow_entry_content` (پیش‌فرض خاموش حتی وقتی master روشن است).
- مجوز هر ability: کلید تنظیم مربوط، `current_user_can('manage_options')` و گیت پلن همان سطح (`allows_agent_read()` یا `allows_agent_write()`). تأیید proposal همان دو شرط `ActionExecutor::approve_and_execute()` را دارد: `manage_options` و claim رکورد توسط همان کاربر، به‌علاوهٔ گیت پلن نوشتن.
- Audit log: از `Logger` موجود (`includes/Logging/Logger.php`، همان که `ActionExecutor` مصرف می‌کند) با event نام‌گذاری `efb_ability.*`. نوشتن آن از T28 (۲۰۲۶-۰۹-۱۵) زیر قفل پایگاه داده اتمی است.
- Rate limit: `RateLimiter::allow()` (`includes/Security/RateLimiter.php`) امروز فقط در `ChatWidgetController`، `PublicChatController` و `VoiceController` به کار می‌رود و در `/efb/*` نیست. استفاده در abilityها کاربرد تازه با scope جدا به ازای هر ability است، نه تکرار الگوی `/efb/chat`.

گیت پذیرش S4.1.0: PHPUnit برای `EfbLicense` (هر مقدار option، مقدار غایب، رشتهٔ `"1"`، EFB غیرفعال با option باقی‌مانده ← false، و Pro بدون `is_efb_pro()` معتبر ← نوشتن false)، برای نگاشت ability→tier، و برای اینکه `AbilityActionExecutor` بدون تأیید مدیر (تنظیم خاموش، کاربر بدون `manage_options`، یا گیت پلن بسته) هیچ چیزی اجرا نمی‌کند. PHPCS/PHPStan متمرکز روی فایل‌های تازه.

## نگاشت قابلیت اعلام‌شدهٔ Gravity Forms به وضعیت این پروژه

نام abilityها قالب WordPress Abilities API را دارند: namespace و فقط حروف کوچک، رقم، `-` و `/` (`wp-includes/abilities-api.php:237`). نام‌های نقطه‌دار نسخهٔ قبلی (`efb.forms.list`) نامعتبر بودند.

| قابلیت GF | وضعیت پیش از S4.1 | ability پیشنهادی | سطح | وضعیت |
|---|---|---|---|---|
| ساخت فرم | موجود و کار می‌کند (`EasyFormBuilderConnector::execute_action`, خط ۸۴) اما فقط از مسیر چت داخلی خودمان قابل صداکردن است، نه از یک ability عمومی قابل کشف | `aisaautopilot/efb-forms-create` | write (proposal) | فقط re-expose، صفر منطق تازه |
| فهرست/خواندن فرم | به‌صورت ability مستقل وجود ندارد (مسیر «customize» فقط روی فرم *باز* در مرورگر کار می‌کند) | `aisaautopilot/efb-forms-list`، `aisaautopilot/efb-forms-read-structure` | read | کاملاً تازه |
| ویرایش فرم (فیلد/تنظیم) | نسخهٔ محدودش برای فرم باز از طریق DOM bridge موجود است (`efb-copilot.js` + کاتالوگ) | `aisaautopilot/efb-forms-update-field` (فراخوان همان کاتالوگ، نه مسیر تازه) | write (proposal) | wrapper روی چیز موجود |
| آمار فرم | به‌صورت ability وجود ندارد | `aisaautopilot/efb-forms-stats` | read | تازه |
| ساخت entry آزمایشی | وجود ندارد | — | — | **کنار گذاشته (D-12)** تا EFB فلگ واقعی «آزمایشی» بیاورد |
| conditional logic | صریحاً «not AI-writable» (`EFB-INTEGRATION-ROADMAP.md` سطر «conditional-logic rules») | `aisaautopilot/efb-logic-explain` فقط | read | فقط explain؛ write/generate **مسدود بیرونی** |
| notifications/confirmations | تنظیمات غیرشرطی (متن confirmation) از قبل در کاتالوگ AI-writable است؛ نسخهٔ شرطی جزو conditional logic مستثنا است | `aisaautopilot/efb-notifications-get`، `-update`، `aisaautopilot/efb-confirmations-get`، `-update` (فقط غیرشرطی) | read/write (proposal) | تازه، محدود به غیرشرطی |
| جست‌وجو/خلاصه/به‌روزرسانی entry | صریحاً «not AI-writable» (`EFB-INTEGRATION-ROADMAP.md` سطر «submission data») | `aisaautopilot/efb-entries-search`، `-get`، `-reply`، `-update-status`، `-delete` | read/write/destructive | تازه |
| تنظیمات PayPal/Stripe | صریحاً «not AI-writable» (سطر «payment/pricing configuration») | `aisaautopilot/efb-payment-settings-get` فقط | read | تازه، فقط خواندن، بدون secret |
| تنظیمات Mailchimp | افزونه‌اش در EFB وجود ندارد | — | — | **خارج از محدوده** (تصمیم ثبت‌شده، بالا) |

## قابلیت‌هایی که این بسته عمداً نمی‌سازد

- نوشتن/تولید conditional logic. سند خودِ EFB (`docs/EFB-AI-FORM-BUILDER-EXECUTION-PLAN.en.md`، فاز -۱) یک باگ واقعی و تأییدشده در آن پروژه ثبت کرده: ارزیاب دوگانهٔ منطق شرطی (یکی برای فیلد، یکی دستی‌کپی‌شده برای notification/confirmation/webhook در `class-Emsfb-public.php`) که هنگام خاموش‌شدن addon منطق شرطی هم متوقف نمی‌شود. این یک پیش‌نیاز **بیرونی** است که تیم EFB باید در ریپازیتوری خودش رفع کند؛ AISAAutoPilot نه مالک آن کد است و نه اجازهٔ تغییرش را دارد. تا آن زمان، `aisaautopilot/efb-logic-explain` (فقط توضیح زبان‌طبیعی rule موجود، بدون نوشتن) امن است چون هیچ‌چیز اجرا نمی‌کند؛ هر ability نوشتنی روی منطق شرطی به تصمیم تازهٔ مالک پس از تأیید رفع آن باگ در EFB نیاز دارد.
- ساخت entry آزمایشی (D-12): EFB ستون یا فلگی برای entry آزمایشی ندارد و `Email_Monitor::get_form_activity_stats` هر ردیف `emsfb_msg_` را واقعی می‌شمارد. entry ساختگی در آمار واقعی با قاعدهٔ «بدون دادهٔ جعلی» تعارض دارد. اگر EFB فلگ واقعی بیاورد، با تصمیم تازه باز می‌شود.
- نوشتن secret پرداخت (کلید Stripe/PayPal) یا فعال/غیرفعال‌کردن addon پرداخت. این‌ها همیشه فقط از خود رابط کاربری EFB قابل تغییرند.
- هر چیز مربوط به Mailchimp (بالا).
- تولید MCP server adapter مستقل (بالا).
- تغییر `ActionExecutor` فعلی یا مسیر `form.create` موجود.

## قرارداد گیت پلن EFB — تأیید لازم پیش از S4.1.1

وارسی ۲۰۲۶-۰۹-۱۵ (probe فقط‌خواندنی، شاهد `test-artifacts/efb-agent-abilities/20260915-external-review/license-gate-probe.json`):

- سایت محلی واقعاً **Pro** است: `emsfb_pro` برابر رشتهٔ `"1"` با autoload off و کد فعال‌سازی موجود. `get_option('emsfb_pro', 2)` بدون فراخوانی کلاس EFB خوانده می‌شود، ولی رشته برمی‌گرداند و cast به `int` لازم است.
- Free (2)، Free Plus (3)، onboarding (0)، -1، مقدار غایب، 7 و `abc` فقط درون همان پردازش با فیلتر `pre_option_emsfb_pro` شبیه‌سازی شدند، با صفر query نوشتنی. خروجی `EfbLicense` و `get_license_type()` خود EFB برای گیت یکسان بود: فقط 1 و 3 true. تنها تفاوت برچسب مقدار 0 است (`pro_pending` در EFB، `free` در `EfbLicense`) که در هر دو false است.
- «1 → true» در این probe فقط خواندن خام option است، نه اعتبار لایسنس. طبق D-11 نوشتن و حذف با `is_efb_pro()` سنجیده می‌شوند.
- **باز:** تأیید دستی Free و Free Plus روی نصب واقعی EFB انجام نشده، چون پلن و وضعیت لایسنس مالک را روی سایت محلی عوض می‌کند. پیش از بستن S4.1.0 یا مالک پلن را موقتاً عوض می‌کند، یا روی نصب دوم (مانند D-04) آزموده می‌شود. خروجی `allows_agent_read()` و `allows_agent_write()` در هر حالت با جدول قرارداد، مانند جدول DOM در T00، ثبت می‌شود.

## ریزتسک‌ها و گیت‌ها

| ID | کار | وابسته به | گیت پذیرش |
|---|---|---|---|
| S4.1.0 | زیرساخت: `AbilityTier`، گسترش `EfbLicense`، `AbilityActionExecutor` عمومی و فیلد افزودنی `capability` در `ActionStore`، کلیدهای `Settings`، audit log، rate limit | T26 | PHPUnit طبق بالا؛ تأیید سه‌حالتهٔ گیت پلن روی EFB واقعی؛ PHPCS/PHPStan متمرکز |
| S4.1.1 | Read: `aisaautopilot/efb-forms-list`، `-forms-read-structure`، `-forms-stats` — خواندن مستقیم `$wpdb` روی `emsfb_form`، `emsfb_msg_` و `emsfb_stts_` به همان سبک `EasyFormBuilderConnector::has_required_schema()` (خط ۱۸۹؛ ستون‌های لازم را چک می‌کند، فرض نمی‌کند)؛ `read-structure` هرگز `form_structer` خام را برنمی‌گرداند و از decompose سبک (فقط metadata: نوع/تعداد فیلد/step/عنوان) عبور می‌کند تا secret احتمالی داخل تنظیمات فرم هم فیلتر شود | S4.1.0 | PHPUnit: فرم موجود/ناموجود، گیت خواندن بسته (رد با پیام واضح، نه 500)، فیلتر شدن کلید پرداخت از خروجی `read-structure` |
| S4.1.2 | Write (proposal): `aisaautopilot/efb-forms-create` — صرفاً ثبت همین ability در رجیستری تازه، فراخوانی مستقیم `EasyFormBuilderConnector` موجود بدون تغییر منطق آن | S4.1.0 | PHPUnit: مسیر approve/execute موجود برای `form.create` دقیقاً همان نتیجهٔ قبلی را می‌دهد (رگرسیون صفر) |
| S4.1.3 | ~~Write (proposal، sandboxed): ساخت entry آزمایشی~~ | — | **کنار گذاشته طبق D-12** |
| S4.1.4 | Read: `aisaautopilot/efb-entries-search` (فقط metadata از `emsfb_msg_`: `track`، `date`، `status`، `form_id` — **هرگز** `content` مگر کلید `efb_agent_allow_entry_content` روشن باشد)، `-entries-get` (تک‌مورد، پشت همان کلید، با masking الگوهای ایمیل/تلفن پیش از خروج از سرور؛ از `Redactor::text()` و `Redactor::array()` موجود اگر شکلش اجازه دهد، وگرنه تابع masking کوچک محلی) | S4.1.0، S4.1.1 | PHPUnit: کلید خاموش ← هرگز `content` در خروجی؛ کلید روشن ← masking قابل‌تأیید روی fixture حاوی ایمیل/تلفن نمونه |
| S4.1.5 | Write/Destructive (proposal): `aisaautopilot/efb-entries-reply`، `-entries-update-status`، `-entries-delete`/bulk — همه از مسیر `AbilityActionExecutor` (S4.1.0) و گیت نوشتن Pro؛ delete فقط وقتی `efb_agent_allow_destructive` روشن است | S4.1.0، S4.1.4 | PHPUnit: delete بدون کلید destructive رد می‌شود؛ proposal بدون approve مدیر چیزی حذف نمی‌کند؛ Free Plus ← رد |
| S4.1.6 | Read/Write (proposal): `aisaautopilot/efb-notifications-get`/`-update`، `aisaautopilot/efb-confirmations-get`/`-update` — **فقط کلیدهای غیرشرطی** (متن/گیرندهٔ ساده)؛ هر کلید مربوط به `notification_rules`/`confirmation_rules` شرطی صریحاً رد می‌شود، نه نادیده گرفته | S4.1.0 | PHPUnit: تلاش برای نوشتن کلید شرطی ← خطای صریح، نه silent drop |
| S4.1.7 | Read: `aisaautopilot/efb-logic-explain` — فقط خواندن rule موجود از `form_structer` و برگرداندن توضیح ساختاریافته (نگاشت source/operator/action به جمله)؛ **بدون** هیچ ability نوشتنی روی منطق شرطی | S4.1.0، S4.1.1 | PHPUnit: خروجی روی چند fixture rule واقعی (AND/OR، منابع مختلف)؛ تست صریح که هیچ مسیر نوشتنی برای این ability ثبت نشده |
| S4.1.8 | Read: `aisaautopilot/efb-payment-settings-get` — خواندن `emsfb_setting` decoded (مستقیم از پایگاه داده، نه فراخوانی کلاس EFB) با **allow-list صریح خروجی**: کدام addon فعال است (`AdnSPF`/`AdnPAP`)، ارز، حالت dev/sandbox — و تست منفی صریح که هیچ کلید حساس (`stripeSKey`، `paypalSKey` و معادل‌ها) هرگز، حتی در حالت خطا، لاگ یا برگردانده نمی‌شود | S4.1.0 | PHPUnit با fixture شامل کلید secret ساختگی در تنظیمات: assert که secret در هیچ مسیر خروجی/لاگ ظاهر نمی‌شود |
| S4.1.9 | حمل‌ونقل WordPress Abilities API: adapter تازه (مثلاً `includes/Efb/EfbAbilitiesWpAdapter.php`). category با `wp_register_ability_category()` فقط در hook `wp_abilities_api_categories_init` و abilityها با `wp_register_ability()` فقط در hook `wp_abilities_api_init` ثبت می‌شوند (`wp-includes/abilities-api.php:291` و `:646`)؛ `category` برای هر ability الزامی است و سطح‌بندی read/destructive با `meta.annotations` (`readonly`، `destructive`) هم اعلام می‌شود. feature-detect: اگر تابع وجود نداشت، ثبت نمی‌شود و در `get_health()`-معادل اعلام می‌شود، نه fatal. `permission_callback` هر ability سه شرط بخش زیرساخت را دارد. صفحهٔ تنظیمات ادمین برای کلیدهای S4.1.0 | S4.1.1، S4.1.2، S4.1.4 تا S4.1.8 | تست integration: با Abilities API غیرموجود هیچ ability ثبت نمی‌شود و صفحهٔ تنظیمات پیام واضح می‌دهد؛ با موجود بودن، هر ability با permission ناکافی رد می‌شود؛ browser check کوچک برای صفحهٔ تنظیمات تازه (کلیدها ذخیره و بازخوانی می‌شوند) |
| S4.1.10 | regression کامل و گزارش اجرا | همهٔ موارد بالا | `run-gates.ps1 -Tier full` سبز؛ `docs/EFB_AGENT_ABILITIES_S4_1_EXECUTION_REPORT_FA.md` با الگوی execution reportهای موجود؛ به‌روزرسانی `EFB-INTEGRATION-ROADMAP.md` |

## اصلاح‌های وارسی (۲۰۲۶-۰۹-۱۵)

نسخهٔ نخست این سند را نشست بازبینی خارجی نوشت (در commit `459bfd8`). ایجنت ادعاهای آن را روی source وارسی کرد و این‌ها اصلاح شدند؛ جزئیات و file:line در ژورنال رودمپ آمده است:

1. جدول پیام‌های EFB `emsfb_msg_` است، نه `emsfb_message` (`SHOW TABLES` روی سایت محلی). کاتالوگ افزونه هم در T26 اصلاح شد.
2. نام abilityها با نقطه در Abilities API نامعتبر بود؛ قالب `aisaautopilot/efb-...`، `category` الزامی و hookهای ثبت افزوده شدند.
3. `EfbLicenseGate` تازه تکراری بود؛ `EfbLicense` موجود گسترش می‌یابد.
4. `RateLimiter` در `/efb/*` به کار نمی‌رود؛ عبارت «مثل الگوی `/efb/chat`» حذف شد.
5. `ActionStore` فیلد capability ندارد؛ افزودن آن افزودنی تعریف شد.
6. optionهای جدای `aisaautopilot_efb_agent_*` به کلیدهای `Settings` تبدیل شدند.
7. `emsfb_pro` پس از غیرفعال‌سازی EFB باقی می‌ماند؛ گیت با `is_available()` ترکیب شد.
8. برای Pro مرجع EFB `is_efb_pro()` است و Free Plus رایگان و خودانتخابی است؛ گیت طبق D-11 دو سطحی شد.
9. عنوان «سه تصمیم» با چهار بند اصلاح شد و بند گیت به D-11 ارجاع داده شد.
10. شرط‌های تأیید `ActionExecutor` دقیق شد: `manage_options` و claim همان کاربر.
11. `NativeFormConnector` هم `form.create` برمی‌گرداند و در بخش معماری افزوده شد.
12. S4.1.3 طبق D-12 کنار گذاشته شد و وابستگی S4.1.0 به T26 ثبت شد.

## برآورد

با فرض ورود این بسته به صف پس از انتشار ۱.۱.۰ (D-10 و D-07)، ۹ ریزتسک باقی (بدون S4.1.3) در مقیاس بسته‌های S3.x فعلی (هرکدام با گیت PHPUnit/PHPCS/PHPStan خودش، بدون Playwright سنگین جز S4.1.9) در حدود ۳ تا ۵ روز کاری تخمین زده می‌شود؛ مثل بسته‌های قبلی، زمان تقویمی جای شاهد گیت را نمی‌گیرد.
