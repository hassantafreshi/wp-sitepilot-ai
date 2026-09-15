# ممیزی و برنامهٔ اجرای A2.10 — Activity Log

تاریخ: 2026-09-05
مرجع بصری: `mockups/design_handoff_admin_redesign/AutoPilot Admin.dc.html`، بخش Activity Log در خطوط 1347 تا 1375 و دادهٔ نمونه در 1593 تا 1601.

## حکم طراحی

صفحهٔ canonical فقط یک timeline کارت‌مانند دارد: header با `Today` و summary، ردیف‌های پنج‌بخشیِ زمان، feature، شرح، pill وضعیت و فقط actionی که واقعاً پشتوانه دارد. حالت خالی نیز کارت dashed با عنوان `No changes yet` است. تنها action هدر `Export CSV` است.

موکاپ برای حالت خالی هم کارتِ «۷ تغییر» را رندر می‌کند؛ این یک تناقض در prototype است و در محصول بازتولید نمی‌شود: وقتی هیچ رخداد واقعی وجود ندارد، فقط empty state نمایش داده می‌شود.

برای جلوگیری از برچسب زمانی غلط، رخدادها در همان الگوی کارت موکاپ بر اساس روز واقعیِ timezone وردپرس گروه‌بندی می‌شوند: `Today`، `Yesterday` یا تاریخ محلی. هیچ رخداد قدیمی با عنوان Today نشان داده نخواهد شد.

## قرارداد دادهٔ واقعی

منبع فعلی رخدادها `Logger` و read model `ActivityFeed` است: buffer محلی حداکثر ۲۰۰ رخداد را نگه می‌دارد و API/export حداکثر ۱۰۰ رخداد جدید را می‌خوانند. هر رخداد دارای زمان، level، event، پیام redact‌شده و شناسه‌های فنی اختیاری است.

timeline جدید فقط از دادهٔ موجود و قابل‌اثبات می‌سازد:

| بخش موکاپ | دادهٔ واقعی | تصمیم |
|---|---|---|
| زمان | timestamp ثبت‌شده در Logger | زمان محلی وردپرس؛ timestamp نامعتبر صریحاً «Date unavailable» است. |
| feature | event/connector واقعی | نگاشت ثابت و قابل‌آزمون، مانند `content.*` → Content Studio و `kb_*` → Site Knowledge. |
| شرح | message redact‌شده | HTML نیست و فقط با escaping رندر می‌شود. |
| pill | level واقعی | `error` → Needs attention؛ `warning` → Review؛ سایر رخدادها → Recorded. از نام فرد یا وضعیت ساختگی استفاده نمی‌شود. |
| action | resource پایدار با URL قابل‌اعتبار | فقط وقتی منبع واقعی قابل‌دسترسی باشد نمایش می‌یابد؛ در غیر این صورت action ندارد. |

## قابلیت‌های موکاپ که هنوز backend امن ندارند

موکاپ دکمه‌های `Approve all waiting`، `Approve`، `Undo` و `Restore` دارد، اما افزونه برای Activity Log هیچ قرارداد پایدار برای این موارد ندارد:

- proposalها transient پانزده‌دقیقه‌ای و تک‌مصرف‌اند؛ فهرست durable و endpoint امن برای waiting action وجود ندارد.
- rollback snapshot یا endpoint عمومیِ undo/restore برای رخدادهای generic وجود ندارد.
- actor واقعی در read model فعلی خارج شده است؛ بنابراین «Sara approved» یا «Automatic» قابل ادعا نیست.

این کنترل‌ها تا زمانی که قرارداد persistent، nonce/capability، ownership، idempotency و rollback واقعی ساخته نشده‌اند در UI canonical نمایش داده نمی‌شوند. کنترل نمایشی یا دکمهٔ disabled گمراه‌کننده ایجاد نخواهد شد.

## قابلیت‌های موجود که موکاپ برایشان UI ندارد

| قابلیت موجود | تصمیم در بازطراحی |
|---|---|
| search و filter محلی بر اساس level | از surface canonical حذف می‌شود؛ API backward-compatible می‌ماند. |
| table خام UTC/level/event/trace | از surface canonical حذف می‌شود؛ schema فنی فقط مسیر API/export است. |
| Clear log | destructive است و موکاپ UI ندارد؛ از صفحهٔ canonical حذف می‌شود و در این فاز پاک‌سازی یا endpoint تازه‌ای اضافه نمی‌شود. |
| REST filters و retention ۲۰۰/۱۰۰ | حفظ می‌شوند، اما pagination جدید بدون قرارداد محصول ساخته نمی‌شود. |
| History alias | همچنان تاریخچهٔ durable Content Studio است و با Activity Log ادغام نمی‌شود. |

## یافتهٔ correctness و امنیت

`LogExport` مقادیر log را مستقیم به CSV می‌نوشت. چون exception یا پاسخ remote ممکن است با `=`, `+`, `-` یا `@` آغاز شود، این کار خطر formula injection در spreadsheet ایجاد می‌کند. نخستین تغییر اجرایی A2.10 این سلول‌ها را مانند export امن موجود خنثی می‌کند و با تست WordPress پوشش می‌دهد.

