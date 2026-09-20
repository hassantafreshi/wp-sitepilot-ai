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
- رکورد client log هم‌زمان با chat در baseline از `aisaautopilot_logs` افتاد (D-09). ← با T28 رفع شد؛ بخش پایین را ببینید.
- انتخاب دوبارهٔ همان فیلد پس از Clear پیام «You selected …» تکراری می‌سازد (T04). **بسته شد در T04:** پیام فقط وقتی تکرار می‌شود که فیلد تازه‌ای انتخاب شود یا از آخرین اعلام یک نوبت گفت‌وگو گذشته باشد.

شاهد: `test-artifacts/efb-copilot/20260915-t01/`.

### همگام‌سازی steps/progress و نوشتن اتمی لاگ — T26، T27، T28 (۲۰۲۶-۰۹-۱۵)

مالک D-08 (بله) و D-09 (گزینهٔ الف) را تأیید کرد.

| سنجه | نتیجه |
|---|---|
| T26 کاتالوگ | `efb_version_target` ← `4.2.x`؛ `show_icon` و `show_pro_bar` یعنی پنهان کردن ردیف مراحل و نوار پیشرفت (`class-Emsfb-public.php:1623-1624`)؛ property نادرست `show_button_icon` حذف و با `hide_steps_row` و `hide_progress_bar` جایگزین شد؛ `steps_style` و `progress_style` با enum و fallback `classic`؛ جدول entry از `emsfb_message` به `emsfb_msg_`؛ how-to `multi_step` به‌روز شد |
| T26 گیت | PHPUnit `test_steps_row_and_progress_bar_settings_follow_efb_4_2`؛ static 5/5 با PHPUnit 137 / 470، PHPCS 130/130 (0/0)، PHPStan 0 |
| T27 سرور | allowlist همان `ai_writable`؛ enum از کاتالوگ؛ PHPUnit `test_step_and_progress_styles_are_writable_only_with_efb_values` پاس |
| T27 client | enum دوباره در `efb-copilot.js` سنجیده می‌شود؛ نام canonical ذخیره می‌شود؛ پیام نتیجه محلی‌شده (شش msgid تازه، MO با 1235 entry)؛ `syncStepStyleControls()` پنل تنظیمات باز EFB را همگام می‌کند؛ Save فقط با دکمهٔ EFB |
| T27 browser | matrix با exit 0: `en_US` 41 PASS / 0 FAIL و `fa_IR` 41 PASS / 0 FAIL، با پاک‌سازی تأییدشده. پاسخ `ops` mock‌شده `valj_efb[0]` را به pills و ring و نمایش ردیف و نوار تغییر داد؛ `zigzag` در client رد شد؛ پنل تنظیمات باز EFB بدون reload همگام شد؛ preview خود EFB ردیف pills و ring را در LTR و RTL رسم کرد؛ صفر POST بیرون از REST افزونه، پس Save خودکار رخ نداد |
| regression پس از T26 تا T28 | `run-gates.ps1 -Tier release` 52/52 (static 5/5، smoke 3/3، Phase 1 45/0، 43 integration suite) |
| T28 لاگ | `Logger::log` زیر قفل `GET_LOCK` و با خواندن مستقیم ردیف می‌نویسد. `tests/wordpress_logger_concurrency_integration.php` پیش از اصلاح 6/2 (2 از 3 و 24 از 90 entry) و پس از اصلاح در دو اجرا 6/0 (3 از 3 و 90 از 90) |

شاهد: `test-artifacts/logger-concurrency/20260915-t28/`، `test-artifacts/efb-copilot/20260915-t27/`، `test-artifacts/gates/20260915-053701-static` و `test-artifacts/gates/20260915-054818-release`.

