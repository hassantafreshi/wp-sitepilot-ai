# تسک بعدی بعد از انتقال UI/UX موکاپ

تاریخ: 2026-08-26
وضعیت فعلی: UI اصلی موکاپ روی افزونه و نصب WordPress محلی اعمال و smoke شده است، اما هنوز «تطبیق پیکسلی/رفتاری ۱۰۰٪» بسته نشده است.

## آنچه همین حالا انجام شده است

- `Answer Desk` در منوی اصلی روی Conversations باز می‌شود و تب‌های واقعی `Conversations / Widget / Human handoff / Real-time voice` دارد.
- `Site Knowledge` تب‌های `Overview / Collections / Indexing Queue / Sync Sources / Ask your KB` دارد و داده‌ها از index/list/stats/batch/gaps واقعی خوانده می‌شوند.
- `Store Copilot` تب‌های `New product / What it can do / Products / Orders` دارد. جریان `New product` به route واقعی WooCommerce plan/execute وصل است و فقط draft می‌سازد.
- `Content Studio` حالا با `Write` شروع می‌شود؛ نوشتن post/page draft به backend واقعی content plan/execute وصل است و Templates نیز به عنوان سطح جدا اضافه شده است.
- تغییرات روی نصب محلی WordPress در `C:\xampp\htdocs\wp\wp-content\plugins\sitepilot-ai` کپی شد. افزونه همان‌جا فعال بود.
- runtime smoke با `tests/local_admin_render_smoke.php` پاس شد.
- PHPUnit پاس شد: `131 tests, 429 assertions`.
- PHPCS روی فایل‌های PHP دست‌خورده با `phpcs.xml.dist` پاس شد.

## تسک بعدی برای ادامه کار

1. Visual QA واقعی با browser/session ادمین:
   - حل مشکل cookie/login در `Invoke-WebRequest` یا استفاده از Playwright نصب‌شده.
   - گرفتن screenshot از صفحات mockup و صفحات افزونه.
   - مقایسه صفحه‌به‌صفحه برای overflow، spacing، RTL، focus و stateها.

2. تکمیل backendهایی که هنوز نباید صرفاً UI ظاهری باشند:
   - `BE-03`: WhatsApp/Slack/Discord/custom webhook برای handoff، با secret vault و test endpoint.
   - `BE-04`: real-time voice واقعی، نه فقط voice input/transcribe.
   - `BE-05`: queue پایدار Site Knowledge با pause/resume/stop/retry/skip و recovery بعد از reload.
   - `BE-06`: Store Products/Orders با endpoints اختصاصی read-state و proposal-state کامل. هر mutation خطرناک باید task امنیتی جدا داشته باشد.
   - `BE-09`: EFB selected-field contract و audit دقیق فرمان‌های scoped.

3. تکمیل IAهای باقی‌مانده:
   - تبدیل `Content Studio -> History` به read model یکپارچه از `ActivityFeed`.
   - نمایش inbox هندآف در همان تجربه‌ی Answer Desk بدون حس صفحه جدا.
   - بررسی اینکه `Ask your KB` بهتر است inline شود یا همان admin chat console فعلی کافی است.

4. پاک‌سازی نصب محلی:
   - در کپی اول cacheهای توسعه `.phpstan.cache` و `.phpunit.cache` به پوشه نصب WP منتقل شدند.
   - دستور حذف توسط policy محیط رد شد؛ در ادامه باید فقط همین دو مسیر داخل `C:\xampp\htdocs\wp\wp-content\plugins\sitepilot-ai` پاک شوند.

## معیار بسته شدن نهایی

تسک فقط وقتی «کامل» محسوب شود که همه صفحات mockup در افزونه render شوند، runtime/browser QA پاس شود، هر کنترل فعال backend واقعی داشته باشد، و اختلاف‌های عمدی با موکاپ فقط به خاطر قرارداد امنیتی مستند شده باشند.
