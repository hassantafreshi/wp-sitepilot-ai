# Workstreamهای Backend برای تکمیل موکاپ — AutoPilot AI

وضعیت: فعال  
تاریخ: 2026-08-26  
وابستگی: `MOCKUP_UI_UX_EXECUTION_WORKFLOW_FA.md`

## اصل اجرایی جدید

هر کنترلِ طراحی‌شده در موکاپ باید به قابلیت واقعی، امن و آزموده متصل باشد. هیچ سطحی صرفاً به‌دلیل نبود backend به‌عنوان «Phase 2 / Locked» باقی نمی‌ماند؛ برای آن یک task مستقل ساخته می‌شود و UI فقط پس از عبور آن task از معیار پذیرش فعال می‌شود.

این اصل قراردادهای امنیتی را حذف نمی‌کند: کلیدها محرمانه می‌مانند، هر عمل نوشتنی نیازمند plan/validation/preview/approval است، و عملیات مخرب یا انتشار مستقیم بدون تأیید صریح انسان انجام نمی‌شود.

## قرارداد مشترک همهٔ taskها

`request → authorization → validation → immutable plan → explicit approval → execution → audit log → usage metering → UI result`

- تمام routeهای admin با `manage_options` و nonce استاندارد REST محافظت می‌شوند.
- هیچ secret در response، DOM یا log قرار نمی‌گیرد.
- هر plan دارای `id`، مالک، TTL، وضعیت claim و audit trail است.
- عملیات WordPress/WooCommerce به‌صورت پیش‌نویس یا reversible انجام می‌شود، مگر publish صریحِ کاربر در مرحلهٔ approval.
- برای هر task: PHPUnit، تست امنیتی، JS contract test و سناریوی دستی RTL/LTR افزوده می‌شود.

## Backlog اجرایی، به‌ترتیب وابستگی

| ID | شکاف موکاپ | خروجی backend واقعی | سطح UI مقصد | معیار پذیرش |
|---|---|---|---|---|
| BE-01 ⏳ | Content Studio: blog/page/meta drafting | `ContentDraftSkill`، schema/validator، draft plan/executor، routeهای `/content/plan` و `/content/execute` | Content Studio | پیش‌نویس post/page و meta فقط پس از preview+approve ایجاد شود؛ usage/log ثبت شود. |
| BE-02 | Answer Desk: conversation inbox | storage/query امن برای conversationها و routeهای list/get/status | Answer Desk → Conversations | فهرست، جزئیات، handoff state و PDF export با scope سایت/مدیر قابل مشاهده باشد. |
| BE-03 | Answer Desk: WhatsApp/Slack/Discord/Webhook | transportهای جدا، secret vault، test endpoint و SSRF policy برای webhook | Answer Desk → Human handoff | هر کانال فقط با test موفق «connected» است؛ email fallback حفظ می‌شود. |
| BE-04 | Real-time voice | session/turn API، قابلیت provider و TTS/STT adapter با fallback transcript-first | Widget، Answer Desk، EFB | mic، transcript، پاسخ صوتی و خطاهای مرورگر/provider واقعی هستند؛ streaming فقط وقتی transport برقرار است. |
| BE-05 | Site Knowledge: indexing queue | job store، batch worker، pause/resume/stop/retry/skip و failure reasons | Site Knowledge → Indexing Queue | progress پایدار پس از reload، retry/skip واقعی و عدم index هم‌زمان. |
| BE-06 | Store Copilot: صفحات Products/Orders و proposal states | query/status endpoints و plan state mapping بر پایهٔ contract فعلی | Store Copilot | دادهٔ واقعی product/order/analytics؛ draft-only تا task جدا برای تغییر contract. |
| BE-07 | Activity Log واحد | event read model که Logger و ActionStore را با filter/pagination واحد می‌خواند | Activity Log | Logs و approvals/history در یک timeline، بدون حذف route قدیمی. |
| BE-08 | Setup: نتیجه‌های دقیق تست provider | normalized diagnostic code/status map برای 8 حالت موکاپ | Setup wizard | 401/402/404/429/timeout/empty-model/embedding-advisory با پیام واقعی نمایش داده شوند. |
| BE-09 | EFB: selection-aware session | field-selection context، scoped customize/audit response و session validation | EFB Copilot | هر فرمان به field انتخاب‌شده محدود و قابل trace است. |

## مرزبندی قرارداد WooCommerce

