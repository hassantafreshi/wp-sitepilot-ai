# گزارش اجرای A2.10 — Activity Log

تاریخ: 2026-09-06

## خروجی

ساختار Activity Log به timeline کارت‌مانند موکاپ تبدیل شد: زمان، feature، شرح، pill و action واقعی. گروه‌ها بر اساس timezone وردپرس هستند. حالت خالی کارت dashed مستقل است؛ کارت و شمارندهٔ ساختگی ندارد. منوی اصلی همچنان دقیقاً ۱۰ صفحه است و routeهای سازگاری پنهان مانده‌اند.

- `ActivityTimeline`: تاریخ ISO معتبر، Today/Yesterday واقعی، نگاشت connector، لینک فقط به منبع markerدار، نوع صحیح و قابل‌ویرایش؛ نه trash یا ID خراب.
- `ActivityFeed`: تحمل ورودی خراب بدون هشدار PHP، redaction دوباره هنگام خواندن و resource IDهای عددی معتبر.
- `LogExport`: schema هفت‌ستونی ثابت، خنثی‌سازی formula injection، nonce و capability.
- UI: بدون filter/search/table/Clear log قدیمی، بدون REST اضافی، کارت responsive، تراز صحیح فارسی و focus خوانای Export CSV.
- stylesheet اختصاصی Activity Log scope دارد؛ فایل JS قدیمی در این صفحه enqueue نمی‌شود.

## اختلاف‌های مستند با موکاپ

Approve all/Approve/Undo/Restore و actor واقعی در backend قرارداد پایدار ندارند؛ دکمه یا وضعیت جعلی نمایش داده نمی‌شود. `Recorded` و `Needs attention` جای نام شخص/approval حدسی هستند. متن «هر تغییر با راه بازگشت» ادعا نمی‌شود. قابلیت‌های قدیمی بدون UI موکاپ و جزئیات این تصمیم در [ممیزی A2.10](ACTIVITY_LOG_A2_10_AUDIT_AND_PLAN_FA.md) فهرست شده‌اند. History همچنان متعلق به Content Studio است.

## شواهد تست

| گیت | نتیجهٔ واقعی |
|---|---|
| Timeline WordPress | ۲۵ تست، صفر شکست: ID نامعتبر، permission، marker، trash، نوع منبع، connector، timezone و تاریخ خراب، redaction، limit/filter |
| Renderer WordPress | ۳ تست، صفر شکست؛ timeline و empty state |
| CSV WordPress | ۵ تست، صفر شکست؛ schema، formula/newline، capability و nonce نامعتبر |
| Fixture lifecycle | ۴ بررسی PASS و exit 0؛ no-op بدون snapshot، restore تکراری و حفظ رخداد هم‌زمان |
| Browser matrix | `en_US` و `fa_IR`، هرکدام populated/empty؛ هر چهار اجرا صفر شکست و exit نهایی 0 |
| تکرار RTL پس از اصلاح عنوان | دو اجرای `fa_IR` populated/empty، صفر شکست و exit نهایی 0 |
| PHPCS کامل بدون suppress هشدار | ۱۳۰ فایل، صفر error و warning؛ ۱۰ هشدار alignment واقعاً اصلاح شد |
| PHPStan | صفر error |
| PHPUnit | ۱۳۳ تست، ۴۳۹ assertion |
| Menu/assets/render | منوی دقیق ۱۰تایی: ۵ PASS؛ assets: ۱۹ PASS؛ render: ۱۸ حالت موفق |
| Phase 1 | ۴۵ تست، صفر شکست |
| Syntax/diff | PHP lint، Node syntax و `git diff --check` موفق |

مرورگر واقعی Chrome، viewport دسکتاپ 1440×960 و موبایل 390×844، RTL native فارسی، keyboard→CSV، متن focus، bounds و console/pageerror بررسی شد. تصاویر حالت خالی/پر و انگلیسی/فارسی بازبینی شدند؛ نه صرفاً assertionهای DOM. مسیر تصاویر نسبت به workspace: `test-artifacts/activity-log/{en_US,fa_IR}-{empty,populated}/`.

اتصال نصب محلی دوباره با read-only check تأیید شد: `C:\xampp\htdocs\wp\wp-content\plugins\ai-site-assistant-autopilot` یک Junction مستقیم به source همین پروژه است.

## رخداد QA و محدودیت مهم

helper قبلی، buffer لاگ محلی را پیش از snapshot پاک کرده بود. نسخهٔ کامل قابل‌بازیابی پیدا نشد؛ این داده هنوز بازیابی نشده است. helper اصلاح شد و regression چهارگانه پاس شد. fixtureها و حساب‌های موقتِ اجرای فعلی پاک شدند، اما این cleanup به معنی بازیابی buffer تاریخی نیست. شرح کامل: [گزارش رخداد](ACTIVITY_LOG_QA_INCIDENT_FA.md).

## مرحلهٔ بعد

A2.10 در محدودهٔ قرارداد واقعی تکمیل است؛ این به معنی اتمام کل پروژه یا تطبیق پیکسلی تمام چهار موکاپ نیست. S3.1 Form Composer، S3.2 EFB، S3.3 ویجت عمومی و QA نهایی پروژه هنوز باز هستند.
