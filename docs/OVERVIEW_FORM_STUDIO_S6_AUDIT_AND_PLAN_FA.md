# ممیزی و برنامه اجرای S6 — بازآرایی IA و Form Studio

تاریخ ممیزی: ۲۰۲۶-۰۹-۱۷
مرجع: دستور مستقیم مالک در گفتگو (نه یک شکاف موکاپ)؛ تصمیم ثبت‌شده D-23 در `PRODUCT_COMPLETION_ROADMAP_FA.md`
صف اجرا: T54 (S6.1)، T55 (S6.2)

## تصمیم معماری

مالک به‌صورت زنده در گفتگوی ۲۰۲۶-۰۹-۱۷ IA سطح بالای منو را — نه ظاهر تک‌تک صفحات — تغییر داد. این آگاهانه از قاعدهٔ قدیمی «منوی visible دقیقاً ۱۰ آیتم به ترتیب موکاپ» فاصله می‌گیرد. موکاپ (`AutoPilot Admin.dc.html`) همچنان مرجع بصری هر صفحه است؛ فقط چیدمان بالای منو و نام یک صفحه عوض شده‌اند.

### صفحه‌های اصلی تازه (۸ مورد، به ترتیب D-23)

| # | صفحه | slug فعلی | تغییر |
|---|---|---|---|
| ۱ | Overview | `aisaautopilot-home` | بدون تغییر + نوار تب تازه (چهار مورد زیر) |
| ۲ | Setup & Connection | `aisaautopilot-setup` | بدون تغییر |
| ۳ | Integrations | `aisaautopilot-integrations` | بدون تغییر |
| ۴ | Site Knowledge | `aisaautopilot-kb` | بدون تغییر |
| ۵ | Answer Desk | `aisaautopilot-conversations` | بدون تغییر |
| ۶ | Store Copilot (v2 — بدنهٔ فعلی می‌ماند، عمق S5-A طبق D-13) | `aisaautopilot-wc-dashboard` | بدون تغییر |
| ۷ | Content Studio (v2 — بدنهٔ فعلی می‌ماند، عمق S5-A طبق D-13) | `aisaautopilot-form-builder` | بدون تغییر در slug؛ `forms_url()` دیگر به این صفحه اشاره نمی‌کند |
| ۸ | **Form Studio** (نام تازه؛ قبلاً «Form Composer»، ترویج‌یافته از یک deep link به صفحهٔ اصلی) | **تازه:** `aisaautopilot-form-studio` | صفحهٔ تازهٔ visible؛ محتوا از `CopilotPage::render_forms()` می‌آید (همان کدی که تا امروز پشت `page=aisaautopilot-form-builder&mode=forms` بود) |

### تب‌های Overview (چهار مورد)

| تب | slug قبلی (حالا alias پنهان) | صاحب در `ALIAS_OWNERS` |
|---|---|---|
| Overview (پیش‌فرض) | `aisaautopilot-home` | — (خودش) |
| Usage & Cost | `aisaautopilot-usage` | `aisaautopilot-home` |
| Health | `aisaautopilot-connections` | `aisaautopilot-home` |
| Activity Log | `aisaautopilot-logs` | `aisaautopilot-home` |

این دقیقاً همان الگویی است که از قبل برای Content Studio (Write/Templates/History، کلاس `ContentStudioNavigation`) و برای هشت alias دیگر (Settings، Forms، History، Chat، EFB، WC، Widget، Handoffs) در `AdminMenu::ALIAS_OWNERS` وجود دارد؛ هیچ الگوی تازه‌ای اختراع نشد. کلاس تازهٔ `OverviewNavigation` عیناً از `ContentStudioNavigation` کپی‌الگو شده است.

**نکتهٔ v2:** «Store Copilot (v2)» و «Content Studio (v2)» در پیام مالک یعنی عمق قابلیت این دو صفحه (T31 تا T42 در S5-A) کماکان تابع تصمیم D-13 است (پیش یا پس از انتشار 1.1.0)؛ S6 چیزی به آن اضافه یا کم نمی‌کند، فقط این دو صفحه به‌عنوان آیتم‌های ۶ و ۷ از هشت‌گانهٔ اصلی باقی می‌مانند.

## نام‌گذاری «Form Composer» — گزینه‌ها و تصمیم

نام «Form Composer» عیناً از عنوان فایل موکاپ `Form Composer.dc.html` آمده، ولی مالک آن را نامناسب دانست، احتمالاً چون «Composer» با Content Studio (که خودش هم «می‌نویسد»/compose می‌کند) هم‌پوشانی معنایی دارد و هویت AI-محورِ فرم‌ساز را نمی‌رساند.

