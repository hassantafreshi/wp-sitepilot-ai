# گزارش اجرای S6 — بازآرایی IA و Form Studio

تاریخ پایان: ۲۰۲۶-۰۹-۱۸
مرجع: دستور مستقیم مالک ۲۰۲۶-۰۹-۱۷ (D-23)؛ ظاهر هر صفحه همچنان از `mockups/design_handoff_admin_redesign/AutoPilot Admin.dc.html` و `Form Composer.dc.html` پیروی می‌کند. برنامه و ریزتسک‌ها: `OVERVIEW_FORM_STUDIO_S6_AUDIT_AND_PLAN_FA.md`.

## نتیجه

منوی AutoPilot از ده آیتم به **هشت** رسید، به ترتیب D-23: Overview، Setup & Connection، Integrations، Site Knowledge، Answer Desk، Store Copilot، Content Studio، Form Studio. Usage & Cost، Health و Activity Log چهار تب بالای Overview شدند و هرکدام slug، کلاس و نشانی قدیمی خود را نگه داشتند (alias پنهان با مالک Overview). Form Composer با نام تازهٔ «Form Studio» (فارسی: «استودیوی فرم») صفحهٔ اصلی مستقل شد و لینک عمیق قدیمی `page=aisaautopilot-form-builder&mode=forms` همان صفحه را بدون redirect نشان می‌دهد. قرارداد JSON فرم‌ها با `form_structer` در Easy Form Builder از پیش سازگار بود و فقط مستند شد (T55). کل این IA در T56 برای نخستین بار در مرورگر واقعی، در `en_US` و `fa_IR` و در 1440×960 و 390×844 سنجیده شد.

## تغییرات فنی و UI

- **T54:** `OverviewNavigation` (نوار تب مشترک)، `AdminMenu` (سه alias تازه در `ALIAS_OWNERS` به Overview و `SLUG_FORM_STUDIO`)، `CopilotPage::render_forms()`، تغییر رشتهٔ نمایشی «Form Composer» به «Form Studio» در ۸ فایل PHP. شناسه‌های داخلی (`form_composer_enabled`، id ماشینی `form-composer`، کلاس‌های `sp-fc-*`) عمداً دست نخوردند.
- **T55:** بخش «قرارداد JSON فرم و سازگاری با Easy Form Builder» در `docs/technical/FORMS_CONTENT_STORE_FA.md`؛ بدون تغییر کد.
- **T56 — آزمون تازه:** `tests/admin_ia_browser_check.cjs` و `tests/admin_ia_browser_matrix.cjs` (بدون fixture؛ فقط کاربر QA موقت). matrix اگر حساب QA مشترک در دست job دیگری باشد، اجرا را با exit 2 رد می‌کند.
- **T56 — سه نقص واقعی که matrix و بازبینی تصویری پیدا کردند و رفع شدند:**
  1. **ترجمه:** تغییر نام T54 شش msgid تازه ساخته بود که در PO نبودند، پس در `fa_IR` آیتم منو، عنوان صفحه، کارت Integrations، ردیف Home و پیام‌های خطا «Form Studio» انگلیسی بودند؛ برچسب `aria-label` نوار تب («Overview sections») هم ترجمه نداشت. هفت ورودی به PO افزوده و MO با `scripts/compile-mo.php` دوباره ساخته شد. اسکن کامل msgidهای `includes/` با xgettext و msgmerge در scratch هیچ رشتهٔ بی‌ترجمهٔ دیگری نشان نداد.
  2. **فاصله:** نوار تب بیرون از `.sp-pagebar` است و بدنه پس از bar padding بالا ندارد، پس ردیف تب به نخستین کارت چسبیده بود (فاصلهٔ 0px) در هر چهار تب. `.sp-overview-tabs` اکنون `margin-block-end: 24px` دارد (همان فاصلهٔ 24px موکاپ).
  3. **موبایل:** در 390px چهار تب 3 + 1 می‌شکستند. در ≤520px اکنون شبکهٔ 2 × 2 با تب‌های هم‌اندازه و ناحیهٔ لمس 44px است، هم‌الگو با ردیف تب سطح‌های دیگر.

## اختلاف‌های آگاهانه با موکاپ

- منوی کناری موکاپ ده آیتم دارد (Usage & Cost، Health، Activity Log جدا). افزونه طبق D-23 هشت آیتم دارد؛ D-23 بر ترتیب تخت موکاپ مقدم است.
- موکاپ برای Overview هیچ نوار تبی ندارد؛ نوار تب از D-23 آمده و با کلاس‌های موجود `sp-tabs--boxed` ساخته شده است.
- عنوان صفحه در موکاپ «Form Composer» است؛ افزونه «Form Studio» / «استودیوی فرم» (D-23). ردیف «Form Composer» در «Running now» موکاپ Overview هم در افزونه «Form Studio» است.
- قاب موکاپ Form Composer در سناریوی «Form built» گرفته شده؛ افزونه در بارگذاری اول حالت خالی compose را نشان می‌دهد (ساختار header، canvas و Form Assistant یکی است).
- داده‌های نمونهٔ موکاپ (412 پرسش، $6.40 و…) در افزونه جای خود را به دادهٔ واقعی سایت محلی داده‌اند (مثلاً سقف توکن پر و «Generation paused»).

## محدودیت‌های باز

