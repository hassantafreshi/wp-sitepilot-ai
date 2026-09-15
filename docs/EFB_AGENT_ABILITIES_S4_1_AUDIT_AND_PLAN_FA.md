# ممیزی و برنامه اجرای S4.1 — EFB Agent Abilities

تاریخ ممیزی: ۲۰۲۶-۰۹-۱۵
مرجع: اعلام رسمی Gravity Forms (۲۰۲۶-۰۸-۲۶) دربارهٔ قابلیت agent-callable «AI Form Builder» که در **همهٔ** لایسنس‌های Gravity Forms (رایگان تا بالاترین سطح) عرضه شده: ساخت/ویرایش فرم، تنظیم conditional logic/اعلان/confirmation، جست‌وجو/خلاصه/به‌روزرسانی ورودی‌ها، مدیریت تنظیمات PayPal/Stripe/Mailchimp، با سطوح دسترسی جداگانهٔ read-only/write/destructive.
نگاشت مقابل: `docs/EFB-INTEGRATION-ROADMAP.md` بخش «Recorded but intentionally not AI-writable»، `includes/Efb/efb-capability-catalog.json`، و بستهٔ فعلی EFB Copilot (`docs/EFB_COPILOT_S3_2_AUDIT_AND_PLAN_FA.md`).
سطح واقعی: کانکتور/اسکیل‌های EFB در `includes/Connectors/EasyFormBuilderConnector.php`، `includes/Execution/ActionExecutor.php`، `includes/Efb/*` — این بسته گسترش همان زیرساخت است، نه سیستم موازی.

این بسته خارج از صف اجرای فعلی T00–T27 است و در بک‌لاگ فاز ۵ ثبت شده (ردیف B-09 در `PRODUCT_COMPLETION_ROADMAP_FA.md`)؛ اولویت‌بندی نسبت به انتشار ۱.۱.۰ یک تصمیم مالک است (D-10).

## زمینه و تصمیم‌های تأیید‌شدهٔ مالک (۲۰۲۶-۰۹-۱۵)

سه تصمیم زیر در جلسهٔ بررسی همین روز گرفته شده و در این سند به‌عنوان قطعی در نظر گرفته می‌شود، نه باز:

1. **محل ساخت**: این قابلیت در همین افزونهٔ AI Site Assistant AutoPilot («ass») ساخته می‌شود، **نه** با تغییر در سورس Easy Form Builder. EFB همچنان فقط منبع مرجع فقط‌خواندنی است (طبق `CLAUDE.md` ریشهٔ workspace).
2. **گیت لایسنس**: کل این بسته (هر سطحی — read، write، destructive) فقط روی سایت‌هایی باز می‌شود که EFB نصب‌شده روی آن‌ها **Free Plus یا Pro** باشد؛ روی EFB Free باز نمی‌شود.
3. **حمل‌ونقل**: فقط از طریق **WordPress Abilities API** بومی (در صورت وجود روی نسخهٔ وردپرس سایت) عرضه می‌شود. ساخت یک MCP server adapter جدا در این فاز مطرح نیست.
4. **Mailchimp**: خارج از محدودهٔ این بسته. افزونهٔ Mailchimp امروز در EFB وجود ندارد (کار کاملاً از صفر در سمت EFB) و مالک گفته لازم نیست ساخته شود. اگر تیم EFB بعداً چنین افزونه‌ای ساخت، این بند دوباره باز می‌شود؛ تا آن زمان `efb.payment_settings.*` فقط Stripe و PayPal را پوشش می‌دهد.

## تصمیم معماری

