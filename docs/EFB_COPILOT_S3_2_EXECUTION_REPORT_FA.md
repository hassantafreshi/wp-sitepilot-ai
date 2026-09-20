# گزارش اجرای S3.2 — EFB Copilot

تاریخ پایان: ۲۰۲۶-۰۹-۱۷  
مرجع تصویری: `mockups/design_handoff_admin_redesign/EFB Builder Chat.dc.html`  
سطح واقعی: پنجرهٔ Copilot روی ویرایشگر خود Easy Form Builder 4.2.0 در `page=Emsfb` و `page=Emsfb_create`  
ممیزی و ریزتسک‌ها: `EFB_COPILOT_S3_2_AUDIT_AND_PLAN_FA.md`  
taskهای رودمپ: T00، T01، T02، T03، T26، T27، T28، T04، T05 و T06

## نتیجه

EFB Copilot روی ویرایشگر واقعی EFB بازطراحی و تثبیت شد. ویرایشگر نمایشی موکاپ، سناریوسوییچر و داده‌های نمونه وارد محصول نشدند. FAB، پنجرهٔ 388×576، بنر فیلد انتخاب‌شده، کارت how-to، کارت Pro، ورودی صوتی transcript-first و پیام نتیجهٔ هر operation به داده و قرارداد واقعی وصل‌اند. Copilot فقط canvas زنده را تغییر می‌دهد و ذخیره همچنان با دکمهٔ سبز Save خود EFB است.

S3.2 بسته شد. هیچ gate نسبت به خط مبنا افت نکرده است. منوی اصلی همچنان ده آیتم است و صفحهٔ پنهان سازگاری EFB (`EasyFormBuilderPage`) حفظ شده است.

## تغییرات فنی

- **قرارداد selected-field** (`includes/Efb/EfbChatSkill.php`، `includes/Api/EfbCopilotController.php`):
  - مرورگر فقط شناسهٔ فیلد را با `selected_field` می‌فرستد؛
  - کنترلر آن را با فهرست پاک‌سازی‌شدهٔ `fields` تطبیق می‌دهد و label و type را از catalogue می‌گیرد، نه از داده‌های client؛
  - فقط انتخاب معتبر به‌صورت `SELECTED_FIELD: id=… label=… type=…` وارد prompt می‌شود. شناسهٔ ناموجود، شیء selection ساختهٔ client و حالت بدون فرم باز به prompt نمی‌رسند.
- **رفع callback `/efb/log`** (`includes/Api/RestRoutes.php`): route به متد ناموجود `EfbCopilotController::log` وصل بود و هر client log با HTTP 500 می‌شکست. callback به `client_log` تغییر کرد. integration (T02) ثبت، callable بودن، پاسخ 200 مدیر، 400 `invalid_payload` برای نبود event و رد subscriber را می‌سنجد؛ مرورگر (T01 و T04) `[200,200]` را در هر دو locale دید.
- **gate `efb_copilot_enabled`:** با مقدار false مدیر هم رد می‌شود، بدون لاگ و بدون درخواست provider (T02).
- **نوشتن اتمی لاگ** (`includes/Logging/Logger.php`، T28 و D-09): `Logger::log` زیر قفل `GET_LOCK` و با خواندن مستقیم ردیف option می‌نویسد. نام option و شکل entry عوض نشد. پیش از اصلاح 6/2 (فقط 24 از 90 entry هم‌زمان ماند)، پس از اصلاح 6/0 (90 از 90).
- **کاتالوگ و دانش EFB 4.2.0** (`includes/Efb/efb-capability-catalog.json`، `includes/Efb/efb-knowledge.json`، T26):
  - `efb_version_target` ← `4.2.x`؛
  - `show_icon` و `show_pro_bar` با معنای واقعی EFB («true پنهان می‌کند»)؛ property نادرست `show_button_icon` حذف و `hide_steps_row` و `hide_progress_bar` جایش آمدند؛
  - `steps_style` و `progress_style` با enum و fallback `classic`؛
  - جدول entry از `emsfb_message` به `emsfb_msg_` اصلاح شد و how-to `multi_step` به‌روز شد.
- **operationهای سبک step و progress** (T27 و D-08): `set_form_property` با allowlist همان `ai_writable` کاتالوگ؛ enum در `EfbCustomizeSkill` و دوباره در `assets/admin/efb-copilot.js` سنجیده می‌شود؛ `syncStepStyleControls()` پنل تنظیمات باز EFB را بدون reload همگام می‌کند.

## تغییرات UI

