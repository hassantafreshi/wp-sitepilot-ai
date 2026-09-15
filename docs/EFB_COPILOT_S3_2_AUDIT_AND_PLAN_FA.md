# ممیزی و برنامه اجرای S3.2 — EFB Copilot

تاریخ ممیزی: ۲۰۲۶-۰۹-۱۰  
مرجع بصری: `mockups/design_handoff_admin_redesign/EFB Builder Chat.dc.html`  
سطح واقعی: ویرایشگر افزونه Easy Form Builder در `page=Emsfb` و `page=Emsfb_create`

## تصمیم معماری

موکاپ، یک ویرایشگر نمایشی EFB را برای توضیح سناریوها بازسازی کرده است؛ محصول نباید آن ویرایشگر نمایشی را دوباره بسازد. سطح canonical، پنجره Copilot تزریق‌شده روی ویرایشگر واقعی EFB است. سناریوسوییچر موکاپ، داده‌های نمونه و کنترل‌های نمایشی وارد محصول نمی‌شوند.

صفحه پنهان سازگاری EFB در افزونه حفظ می‌شود، اما عضو منوی ده‌تایی اصلی نیست و جایگزین پنجره موکاپ محسوب نمی‌شود.

## نگاشت موکاپ به قابلیت واقعی

| قابلیت موکاپ | وضعیت فنی پیش از S3.2 | تصمیم |
|---|---|---|
| FAB پایین صفحه و پنجره شناور | موجود، اما ابعاد و ظاهر قدیمی | بازطراحی دقیق روی ویرایشگر واقعی |
| گفتگو و quick chips | REST و provider واقعی موجود | حفظ رفتار و بازطراحی UI |
| تشخیص فیلد انتخاب‌شده | وجود نداشت؛ فقط همه فیلدها ارسال می‌شدند | bridge روی DOM واقعی + اعتبارسنجی دوباره سمت سرور |
| اعمال تغییر روی canvas | allowlist و validator موجود | حفظ؛ نتیجه هر operation و مالکیت Save شفاف می‌ماند |
| پاسخ how-to | پایگاه دانش محدود و واقعی موجود | کارت help موکاپ با همان داده واقعی |
| قفل EFB Pro | license و guard واقعی موجود | کارت Pro و CTA رسمی EFB |
| ساخت فرم جدید | proposal و approval یک‌بارمصرف موجود | حفظ؛ هیچ ساختی بدون Approve انجام نمی‌شود |
| میکروفن composer | ضبط/STT واقعی موجود | transcript-first؛ متن قبل از ارسال قابل بازبینی است |
| مکالمه صوتی realtime تمام‌صفحه | transport/session صوتی realtime وجود ندارد | نمایش داده نمی‌شود؛ دکمه header میانبر همان ضبط transcript-first است |
| Save تغییرات فرم باز | Save متعلق به EFB است | Copilot فقط canvas زنده را تغییر می‌دهد و کاربر دکمه Save خود EFB را می‌زند |

## قابلیت‌های افزونه که موکاپ برایشان UI ندارد

- صفحه پنهان diagnostics/compatibility افزونه برای EFB.
- endpointهای قدیمی `plan`، `customize` و `save` که برای سازگاری API نگه داشته شده‌اند؛ مسیر canonical گفتگو از `chat` استفاده می‌کند.
- Activity Log و trace فنی درخواست/اعمال operation؛ مقصد آن صفحه Activity Log اصلی است و داخل پنجره شلوغ نمی‌شود.
- کاتالوگ کامل propertyهای فیلد/فرم و تبدیل ساختاری EFB؛ این‌ها قرارداد اجرایی داخلی هستند، نه کنترل UI جداگانه.

## قرارداد DOM ویرایشگر EFB — تأیید T00 (۲۰۲۶-۰۹-۱۵)

منبع: source فعلی `easy-form-builder` نسخهٔ 4.2.0 (HEAD `63871604`، سپس `031bfa75` که فقط `readme.txt` را عوض کرد)، فقط‌خواندنی. اجرای اول فقط source را ثابت کرد. اجرای دوم (همان روز، پس از فعال شدن واقعی هسته) همین قرارداد را در runtime هم با probe فقط‌خواندنی تأیید کرد: exit 0، 19 PASS، صفر pageerror، console error و پاسخ ناموفق، و cleanup تأییدشده. شاهدها: `test-artifacts/efb-env/20260915-t00/efb-dom-contract.txt` و `test-artifacts/efb-env/20260915-t00-runtime/`.