قابلیت‌های تازه، توسعهٔ همان زیرساخت `ConnectorInterface` / `ConnectorRegistry` / `ActionExecutor` / `ActionStore` هستند که امروز فقط یک capability دارند (`form.create`، در `EasyFormBuilderConnector::get_capabilities()`) و `ActionExecutor::approve_and_execute()` (خط ۵۹) این رشته را hardcode کرده است. این بسته آن مسیر پایدار و تست‌شدهٔ فعلی را **دست‌نخورده نگه می‌دارد** و یک مسیر موازی و عمومی (generic) کنار آن اضافه می‌کند — نه یک بازنویسی — چون تغییر مسیر hardened فعلی بدون نیاز واقعی، ریسک regression روی چیزی است که از قبل PHPUnit سبز دارد.

قواعد غیرقابل‌مذاکره (مستقیم از `CLAUDE.md` ریشهٔ workspace):

- **AI proposes, a human approves, the backend executes.** هر ability با سطح write یا destructive، در همان تماس اول چیزی را تغییر نمی‌دهد؛ فقط یک پیشنهاد (diff) در `ActionStore` می‌سازد. اجرای واقعی فقط از طریق تأیید صریح مدیر در wp-admin (همان الگوی `ActionExecutor::approve_and_execute()`) رخ می‌دهد. ability های read-only مستقیماً نتیجه برمی‌گردانند، چون ریسک تغییر وجود ندارد.
- **Secrets never reach the DOM, REST responses, logs or output.** هیچ ability هرگز مقدار خام `stripeSKey`، `paypalSKey`/`client_secret`، یا معادل SMTP را برنمی‌گرداند یا نمی‌نویسد — نه در خواندن، نه در نوشتن.
- بدون تغییر در `KeyVault::KEY_DERIVATION_SALT`، namespace، REST namespace یا هیچ شناسهٔ منجمد دیگر.

### زیرساخت جدید (S4.1.0)

- `AbilityTier` (ثابت‌های `read`/`write`/`destructive`) و یک نگاشت استاتیک ability→tier در یک کلاس تازه (`includes/Efb/EfbAbilityRegistry.php` یا مشابه) — بدون تغییر امضای `ConnectorInterface` فعلی.
- `EfbLicenseGate::tier(): string` (فایل تازه، مثلاً `includes/Efb/EfbLicenseGate.php`) — **فقط یک خواندن read-only از `get_option('emsfb_pro', 2)`**، بدون فراخوانی هیچ کلاس EFB. تفسیر مقدار طبق معنای مستندشدهٔ `Email_Monitor::get_license_type()` در سورس EFB (فقط ارجاع، نه وابستگی کد): `1` = pro، `3` = free_plus، `0` = pro_pending، هر مقدار دیگر = free. `EfbLicenseGate::allows_agent_abilities(): bool` فقط برای `pro` و `free_plus` true برمی‌گرداند؛ `pro_pending` **false** است (نکتهٔ کوچک قابل تغییر بعدی، نه یک تصمیم رسمی: اگر مالک بعداً خواست pro_pending را هم مجاز کند، فقط همین یک enum عوض می‌شود).
- یک اجراکنندهٔ عمومی تازه (مثلاً `includes/Execution/AbilityActionExecutor.php`) با همان الگوی claim/validate/execute/log که `ActionExecutor` دارد، اما به‌جای hardcode کردن `form.create`، capability را از خود رکورد pinned شده در `ActionStore` می‌خواند. `ActionExecutor` فعلی دست‌نخورده می‌ماند (فقط برای `form.create` همان‌طور که هست).
- تنظیمات مدیر (options جدید، هم‌خانواده با options موجود افزونه، نه یک سیستم تنظیمات جدید): `aisaautopilot_efb_agent_enabled` (master، پیش‌فرض خاموش)، `aisaautopilot_efb_agent_allow_write`، `aisaautopilot_efb_agent_allow_destructive`، `aisaautopilot_efb_agent_allow_entry_content` (این یکی طبق خودِ متن اعلام Gravity Forms/تصمیم اولیه، **پیش‌فرض خاموش** حتی وقتی master روشن است). هر ability، علاوه بر این optionها، `current_user_can('manage_options')` و `EfbLicenseGate::allows_agent_abilities()` را هم چک می‌کند — دقیقاً همان دو شرطی که `ActionExecutor::approve_and_execute()` امروز برای تأیید دارد، به‌علاوهٔ گیت پلن.
- Audit log: از `Logger` موجود (`includes/Logging/Logger.php`، همان که `ActionExecutor` مصرف می‌کند) با event نام‌گذاری `efb_ability.*` — سیستم لاگ تازه ساخته نمی‌شود.
- Rate limit: از `RateLimiter::allow()` موجود (`includes/Security/RateLimiter.php`) با scope تازه به ازای هر ability، مثل الگوی موجود در `/efb/chat`.

