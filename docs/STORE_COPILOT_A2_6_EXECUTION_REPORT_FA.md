# گزارش اجرایی Store Copilot - A2.6

تاریخ تکمیل و اجرای QA: ۲۰۲۶-۰۹-۰۱

## وضعیت فعلی

چهار سطح Store Copilot پیاده‌سازی، در WordPress محلی اجرا و با گیت‌های کامل تأیید شد. وضعیت A2.6 اکنون **تکمیل‌شده** است: lint، تست‌های WordPress integration، suite رگرسیون، QA مرورگر در دسکتاپ/RTL/390px و بازبینی مستقیم screenshotها همگی پاس شده‌اند. هر دو fixture و حساب موقت QA پس از هر اجرا پاک شدند.

| کار ریز | وضعیت کد | گیت پذیرش باقی‌مانده |
|---|---|---|
| A2.6.1 پوسته، route و alias | تکمیل | PHP syntax، PHPCS کامل، PHPStan، render/assets smoke و بررسی هر دو alias پاس |
| A2.6.2 New product | تکمیل | REST، proposal/TTL، draft-only smoke، RTL و responsive پاس |
| A2.6.3 What it can do | تکمیل | persistence، administrator/subscriber permission، paused read/write gate و RTL/responsive پاس |
| A2.6.4 Products و Orders | تکمیل | read-model واقعی، marker، empty-state contract و cleanup fixture پاس |
| A2.6.5 QA نهایی Store | تکمیل | browser desktop/RTL/390px، console/pageerror و screenshot review در LTR و RTL واقعی پاس |

## صفحات دقیقاً مطابق ساختار موکاپ

| tab موکاپ | renderer فعلی | قرارداد واقعی |
|---|---|---|
| `New product` | `StoreCopilotPage::render_new_product()` | prompt → proposal → TTL پانزده‌دقیقه‌ای → تأیید صریح → فقط یک WooCommerce draft ساده. پیش از تأیید هیچ تغییری در فروشگاه رخ نمی‌دهد. |
| `What it can do` | `StoreCopilotPage::render_capabilities()` | ۹ ردیف capability، ۶ ردیف status، دو setting زنده و دو setting قفل‌شدهٔ Phase 2. |
| `Products` | `StoreCopilotPage::render_products()` | فهرست واقعی و compact محصولات، با marker واقعی `_aisaautopilot_generated` برای draft ساخته‌شده توسط AutoPilot؛ بدون edit/publish/bulk CTA. |
| `Orders` | `StoreCopilotPage::render_orders()` | قرارداد واقعی permissionها را نشان می‌دهد؛ UI ساختگی سفارش، refund یا status-change ندارد. |

هر دو route قدیمی `aisaautopilot-wc-dashboard` و `aisaautopilot-woocommerce` حفظ شده‌اند. اولی renderer canonical را نمایش می‌دهد و دومی به tab `What it can do` می‌رود تا copy قدیمیِ create/edit/bulk قابل‌برداشت نادرست نباشد.

## قرارداد فنی و ایمنی

- `AdminStoreSettingsController` فقط دو preference واقعی را ذخیره می‌کند: `enabled` و `enable_search`.
- invariantهای Phase 1 در سرور ثابت‌اند: `auto_draft=true` و `enable_edit=false`؛ کلاینت نمی‌تواند آن‌ها را باز کند.
- خاموش‌کردن connector اکنون علاوه بر UI، permission تمام routeهای محافظت‌شدهٔ Store را می‌بندد. خود create-draft skill نیز مستقل از route همین gate را دوباره بررسی می‌کند.
- smart search فقط یک endpoint محافظت‌شده، read-only و پیش‌فرض خاموش است؛ هیچ provider call یا visitor-facing search از آن ساخته نشده است.
- fixture مرورگر فقط یک draft نام‌دار با marker اختصاصی می‌سازد و در restore فقط همان رکورد markerدار و setting قبلی را برمی‌گرداند.

## قابلیت‌های موجودِ افزونه که موکاپ برایشان UI ندارد

این موارد عمداً به action قابل‌استفاده در UI Store تبدیل نشده‌اند؛ این فهرست پاسخ مستقیم به اختلاف «feature موجود بدون UI موکاپ» است.