| گزینه | استدلال موافق | استدلال مخالف |
|---|---|---|
| **Form Studio** (انتخاب‌شده) | هم‌خانواده با Content Studio: هر دو الگوی canvas + دستیار کناری + review دارند؛ الگوی نام‌گذاری «Noun + Studio» برای صفحات خلاقانه از قبل جا افتاده | دو «Studio» در فهرست اصلی — عمداً پذیرفته شد چون این دو صفحه واقعاً از یک خانواده UI‌اند |
| Form Copilot | هم‌خانواده با Store Copilot: هر دو propose → approve → execute هستند | با «EFB Copilot» (سطح تخصصی دیگر، پنجرهٔ روی ویرایشگر EFB) قابل‌اشتباه است |
| Smart Forms | ساده و بازاریابی‌پسند | با سبک نام‌گذاری بقیهٔ صفحات (اسم دو کلمه‌ای خنثی) هم‌خوان نیست |

تصمیم: **Form Studio**. فقط رشتهٔ نمایشی (`__()`) عوض شد. شناسه‌های زیر عمداً دست‌نخورده ماندند، دقیقاً به همان دلیلی که «Content Studio» روی ثابت `SLUG_FORM_BUILDER` مانده است — برچسب نمایشی و شناسهٔ داخلی الزاماً یکی نیستند:

- کلید تنظیمات `form_composer_enabled` (در `Settings`، REST و PHPUnit).
- id ماشینی `'form-composer'` در `IntegrationsPage`/`HomePage`/`SettingsPage` و `data-sp-integration-card`، `data-sp-overview-running`، `data-sp-integration-dialog` که از آن مشتق می‌شوند.
- پیشوند کلاس CSS `sp-fc-*` و id‌های `aisaautopilot-compose-screen`/`aisaautopilot-review-screen`/`aisaautopilot-prompt`/`aisaautopilot-voice-toggle`/`aisaautopilot-examples` در `CopilotPage.php` و `assets/admin/content-studio.js`.
- نام کلاس PHP `CopilotPage` (که از قبل هم برای بخش Write/Templates/History همین صفحه به کار می‌رفت).

## نگاشت کد — تغییرهای S6.1

### `includes/Admin/AdminMenu.php`

- ثابت تازه `SLUG_FORM_STUDIO = 'aisaautopilot-form-studio'`.
- `SLUG_USAGE`، `SLUG_CONNECTIONS`، `SLUG_LOGS` از آرایهٔ `$pages` (visible) به آرایهٔ `$aliases` (parent خالی) منتقل شدند؛ در `ALIAS_OWNERS` به `SLUG_HOME` نگاشت شدند.
- `SLUG_FORM_STUDIO` به `$pages` بلافاصله بعد از Content Studio افزوده شد و به `array( $copilot_page, 'render_forms' )` اشاره می‌کند (همان نمونهٔ `CopilotPage` که Content Studio هم از آن استفاده می‌کند).
- `SCREEN_SCRIPTS[SLUG_FORM_STUDIO]` روی `content-studio.js` تنظیم شد (همان اسکریپت Form Studio/Form Composor قبلی).
- `current_screen_slug()`’s `$known` شامل `SLUG_FORM_STUDIO` شد.

### `includes/Admin/OverviewNavigation.php` (کلاس تازه)

الگوی دقیق `ContentStudioNavigation` با چهار تب: `overview` (پیش‌فرض)، `usage`، `health`، `logs`؛ `current_view()` از `$_GET['tab']` می‌خواند، `url()` و `subtabs()` برای رندر نوار مشترک.

### `includes/Admin/HomePage.php`، `UsagePage.php`، `ConnectionsPage.php`، `LogsPage.php`

هرکدام بلافاصله بعد از `Ui::page_header(...)` یک‌خط `OverviewNavigation::render( OverviewNavigation::TAB_xxx );` اضافه کردند.

### `includes/Admin/CopilotPage.php`

بدنهٔ شاخهٔ پیش‌فرض (compose/review/result) به متد عمومی `render_forms()` استخراج شد. `render()` در انتهایش `$this->render_forms();` صدا می‌زند — یعنی deep link قدیمی `page=aisaautopilot-form-builder&mode=forms` عیناً همان محتوا را نشان می‌دهد؛ چیزی حذف یا redirect نشده. رشتهٔ نمایشی «Form Composer» در h1 و disabled-state به «Form Studio» تغییر کرد.

### `includes/Admin/ContentStudioNavigation.php`

`forms_url()` اکنون به `admin_url('admin.php?page=' . AdminMenu::SLUG_FORM_STUDIO)` اشاره می‌کند، نه به deep link قدیمی.

### رشتهٔ نمایشی «Form Composer» → «Form Studio»