یافته‌های باز: `Not applied: …` و `Applied to the canvas…` هنوز انگلیسی‌اند (T03)؛ نام سبک‌ها و «Step 1 of 2 · 50% complete» در preview از ترجمهٔ خود EFB می‌آیند و در `fa_IR` انگلیسی‌اند (سمت EFB)؛ پس از New chat تنها پیام باقی «You selected …» است، نه welcome (T04). **بررسی T04:** این رفتار درست است، چون انتخاب فیلد پس از New chat زنده می‌ماند و بنر «در حال کار روی …» هم باز است؛ assertion به‌جای شمردن، اکنون هم‌خوانی پیام با انتخاب زنده را می‌سنجد.

### localization باقی‌ماندهٔ Copilot — T03 (۲۰۲۶-۰۹-۱۶)

همهٔ رشته‌های نمایشی که `assets/admin/efb-copilot.js` در مرورگر می‌سازد، اکنون از `cfg.i18n` می‌آیند و fallback انگلیسی دارند. کلیدها در `EfbBuilderButton::enqueue()` با `__()` و comment مترجم ثبت شده‌اند. `t()` حالا placeholder شماره‌دار (`%1$s`، `%2$s`) را هم می‌پذیرد و با تابع جایگزین، دنباله‌هایی مانند `$&` در برچسب فیلد را دست‌نخورده می‌گذارد.

| رشتهٔ انگلیسی | کلید `i18n` | ترجمهٔ فارسی |
|---|---|---|
| Invalid server response. | `invalidResponse` | پاسخ سرور نامعتبر است. |
| Sorry, something went wrong. | `genericError` | متأسفم، مشکلی پیش آمد. |
| The request could not be completed. | `requestFailed` | درخواست کامل نشد. |
| The form could not be created. Please try again. (پیش‌تر `Failed.`) | `createFailed` | فرم ایجاد نشد. لطفاً دوباره تلاش کنید. |
| Open a form in the editor first (…), then ask me to change it. | `openFormFirst` | ابتدا یک فرم را در ویرایشگر باز کنید (…)، سپس از من بخواهید آن را تغییر دهم. |
| Done. | `done` | انجام شد. |
| Renamed the form to “%s” | `formRenamed` | نام فرم به «%s» تغییر کرد |
| Updated the confirmation message | `confirmationSet` | پیام تأیید ارسال به‌روز شد |
| Renamed “%1$s” to “%2$s” | `fieldRenamed` | نام «%1$s» به «%2$s» تغییر کرد |
| Made “%s” required / optional | `fieldRequired`، `fieldOptional` | «%s» اجباری شد / «%s» اختیاری شد |
| Set the placeholder of “%s” | `placeholderSet` | متن نگهدارندهٔ «%s» تنظیم شد |
| Set the width of “%1$s” to %2$s | `widthSet` | عرض «%1$s» روی %2$s تنظیم شد |
| full width / half width / one third / one quarter | `widthFull`، `widthHalf`، `widthThird`، `widthQuarter` | تمام‌عرض / نیم‌عرض / یک‌سوم / یک‌چهارم |
| Set the help text of “%s” | `helpTextSet` | متن راهنمای «%s» تنظیم شد |
| Updated %1$s for “%2$s” | `fieldSettingSet` | تنظیم %1$s برای «%2$s» به‌روز شد |
| Updated the form setting “%s” | `formPropertyUpdated` | تنظیم فرم «%s» به‌روز شد |
| Added “%s” / New field | `fieldAdded`، `newFieldLabel` | «%s» اضافه شد / فیلد تازه |
| Added the step “%s” / Step %s | `stepAdded`، `stepDefaultName` | مرحلهٔ «%s» اضافه شد / مرحلهٔ %s |
| Removed “%s” | `fieldRemoved` | «%s» حذف شد |
| Added the option “%1$s” to “%2$s” | `optionAdded` | گزینهٔ «%1$s» به «%2$s» اضافه شد |
| Removed the option “%1$s” from “%2$s” | `optionRemoved` | گزینهٔ «%1$s» از «%2$s» حذف شد |
| No change was applied: %s. | `noChangeApplied` | هیچ تغییری اعمال نشد: %s. |
| Not applied: %s | `notApplied` | اعمال نشد: %s |
| Applied to the canvas. Review it, then click Easy Form Builder’s green Save button to keep the changes. | `appliedSaveNote` | روی بوم اعمال شد. آن را بازبینی کنید، سپس برای ماندگار شدن تغییرها دکمهٔ سبز ذخیرهٔ Easy Form Builder را بزنید. |
| The Easy Form Builder canvas could not redraw, so the editor reloads now. … trace %s is in … → Activity Log. | `canvasRefreshFail` | بوم Easy Form Builder دوباره رسم نشد، پس ویرایشگر اکنون دوباره بارگذاری می‌شود. … ردیاب %s در … ← لاگ فعالیت است. |
| 17 دلیل اعمال‌نشدن (کد `field_not_found`، `invalid_form_property_value`، `option_not_found`، `exception:*`، خطای render و …) | `reason*` (از `reasonInvalidOperation` تا `reasonUnknown`) | جملهٔ فارسی، مثلاً «فیلد در این فرم پیدا نشد» و «Easy Form Builder این مقدار را نمی‌پذیرد» |