| قرارداد | شاهد در EFB | مصرف در افزونه | نتیجه |
|---|---|---|---|
| global واژگانی `valj_efb` | `includes/admin/assets/js/new-efb.js:16` (`let valj_efb = [];` در سطح بالا)، enqueue در `includes/admin/class-Emsfb-create.php:324` و `includes/admin/class-Emsfb-panel.php:319`؛ `admin-efb.js:5853` فقط متغیر محلی درون تابع است | `assets/admin/efb-copilot.js:1،106` (`typeof valj_efb`) | بدون تغییر. محل تعریف `new-efb.js` است، نه `admin-efb.js` که رودمپ نوشته بود |
| wrapper فیلد `.efbField[data-id="<id>-id"]` | `admin-efb.js:7162` (`<section>` در حالت ویرایش) و `admin-efb.js:7164` (`<div>`) | `efb-copilot.js:130-131،148-152`؛ `tests/efb_copilot_browser_check.cjs:51،67` | شناسه بدون تغییر. commit `26515b6f` (۲۰۲۶-۰۹-۱۴) تگ غلط `<setion>` را به `<section>` اصلاح کرد؛ هر دو wrapper همان `data-id` را دارند |
| کنترل‌های درونی `.efbField[data-id="<id>-el"]` | `admin-efb.js:6386،6396،6463،7118`؛ `pro_els-efb.js:225،699،817` | `efb-copilot.js:148-151`: `closest()` روی خود کنترل متوقف می‌شود و چون پسوند `-id` نیست، انتخابی انجام نمی‌شود | runtime: 3 کنترل درونی (`input` و `textarea`) با کلاس `efbField`، و `closest()` از آن‌ها `<id>-el` برمی‌گرداند. اما کلیک Playwright روی input فیلد دوم در Chrome، فیلد را انتخاب کرد و banner نشان داد، احتمالاً چون کنترل در حالت ویرایش `disabled` است. ریسک در عمل بازتولید نشد؛ assertion دائمی در T01 |
| stepها `.efbField[data-id="<step>"]` بدون پسوند | `admin-efb.js:6256-6257،6626` | شرط پسوند `-id` در `efb-copilot.js:151` آن‌ها را کنار می‌گذارد | درست؛ step فیلد انتخاب‌پذیر نیست |
| کلاس انتخاب EFB `field-selected-efb` | `admin-efb.js:4502-4510` (افزودن روی `[data-id="${activeEl_efb}"]`) و `4516-4518` (حذف) | افزونه این کلاس را نمی‌خواند و کلاس مستقل `aisaautopilot-efb-field-selected` می‌گذارد | بدون تغییر؛ تداخل ندارد |
| slug صفحه‌ها `Emsfb` و `Emsfb_create` | `includes/admin/class-Emsfb-admin.php:170-178`، `includes/admin/class-Emsfb-create.php:36` | `includes/Efb/EfbBuilderButton.php:30` | بدون تغییر؛ runtime هر دو HTTP 200 |
| تابع EFB مصرف‌شده | `editFormEfb` | `efb-copilot.js:327` (تنها فراخوانی مستقیم تابع EFB) | runtime: function |
| لایهٔ تازهٔ steps/progress (commit `097de0b8`) | کلیدهای `valj_efb[0].steps_style` (`classic/circles/pills/chevrons`) و `progress_style` (`classic/bar/segments/ring`)، fallback `classic` در `steps-progress-builder-efb.js:147-158` و `functions.php:356-370`؛ picker در `val-efb.js:892،903`؛ writer در `admin-efb.js:4931-4951`؛ assetها در `class-Emsfb-admin.php` | افزونه این کلیدها را نمی‌شناسد؛ کاتالوگ آن‌ها را ندارد | runtime: هر پنج global و CSS/runtime/builder بارگذاری شده؛ canvas ویرایش همچنان step را با `.efbField.stepNavEfb[data-tag="steps"]` رسم می‌کند (1 عدد) و `.efb-sp` فقط در preview و front است (0 در canvas)، پس bridge تأثیر نمی‌پذیرد. شکاف کاتالوگ ← T26 و T27 |
| `show_icon` و `show_pro_bar` | `admin-efb.js:2122-2130`: `true` یعنی پنهان کردن ردیف stepها («dontShowIconsStepsName») و progress bar («dontShowProgressBar») | کاتالوگ آن‌ها را «button icon visibility» و «EFB branding/pro bar» توصیف کرده است | نقص کاتالوگ ← T26 |
| palette `fields_efb` | `val-efb.js:139-195`: 48 نوع، بدون تغییر از commit `03214035` (۲۰۲۶-۰۸-۱۷)؛ `persiaPay` فقط در `fa_IR` (`val-efb.js:2303`) | `efb-capability-catalog.json` `field_types`: 68 نوع | runtime: هر 48 نوع پوشش دارند؛ نوع تازه‌ای نیست |

