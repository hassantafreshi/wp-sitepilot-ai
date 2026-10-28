# فرم‌ها، Content Studio، Store Copilot، Health و Activity Log

## زنجیرهٔ «پیشنهاد، تأیید، اجرا»

همهٔ عملیاتی که چیزی در وردپرس می‌سازند از یک الگو پیروی می‌کنند:

1. **skill:** درخواست متنی را با provider به plan تبدیل می‌کند. نمونه‌ها: `CreateFormSkill`، `EfbFormSkill`، `ContentDraftSkill`، `WooCommerceProductSkill`.
2. **validator:** plan را با schema می‌سنجد و عادی‌سازی می‌کند. نمونه‌ها: `FormPlanValidator`، `ContentPlanValidator`، `ProductPlanValidator`. خروجی `ValidationResult` است و خروجی مدل هرگز مستقیم اجرا نمی‌شود.
3. **ذخیرهٔ پیشنهاد:** `ActionStore::create()` پیشنهاد را برای کاربر سازنده و 15 دقیقه ذخیره می‌کند. کاربر پیش‌نمایش تغییرناپذیر را می‌بیند.
4. **claim و اجرا:** با تأیید صریح، `ActionStore::claim()` پیشنهاد را به‌صورت اتمی و فقط برای سازنده claim می‌کند تا از تکرار جلوگیری شود. executor دوباره اعتبارسنجی و اجرا و لاگ می‌کند. نمونه‌ها: `ActionExecutor::approve_and_execute()` و `ContentDraftExecutor`.
5. **ثبت:** نتیجه در `Logger` ثبت می‌شود و مصرف در `UsageTracker`.

## فرم بومی

- **ذخیره:** `NativeFormConnector` plan تأییدشده را در post type `aisaautopilot_form` نگه می‌دارد. plan در meta تغییرناپذیر است و از آن رندر می‌شود.
- **نمایش:** `FormShortcode` فرم را با `[aisaautopilot_form id="123"]` نمایش می‌دهد.
- **ارسال:** به `admin-post.php` با action `aisaautopilot_form_submit` می‌رود. `NativeFormSubmission::validate()` پاسخ را با همان plan می‌سنجد و پاسخ فقط به `form_notification_email` یا ایمیل مدیریت فرستاده می‌شود. پاسخ در وردپرس ذخیره نمی‌شود.
- **صفحهٔ «Your forms»:** alias `aisaautopilot-forms` (`NativeFormsPage`) است. فرم‌ها، شمار فیلد، shortcode با دکمهٔ کپی و آزمون تحویل (`/forms/delivery/test`) را نشان می‌دهد.

## Form Studio (نام تازه؛ تا ۲۰۲۶-۰۹-۱۷ «Form Composer»)

D-23 (۲۰۲۶-۰۹-۱۷): این صفحه از یک deep link زیر Content Studio (`page=aisaautopilot-form-builder&mode=forms`) به صفحهٔ اصلی مستقل هشتم ترویج یافت (`page=aisaautopilot-form-studio`، ثابت `AdminMenu::SLUG_FORM_STUDIO`). رندرکنندهٔ آن همچنان همان کلاس `CopilotPage` است؛ متد عمومی `render_forms()` بدنهٔ compose/review/result را نگه می‌دارد و از هر دو مسیر (صفحهٔ تازه مستقیم، و لینک قدیمی برای سازگاری) صدا زده می‌شود. `ContentStudioNavigation::forms_url()` از این پس به مسیر تازه اشاره می‌کند. فقط برچسب نمایشی عوض شد؛ کلید تنظیمات `form_composer_enabled` و id ماشینی `form-composer` (در Integrations/Overview) عمداً دست‌نخورده ماندند.