- **قرارداد تازهٔ یادداشت‌ها:** هر یادداشت نتیجه `data-sp-efb-note` دارد (`applied`، `failed`، `no-change`، `server`، `save-ownership`، `render-failed`). یادداشت شکست `data-sp-efb-reason` هم دارد، با کد پایدار و بدون متن خطای خام. کد و متن خطای JS فقط در `clientLog` می‌ماند و روی رابط دیده نمی‌شود.
- **ستون عرض پیش‌نمایش:** به‌جای حرف اول انگلیسی (`f`، `h`) درصد می‌گیرد (`100%`، `50%`، `33%`، `25%`)، همان مقدارهای `WIDTH` که در EFB ذخیره می‌شوند.
- **عمداً بیرون از scope:**
  - متن‌های `clientLog`، که داخلی‌اند؛
  - متن‌های `history`، که فقط به‌عنوان زمینهٔ گفت‌وگو برای مدل فرستاده می‌شوند و روی رابط دیده نمی‌شوند؛
  - پیام‌های سرور (`reply`، `message`، `errors`)، که از پیش در `EfbCopilotController` با `__()` محلی‌اند.
- **اختلاف‌ها و محدودیت‌های صادقانه:**
  - در `fieldSettingSet` و `formPropertyUpdated` نام تنظیم همان کلید کاتالوگ است (مثلاً `button color`)، چون کاتالوگ برچسب ترجمه‌شده ندارد.
  - `stepDefaultName` محتوای فرم است و به زبان مدیر نوشته می‌شود. در عمل فقط fallback است، چون `EfbCustomizeSkill` عملیات `add_step` بدون label را کنار می‌گذارد.
  - نام سبک‌ها و «Step 1 of 2 · 50% complete» در preview همچنان از ترجمهٔ خود EFB می‌آیند (سمت EFB).
  - `fieldsCount` هنوز یک صورت جمع دارد. شمار در مرورگر معلوم می‌شود و فارسی یک صورت دارد؛ `_n` در T18 بررسی می‌شود.

