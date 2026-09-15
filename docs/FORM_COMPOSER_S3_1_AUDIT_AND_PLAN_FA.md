# ممیزی و برنامهٔ S3.1 — Form Composer

تاریخ ممیزی: 2026-09-08

مرجع: `mockups/design_handoff_admin_redesign/Form Composer.dc.html`

## نتیجهٔ مهندسی

موکاپ یک سطح تخصصی واحد است، نه صفحهٔ یازدهم منوی AutoPilot. ساختار آن شامل header محصول، canvas فرم، پنل Form Assistant، وضعیت delivery، handoff به Easy Form Builder و نتیجهٔ created/shortcode است. مسیر فعلی افزونه سه مرحلهٔ واقعی compose → review → result، RESTهای `forms/plan` و `forms/execute`، proposal یک‌بارمصرف ۱۵ دقیقه‌ای و فرم native email-only دارد؛ بنابراین می‌توان ساختار موکاپ را بدون افزودن صفحهٔ اصلی تازه روی همین مسیر بازطراحی کرد.

## نگاشت قابلیت‌ها

| موکاپ | قرارداد واقعی افزونه | تصمیم |
|---|---|---|
| Describe/send و voice | prompt متنی و voice transcription موجود | در footer پنل Assistant مطابق موکاپ قرار می‌گیرد. transcript قبل از ارسال قابل‌ویرایش می‌ماند. |
| empty canvas و example chips | مثال‌های محلی موجود | در canvas اصلی رندر و فقط prompt را prefill می‌کنند؛ اجرای AI خودکار ندارند. |
| email destination | `Settings::form_notification_email()` | مقصد واقعی/ماسک‌شده نشان داده می‌شود؛ AI حق تغییر recipient را ندارد. |
| form preview | plan validate‌شدهٔ provider | فیلدها، title، button و مقصد پیش از create نمایش داده می‌شوند. |
| approval | ActionStore/ActionExecutor تک‌مصرف | دکمهٔ create فقط بعد از preview و approval واقعی فعال است. |
| draft/publish | `default_form_status` | status در proposal ثابت می‌شود تا تغییر Settings بعدی نتیجهٔ دیده‌شده را عوض نکند. publish تأیید اضافه دارد. |
| created + shortcode | نتیجهٔ NativeFormConnector | shortcode واقعی، copy، Open draft و Build another نمایش داده می‌شوند. |
| Advanced → EFB | route و availability واقعی EFB | فقط link/handoff واقعی؛ تبدیل خودکار فرم native به EFB ادعا نمی‌شود. |

## عناصر موکاپ که backend آن‌ها موجود نیست

- انتخاب title/field/button و الصاق reference chip به پیام بعدی؛ endpoint refinement یا patch plan وجود ندارد.
- تاریخچهٔ chat و operationهای `add/set/remove` برای هر revision؛ storage و audit این داده وجود ندارد.
- typing نمایشی، پاسخ‌های مکالمه‌ای چندمرحله‌ای و scenario switch؛ فقط ابزار prototype هستند.
- تبدیل مستقیم فرم ساخته‌شده به Easy Form Builder؛ مسیر EFB مستقل است.
- تغییر recipient از داخل canvas؛ recipient یک تنظیم مدیر است و ورودی مدل نمی‌شود.

این موارد کنترل ظاهری یا state جعلی نخواهند داشت. preview read-only باقی می‌ماند و برای تغییر، مدیر به prompt برمی‌گردد و proposal تازه تولید می‌کند.

## قابلیت‌های افزونه که موکاپ UI صریح ندارد

| قابلیت | محل نگهداری |
|---|---|
| فهرست همهٔ فرم‌های native | route پنهان `aisaautopilot-forms` حفظ شده است؛ روی سطح canonical موکاپ لینک اضافه نمایش داده نمی‌شود. |
| ساختار JSON/Structure tab و mobile preview switch | در review حفظ می‌شود چون دادهٔ واقعی است؛ در گزارش visual به‌عنوان افزودهٔ محصول ثبت می‌شود. |
| proposal expiry/action ID/risk details | در review واقعی حفظ می‌شود؛ برای امنیت approval لازم است. |
| تنظیم publish پیش‌فرض و delivery diagnostic | Settings alias پنهان؛ روی canvas کنترل جعلی اضافه نمی‌شود. |

## ریزبرنامه و گیت پذیرش

| ID | کار کوچک | وضعیت | گیت |
|---|---|---|---|
| S3.1.0 | ثابت‌کردن context تأییدشدهٔ draft/publish | تکمیل | integration وضعیت publish/draft و fail-closed = ۳/۰ |
| S3.1.1 | shell تخصصی: header، canvas و Assistant panel | تکمیل | renderer contract + screenshot desktop پاس |
| S3.1.2 | compose/empty/delivery/examples/voice | تکمیل | keyboard، no-auto-request و disabled states پاس |
| S3.1.3 | review/approval/expiry و preview responsive | تکمیل | fixture، approval، publish confirmation و status freeze پاس |
| S3.1.4 | result/shortcode/open draft/build another/EFB handoff | تکمیل | result fixture و cleanup کامل پاس |
| S3.1.5 | RTL، 390px، accessibility و visual QA | تکمیل | en_US/fa_IR، desktop/mobile و console/pageerror صفر |
| S3.1.6 | regression و گزارش اجرا | تکمیل | PHPCS، PHPStan، PHPUnit، Phase 1 و diff check پاس |

S3.1 در ۲۰۲۶-۰۹-۱۰ بسته شد. نتیجه و شواهد کامل در `FORM_COMPOSER_S3_1_EXECUTION_REPORT_FA.md` ثبت شده است. backend refinement مکالمه‌ای و تبدیل خودکار EFB همچنان خارج از قرارداد موجودند و کنترل جعلی برایشان ساخته نشده است.
