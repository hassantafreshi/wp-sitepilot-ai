# Workflow اجرایی بازطراحی موکاپ — AutoPilot AI

وضعیت: در حال اجرا  
تاریخ شروع: 2026-08-26  
مالک فنی: تیم افزونهٔ WordPress

## 1. منبع حقیقت و دامنه

منبع بصری و رفتاری:

- `C:\files\projects\SitePilot AI\mockups\design_handoff_admin_redesign\AutoPilot Admin.dc.html`
- `C:\files\projects\SitePilot AI\mockups\design_handoff_admin_redesign\Form Composer.dc.html`
- `C:\files\projects\SitePilot AI\mockups\design_handoff_admin_redesign\EFB Builder Chat.dc.html`
- `C:\files\projects\SitePilot AI\mockups\design_handoff_admin_redesign\Front Chat Widget.dc.html`
- `C:\files\projects\SitePilot AI\mockups\design_handoff_admin_redesign\IMPLEMENTATION_GUIDE.md`
- `C:\files\projects\SitePilot AI\mockups\design_handoff_admin_redesign\REDESIGN_PROMPT.md`

منبع قرارداد محصول و کیفیت:

- `C:\files\projects\SitePilot AI\Docs\UI_UX_FIGMA_BRIEF_FA.md`
- `C:\files\projects\SitePilot AI\Docs\UI_UX_REDESIGN_IMPLEMENTATION_PLAN_FA.md`

دامنه، wp-admin افزونه و ویجت عمومی است. پنل cloud، billing و قابلیت‌های backend که هنوز وجود ندارند در این بازطراحی ساخته یا شبیه‌سازی نمی‌شوند.

## 2. قواعد غیرقابل‌تغییر

1. قرارداد اقدام ایمن پابرجاست: درخواست → طرح → اعتبارسنجی → پیش‌نمایش → تأیید صریح انسان → اجرا → نتیجه/لاگ/مصرف.
2. namespace `AISAAutoPilot`، slug/options/tables/hooks با پیشوند `aisaautopilot_*` و REST namespace `aisaautopilot/v1` تغییر نمی‌کنند.
3. WooCommerce فقط پیش‌نویس محصول می‌سازد؛ انتشار، ویرایش، عملیات گروهی و تغییر سفارش در UI فعال نمی‌شوند.
4. API key هرگز به DOM، پاسخ REST، log یا مرورگر بازگردانده نمی‌شود.
5. هیچ قابلیت cloud یا Phase 2 به‌صورت عملیاتیِ جعلی نمایش داده نمی‌شود؛ کنترل‌های آن قفل‌شده و صریح‌اند.
6. همهٔ copyها i18n-ready، CSSها RTL با logical properties و کنترل‌ها دسترس‌پذیرند.
7. slugهای قدیمی باید alias باقی بمانند؛ حذف صفحه فقط از منوی قابل‌مشاهده مجاز است، نه از مسیرهای bookmark یا routeهای پشتیبان.

## 3. تصمیم‌های ثبت‌شده

| موضوع | تصمیم | دلیل |
|---|---|---|
| نام نمایشی UI | `AutoPilot AI` | نام هدف در موکاپ؛ فقط رشتهٔ نمایشی است و شناسه‌ها را تغییر نمی‌دهد. |
| AI Chat ادمین | نگه‌داری و انتقال از منوی اصلی به Site Knowledge با نام «Ask your knowledge base» | feature و routeهای `/chat/*` باقی می‌مانند ولی IA موکاپ حفظ می‌شود. |
| Native Forms | نگه‌داری به‌صورت tab «Your forms» در Content Studio | فهرست/shortcode حذف نمی‌شود. |
| Logs + History | یک Activity Log در منوی اصلی؛ slugهای هر دو صفحه alias هستند | از پراکندگی IA جلوگیری می‌کند. |
| Woo pages | Store Copilot با tabها؛ فقط وقتی Woo فعال است | منطبق با mockup و contract فعلی. |

هر تغییر آینده که با این تصمیم‌ها یا قرارداد محصول در تضاد باشد، پیش از اجرا باید در همین فایل ثبت شود.

## 4. نقشهٔ مقصد و نگاشت کد