| گیت T03 | نتیجه |
|---|---|
| grep نهایی | هیچ رشتهٔ نمایشی hard-coded نماند؛ تنها متن‌های باقی‌مانده `clientLog` و `history` هستند که روی رابط دیده نمی‌شوند |
| syntax | `node --check` روی `assets/admin/efb-copilot.js` و `tests/efb_copilot_browser_check.cjs` با exit 0؛ `php -l` سالم |
| PHPCS و PHPStan | `includes/Efb/EfbBuilderButton.php` 0 error و 0 warning (۷۰ هشدار alignment ناشی از کلیدهای تازه با `phpcbf` روی همان فایل رفع شد)؛ PHPStan 0 |
| static | 5/5: php-lint 221/221، node-check 35/35، PHPCS 130/130 با 0/0، PHPStan 0، PHPUnit 137 / 470 (برابر خط مبنا) — `test-artifacts/gates/20260916-020405-static` |
| ترجمه | MO با **2187** entry ترجمه‌شده از 2329 msgid در PO (آستانهٔ گیت 1229)؛ `msgfmt --check` سالم؛ هر ۸۷ رشتهٔ i18n این سطح msgstr فارسی ناتهی دارد. عدد «2329 entry» که در ۲۰۲۶-۰۹-۱۶ ثبت شده بود شمار merge بود نه شمار نوشته‌شده؛ در وارسی دوبارهٔ همان روز اصلاح شد (پایین) |
| browser matrix | اجرای دوم exit 0 با `en_US` 42 PASS / 0 FAIL و `fa_IR` 42 PASS / 0 FAIL و پاک‌سازی تأییدشده. اجرای اول به timeout محیطی `page.goto` خورد (پیش از هر check؛ سایت محلی کند است) و هیچ assertion یا timeout سست نشد. در `fa_IR` نتیجه‌ها، دلیل رد enum و یادداشت Save فارسی بودند — `test-artifacts/efb-copilot/20260916-t03/` |

دو assertion که متن انگلیسی `invalid form property value` را می‌سنجیدند، عمداً به hook `data-sp-efb-reason` و زبان رابط تغییر کردند و یک assertion تازه برای فارسی بودن یادداشت مالکیت Save افزوده شد (۴۱ → ۴۲ check در هر locale). بدون این تغییر، همان assertionها با ترجمه شدن رشته‌ها در `fa_IR` قرمز می‌شدند.

#### وارسی دوبارهٔ گیت T03 — ۲۰۲۶-۰۹-۱۶

مالک دوباره «T03» را خواست، در حالی که ردیف از پیش **تکمیل** بود. به‌جای اجرای دوبارهٔ کار، گیت دوباره اجرا شد.

| بند گیت | نتیجهٔ وارسی |
|---|---|
| grep نهایی | پابرجا — هیچ انتساب `textContent`/`innerText` با literal نمانده و ۸۹ فراخوانی `t()` در فایل است |
| `node --check` و `php -l` | پابرجا |
| PHPCS متمرکز | `EfbBuilderButton.php` 0 error و 0 warning |
| پوشش i18n | ۸۷ رشتهٔ ترجمه‌پذیر در `EfbBuilderButton.php`؛ ۰ غایب از PO و ۰ msgstr خالی — `test-artifacts/efb-copilot/20260916-t03-reverify/i18n-coverage.txt` |
| شمار MO | **نادرست بود.** `scripts/compile-mo.php` با `count( $mo->entries )` شمار merge را «translated entries compiled» می‌نامید، ولی `MO::export_to_file()` فقط entryهای دارای ترجمه را می‌نویسد. MO واقعی 2187 entry داشت. اسکریپت اصلاح شد و هر سه عدد را چاپ می‌کند؛ کامپایل دوباره MO را با sha256 یکسان گذاشت |
| بی‌ترجمه‌ها | ۱۴۳ msgid بی‌ترجمه بررسی شد: ۱۳۰ کهنه (msgid دیگر در `includes/` و `assets/` نیست؛ T18 با msgmerge پاکشان می‌کند)، ۱۲ زیررشتهٔ اتفاقی، و **۱ زندهٔ واقعی**: `ChatEngine.php:383`. msgstr فارسی افزوده شد و runtime با `switch_to_locale( 'fa_IR' )` هر سه جملهٔ «پاسخی پیدا نشد» را فارسی داد — `test-artifacts/efb-copilot/20260916-t03-reverify/` |

### تکمیل browser و visual QA — T04 (۲۰۲۶-۰۹-۱۶)

`tests/efb_copilot_browser_check.cjs` گسترش یافت (اسکریپت موازی ساخته نشد) و از ۴۲ به **۷۶ check در هر locale** رسید. mockها همان شکل payload کنترلر واقعی را دارند و هیچ provider واقعی صدا زده نشد.