- **پوسته** (`includes/Efb/EfbBuilderButton.php`، `assets/admin/efb-copilot.js`، `assets/admin/efb-copilot.css`): FAB navy، پنجرهٔ 388×576 با radius 18، هدر gradient، actions، پیام‌ها، chips و composer مطابق ساختار موکاپ؛ logical properties برای RTL؛ `prefers-reduced-motion`؛ پنجره در 390×844 داخل viewport.
- **انتخاب فیلد:** bridge روی `.efbField[data-id="<id>-id"]`، highlight مستقل با کلاس `aisaautopilot-efb-field-selected`، بنر «در حال کار روی …» با Clear. کلیک روی کنترل درونی `-el` هم فیلد را انتخاب می‌کند (T01). پیام انتخاب برای همان فیلد تکرار نمی‌شود (T04).
- **chipها فقط composer را پر می‌کنند** و focus را همان‌جا می‌برند؛ هیچ درخواستی هنگام باز کردن پنل یا کلیک chip فرستاده نمی‌شود (T04).
- **voice صادقانه:** کد realtime و ارسال خودکار حذف شد. دکمهٔ هدر و میکروفن composer فقط ضبط و تبدیل گفتار موجود را آغاز و متوقف می‌کنند، transcript در composer قابل ویرایش است و هرگز خودکار ارسال نمی‌شود. وضعیت با `aria-live` اعلام می‌شود و New chat نوار وضعیت میکروفن را پاک می‌کند.
- **کارت‌ها:** how-to از پاسخ واقعی `answer_help`؛ chipهای پیشنهاد موضوع؛ کارت Pro با نام فیلد کنارگذاشته‌شده، CTA که دیالوگ خود EFB را باز می‌کند و پیوند قیمت محلی‌شده با `rel="noopener noreferrer"`.
- **پیام نتیجه:** هر operation پیام محلی‌شده دارد و هر یادداشت `data-sp-efb-note` می‌گیرد (`applied`، `failed`، `no-change`، `server`، `save-ownership`، `render-failed`). دلیل شکست به‌جای کد خام جملهٔ ترجمه‌شده است و کد پایدار در `data-sp-efb-reason` می‌ماند. ستون عرض پیش‌نمایش درصد نشان می‌دهد.
- **i18n (T03):** همهٔ رشته‌های نمایشی JS از `cfg.i18n` با fallback انگلیسی می‌آیند؛ ۸۷ رشتهٔ ترجمه‌پذیر `EfbBuilderButton.php` در PO msgstr فارسی ناتهی دارند. `t()` placeholder شماره‌دار را می‌پذیرد و `$&` در برچسب فیلد را تفسیر نمی‌کند.
- **hookهای آزمون:** `data-sp-efb-chips`، `data-sp-efb-chip` (`starter`، `suggestion`)، `data-sp-efb-intro` (`field`، `form`، `new-form`)، `data-sp-efb-note` و `data-sp-efb-reason`.

## اختلاف‌های آگاهانه با موکاپ

| موکاپ | افزونه | دلیل |
|---|---|---|
| ویرایشگر نمایشی EFB و سناریوسوییچر | پنجره روی ویرایشگر واقعی EFB | ابزار prototype است؛ بازسازی ویرایشگر EFB خارج از مالکیت این افزونه است |
| مکالمهٔ صوتی realtime تمام‌صفحه با Mute، End و «English · auto-detected» (E-01) | دکمهٔ هدر میانبر همان ضبط transcript-first است | transport یا session صوتی realtime در کد نیست؛ تصمیم D-02 و بستهٔ T47 (S5-B) |
| chip با کلیک بلافاصله اجرا می‌شود (`runChip`) | chip فقط composer را پر می‌کند | انسان پیش از ارسال می‌بیند و ویرایش می‌کند؛ همان رفتار Form Composer و کنسول پرسش |
| خط زمینهٔ هدر «Contact form · 4 fields» | «در حال ویرایش فرم فعلی» | دادهٔ نام فرم و شمار فیلد در `valj_efb` هست ولی به هدر وصل نشده است؛ باز و برای مقایسهٔ بصری T16 ثبت شد |
| کارت how-to با آیکن و گام شماره‌دار | عنوان و پاراگراف‌های نثر | `efb-knowledge.json` پاسخ‌ها را نثر نگه می‌دارد |
| کارت کهربایی با قفل برای Pro | یادداشت درون حباب با دکمهٔ طلایی و پیوند قیمت | محتوا صادقانه‌تر است؛ اختلاف فقط بصری است |
| آیکن کاغذ پرنده و placeholder «Ask for a change, or click a field…» | فلش رو به بالا و «دربارهٔ این فرم بپرسید…» | از S3.2.1 |
| ذخیرهٔ ضمنی تغییرها | Save فقط با دکمهٔ سبز EFB و یادداشت صریح `save-ownership` | persistence متعلق به EFB است |

## قابلیت‌های افزونه که موکاپ برایشان UI ندارد

- صفحهٔ پنهان جزئیات و سازگاری EFB (`EasyFormBuilderPage`، alias `aisaautopilot-efb` زیر Integrations، B-10): عملیات مجاز، حوزه‌های ممنوع و آخرین تغییر از لاگ.
- endpointهای قدیمی `plan`، `customize` و `save` برای سازگاری API؛ مسیر canonical گفت‌وگو `chat` است.
- Activity Log و trace فنی درخواست و اعمال operation؛ شناسهٔ trace در پیام شکست رسم دوباره به کاربر نشان داده می‌شود.
- کاتالوگ کامل propertyهای فیلد و فرم و تبدیل ساختاری EFB، به‌عنوان قرارداد اجرایی داخلی.
- ساخت فرم تازه با proposal و approval یک‌بارمصرف؛ هیچ ساختی بدون Approve انجام نمی‌شود.
- operationهای سبک step و progress روی canvas زنده (T27).