| مقصد نهایی | کد/slug فعلی | اقدام |
|---|---|---|
| Overview | `HomePage` / `-home` | بازطراحی و تغییر برچسب |
| Setup & Connection | `SettingsPage` / `-setup`, `-settings` | wizard چهارمرحله‌ای؛ alias حفظ شود |
| Integrations | — | `IntegrationsPage` و slug جدید؛ بدون route جدید مگر ضرورت ثابت‌شده |
| Site Knowledge | `KnowledgeBasePage` / `-kb` | tabهای Overview, Collections, Indexing Queue, Sync Sources + دسترسی به AI Chat |
| Answer Desk | `ChatWidgetSettings` / `-widget` | Widget, Conversations, Handoff, Voice؛ ویجت public هم‌سو شود |
| Store Copilot | `WooCommerceDashboardPage`, `WooCommerceSettings` | ادغام ظاهری با tab؛ slugهای قدیمی alias |
| Content Studio | `CopilotPage`, `NativeFormsPage` | composer فرم، review/result و tab Your forms؛ کارت‌های Phase 2 قفل |
| Usage & Cost | `UsagePage` / `-usage` | بازطراحی |
| Health | `ConnectionsPage` / `-connections` | بازطراحی |
| Activity Log | `LogsPage`, `HistoryPage` | ادغام ظاهری؛ aliases حفظ |
| EFB Copilot | `EasyFormBuilderPage`, `EfbBuilderButton` | بازطراحی FAB/modal و field-selection awareness |

## 5. مراحل اجرا و معیار پذیرش

- [ ] **0. Baseline و قراردادها:** inventory صفحه/route/asset، ثبت featureهای حفظ‌شونده، اجرای تست‌های پایه و تعیین شکاف‌های backend.
- [ ] **1. Foundation:** tokenهای semantic، رنگ navy/coral، typography، iconها، card/button/field/status/alert/modal، RTL/a11y مشترک.
- [ ] **2. Navigation و aliases:** ترتیب منوی موکاپ، برچسب‌های نهایی، ثبت/پنهان‌سازی slugهای قدیمی و enqueue دقیق هر screen.
- [ ] **3. صفحات کم‌ریسک:** Overview، Usage & Cost، Health و Activity Log با stateهای واقعی.
- [ ] **4. دانش و گفتگو:** Site Knowledge tabها، صف واقعی indexing، Chat ادمین به‌عنوان ابزار دانش‌نامه و source/error/empty state.
- [ ] **5. ساخت و تأیید:** Content Studio، preview غیرقابل‌دورزدن، expiry، approval، نتیجه، Your forms، voice transcript-first.
- [ ] **6. Setup و Integrations:** wizard چهارمرحله‌ای و hub تنظیمات/قابلیت‌ها با detectorهای واقعی.
- [ ] **7. Answer Desk و frontend:** تنظیمات ویجت، handoff، voice، launcher/panel/fullscreen، RTL و محدودیت نرخ.
- [ ] **8. Store و EFB:** Store Copilot feature-gated و draft-only؛ EFB field selection/FAB/modal.
- [ ] **9. تکمیل و QA:** i18n/POT/fa_IR، RTL/LTR، a11y، PHP/JS/CSS checks، manual flowهای اصلی و audit حفظ‌ویژگی‌ها.

## 6. Checklist ویژگی‌هایی که هرگز حذف نمی‌شوند

- [ ] approval و TTL/action one-time
- [ ] KeyVault و masked key status
- [ ] voice در form, admin chat, EFB و public widget (با progressive enhancement)
- [ ] Native Forms، shortcode و delivery test
- [ ] admin AI Chat و همهٔ routeهای `/chat/*`
- [ ] Telegram/Twilio handoff و secret handling
- [ ] public assistant/widget انتخابیِ یکی از دو launcher
- [ ] `kb_auto_index`، usage budget/reset، PDF transcript export
- [ ] Woo capability contract، draft-only و Phase 2 locked
- [ ] redaction، nonce، capability و rate limit

## 7. روش اجرای هر تغییر

1. پیش از تغییر، class/route/asset و stateهای واقعی مرتبط با صفحه را با `rg` inventory می‌کنیم.
2. اول markup و token مشترک، سپس JS محدود و بدون `innerHTML` برای دادهٔ غیرقابل‌اعتماد.
3. هر تغییر فقط روی کد قابل deploy در `apps/wordpress-plugin/ai-site-assistant-autopilot` انجام می‌شود.
4. پس از هر مرحله: `php -l` فایل‌های تغییرکرده، parse JS، بررسی selectorهای CSS و تست‌های واحد/PHPCS متناسب اجرا می‌شود.
5. تنها پس از حفظ مسیرهای موفقیت، خطا، empty، loading، no-permission و RTL، checkbox مرحله تیک می‌خورد.

## 8. خروجی نهایی مورد انتظار

- همهٔ سطوح admin و public با tokenهای موکاپ (navy `#202a8d`، coral `#ff455f`، کارت‌های تخت و کنتراست AA) هم‌خوان‌اند.
- همهٔ صفحات واقعی در IA موکاپ قابل دسترسی‌اند و صفحهٔ حیاتیِ فعلی orphan یا حذف نشده است.
- مسیرهای فرم و Woo همچنان فقط پس از approval اجرا می‌شوند؛ وضعیت‌های Phase 2 هرگز فعال نیستند.
- هیچ secret، UI ساختگی یا regression در route/slugهای قدیمی وجود ندارد.