| پوشش تازه | چه چیزی اثبات شد |
|---|---|
| صفر درخواست خودکار | باز کردن پنل و کلیک روی chip هیچ درخواستی به `/aisaautopilot/v1/` نمی‌فرستد (`PLUGIN_REQUESTS_ON_OPEN []`) |
| chipهای prefill | chip فقط composer را پر و focus می‌کند، چیزی در گفت‌وگو ثبت نمی‌کند و `data-sp-efb-chip` دارد |
| زبان واقعی رابط | عنوان، خط زمینه، برچسب FAB، خوشامد، placeholder و aria-label چهار action در `fa_IR` فارسی‌اند (`LOCALE_COPY`) |
| keyboard | focus ring دیدنی روی composer و دکمهٔ کنار آن، Tab به کنترل‌های composer، Shift+Enter خط تازه بدون ارسال، Enter ارسال |
| حالت بدون provider | متن `provider_not_configured` عیناً نمایش داده می‌شود، هیچ یادداشت «اعمال شد» ساخته نمی‌شود و composer دوباره فعال می‌شود |
| کارت how-to | عنوان و هر دو پاراگراف از پاسخ واقعی `answer_help` رندر می‌شوند |
| chipهای پیشنهاد موضوع | یک chip به ازای هر suggestion و prefill بدون ارسال |
| کارت Pro | نام فیلد کنارگذاشته‌شده، پیوند قیمت با `UPGRADE_URL` محلی‌شده و `rel="noopener noreferrer"`؛ CTA ویرایشگر را ترک نمی‌کند و tab بیرونی باز نمی‌کند، بلکه دیالوگ خود EFB را باز می‌کند |
| پیام انتخاب | پاک کردن و انتخاب دوبارهٔ همان فیلد پیام تکراری نمی‌سازد؛ انتخاب فیلد دیگر با نام آن اعلام می‌شود |
| New chat | تنها پیام باقی‌مانده با انتخاب زندهٔ همان لحظه هم‌خوان است و نوار وضعیت میکروفن پاک می‌شود |
| screenshot محدود | به‌جای full-page (که در ویرایشگر EFB بسیار بلند است) از `locator.screenshot()` برای FAB، پنل، بنر انتخاب، کارت how-to، کارت Pro و نمای ۳۹۰px استفاده می‌شود |

**سه اصلاح کوچک محصول در همین task:**

1. **chip دیگر ارسال نمی‌کند.** `chips()` در `assets/admin/efb-copilot.js` به `prefill()` وصل شد. قرارداد `docs/technical/ARCHITECTURE_FA.md` از پیش همین را برای EFB Copilot ادعا می‌کرد و کد با آن هم‌خوان نبود.
2. **پیام انتخاب تکرار نمی‌شود.** `lastIntroFieldId` نگه می‌دارد آخرین فیلدی که اعلام شده است؛ با ارسال یک پیام یا New chat صفر می‌شود.
3. **New chat نوار وضعیت میکروفن را پاک می‌کند**، چون خطای اجازهٔ گفت‌وگوی قبلی پس از پاک شدن transcript دیگر معنا ندارد.

**اختلاف‌های عمدی با `EFB Builder Chat.dc.html` که در بازبینی تصویر ثبت شد:**