- **برخورد QA با نشست هم‌زمان (افشا):** اجرای چهارم matrix هم‌زمان با `widget_customise_browser_matrix.cjs` نشستی دیگر اجرا شد. هر دو حساب QA مشترک `aisaautopilot_local_browser_user` را به کار می‌برند: matrix من locale آن را به `fa_IR` برگرداند و در `finally` حساب را حذف کرد، پس اجرای چهارم (68 FAIL) و احتمالاً اجرای آن نشست بی‌اعتبارند. به آن نشست پیام داده شد تا دوباره اجرا کند؛ نگهبان exit 2 در matrix افزوده شد. اجرای معتبر پس از خالی شدن محیط گرفته شد.
- عنوان ردیف «Embeddings» در Health در `fa_IR` لاتین («Embedding») و سمت چپ ستون عنوان می‌نشیند؛ مربوط به IA نیست و برای T17 (ممیزی دسترس‌پذیری و RTL) ثبت شد.
- نگهبان matrix فقط هنگام شروع می‌سنجد؛ jobی که پس از شروع این matrix آغاز شود هنوز می‌تواند همان حساب را بردارد. راه‌حل ریشه‌ای (نام حساب جدا برای هر job در `local_browser_user.php`) به T15/T17 سپرده شد، چون همهٔ matrixها را لمس می‌کند.

## شواهد تست

| گیت | نتیجه | شاهد |
|---|---|---|
| T54 full | 9/9: php-lint 230/230، node-check 37/37، PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit 149 / 514، menu 5/0، assets 20/0، render 18/0، Phase 1 45/0 | `test-artifacts/gates/20260917-093847-full` |
| T54 integrationها | content studio navigation 5/0، form composer page 4/0، integrations hub 14/0، activity timeline 25/0، overview metrics 4/0، usage cap response 6/0، form proposal context 3/0 | رودمپ، جزئیات T54 |
| T56 admin IA matrix | **652 PASS / 0 FAIL** و `MATRIX RESULT PASS` (exit 0): `en_US` 316 و `fa_IR` 336؛ صفر console error، صفر pageerror، صفر HTTP≥400 در هر دو؛ فاصلهٔ تب تا کارت 24px در هر ۸ ترکیب؛ شبکهٔ 2 × 2 با تب‌های 44px در 390 | `test-artifacts/admin-ia/20260918-t56/matrix-run5.txt` |
| T56 مسیر اجرا | اجرای 1 (فقط en_US) 304/0؛ اجرای 2: en_US 0 FAIL و fa_IR 3 FAIL (ترجمهٔ Form Studio)؛ اجرای 3 پس از ترجمه 628/0؛ اجرای 4 پس از CSS بی‌اعتبار (برخورد، بالا)؛ اجرای 5 نخست با نگهبان رد شد (exit 2، job دیگری حساب را داشت) و پس از خالی شدن محیط در 20:08 اجرا و 20:17 تمام شد | `…/20260918-t56/run1-en_US.txt`، `matrix-run2.txt` تا `matrix-run5.txt` |
| T56 گیت ایستا | smoke **8/8**: php-lint 233/233، node-check 42/42، PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit 149 / 514، menu 5/0، assets 20/0، render 18/0 | `test-artifacts/gates/20260918-202017-smoke` |
| ترجمه | MO با 2324 ترجمه از 2465 ورودی PO (141 بی‌ترجمهٔ کهنه)؛ 7 msgid تازه؛ `msgfmt --check` سالم | خروجی `scripts/compile-mo.php` |
| پاک‌سازی | «Local browser QA account removed.» در `finally` اجرای 5؛ preflight درست پیش از آن (19:59) WARNINGS 0 و BLOCKERS 0. preflight پایانی (20:25) دو هشدار حساب QA دارد که **از آنِ matrix در حال اجرای T15 در نشست دیگر است** (فرایند `release_smoke_browser_matrix.cjs`)، نه این task؛ طبق قاعده دست نخورد. `settings_stored=False`، locale `en_US` | `…/20260918-t56/preflight-final.txt` |

## خروجی visual QA

تصویرها: `test-artifacts/admin-ia/<locale>/` — `main-<slug>-1440.png` و `-390.png` برای هر ۸ صفحه، `overview-tab-<slug>-1440|390.png` برای سه تب پنهان، `legacy-form-builder-mode-forms-1440|390.png` و `overview-tabs-focus-1440.png`. قاب‌های موکاپ از `test-artifacts/mockup-parity/mockup/` (گرفته‌شده ۲۰۲۶-۰۹-۱۵؛ فایل‌های موکاپ از ۲۰۲۶-۰۸-۲۶ تغییر نکرده‌اند): `admin-home-default.png`، `admin-usage-default.png`، `admin-health-default.png`، `admin-logs-default.png`، `composer-form-ltr-1240.png`، `composer-form-rtl-390.png`.

| جفت | نتیجه |
|---|---|
| Overview ↔ `admin-home-default` | ساختار یکی (سرتیتر و chip اتصال، چهار کارت آمار، Needs you، Running now با شش ردیف)؛ تفاوت‌ها: نوار تب (D-23)، ردیف Form Studio، دادهٔ واقعی. پیش از اصلاح، تب‌ها به بنر چسبیده بودند (رفع شد). |
| Usage & Cost ↔ `admin-usage-default` | ساختار یکی؛ نوار تب بالای کارت‌ها؛ منوی کناری Overview را برجسته می‌کند نه Usage & Cost (D-23). |
| Health، Activity Log ↔ قاب‌های موکاپ | ساختار یکی؛ همان دو تفاوت D-23. |
| Form Studio ↔ `composer-form-ltr-1240` / `composer-form-rtl-390` | header، کارت مقصد ایمیل، canvas و Form Assistant یکی؛ عنوان «Form Studio» / «استودیوی فرم» و حالت خالی به‌جای «Form built». |
| منوی کناری | هشت آیتم به ترتیب D-23 در هر دو زبان؛ در `fa_IR` همه فارسی و راست‌به‌چپ. |