- **جایگاه:** صفحهٔ مستقل Form Studio (پیش‌تر: نمای Forms در Content Studio). `content-studio.js` و مسیرهای `/forms/plan`، `/forms/execute` و `/forms/voice/transcribe` را به کار می‌گیرد.
- **انتخاب connector:** `ConnectorRegistry::resolve()` برای قابلیت `form.create` تصمیم می‌گیرد. اگر `prefer_efb` روشن و `EasyFormBuilderConnector::is_available()` صادق باشد، فرم در EFB ساخته می‌شود؛ در غیر این صورت connector بومی.
- **وضعیت اولیه:** `default_form_status` پیش‌فرض draft است. انتشار عملیات پرخطر است و دوباره تأیید می‌خواهد.
- **رابط (بازطراحی ۲۰۲۶-۱۰-۲۸، تأیید مالک):** `CopilotPage::render_forms()` یک فضای کار تمام‌ارتفاع می‌سازد: بوم `[data-sp-form-canvas]` (گالری شش نمونه از `CopilotPage::examples()` که فقط نوع‌های بومی دارند، ghost هنگام ساخت، نمای طرح با فهرست فیلد و پیش‌نمایش Desktop/Mobile، نتیجه) و ستون گفت‌وگو `[data-sp-form-assistant]` با اجزای مشترک `ai-dock.css`/`ai-dock.js`. `form-builder.js` رفتار را دارد: آمادگی از `boot.ready{studio,ai,recipient}` می‌آید و با `GET /ai/status` هنگام بازگشت focus به زبانه دوباره سنجیده می‌شود؛ پیام دوم وقتی طرحی باز است با قالب `revisePrompt` («Change this form plan. Current plan: … Change: …») همان `/forms/plan` را صدا می‌زند، پس قرارداد سرور تغییر نکرد؛ کارت‌های طرح قدیمی `.is-retired` می‌شوند و idهای تأیید را از دست می‌دهند تا فقط آخرین طرح قابل تأیید باشد. `fitWorkspace()` ارتفاع را با viewport و اعلان‌های مدیریت هماهنگ می‌کند و زیر 960px بوم و گفت‌وگو تب می‌شوند. بدون JS و با Form Studio خاموش، `<noscript>` راه بازگشت به Integrations را نشان می‌دهد.
- **ویژگی‌های گفت‌وگومحور:** بازنگری طرح با پیام ساخته شد؛ کلیک روی فیلد و chip ارجاع هنوز نه (T40).

### قرارداد JSON فرم و سازگاری با Easy Form Builder (T55/S6.2)

طرح «plan» که `FormPlanSchema` توصیف می‌کند — `{title, description?, confirmation_message?, fields:[{key,type,label,required,placeholder,options,settings}]}` — بین connector بومی (`NativeFormConnector`) و connector EFB (`EasyFormBuilderConnector`) **یکسان** است؛ همان plan که Form Studio با AI می‌سازد، بدون تغییر ساختار به هر دو connector می‌رود. سازگاری واقعی از قبل در `EfbFormConverter`/`EfbFormModel` (بخش «Easy Form Builder» بالا، ردیف «ساخت فرم») پیاده‌سازی شده است.

شکل واقعی ستون `form_structer` در EFB (منبع: `easy-form-builder/docs/EFB-CORE-KNOWLEDGE-BASE.en.md` §۲.۲، فقط‌خواندنی) یک آرایهٔ **مسطح** است، نه تودرتو:

| اندیس/کلید | معنا |
|---|---|
| اندیس ۰ | تنظیمات سطح فرم: `formName`، `steps`، `currency`، `button_state`، … |
| هر آیتم بعدی | یک فیلد/step/option با `id_` (آندرلاین انتهایی، شناسهٔ یکتا)، `type`، `step`، `amount` (ترتیب صعودی) |
| `parent` | فقط روی آیتم‌های option/matrix-row: `id_` فیلد مادر. option یک آیتم مستقل است، نه آرایهٔ داخل فیلد |

`EfbFormConverter::convert()` دقیقاً همین ساختار را تولید می‌کند: تنظیمات در اندیس ۰، هر step آیتمی با `id_`/`step`/`amount` صعودی، و option‌ها آیتم جدا با `parent` به‌سوی فیلد مادر. محدودیت آگاهانه (نه نقص): `FormPlanSchema::ALLOWED_FIELD_TYPES` مسیر بومی را به هفت نوع ساده محدود می‌کند (`text, email, tel, textarea, select, checkbox, radio`)؛ انواع پیشرفهٔ EFB (پرداخت، آپلود، امضا، matrix) فقط وقتی connector واقعاً EFB باشد از `EfbFormModel::allowed_types()` عبور می‌کنند و `EfbFormModel::split_pro_fields()` فیلدهای نیازمند Pro را جدا و اعلام می‌کند.

## Easy Form Builder