| موکاپ | افزونه | دلیل |
|---|---|---|
| chip با کلیک بلافاصله اجرا می‌شود (`runChip`) | chip فقط composer را پر می‌کند | قاعدهٔ «انسان پیش از ارسال می‌بیند و ویرایش می‌کند»، همان رفتار Form Composer و کنسول گفت‌وگو |
| خط زمینهٔ هدر «Contact form · 4 fields» | «در حال ویرایش فرم فعلی» | دادهٔ نام فرم و شمار فیلد در `valj_efb` موجود است ولی هنوز به هدر وصل نشده. T06 فقط سند است، پس این اختلاف در گزارش اجرا باز ثبت و برای مقایسهٔ بصری T16 گذاشته شد |
| کارت how-to با آیکن و گام‌های شماره‌دار | عنوان و پاراگراف‌های نثر | `efb-knowledge.json` پاسخ‌ها را به‌صورت نثر نگه می‌دارد؛ گام شماره‌دار یعنی بازنویسی دانش، نه تغییر رابط |
| کارت کهربایی با قفل برای Pro | یادداشت درون حباب معمولی با دکمهٔ طلایی و پیوند قیمت | محتوا صادقانه‌تر است (نام فیلد کنارگذاشته‌شده و پیوند رسمی)؛ اختلاف فقط بصری است |
| آیکن ارسال کاغذ پرنده و placeholder «Ask for a change, or click a field…» | فلش رو به بالا و «دربارهٔ این فرم بپرسید…» | از S3.2.1 و در همان نوبت ثبت شده است |

**یافتهٔ باز برای T17 (RTL فاز ۴):** حباب پیام `dir` ندارد، پس پاسخی که زبانش با زبان رابط فرق دارد (در این اجرا payload انگلیسی mock در پنل فارسی) با جهت رابط رندر می‌شود و نقطهٔ پایان جمله جابه‌جا دیده می‌شود. در محصول واقعی پاسخ به زبان سایت است، پس این حالت فقط با پاسخ چندزبانه پیش می‌آید. راه‌حل پیشنهادی: `dir="auto"` روی متن حباب، که باید با همهٔ حالت‌های RTL آزموده شود.

| گیت T04 | نتیجه |
|---|---|
| browser matrix | exit 0؛ `en_US` 76 PASS / 0 FAIL و `fa_IR` 76 PASS / 0 FAIL (مجموع 152) — `test-artifacts/efb-copilot/20260916-t04/` |
| console، pageerror و HTTP | صفر خطای غیرمنتظره در هر دو locale؛ تنها 400 مجاز همان `provider_not_configured` خودِ mock است |
| screenshotهای بازبینی‌شده | ۷ تصویر در هر locale: FAB، پنل دسکتاپ، بنر انتخاب، کارت how-to، کارت Pro، preview سبک و پنل ۳۹۰px |
| static | 5/5: php-lint 221/221، node-check 35/35، PHPCS 130/130 با 0/0، PHPStan 0، PHPUnit 137 / 470 — `test-artifacts/gates/20260916-032208-static` |
| پاک‌سازی | `EFB Copilot browser fixture removed.` و `Local browser QA account removed.` در هر سه اجرا؛ preflight پایانی WARNINGS 0 و BLOCKERS 0 |

اجرای اول پیش از هر check شکست: کلیک ورود با timeout پیش‌فرض ۳۰ ثانیهٔ Playwright منتظر navigation ماند. ریشه همان کندی شناخته‌شدهٔ سایت محلی است، پس بودجهٔ کلیک و انتظار URL به ۶۰ ثانیه رسید و assertion دست نخورد (ورود نادرست هنوز روی `wp-login.php` می‌ماند و بلند شکست می‌خورد). fixture و حساب QA در همان اجرا پاک شدند.

### بستن بسته — T06 (۲۰۲۶-۰۹-۱۷)

همهٔ ریزتسک‌های S3.2.0 تا S3.2.6 تکمیل‌اند. گزارش نهایی: `EFB_COPILOT_S3_2_EXECUTION_REPORT_FA.md`. شمارهای پایانی همان regression T05 است: full 9/9 (php-lint 229/229، node-check 37/37، PHPCS 133/133 با 0/0، PHPStan 0، PHPUnit 149 / 514، menu 5/0، assets 19/0، render 18/0، Phase 1 45/0) و release 60/60 (EFB Copilot contract 15/0، Logger concurrency 6/0)، به‌علاوهٔ browser matrix T04 با 76/0 در هر locale.

## ریزتسک‌ها و گیت‌ها