گیت پذیرش S4.1.0: PHPUnit برای `EfbLicenseGate` (چهار مقدار option و مقدار غایب/EFB غیرفعال → false)، برای نگاشت ability→tier، و برای اینکه `AbilityActionExecutor` بدون تأیید مدیر (option خاموش، کاربر بدون `manage_options`، یا EFB زیر Free Plus) هیچ چیزی اجرا نمی‌کند. PHPCS/PHPStan متمرکز روی فایل‌های تازه.

## نگاشت قابلیت اعلام‌شدهٔ Gravity Forms به وضعیت این پروژه

| قابلیت GF | وضعیت پیش از S4.1 | ability پیشنهادی | سطح | وضعیت |
|---|---|---|---|---|
| ساخت فرم | موجود و کار می‌کند (`EasyFormBuilderConnector::execute_action`, خط ۸۴) اما فقط از مسیر چت داخلی خودمان قابل صداکردن است، نه از یک ability عمومی قابل کشف | `efb.forms.create` | write (proposal) | فقط re-expose، صفر منطق تازه |
| فهرست/خواندن فرم | وجود ندارد به‌صورت ability مستقل (مسیر «customize» فقط روی فرم *باز* در مرورگر کار می‌کند، نه از بیرون) | `efb.forms.list`, `efb.forms.read_structure` | read | کاملاً تازه |
| ویرایش فرم (فیلد/تنظیم) | نسخهٔ محدودش برای فرم باز از طریق DOM bridge موجود است (`efb-copilot.js` + کاتالوگ) | `efb.forms.update_field` (فراخوان همان کاتالوگ، نه مسیر تازه) | write (proposal) | wrapper روی چیز موجود |
| آمار فرم | وجود ندارد به‌صورت ability | `efb.forms.stats` | read | تازه |
| ساخت entry آزمایشی | وجود ندارد | `efb.entries.generate_test` | write (proposal، sandboxed) | تازه، با محدودیت مستند (پایین) |
| conditional logic | صریحاً «not AI-writable» (`EFB-INTEGRATION-ROADMAP.md` سطر «conditional-logic rules») | `efb.logic.explain` فقط | read | فقط explain؛ write/generate **مسدود بیرونی** |
| notifications/confirmations | تنظیمات غیرشرطی (متن confirmation) از قبل در کاتالوگ AI-writable است؛ نسخهٔ شرطی جزو conditional logic مستثنا است | `efb.notifications.get/update`, `efb.confirmations.get/update` (فقط غیرشرطی) | read/write (proposal) | تازه، محدود به غیرشرطی |
| جست‌وجو/خلاصه/به‌روزرسانی entry | صریحاً «not AI-writable» (`EFB-INTEGRATION-ROADMAP.md` سطر «submission data») | `efb.entries.search`, `efb.entries.get`, `efb.entries.reply`, `efb.entries.update_status`, `efb.entries.delete` | read/write/destructive | تازه |
| تنظیمات PayPal/Stripe | صریحاً «not AI-writable» (سطر «payment/pricing configuration») | `efb.payment_settings.get` فقط | read | تازه، فقط خواندن، بدون secret |
| تنظیمات Mailchimp | افزونه‌اش در EFB وجود ندارد | — | — | **خارج از محدوده** (تصمیم مالک، بالا) |