نتیجه: شناسه‌های قرارداد bridge در 4.2.0 تغییر نکرده‌اند و در runtime روی EFB فعال تأیید شدند، پس task اصلاح bridge پیش از T01 لازم نیست. T00 بسته شد. لایهٔ تازهٔ steps/progress به قرارداد DOM آسیب نمی‌زند، اما کاتالوگ AI را کهنه کرده است و به T26 و T27 سپرده شد.

### نتیجهٔ browser matrix روی EFB فعال — T01 (۲۰۲۶-۰۹-۱۵)

| سنجه | نتیجه |
|---|---|
| `node tests/efb_copilot_browser_matrix.cjs` | exit 0؛ `en_US` 31 PASS / 0 FAIL و `fa_IR` 31 PASS / 0 FAIL؛ پاک‌سازی fixture و کاربر تأیید شد |
| 429 روی `/efb/chat` | منشأ: `usage_cap_reached` خود افزونه (`UsageCappedProvider`). کلید واقعی در WordPress Connectors است و سقف مصرف پر است. check اکنون `/efb/chat` را با پاسخ 400 `provider_not_configured` کنترلر mock می‌کند |
| `/efb/log` | `[200,200]` برای `chat_submitted` و `chat_response_error` در هر دو locale؛ callback `client_log` در مرورگر تأیید شد |
| کلیک کنترل درونی `-el` | input `readonly` با `pointer-events:none` است. عنصر رو در مرکز آن `div#<id>-f` درون wrapper `<id>-id` است، پس کلیک واقعی فیلد را انتخاب و highlight می‌کند. assertion دائمی در check است |
| voice | `getUserMedia` رد می‌شود؛ پیام «میکروفن رد شد» دیده می‌شود، auto-send و upload صوت رخ نمی‌دهد |
| RTL و 390px | جهت `rtl` در `fa_IR`، پنل در `x=24`؛ در 390×844 داخل viewport و بدون overflow |

یافته‌های باز:
- رکورد client log هم‌زمان با chat در baseline از `aisaautopilot_logs` افتاد (D-09).
- انتخاب دوبارهٔ همان فیلد پس از Clear پیام «You selected …» تکراری می‌سازد (T04).

شاهد: `test-artifacts/efb-copilot/20260915-t01/`.

## ریزتسک‌ها و گیت‌ها

| ID | کار | وضعیت | گیت پذیرش |
|---|---|---|---|
| S3.2.0 | تثبیت قرارداد selected-field و voice | در حال اجرا | context انتخاب‌شده فقط پس از تطبیق با فهرست پاک‌سازی‌شده وارد prompt شود؛ voice خودکار send نکند |
| S3.2.1 | بازسازی markup و ظاهر FAB/chat | در حال اجرا (browser T01: پنل 388×576 و 390px پاس؛ مقایسهٔ بصری در T04) | ابعاد/فاصله/رنگ/هدر/composer مطابق موکاپ، بدون تداخل با EFB |
| S3.2.2 | اتصال انتخاب فیلد واقعی | در حال اجرا (browser T01 پاس، از جمله کلیک واقعی `-el`؛ context prompt سمت سرور در T02) | click روی field، banner، Clear، context prompt و reset پاس |
| S3.2.3 | هم‌راستاسازی help/Pro/operation cards | منتظر | داده واقعی، CTA واقعی و عدم ادعای موفقیت کاذب |
| S3.2.4 | ورودی صوتی صادقانه | در حال اجرا (T01: رد میکروفن، نبود auto-send و نبود upload پاس؛ ضبط و transcript با mock در T04) | ضبط، توقف، transcript قابل ویرایش، بدون auto-send/realtime جعلی |
| S3.2.5 | RTL/responsive/accessibility/browser QA | در حال اجرا (T01: en/fa، 390px، Escape/focus و console صفر پاس؛ keyboard کامل در T04) | en/fa، desktop/390px، focus/Escape، overflow و console صفر |
| S3.2.6 | regression و گزارش اجرا | منتظر | syntax، PHPCS، PHPStan، PHPUnit، Phase 1 و گزارش نهایی سبز |

## برآورد

برآورد اولیه این بسته با تست کامل، حدود ۱ تا ۲ روز کاری بود. اجرای فعلی مرحله‌ای است و هر ریزتسک فقط بعد از عبور از گیت خودش بسته می‌شود؛ زمان تقویمی جایگزین شواهد تست نیست.