فایل‌های تغییریافته (فقط `__()`/`esc_html_e()`؛ هیچ شناسه یا کلید داده‌ای عوض نشد): `CopilotPage.php`، `IntegrationsPage.php` (عنوان کارت + دیالوگ + برچسب checkbox)، `NativeFormsPage.php` (توضیح صفحه + doc-comment)، `SettingsPage.php`، `HomePage.php` («Running now»)، `EasyFormBuilderPage.php`، `Api/CopilotController.php` (دو پیام خطای REST)، `Activity/ActivityTimeline.php` (برچسب feature در Activity Log).

### تست‌ها

- `tests/local_admin_menu_smoke.php`: فهرست ۸تایی visible تازه + `SLUG_FORM_STUDIO`؛ `SLUG_USAGE`/`SLUG_CONNECTIONS`/`SLUG_LOGS` به `legacy_slugs` (بررسی alias پنهان) منتقل شدند.
- `tests/local_admin_assets_smoke.php`: نگاشت اسکریپت `SLUG_FORM_STUDIO`.
- `tests/wordpress_activity_timeline_integration.php`: مقدار نگاشت به «Form Studio».
- `tests/wordpress_content_studio_navigation_integration.php`، `tests/wordpress_form_composer_page_integration.php`، `tests/wordpress_integrations_hub_integration.php`: رشتهٔ مورد assertion به «Form Studio».
- `tests/browser_release_check.cjs`، `tests/form_composer_browser_matrix.cjs`: فقط برچسب توصیفی `check()`ها به‌روزرسانی شد (assertion واقعی روی id ماشینی `form-composer` است، نه متن نمایشی، پس بدون تغییر رفتاری کار می‌کنند). **این دو فایل در این نشست اجرا نشدند** — ریزتسک باز زیر را ببینید.

## S6.2 — سازگاری JSON فرم‌ها با Easy Form Builder

### یافتهٔ اصلی: سازگاری از قبل وجود دارد

منبع واقعیت EFB: `easy-form-builder/docs/EFB-CORE-KNOWLEDGE-BASE.en.md` §۲.۲ (فقط‌خواندنی، بررسی‌شده ۲۰۲۶-۰۹-۱۷). جدول `{prefix}emsfb_form` ستون `form_structer` (نام غلط تاریخی، عمداً دست‌نخورده در کد EFB) یک آرایهٔ **مسطح** از آبجکت‌هاست:

- اندیس ۰: تنظیمات سطح فرم (`formName`، `steps`، `currency`، …).
- هر آیتم بعدی: یک فیلد/step/option با `id_` (با آندرلاین انتهایی)، `type`، `parent` (برای option/matrix-row، اشاره به `id_` فیلد مادر)، `step`، `amount`.
- **option‌ها آیتم مستقل با `type: 'option'` و `parent`اند، نه آرایهٔ تودرتو داخل فیلد.**

`includes/Efb/EfbFormConverter.php` (متد `convert()`) دقیقاً همین شکل را می‌سازد: تنظیمات در اندیس ۰، هر step به‌عنوان آیتم جدا با `id_`/`step`/`amount` صعودی، فیلدها با `id_` یکتا، option‌ها آیتم جداگانه با `parent` به‌سوی `id_` فیلد. این کلاس و `EfbFormModel` (پشتیبان `efb-form-model.json` + `efb-capability-catalog.json`) از بستهٔ S3.2/S3.1 موجودند و تست‌شده‌اند (PHPUnit، `wordpress_efb_copilot_contract_integration`).

طرح مشترک «plan» — `{title, description?, confirmation_message?, fields:[{key,type,label,required,placeholder,options,settings}]}` — بین `NativeFormConnector` (فرم بومی) و `EasyFormBuilderConnector` (فرم EFB) **یکسان** است. یعنی همان پیش‌نویسی که در Form Studio با AI ساخته می‌شود، بدون تغییر ساختار، هم به‌صورت فرم بومی و هم به‌صورت فرم واقعی EFB قابل اجراست — دقیقاً همان چیزی که مالک خواسته: «برای فرم‌های پیشرفته به‌راحتی بتوان منتقل کرد».

### شکاف واقعی (نه نقص، محدودیت آگاهانه)

`FormPlanSchema::ALLOWED_FIELD_TYPES` (مسیر MVP بومی) عمداً به هفت نوع ساده محدود است: `text, email, tel, textarea, select, checkbox, radio`. فیلدهای پیشرفهٔ EFB (پرداخت، آپلود، امضا، matrix، چندمرحله‌ای غیر از marker `steps`) فقط وقتی connector واقعاً EFB باشد از کاتالوگ گسترده‌تر (`EfbFormModel::allowed_types()`) عبور می‌کنند؛ `EfbFormModel::split_pro_fields()` هم فیلدهای نیازمند Pro را جدا و به مالک اعلام می‌کند. این دقیقاً همان مرز مستندشده در `FORM_COMPOSER_S3_1_EXECUTION_REPORT_FA.md` («تبدیل خودکار فرم native به EFB ادعا نشده؛ فقط handoff واقعی وجود دارد») است، نه یک باگ تازه.