## قابلیت‌هایی که این بسته عمداً نمی‌سازد

- نوشتن/تولید conditional logic. سند خودِ EFB (`docs/EFB-AI-FORM-BUILDER-EXECUTION-PLAN.en.md`، فاز -۱) یک باگ واقعی و تأییدشده در آن پروژه ثبت کرده: ارزیاب دوگانهٔ منطق شرطی (یکی برای فیلد، یکی دستی‌کپی‌شده برای notification/confirmation/webhook در `class-Emsfb-public.php`) که هنگام خاموش‌شدن addon منطق شرطی هم متوقف نمی‌شود. این یک پیش‌نیاز **بیرونی** است که تیم EFB باید در ریپازیتوری خودش رفع کند؛ AISAAutoPilot نه مالک آن کد است و نه اجازهٔ تغییرش را دارد. تا آن زمان، `efb.logic.explain` (فقط توضیح زبان‌طبیعی rule موجود، بدون نوشتن) امن است چون هیچ‌چیز اجرا نمی‌کند؛ هر ability نوشتنی روی منطق شرطی در این بسته وجود ندارد و اضافه‌شدنش به یک تصمیم مالک تازه بعد از تأیید رفع آن باگ در EFB نیاز دارد.
- نوشتن secret پرداخت (کلید Stripe/PayPal) یا فعال/غیرفعال‌کردن addon پرداخت. این‌ها همیشه فقط از خود رابط کاربری EFB قابل تغییرند.
- هر چیز مربوط به Mailchimp (بالا).
- تولید MCP server adapter مستقل (تصمیم مالک، بالا).
- تغییر `ActionExecutor` فعلی یا مسیر `form.create` موجود.

## قرارداد گیت پلن EFB — تأیید لازم پیش از S4.1.1

قبل از شروع کار نوشتنی، `EfbLicenseGate` باید روی یک نصب واقعی EFB در هر سه حالت Free / Free Plus / Pro دستی تأیید شود (همان الگوی جدول قرارداد DOM در T00): مقدار خام `get_option('emsfb_pro')` در هر حالت، و خروجی `tier()`/`allows_agent_abilities()`. این تأیید بخشی از گیت S4.1.0 است، نه یک فرض نوشته‌شده.

## ریزتسک‌ها و گیت‌ها