- **تشخیص:** `EasyFormBuilderConnector::get_health()` این کلیدها را برمی‌گرداند: `available`، `active`، `version`، `compatible`، `notes`. حداقل نسخه 4.1 است و schema جدول EFB هم سنجیده می‌شود. filterهای `aisaautopilot_efb_available` و `aisaautopilot_efb_create_form` هم هستند.
- **Copilot درون ویرایشگر:** `EfbBuilderButton` دکمهٔ شناور و پنل را روی `page=Emsfb` و `page=Emsfb_create` قرار می‌دهد (`efb-copilot.js` و `efb-copilot.css`، با وابستگی به `ai-dock.css`/`ai-dock.js`). مسیرهای `/efb/*` پشت `can_manage_efb_copilot` هستند.
  - **بازطراحی ۲۰۲۶-۱۰-۲۸:** پنل یک ستون ثابت 420px کنار ویرایشگر است (`.aisaautopilot-efb-chat`؛ از 1100px به بالا `body.aisaautopilot-efb-docked` حاشیهٔ `#wpcontent` را باز می‌کند) و زیر 640px برگهٔ پایین صفحه با z-index بالاتر از toastهای EFB. موتور تغییر (`apply()` روی `valj_efb`) دست نخورد؛ فقط خروجی گزارش ساختاریافته شد (`done(report, text, kind, fieldId, extra)`) تا کارت‌های applied، منطق IF/THEN (`EfbLogicRules::build()` اکنون `display` را هم می‌دهد)، review با Apply، preview با Create، nothing-changed و خطا رسم شوند. launcher: یادداشت اولین بازدید (`localStorage` `aisaautopilot_efb_nudged`، صبر تا بسته‌شدن دیالوگ‌های EFB)، Ctrl/⌘+/، دکمهٔ «Ask AI about» کنار فیلد انتخاب‌شده، و شمار تغییرهای ذخیره‌نشده که با ajaxSuccess اکشن‌های `add_form_Emsfb`/`update_form_Emsfb` صفر می‌شود. بدون سرویس AI کارت راه‌اندازی با `GET /ai/status` دوباره سنجیده می‌شود. همهٔ درخواست‌های JSON با `_locale=user` می‌روند.
  - **`ai-dock.js`:** `window.AISAAutoPilotDock` با `icon`، `el`، `hydrate`، `cardHead`، `row`، `steps` و `autoGrow`. آیکن‌ها از جدول ثابت مسیر SVG ساخته می‌شوند و هیچ HTML خامی تزریق نمی‌شود.
  - **متن رابط Copilot:** همهٔ رشته‌های نمایشی از `AISAAutoPilotEFB.i18n` می‌آیند، که در `EfbBuilderButton::enqueue()` با `__()` ساخته می‌شود. JS فقط fallback انگلیسی دارد. `t( key, fallback, value | [values] )` هم `%s` و هم `%1$s`/`%2$s` را جایگزین می‌کند. متن‌های `clientLog` و `history` داخلی‌اند و ترجمه نمی‌شوند.
  - **hookهای یادداشت نتیجه:** هر یادداشت `data-sp-efb-note` دارد (`applied`، `failed`، `no-change`، `server`، `save-ownership`، `render-failed`). یادداشت شکست `data-sp-efb-reason` هم دارد، با کد پایدار مانند `field_not_found` یا `invalid_form_property_value` (برای خطای JS: `exception`؛ برای رسم دوباره: `render`). رابط جملهٔ ترجمه‌شده را نشان می‌دهد و کد خام فقط در client log ثبت می‌شود. check مرورگر از همین hook و زبان رابط استفاده می‌کند، نه از متن انگلیسی.
  - **chipها فقط پر می‌کنند:** chipهای خوشامد و chipهای پیشنهاد موضوع (`data-sp-efb-chips` روی جعبه و `data-sp-efb-chip` روی هر دکمه، با مقدار `starter` یا `suggestion`) متن خود را در composer می‌گذارند، focus را به composer می‌برند و هیچ درخواستی نمی‌فرستند. ارسال فقط با دکمهٔ ارسال یا Enter است و Shift+Enter خط تازه می‌سازد. موکاپ chip را بلافاصله اجرا می‌کند؛ این اختلاف عمدی است و در گزارش S3.2 ثبت شده است.
  - **پیام زمینهٔ انتخاب:** پیام «فیلدی را انتخاب کرده‌اید…» و خوشامد هر دو `data-sp-efb-intro` دارند (`field`، `form` یا `new-form`). پیام انتخاب برای همان فیلد تکرار نمی‌شود؛ `lastIntroFieldId` تا ارسال پیام بعدی یا New chat نگه داشته می‌شود. New chat افزون بر transcript، نوار وضعیت میکروفن (`#aisaautopilot-efb-voice-status`) را هم پاک می‌کند.