### خروجی این task

فقط سند: بخش تازهٔ «قرارداد JSON فرم و سازگاری با Easy Form Builder» در `docs/technical/FORMS_CONTENT_STORE_FA.md` با جدول کلیدهای `form_structer` (بالا) و نگاشت آن به کلیدهای plan داخلی. **هیچ کد رفتاری در S6.2 عوض نمی‌شود** — اگر مالک بخواهد فرم بومی هم انواع پیشرفته بپذیرد یا فرم بومی موجود به EFB «migrate» شود (نه فقط دوباره‌سازی از plan)، آن یک تصمیم و task جداست.

## ریزتسک‌های S6.1

| ID | کار | وضعیت |
|---|---|---|
| S6.1.1 | `OverviewNavigation` تازه + رندر تب در چهار صفحه | تکمیل |
| S6.1.2 | `AdminMenu`: انتقال Usage/Health/Logs به alias، افزودن `SLUG_FORM_STUDIO` | تکمیل |
| S6.1.3 | استخراج `CopilotPage::render_forms()` و بازتنظیم `forms_url()` | تکمیل |
| S6.1.4 | رشتهٔ نمایشی «Form Composer» → «Form Studio» در ۸ فایل PHP | تکمیل |
| S6.1.5 | هم‌گامی تست‌های PHP (menu/assets smoke + ۴ integration) | تکمیل |
| S6.1.6 | گیت static/full + PHPUnit کامل روی درخت تغییریافته | تکمیل — نتایج در execution report |
| S6.1.7 | برچسب توصیفی `browser_release_check.cjs`/`form_composer_browser_matrix.cjs` | تکمیل (فقط متن، بدون اجرا) |
| S6.1.8 | اجرای واقعی browser matrix (en_US/fa_IR، دسکتاپ/۳۹۰px) روی IA تازه + بازبینی تصویری با موکاپ | تکمیل در T56 (S6.3) — بخش «ریزتسک‌های S6.3» و `OVERVIEW_FORM_STUDIO_S6_EXECUTION_REPORT_FA.md` |
| S6.1.9 | بستن اسناد: `docs/technical/ADMIN_SURFACES_FA.md` (هشت صفحهٔ اصلی + چهار تب Overview + یازده alias)، رودمپ، tracker، `handoff.md` | تکمیل |

## ریزتسک‌های S6.2

| ID | کار | وضعیت |
|---|---|---|
| S6.2.1 | خواندن `EFB-CORE-KNOWLEDGE-BASE.en.md` §۲.۲ و تأیید تطابق با `EfbFormConverter` | تکمیل |
| S6.2.2 | نوشتن بخش قرارداد JSON در `docs/technical/FORMS_CONTENT_STORE_FA.md` | تکمیل |
| S6.2.3 | ثبت شکاف آگاهانه (انواع پیشرفته فقط با EFB) به‌عنوان محدودیت مستند، نه باگ | تکمیل |

## ریزتسک‌های S6.3 (T56)

| ID | کار | وضعیت |
|---|---|---|
| S6.3.1 | `tests/admin_ia_browser_check.cjs` و `tests/admin_ia_browser_matrix.cjs` (بدون fixture؛ نگهبان حساب QA مشترک) | تکمیل |
| S6.3.2 | اجرا در `en_US` و `fa_IR` × 1440 و 390 | تکمیل |
| S6.3.3 | یافتهٔ matrix: شش msgid «Form Studio» و «Overview sections» در `fa_IR` بی‌ترجمه → PO و MO | تکمیل |
| S6.3.4 | یافتهٔ تصویری: ردیف تب به کارت اول چسبیده (0px) و شکست 3 + 1 در 390px → CSS و دو سنجهٔ تازه | تکمیل |
| S6.3.5 | بازبینی تصویری در برابر قاب‌های `test-artifacts/mockup-parity/mockup/` | تکمیل |
| S6.3.6 | گزارش اجرای بسته، tracker، رودمپ، اسناد فنی، handoff | تکمیل |

## وابستگی‌ها و ترتیب با صف موجود

T54/T55 مستقل از S3.3 (T07 تا T14) اجرا شدند؛ T07 در حال اجرا دست‌نخورده ماند. T29 (ممیزی S5) باید D-23 را داده‌شده بداند: T40 (Form Composer مکالمه‌ای) از این پس روی صفحهٔ Form Studio مستقل اجرا می‌شود، نه deep link زیر Content Studio؛ T31 (بنر افزونهٔ هم‌پوشان) باید بنر Integrations را با id ماشینی بدون‌تغییر `form-composer` هدف بگیرد.