| ID | کار | وابسته به | گیت پذیرش |
|---|---|---|---|
| S4.1.0 | زیرساخت: `AbilityTier`، `EfbLicenseGate`، `AbilityActionExecutor` عمومی، optionهای toggle، audit log، rate limit | — | PHPUnit طبق بالا؛ تأیید دستی سه‌حالتهٔ گیت پلن روی EFB واقعی؛ PHPCS/PHPStan متمرکز |
| S4.1.1 | Read: `efb.forms.list`, `efb.forms.read_structure`, `efb.forms.stats` — خواندن مستقیم `$wpdb` روی `emsfb_form`/`emsfb_message`/`emsfb_stts_` به همان سبک `EasyFormBuilderConnector::has_required_schema()` (ستون‌های لازم را چک می‌کند، فرض نمی‌کند)؛ `read_structure` باید مقدار خام `form_structer` را هرگز مستقیم برنگرداند، بلکه از یک decompose سبک (فقط metadata: نوع/تعداد فیلد/step/عنوان، نه کل payload خام) عبور کند تا secret احتمالی داخل تنظیمات فرم (کلید پرداخت ذخیره‌شده روی فرم پرداختی) هم فیلتر شود | S4.1.0 | PHPUnit: فرم موجود/ناموجود، EFB زیر Free Plus (رد با پیام واضح، نه 500)، فیلتر شدن کلید پرداخت از خروجی `read_structure` |
| S4.1.2 | Write (proposal): `efb.forms.create` — صرفاً ثبت همین ability در رجیستری تازه، فراخوانی مستقیم `EasyFormBuilderConnector` موجود بدون تغییر منطق آن | S4.1.0 | PHPUnit: مسیر approve/execute موجود برای `form.create` دقیقاً همان نتیجهٔ قبلی را می‌دهد (رگرسیون صفر) |
| S4.1.3 | Write (proposal، sandboxed): `efb.entries.generate_test` — درج یک ردیف در `emsfb_message` با محتوای نمونه و یک برچسب قابل‌تشخیص در خودِ `content` (چون EFB هیچ ستون/فلگ سطح دیتابیس برای «entry آزمایشی» ندارد و افزودنش نیاز به تغییر سورس EFB دارد که خارج از محدودهٔ این بسته است) — **محدودیت مستند**: این برچسب را فقط ادمین‌های آگاه می‌بینند؛ آمار/شمارندهٔ EFB (`Email_Monitor::get_form_activity_stats`) این ردیف را واقعی حساب می‌کند. اگر این محدودیت قابل‌قبول نیست، ساخت یک فلگ واقعی نیاز به درخواست تغییر جدا به تیم EFB دارد (خارج از این بسته) | S4.1.0، S4.1.1 | PHPUnit: ردیف درج‌شده قابل‌تفکیک با query است؛ توضیح محدودیت در گزارش اجرا؛ تأیید مالک روی همین محدودیت پیش از بستن S4.1 |
| S4.1.4 | Read: `efb.entries.search` (فقط metadata: `track`, `date`, `status`, `form_id` — **هرگز** `content` مگر option جدا `aisaautopilot_efb_agent_allow_entry_content` روشن باشد)، `efb.entries.get`/`summarize` (تک‌مورد، پشت همان option، با masking ساده روی الگوهای ایمیل/تلفن قبل از خروج از سرور، با استفاده از `Redactor` موجود در `includes/Security/Redactor.php` اگر شکلش اجازه می‌دهد، وگرنه یک تابع masking کوچک محلی) | S4.1.0، S4.1.1 | PHPUnit: option خاموش → هرگز `content` در خروجی؛ option روشن → masking قابل‌تأیید روی fixture حاوی ایمیل/تلفن نمونه |
| S4.1.5 | Write/Destructive (proposal): `efb.entries.reply`, `efb.entries.update_status`, `efb.entries.delete`/bulk — همه از مسیر `AbilityActionExecutor` (S4.1.0)، delete فقط وقتی `aisaautopilot_efb_agent_allow_destructive` روشن است | S4.1.0، S4.1.4 | PHPUnit: delete بدون option destructive رد می‌شود؛ proposal بدون approve مدیر چیزی حذف نمی‌کند |
| S4.1.6 | Read/Write (proposal): `efb.notifications.get/update`, `efb.confirmations.get/update` — **فقط کلیدهای غیرشرطی** (متن/گیرندهٔ ساده)؛ هر کلید مربوط به `notification_rules`/`confirmation_rules` شرطی صریحاً رد می‌شود، نه نادیده گرفته | S4.1.0 | PHPUnit: تلاش برای نوشتن کلید شرطی → خطای صریح، نه silent drop |
| S4.1.7 | Read: `efb.logic.explain` — فقط خواندن rule موجود از `form_structer` و برگرداندن توضیح ساختاریافته (نه NLG آزاد، فقط نگاشت source/operator/action به جمله)؛ **بدون** هیچ ability نوشتنی روی منطق شرطی | S4.1.0، S4.1.1 | PHPUnit: خروجی روی چند فیکسچر rule واقعی (AND/OR، منابع مختلف)؛ تست صریح که هیچ مسیر نوشتنی برای این ability ثبت نشده |
| S4.1.8 | Read: `efb.payment_settings.get` — خواندن `emsfb_setting` decoded (همان تابع/جدول که خود EFB دارد، فقط خواندهٔ مستقیم از دیتابیس، نه فراخوانی کلاس EFB) با **allow-list صریح خروجی**: کدام addon فعال است (`AdnSPF`/`AdnPAP`)، ارز، حالت dev/sandbox — و یک تست منفی صریح که هیچ کلید حساس (`stripeSKey`, `paypalSKey`, معادل‌ها) هرگز حتی در حالت خطا لاگ یا برگردانده نمی‌شود | S4.1.0 | PHPUnit با fixture شامل کلید secret واقعی در تنظیمات: assert که secret در هیچ مسیر خروجی/لاگ ظاهر نمی‌شود |
| S4.1.9 | حمل‌ونقل WordPress Abilities API: یک adapter تازه (مثلاً `includes/Efb/EfbAbilitiesWpAdapter.php`) که ability های S4.1.1–S4.1.8 را با `wp_register_ability()` (feature-detect: اگر تابع/کلاس وجود نداشت، ثبت نمی‌شود و در `get_health()`-معادل اعلام می‌شود، نه fatal) ثبت می‌کند؛ هر `permission_callback` سه‌گانه: option enable مربوط، `current_user_can('manage_options')`، `EfbLicenseGate::allows_agent_abilities()`. صفحهٔ تنظیمات ادمین برای toggleهای S4.1.0 | S4.1.1–S4.1.8 | تست integration: با Abilities API غیرموجود هیچ ability ثبت نمی‌شود و صفحهٔ تنظیمات پیام واضح می‌دهد؛ با موجود بودن، هر ability با permission ناکافی رد می‌شود؛ browser check کوچک برای صفحهٔ تنظیمات تازه (توگل‌ها ذخیره و بازخوانی می‌شوند) |
| S4.1.10 | regression کامل و گزارش اجرا | همهٔ موارد بالا | `run-gates.ps1 -Tier full` سبز؛ `docs/EFB_AGENT_ABILITIES_S4_1_EXECUTION_REPORT_FA.md` با الگوی execution report های موجود؛ به‌روزرسانی `EFB-INTEGRATION-ROADMAP.md` |

