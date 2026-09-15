# Workflow اجرایی بازطراحی UI/UX موکاپ — AutoPilot AI

وضعیت: بازبینی تطبیق صفحه‌به‌صفحه در جریان؛ تکمیل نهایی تأیید نشده  
تاریخ: 2026-08-26  
دامنه: `apps/wordpress-plugin/ai-site-assistant-autopilot`

## 1. هدف و منبع حقیقت

هدف، هم‌راستا کردن تمام سطوح wp-admin و سطوح عمومی افزونه با چهار موکاپ hi-fi است، بدون بازنویسی یا تغییر رفتار backend.

منبع حقیقت بصری و رفتاری:

- `mockups/design_handoff_admin_redesign/AutoPilot Admin.dc.html`
- `mockups/design_handoff_admin_redesign/Form Composer.dc.html`
- `mockups/design_handoff_admin_redesign/EFB Builder Chat.dc.html`
- `mockups/design_handoff_admin_redesign/Front Chat Widget.dc.html`
- `mockups/design_handoff_admin_redesign/REDESIGN_PROMPT.md`
- `mockups/design_handoff_admin_redesign/IMPLEMENTATION_GUIDE.md`

## 2. قراردادهای غیرقابل‌تغییر

1. namespace `AISAAutoPilot`، پیشوندهای `aisaautopilot_*` و REST namespace `aisaautopilot/v1` ثابت می‌مانند.
2. قرارداد WooCommerce ثابت است: فقط ساخت product draft؛ هرگونه publish/edit/bulk یا تغییر/refund سفارش در UI قفل‌شده و غیرعملیاتی می‌ماند.
3. API key و secret هرگز به DOM، REST response یا log برنمی‌گردد.
4. route و قابلیت قدیمی حذف نمی‌شود. اگر صفحه‌ای از IA جدید حذف شد، slug آن alias قابل‌دسترسی باقی می‌ماند و فقط از منو پنهان می‌شود.
5. تمام متن‌های جدید قابل ترجمه و تمام CSSهای جدید RTL-safe با logical properties هستند.
6. قابلیت بدون backend هرگز به‌شکل قابلیت فعال نمایشی ارائه نمی‌شود؛ برای آن یک task مستقل در `MOCKUP_BACKEND_WORKSTREAMS_FA.md` ساخته و backend آن تکمیل می‌شود. تا عبور از تست، کنترل فقط در دسترس توسعه‌دهنده است و برای کاربر نهایی نمایش عملیاتی ندارد.

## 3. تصمیم‌های IA ثبت‌شده

| مقصد موکاپ | پیاده‌سازی/تصمیم |
|---|---|
| Overview | `HomePage` با برچسب جدید |
| Setup & Connection | `SettingsPage` به wizard چهارمرحله‌ای؛ `-settings` alias |
| Integrations | `IntegrationsPage` جدید، مبتنی بر Settings و detectorهای فعلی |
| Site Knowledge | `KnowledgeBasePage` با tabها؛ AI Chat ادمین زیر-tab «Ask your knowledge base» |
| Answer Desk | `ChatWidgetSettings` با tabهای Conversations/Widget/Handoff/Voice؛ widget عمومی هم‌سو |
| Store Copilot | سطح یکپارچهٔ `WooCommerceDashboardPage` و `WooCommerceSettings` با tab؛ slugهای قدیمی alias |
| Content Studio | `CopilotPage` + `NativeFormsPage` در tab «Your forms»؛ فرم، blog/page/meta draft با preview و approval واقعی |
| Activity Log | نمایش واحد از `LogsPage` و `HistoryPage`؛ هر دو slug alias |
| EFB Copilot | صفحهٔ EFB و FAB/modal با field-selection awareness |

نام نمایشی در UI: **AutoPilot AI**. این نام فقط رشتهٔ نمایشی است و هیچ شناسهٔ فنی را عوض نمی‌کند.

## 4. ترتیب اجرایی (تنها ترتیب مجاز تغییر)