## محدودیت‌های باز و صادقانه

- نام سبک‌ها و «Step 1 of 2 · 50% complete» در preview از ترجمهٔ خود EFB می‌آیند و در `fa_IR` انگلیسی‌اند؛ سمت EFB است.
- در `fieldSettingSet` و `formPropertyUpdated` نام تنظیم همان کلید کاتالوگ است، چون کاتالوگ برچسب ترجمه‌شده ندارد.
- `fieldsCount` یک صورت جمع دارد؛ `_n` در T18 بررسی می‌شود.
- حباب پیام `dir` ندارد، پس پاسخی با زبانی جز زبان رابط با جهت رابط رندر می‌شود؛ پیشنهاد `dir="auto"` برای T17.
- ضبط واقعی میکروفن و transcript در headless آزموده نشده است (فقط رد اجازه)؛ پذیرش آن با میکروفن واقعی بر عهدهٔ مالک است.
- سقف مصرف محلی پر است و کلید واقعی در WordPress Connectors است، پس همهٔ آزمون‌های مرورگر `/efb/chat` را با `page.route()` mock می‌کنند.

## شواهد تست

شمارهای regression پایانی از T05 (۲۰۲۶-۰۹-۱۶) است. پس از آن هیچ فایل کد یا آزمونی تغییر نکرده است؛ T06 فقط سند است.

| گیت | نتیجه | artifact |
|---|---|---|
| گیت full (T05) | **9/9**، exit 0 | `test-artifacts/gates/20260916-214733-full` |
| php-lint و node-check | 229/229 و 37/37 | همان |
| PHPCS کامل | 133/133، 0 error، 0 warning | همان |
| PHPStan | 0 error | همان |
| PHPUnit | 149 / 514 (test / assertion؛ پیش از S3.2: 133 / 439) | همان |
| menu، assets و render smoke | 5/0، 19/0 و 18/0، PHP diagnostic صفر | همان |
| Phase 1 integration | 45/0 | همان |
| گیت release (T05) | **60/60**، exit 0، 51 integration suite | `test-artifacts/gates/20260916-215043-release` |
| EFB Copilot contract integration | 15/0 (T02: دو اجرا؛ T05: یک اجرا) | `test-artifacts/efb-copilot/20260915-t02/` |
| Logger concurrency integration | 6/0 (پیش از اصلاح 6/2) | `test-artifacts/logger-concurrency/20260915-t28/` |
| EFB details و usage cap EFB | 7/0 و 2/0 | `test-artifacts/gates/20260916-215043-release` |
| قرارداد DOM EFB 4.2.0 | probe runtime 19 PASS، صفر pageerror و پاسخ ناموفق | `test-artifacts/efb-env/20260915-t00-runtime/` |
| browser matrix (T04) | `en_US` 76 PASS / 0 FAIL و `fa_IR` 76 PASS / 0 FAIL؛ console، pageerror و HTTP غیرمنتظره صفر | `test-artifacts/efb-copilot/20260916-t04/` |
| سبک step و progress (T27) | preview خود EFB ردیف pills و ring را در LTR و RTL رسم کرد؛ صفر POST ذخیره | `test-artifacts/efb-copilot/20260915-t27/` |
| ترجمه | MO با 2274 ترجمه از 2415 msgid (141 بی‌ترجمهٔ کهنه، کار T18)؛ `msgfmt --check` سالم؛ 87/87 رشتهٔ EFB Copilot ترجمه دارند | `test-artifacts/efb-copilot/20260916-t03-reverify2/` |
| پاک‌سازی | `EFB Copilot browser fixture removed.` و `Local browser QA account removed.` در هر اجرا؛ preflight پس از T05 با WARNINGS 0 و BLOCKERS 0 | `test-artifacts/gates/20260916-215043-release/preflight-after.txt` |

شکست‌های میانی پنهان نشده‌اند: اجرای اول matrix در T03 به timeout `page.goto` و در T04 به timeout کلیک ورود خورد، هر دو پیش از هر check و به‌خاطر کندی سایت محلی. در T04 بودجهٔ کلیک و انتظار URL ورود به ۶۰ ثانیه رسید و هیچ assertion سست نشد. در T03 دو assertion که متن انگلیسی را می‌سنجیدند، عمداً به hook `data-sp-efb-reason` و زبان رابط تغییر کردند.

## خروجی visual QA

در T04 هفت screenshot محدود در هر locale با `locator.screenshot()` گرفته و با موکاپ مقایسه شد: FAB، پنل دسکتاپ، بنر انتخاب، کارت how-to، کارت Pro، preview سبک و پنل 390px (۱۴ تصویر در `test-artifacts/efb-copilot/20260916-t04/`). ساختار، hierarchy، فاصله‌ها، رنگ و RTL واقعی با موکاپ هم‌راستا هستند و اختلاف‌های باقی در جدول بالا ثبت شده‌اند. screenshot تمام‌صفحه عمداً کنار گذاشته شد، چون صفحهٔ ویرایشگر EFB بسیار بلند است.