| مورد | وضعیت واقعی | تصمیم UI |
|---|---|---|
| `GET /woocommerce/check` و `GET /woocommerce/categories` | endpoint مدیریتی محافظت‌شده؛ با connector paused بسته است | UI جداگانه ندارد؛ دادهٔ لازم در render سرور استفاده می‌شود. |
| `POST /woocommerce/smart-search` | endpoint مدیریتی read-only، opt-in و بدون provider call | UI search در موکاپ وجود ندارد؛ بنابراین هیچ فرم جست‌وجوی visitor یا dashboard اضافه نشده است. |
| `GET /woocommerce/analytics/dashboard` | endpoint مدیریتی read-only و ثبت‌شده | موکاپ tab analytics ندارد؛ به‌جای نمودار قدیمی فقط capability واقعی نمایش داده می‌شود. |
| `GET /woocommerce/orders/recent` | فقط summary اخیر برای store manager و ثبت‌شده | به‌دلیل نبود UI معتبر در موکاپ، Orders فقط قرارداد دسترسی واقعی را نشان می‌دهد. |
| handlerهای order search/detail/status/refund و Woo content/SEO/bulk | کدهای legacy وجود دارند، ولی route ثبت‌شده ندارند | dormant هستند و در UI، CTA یا prompt ندارند؛ برای Phase 2 گزارش شده‌اند. |
| `assets/admin/dashboard.js` | asset قدیمی هنوز در tree هست اما enqueue نمی‌شود | dashboard قدیمی دیگر renderer یا UI فعال ندارد؛ حذف فیزیکی asset در cleanup جداگانه انجام می‌شود تا تغییر ناخواسته در worktree رخ ندهد. |

## اختلاف‌های مهم موکاپ با قابلیت واقعی

- موکاپ lookup سفارش برای مشتری و خواندن سفارش خودش را القا می‌کند. افزونه چنین route عمومی و ownership-safe ثبت‌شده‌ای ندارد؛ بنابراین UI به‌جای ادعای نادرست، آن را `Not registered` نشان می‌دهد.
- publish، edit، bulk product، تغییر status، cancel و refund هیچ route ثبت‌شده یا کنترل فعال Phase 1 ندارند؛ همه قفل هستند.
- نمونه‌داده‌ها و آمار نمایشی موکاپ به دادهٔ ساختگی تبدیل نشده‌اند. Products فقط دادهٔ WooCommerce واقعی را می‌خواند و در حالت خالی، empty state صادقانه دارد.
- provider success/error واقعی برای proposal فقط با provider آزمایشی پیکربندی‌شده قابل‌سنجش است؛ تست معمولی Store عمداً provider را فراخوانی نمی‌کند.

## گیت‌های اجراشده و شواهد

1. PHP syntax برای همهٔ فایل‌های Store/تست تغییرکرده و `node --check` برای `woocommerce.js` و browser suite: **PASS**.
2. PHPCS کامل: **124/124، ۰ error و ۰ warning**. PHPStan با سقف حافظهٔ 1G (حد 512M پیکربندی‌شدهٔ محیط کافی نبود): **۰ error**.
3. `wordpress_store_copilot_settings_integration.php`: **7/0**؛ route، permission، persistence، invariant و paused read/write gate.
4. `wordpress_store_copilot_page_integration.php`: **6/0**؛ ترتیب چهار tab، proposal/TTL، capability/status/settings، alias و حذف dashboard قدیمی.
5. `woo_draft_smoke.php`: **3/0**؛ یک draft سادهٔ WooCommerce با marker مالکیتی، replay و publish validator. `wordpress_store_copilot_read_model_integration.php`: **3/0**؛ read-model واقعی، marker و cleanup در `finally`.
6. `local_admin_assets_smoke.php`: **18** قرارداد route/asset پاس؛ `local_admin_render_smoke.php` چهار tab Store را روی WordPress محلی render کرد. regression: `wordpress_phase1_integration.php` **45/0** و PHPUnit **131 test / 429 assertion**.
7. browser release check روی هر دو alias `aisaautopilot-wc-dashboard` و `aisaautopilot-woocommerce`: **exit 0**. desktop، toggle persistence، Products/Orders contract، 390px، console/pageerror و screenshot review پاس شدند. RTL هم یک‌بار با تغییر کنترل‌شدهٔ `dir` و یک‌بار با shell واقعی WordPress در locale `fa_IR` اجرا شد؛ title، description و capability panels همگی داخل viewport ماندند.

همهٔ گیت‌های A2.6 سبز هستند و نتیجهٔ PASS در tracker اصلی ثبت شده است. موفقیت provider واقعی خارج از این suite باقی می‌ماند، زیرا QA عمداً هیچ درخواست provider خارجی ایجاد نمی‌کند.