- [x] 0. Baseline: inventory کامل page/asset/route و ثبت featureهای حفظ‌شونده. PHP 8.2 محلی و vendor برای lint/test پیدا و استفاده شد.
- [x] 1. Foundation: tokenهای semantic، navy `#202a8d`، coral `#ff455f`، typography، primitives، icon/gradient و RTL/a11y پایه.
- [x] 2. Navigation: ترتیب و برچسب‌های منو، slugهای جدید و alias/hide برای مسیرهای قدیمی، enqueue صحیح assetها.
- [~] 3. Low-risk pages: Overview، Usage & Cost و Health re-skin/rename شدند؛ Activity Log از `ActivityFeed` واحد برای proposal/approval/result/error می‌خواند. تطبیق visual/live باقی است.
- [~] 4. Knowledge: Site Knowledge rename شد و AI Chat با alias حفظ شده است؛ tabها و indexing queue در حال پیاده‌سازی‌اند.
- [~] 5. Creation: Content Studio به form و blog/page/meta draft واقعی با plan/validate/preview/approval/execute متصل شد؛ اما Templates/History موکاپ هنوز یکپارچه نشده‌اند.
- [~] 6. Setup + Integrations: wizard چهارمرحله‌ای و hub دارای feature-gate واقعی؛ تطبیق visual/live باقی است.
- [~] 7. Answer Desk + frontend: widget، conversation opt-in، handoff inbox، voice و RTL/LTR public UI؛ IA کامل موکاپ و real-time voice تکمیل نشده‌اند.
- [ ] 8. Store + EFB: Store Copilot draft-only و EFB modal/FAB/field selection.
- [~] 9. QA: syntax PHP، PHPUnit و PHPCS فایل‌های تغییرکرده انجام شد؛ بررسی live WordPress/Playwright، POT/fa_IR و audit صفحه‌به‌صفحه باز هستند. مرجع وضعیت: `MOCKUP_PARITY_AUDIT_FA.md`.

هر مرحله فقط پس از عبور از معیار پذیرش خودش به مرحلهٔ بعد می‌رود؛ هر تغییر خارج از این دامنه باید ابتدا به این فایل افزوده شود.

## 4.1 گزارش اجرای فعلی

- 2026-08-26: موکاپ‌های تکراری root و `design_handoff_admin_redesign` با SHA-256 یکسان تأیید شدند؛ بنابراین فقط مجموعهٔ handoff منبع بصری یکتا است.
- 2026-08-26: منوی فعلی، 15 صفحهٔ ادمین، routeهای REST، تنظیمات، دو launcher عمومی و capability contract ووکامرس بررسی شدند.
- 2026-08-26: foundation و navigation اعمال شد. `IntegrationsPage` بدون REST جدید و فقط با `Settings`/detectorهای موجود ایجاد شد. تا تکمیل ادغام نمای Activity Log، `-history` با renderer مستقل خود به‌عنوان alias حفظ شده است تا قابلیت approval/history از دست نرود.

## 5. معیار پذیرش هر تغییر

- داده از route/Settings فعلی می‌آید؛ mock data یا endpoint نو فقط برای ظاهر ساخته نمی‌شود.
- حالت‌های empty/loading/error/success موکاپ با وضعیت واقعی نمایش داده می‌شوند.
- کنترل مخرب یا نیازمند تأیید، جریان واقعی plan → validate → immutable preview → explicit approval → execute را دور نمی‌زند.
- صفحه در LTR و `fa_IR` بدون `left/right` جدید، overflow یا ترتیب بصری اشتباه کار می‌کند.
- هر slug قبلی که از منو ادغام شده، همچنان صفحهٔ درست را render می‌کند.

## 6. Preserve-list برای بازبینی نهایی

- Voice input در composerهای فرم، چت ادمین، EFB و widget.
- Native Forms، shortcode و مسیرهای ارسال فرم.
- AI Chat ادمین و routeهای `/chat/*`.
- Handoff ایمیل/Telegram/Twilio، test routeها و secret handling.
- منطق either/or میان public assistant و configurable widget.
- KB auto-index، budget/reset، PDF transcript export، RTL فرم و widget.
- تمام capability gateهای WooCommerce و TTL/claim/approval ActionStore.

## 7. پروتکل QA نهایی

1. lint/PHPCS و testهای PHPUnit/سناریوهای موجود.
2. مرور همهٔ slugهای منو و aliasها به‌همراه حالت‌های mockup.
3. تست دستی provider/key بدون افشای key، KB، form create/delivery، widget/handoff، EFB و Woo draft.
4. تست responsive 390px، LTR/RTL و keyboard/focus/contrast.
5. مقایسهٔ نهایی هر صفحه با موکاپ و ثبت هر اختلاف عمدیِ ناشی از قرارداد Phase 1.