Logger نیز ring buffer محلیِ read-modify-write است؛ برای audit-grade concurrent ledger مناسب نیست. این بازطراحی آن را به‌اشتباه «هر تغییر» یا immutable audit trail نمی‌نامد و retention واقعی را پنهان نمی‌کند.

## برنامهٔ ریز و گیت پذیرش

| ID | کار کوچک | گیت پذیرش |
|---|---|---|
| A2.10.0 | ممیزی موکاپ، قرارداد داده و فهرست شکاف‌ها | همین سند + `git diff --check` |
| A2.10.1 | خنثی‌سازی CSV formula injection در Export | lint، PHPCS/PHPStan هدفمند و WordPress integration برای `= + - @` و newline |
| A2.10.2 | read model timeline با زمان/گروه/feature/status واقعی | تست mapping، redaction، newest-first و timestamp نامعتبر |
| A2.10.3 | markup و CSS scoped کارت/empty-state موکاپ | render integration: کارت دقیق، عدم وجود legacy filters/clear/table و empty بدون phantom card |
| A2.10.4 | browser QA با fixture | desktop، native `fa_IR` RTL، 390px، keyboard/export، عدم overflow و console/page error صفر |
| A2.10.5 | regression و گزارش اجرایی | PHPCS کامل، PHPStan، PHPUnit، Phase 1 و visual QA نهایی |

## محدودهٔ آگاهانهٔ این فاز

این فاز proposal persistence، bulk approval، rollback engine یا database audit ledger اضافه نمی‌کند؛ هر کدام تغییر امنیتی/محصولی مستقل هستند. هدف این فاز یک Activity Log درست، امن‌تر و بصری مطابق موکاپ است، نه شبیه‌سازی رفتار prototype.

## نتیجهٔ A2.10.1

`LogExport` اکنون مانند export امن Content Studio دارای `records()` و `write_csv()` قابل‌آزمون است و تمام ستون‌های آن از policy مشترک `csv_cell()` عبور می‌کنند. مقدارهای شروع‌شونده با `=`, `+`, `-` یا `@` با apostrophe خنثی می‌شوند و CR/LF پیش از نوشتن CSV به فاصله تبدیل می‌شود. schema موجود (`time, level, event, trace_id, action_id, connector_id, message`) عمداً در این task تغییر نکرده است.

گیت سبز A2.10.1: PHP lint، PHPCS هدفمند، PHPStan هدفمند و `wordpress_activity_log_export_integration.php` با **3/0** روی WordPress محلی. تست URL nonce‌دار، حفظ contract رکورد، formula characters و CR/LF را پوشش می‌دهد و در `finally` option قبلی log را بازمی‌گرداند.

## نتیجهٔ A2.10.2 — read model

`ActivityTimeline` اضافه شد. این read model رخدادها را newest-first و با timezone وردپرس در گروه‌های `Today`، `Yesterday`، تاریخ محلی یا `Date unavailable` قرار می‌دهد. feature از namespace واقعی event و pill از level واقعی استخراج می‌شود؛ actor، approval و rollback حدس زده نمی‌شوند.

`ActivityFeed` فقط شناسه‌های غیرحساس `post_id`، `product_id` و `form_id` را برای ساخت action قابل‌اثبات اضافه می‌کند. `Open draft` فقط وقتی ظاهر می‌شود که یک post/plugin-generated واقعی هنوز وجود دارد و کاربر حق ویرایش آن را دارد. برای بهبود correlation واقعی، رخداد موفق `action.executed` اکنون `action_id` را نیز ثبت می‌کند.

گیت سبز A2.10.2: PHP lint، PHPCS هدفمند، PHPStan هدفمند و `wordpress_activity_timeline_integration.php` با **4/0**. تست نگه‌داری فقط resource IDهای لازم، ترتیب newest-first، action واقعی، feature/status بدون حدس actor/rollback و timestamp نامعتبر را پوشش می‌دهد؛ fixture draft، log و timezone همگی در `finally` restore می‌شوند.

## نتیجهٔ نهایی — 2026-09-06

گیت‌های A2.10.3 تا A2.10.5 تکمیل شدند. پوشش timeline اکنون **25/0** و export **5/0** است. شرح اجرا و شواهد در [گزارش نهایی](ACTIVITY_LOG_A2_10_EXECUTION_REPORT_FA.md) آمده است.

**استثنای مهم QA:** helper قدیمی در یک اجرای پیشین لاگ محلی را پیش از snapshot حذف کرده بود؛ دادهٔ تاریخی بازیابی نشده است. علت، اصلاح و تست پیشگیری در [گزارش رخداد](ACTIVITY_LOG_QA_INCIDENT_FA.md) ثبت شده‌اند. ادعای restore در تست‌های اصلاح‌شده مربوط به snapshot همان اجراست، نه بازیابی لاگ تاریخیِ ازدست‌رفته.