- **گفت‌وگو و راهنما:** `EfbChatSkill` هماهنگ‌کنندهٔ گفت‌وگوست و `EfbKnowledge` به «چطور…» از دانش گردآوری‌شده پاسخ می‌دهد.
- **ساخت فرم:** `EfbFormSkill` plan می‌سازد و `EfbFormConverter` آن را به `form_structer` واقعی EFB تبدیل می‌کند.
- **ویرایش فرم باز:** `EfbCustomizeSkill` فرمان را به عملیات می‌سازد. `EfbCustomizeSkill::OPS` این عملیات را دارد:
  - `set_form_name`، `set_confirmation`، `set_field`، `set_field_property`، `set_form_property`
  - `add_field`، `add_step`، `remove_field`
  - `add_option`، `remove_option`

  هر عملیات سمت سرور با کاتالوگ سنجیده می‌شود. مرورگر آن را روی `valj_efb` در حافظه اعمال می‌کند و EFB دوباره رندر می‌کند. ذخیره فقط با دکمهٔ Save خود EFB است.
- **کاتالوگ:** `includes/Efb/efb-capability-catalog.json` مرجع قابلیت‌هاست:
  - 68 نوع فیلد، که 16 تا `ai_create` دارند؛
  - 75 ویژگی فیلد، که 61 تا `ai_writable` دارند؛
  - 38 ویژگی فرم، که 26 تا `ai_writable` دارند؛
  - فهرست `safe_ai_contract.blocked`: تنظیمات سراسری، فعال‌سازی افزونهٔ جانبی، اطلاعات ورود و کلید API، پرداخت و قیمت، HTML خام، URL غیرمجاز، منطق شرطی و دادهٔ ارسال‌شده.
- **پلن EFB:** `EfbLicense` پلن EFB را آینه می‌کند. ویژگی‌های Pro در `EfbFormModel` قفل می‌شوند.
- **صفحهٔ جزئیات:** `aisaautopilot-efb` همین عملیات، شمارها، حوزه‌های ممنوع و آخرین `efb.apply*` لاگ را نشان می‌دهد.

## Content Studio

- **نماها:** `CopilotPage` با `ContentStudioNavigation` نماهای Write، Templates، History و Forms را نمایش می‌دهد.
- **نوشتن:** `/content/plan` plan پیش‌نویس را با `ContentDraftSkill` و `ContentPlanSchema` می‌سازد. `/content/execute` با `ContentDraftExecutor` **فقط پیش‌نویس** نوشته یا برگه می‌سازد.
- **تاریخچه:** `ContentStudioReadModel` فقط پیش‌نویس‌هایی را فهرست می‌کند که واقعاً Content Studio ساخته است. خروجی CSV با `aisaautopilot_export_content_studio_history` است.
- **هنوز ساخته نشده:** لحن Formal و Plain (T32)، شمار استفادهٔ الگو (T33)، ویرایشگر الگوی سفارشی (T34) و «Publish on approval» (T52).

## Store Copilot

- **قرارداد قابلیت:** `WooCommerceConnector::get_capabilities()` این قرارداد را برمی‌گرداند:

  | حوزه | مجاز | غیرمجاز |
  |---|---|---|
  | محصول | `read` و `create_draft` | `publish`، `edit`، `bulk_edit` |
  | سفارش | خواندن اخیر | هر تغییری |

- **ساخت محصول:** `/woocommerce/plan` و `/woocommerce/execute` با `WooCommerceProductSkill` فقط پیش‌نویس محصول می‌سازند.
- **جست‌وجوی هوشمند:** `/woocommerce/smart-search` فقط‌خواندنی است و فقط با ترجیح صریح آن کار می‌کند (`is_smart_search_enabled()`).
- **مسیرهای خاموش:** کنترلرهای analytics دیگر، content و order-write در کد مانده‌اند اما ثبت نشده‌اند (T48 و T49).
- **رفتار صفحه:** بدون WooCommerce حالت صادقانهٔ «در دسترس نیست» نمایش می‌دهد و هیچ کنترل نوشتنی ندارد.

## Health و Activity Log

- **Health:** هشت بررسی `HealthDiagnostics` را نشان می‌دهد و اجرای آن فقط یک درخواست provider می‌فرستد.
- **رکوردها:** `Logger` حداکثر 200 رکورد دارد، با قفل هم‌زمانی و `Redactor`.
- **read modelها:** `ActivityFeed` و `ActivityTimeline` رکوردها را برای Activity Log به کارت روز، ردیف با عنوان واقعی و پیوند «Open» تبدیل می‌کنند. `/activity` همان خوراک پاک‌سازی‌شده است.
- **جمع شدن تاریخچه:** `LogsPage` روزهای قدیمی‌تر از `VISIBLE_DAYS` و ردیف‌های بیشتر از `VISIBLE_ROWS` را با `<details>` جمع می‌کند.
- **خروجی:** `aisaautopilot_export_logs` خروجی CSV کامل می‌دهد.
- **هنوز ساخته نشده:** ردیف‌های «منتظر تأیید» از proposal واقعی، و Undo و Restore (T41).