BE-06 عمداً contract فعلی را حفظ می‌کند: محصول فقط draft، و سفارش فقط read. برای publish/edit/bulk/refund یک task امنیتی مستقل با مدل مجوز، preview، confirmation و rollback لازم است و نباید ضمن بازطراحی UI فعال شود.

## ترتیب اجرا

1. BE-01 و BE-07: زیرساخت plan/audit قابل استفادهٔ مجدد و Content Studio.
2. BE-02 و BE-03: داده و انتقال انسانی Answer Desk.
3. BE-05 و BE-08: وضعیت‌های واقعی Knowledge/Setup.
4. BE-04 و BE-09: voice/EFB adapterها.
5. BE-06: کامل‌سازی read-side Store Copilot و سپس اتصال همهٔ tabها.

هر task پس از اجرای تست‌ها به «done» تبدیل و همان لحظه از UI مقصد به‌صورت فعال نمایش داده می‌شود.

## گزارش اجرا

## به‌روزرسانی اجرای واقعی — 2026-08-26

- **BE-01 انجام شد (نیازمند اجرای نهایی در محیط PHP):** `ContentDraftSkill` از همان provider استفاده می‌کند که usage آن را در `UsageTracker` ثبت می‌کند؛ خروجی فقط پس از validate، preview و approval به post/page با وضعیت `draft` تبدیل می‌شود. هیچ publish خودکار وجود ندارد.
- **BE-02 انجام شد:** درخواست صریح visitor برای human handoff در `HandoffInbox` خصوصی ثبت می‌شود؛ و Conversation Inbox فقط در صورت فعال‌سازی آگاهانهٔ administrator (`conversation_logging_enabled` + notice قابل‌ویرایش) مکالمات widget را ذخیره می‌کند. routeهای محافظت‌شدهٔ فهرست/جزئیات برای هر دو وجود دارد و هر دو صفحهٔ مدیریتی به IA افزوده شده‌اند.
- **BE-07 انجام شد:** `ActionStore` هنگام ساخت proposal رویداد redacted ثبت می‌کند و `ActivityFeed` یک read model واحد برای proposal، approval، نتیجه و خطا می‌سازد. صفحهٔ Activity Log و route محافظت‌شدهٔ `/activity` از همین منبع می‌خوانند.
- **BE-08 انجام شد:** provider diagnostics برای key، billing (402)، endpoint/model (404)، rate-limit، network و configuration به کدهای پایدار تبدیل شد؛ Settings UI کد را به اقدام بعدی روشن تبدیل می‌کند. خطای قدیمیِ استفاده از پاسخ decode‌نشده در مسیر 404 نیز رفع شد.

## taskهای جداگانهٔ باقی‌مانده (نباید به‌صورت UI ظاهری فعال شوند)

| ID | Scope دقیق | شرط اتمام |
|---|---|---|
| BE-03 | WhatsApp Business، Slack، Discord و custom signed webhook | test اتصال موفق، secret vault، SSRF policy، audit redacted و retry policy. |
| BE-04 | گفت‌وگوی صوتی real-time واقعی در widget | session lifecycle، capability detection، browser/provider failure states و usage. |
| BE-05 | صف پایدار Site Knowledge با pause/resume/stop/retry/skip | job store + worker + recovery after reload + per-item reasons. |
| BE-06 | تب‌های کامل Store Copilot | داده واقعی products/orders/analytics و proposal states؛ هر mutation یک task امنیتی جدا. |
| BE-09 | انتخاب‌شدن field در EFB و trace آن | selected-field contract، validate target و audit response. |

### gate نهایی این تغییر

قبل از release باید `composer test`، `composer lint` و `composer analyze` در محیط PHP 8.1+ اجرا شوند. محیط فعلی PHP/Composer ندارد؛ بنابراین این gate هنوز باز است و تنها JS syntax check انجام شده است.

- 2026-08-26 — BE-01 هستهٔ اجرایی پیاده‌سازی شد: proposal دارای TTL و مالک است، `ContentPlanValidator` فقط `post`/`page` را می‌پذیرد، خروجی مدل را sanitize می‌کند و `ContentDraftExecutor` تنها `draft` می‌سازد. routeهای جدید با `manage_options` ثبت شده و UI Content Studio به preview/approval/result واقعی متصل است. تست واحد validator افزوده شد. اتصال دقیق token usage به پاسخ structured provider و اجرای PHPUnit/PHPCS در محیط PHP باقی مانده است.