| ID | کار | وضعیت | گیت پذیرش |
|---|---|---|---|
| S3.2.0 | تثبیت قرارداد selected-field و voice | تکمیل (T02: قرارداد سمت سرور selected-field، `/efb/log` و gate در integration با 15/0 پاس؛ T26: کاتالوگ و دانش با EFB 4.2.0 همگام شد؛ T04: voice هرگز خودکار ارسال نمی‌کند و رد میکروفن هیچ آپلودی نمی‌سازد) | context انتخاب‌شده فقط پس از تطبیق با فهرست پاک‌سازی‌شده وارد prompt شود؛ voice خودکار send نکند |
| S3.2.1 | بازسازی markup و ظاهر FAB/chat | تکمیل (browser T01 و T04: پنل 388×576، نمای 390px و مقایسهٔ بصری با موکاپ انجام شد؛ پنج اختلاف عمدی ثبت شد) | ابعاد/فاصله/رنگ/هدر/composer مطابق موکاپ، بدون تداخل با EFB |
| S3.2.2 | اتصال انتخاب فیلد واقعی | تکمیل (browser T01 پاس، از جمله کلیک واقعی `-el`؛ T02: context prompt سمت سرور پاس — ID معتبر با label/type از catalogue پاک‌سازی‌شده، ID ناموجود و شیء client رد، بدون فرم باز حذف؛ T04: پیام تکراری پس از Clear رفع و assert شد) | click روی field، banner، Clear، context prompt و reset پاس |
| S3.2.3 | هم‌راستاسازی help/Pro/operation cards | تکمیل (T26، T27 و T03؛ T04: کارت how-to، chipهای پیشنهاد و کارت Pro با mock سنجیده شدند — CTA ویرایشگر را ترک نمی‌کند و پیوند قیمت محلی و امن است) | داده واقعی، CTA واقعی و عدم ادعای موفقیت کاذب |
| S3.2.4 | ورودی صوتی صادقانه | تکمیل (T01 و T04: رد میکروفن، نبود auto-send، نبود upload و پاک شدن نوار وضعیت با New chat پاس؛ ضبط واقعی و transcript بخش پذیرش محیطی مالک است، چون میکروفن واقعی لازم دارد) | ضبط، توقف، transcript قابل ویرایش، بدون auto-send/realtime جعلی |
| S3.2.5 | RTL/responsive/accessibility/browser QA | تکمیل (T04: en/fa واقعی با متن فارسی، 390×844، Tab و focus ring، Shift+Enter، Enter، Escape و صفر console/pageerror/HTTP؛ 76 check در هر locale) | en/fa، desktop/390px، focus/Escape، overflow و console صفر |
| S3.2.6 | regression و گزارش اجرا | تکمیل (T06 ۲۰۲۶-۰۹-۱۷: `EFB_COPILOT_S3_2_EXECUTION_REPORT_FA.md` نوشته شد و tracker، رودمپ و handoff با همین شمارها بسته شدند؛ از T05 هیچ فایل کد یا آزمونی تغییر نکرده است. T28 تکمیل: lost update لاگ رفع شد؛ T05 تکمیل ۲۰۲۶-۰۹-۱۶: full 9/9 — php-lint 229/229، node-check 37/37، PHPCS 133/133 با 0/0، PHPStan 0، PHPUnit 149 / 514، menu 5/0، assets 19/0، render 18/0، Phase 1 45/0 — و release 60/60 با EFB Copilot contract 15/0 و Logger concurrency 6/0، `test-artifacts/gates/20260916-214733-full` و `20260916-215043-release`) | syntax، PHPCS، PHPStan، PHPUnit، Phase 1 و گزارش نهایی سبز |

## برآورد

برآورد اولیه این بسته با تست کامل، حدود ۱ تا ۲ روز کاری بود. اجرای فعلی مرحله‌ای است و هر ریزتسک فقط بعد از عبور از گیت خودش بسته می‌شود؛ زمان تقویمی جایگزین شواهد تست نیست.