## تصمیم‌های مالک باز (به `PRODUCT_COMPLETION_ROADMAP_FA.md` هم افزوده شده)

- **D-10**: این بسته پیش از انتشار ۱.۱.۰ وارد صف اجرای فعال (T-جدول) شود یا در بک‌لاگ فاز ۵ بماند؟ پیشنهاد ایجنت: بک‌لاگ فاز ۵، چون انتشار فعلی نزدیک به T25 است و این بسته جزو تعریف «محصول آماده» فعلی نیست.
- **D-11**: آیا گیت این بسته باید علاوه بر Free Plus/Pro خودِ EFB، یک سطح پرداختی/اعتباری جدا هم در AI Site Assistant AutoPilot داشته باشد؟ پیشنهاد ایجنت: نه در نسخهٔ اول — فقط گیت EFB کافی است؛ بعد از دیدن دادهٔ مصرف واقعی تصمیم گرفته شود.
- **D-12**: محدودیت `efb.entries.generate_test` (بدون فلگ واقعی «آزمایشی» در دیتابیس EFB، پس در آمار EFB واقعی شمرده می‌شود) قابل‌قبول است یا این ability باید تا وقتی تیم EFB یک فلگ واقعی اضافه کند، ساخته نشود؟ پیشنهاد ایجنت: با محدودیت مستند بساز؛ حذف پیشنهادی برای ایمن‌سازی بیشتر یک درخواست جدا به تیم EFB است، نه کاری که اینجا حل شود.

## برآورد

با فرض تأیید D-10 (شروع بعد از انتشار ۱.۱.۰)، ۱۰ ریزتسک بالا در مقیاس بسته‌های S3.x فعلی (هرکدام با گیت PHPUnit/PHPCS/PHPStan خودش، بدون Playwright سنگین جز S4.1.9) در حدود ۳ تا ۵ روز کاری تخمین زده می‌شود؛ مثل بسته‌های قبلی، زمان تقویمی جای شاهد گیت را نمی‌گیرد.
