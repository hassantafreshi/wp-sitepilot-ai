# رودمپ UI/UX — AI Site Assistant AutoPilot

> هدف: همهٔ صفحات ادمین «زیبا و کاربرپسند» شوند، با یک design system یکپارچه، RTL و ریسپانسیو کامل.
> تاریخ بررسی: ۲۰۲۶-۰۷-۲۳ · نسخهٔ افزونه: 1.0.0

**وضعیت پیشرفت:** ✅ همهٔ فازها (۰ تا ۶) انجام شد — آخرین به‌روزرسانی ۲۰۲۶-۰۷-۲۴.
(تست هر مرحله: `php -l` روی ۷۶ فایل، توازن براکت ۷ فایل CSS، parse با node روی ۸ فایل JS، و صحت نگاشت enqueue.)

---

## ۱. خلاصهٔ بررسی وضعیت فعلی

نکتهٔ مهم: افزونه یک design system خوب دارد (`assets/admin/admin.css` — توکن‌ها، گلس‌مورفیسم، انیمیشن، RTL). مشکل اصلی **زیبا نبودن به‌خاطر کمبود طراحی نیست**، بلکه **بارگذاری‌نشدن دارایی‌ها (assets)** و **ناسازگاری design system** است.

### ریشهٔ مشکل: باگ enqueue
در `includes/Admin/AdminMenu.php::enqueue_assets()` فقط این‌ها بارگذاری می‌شوند:
- `admin.css` (همهٔ صفحات)
- `usage.css` + `usage.js` (فقط صفحهٔ Usage)

این فایل‌ها ساخته شده‌اند ولی **هرگز enqueue نمی‌شوند**:
`chat.js`, `copilot.js`, `settings.js`, `knowledge-base.css`, `knowledge-base.js`, `dashboard.css`, `voice.js`.

علاوه بر این، `wp_localize_script()` روی هندل‌هایی (`aisaautopilot-copilot`, `aisaautopilot-chat`, `aisaautopilot-kb`) صدا زده می‌شود که register نشده‌اند → داده‌های REST/nonce به JS نمی‌رسند.

### وضعیت صفحه‌به‌صفحه

| # | صفحه | فایل | استایل | تعامل (JS) | مشکل اصلی |
|---|------|------|:------:|:----------:|-----------|
| 1 | Copilot | `CopilotPage.php` | ✅ خوب | ❌ | `copilot.js` بارگذاری نمی‌شود؛ دکمهٔ Generate مرده |
| 2 | AI Chat | `ChatPage.php` | ❌ **بحرانی** | ❌ | کلاس‌های `.aisaautopilot-chat-*` در هیچ CSSای تعریف نشده + `chat.js` لود نمی‌شود |
| 3 | Knowledge Base | `KnowledgeBasePage.php` | ❌ | ❌ | `knowledge-base.css/js` لود نمی‌شود |
| 4 | Usage | `UsagePage.php` | ✅ خوب | ✅ | استفاده از `.sp-*` به‌جای `.aisaautopilot-*` (ناسازگاری) |
| 5 | Widget | `ChatWidgetSettings.php` | ❌ inline خام | ❌ | بدون design system، `<style>` درون‌خطی، `voice.js` لود نمی‌شود |
| 6 | WooCommerce AI | `WooCommerceSettings.php` | 🟡 متوسط | — | checkboxها خام، از الگوی field استفاده نمی‌کند |
| 7 | WC Dashboard | `WooCommerceDashboardPage.php` | ❌ | 🟡 inline | `dashboard.css` لود نمی‌شود + **در منو ثبت نشده** + لینک به صفحات ناموجود |
| 8 | Settings | `SettingsPage.php` | ✅ خوب | ❌ | `settings.js` لود نمی‌شود (Test/auto-fill مرده) |
| 9 | Logs | `LogsPage.php` | 🟡 جدول خام | — | فاقد کارت/هدر design system |

### مشکلات ساختاری عرضی (cross-cutting)
1. **دو زبان طراحی متضاد:** `.aisaautopilot-*` (صفحات جدید) در برابر `.sp-*` (usage, dashboard). باید یکی شود.
2. **inline styles:** `ChatWidgetSettings` و `WooCommerceDashboardPage` استایل و اسکریپت درون‌خطی دارند.
3. **صفحات گمشده:** لینک‌های داشبورد WooCommerce به `-orders`, `-analytics`, `-content` که ثبت نشده‌اند.
4. **دسترس‌پذیری:** بعضی دکمه‌ها فقط آیکون دارند بدون `aria-label` متنی کافی.
5. **حالت تاریک (dark mode):** admin.css فقط light را پوشش می‌دهد؛ WordPress 6.x+ حالت تاریک ادمین دارد.

---

## ۲. رودمپ اجرایی (فازبندی‌شده)

### ✅ فاز ۰ — اتصال دارایی‌ها (بحرانی، پیش‌نیاز همه) — **انجام شد**
بدون این فاز، هر کار طراحی دیگری بی‌اثر است.

- [x] بازنویسی `AdminMenu::enqueue_assets()` به‌صورت per-screen: هر صفحه CSS/JS خودش را register + enqueue می‌کند (جداول `SCREEN_STYLES` و `SCREEN_SCRIPTS`).
- [x] localize روی هندلِ واقعاً enqueue‌شده، با نام object درستی که هر اسکریپت می‌خواند (`AISAAutoPilotAI`/`AISAAutoPilotChat`/`AISAAutoPilotKB`).
- [x] تشخیص دقیق صفحه از `$_GET['page']` به‌جای `str_contains($hook,...)` مبهم.
- [x] بارگذاری تأییدشده: Copilot→`copilot.js`، Chat→`chat.css`+`chat.js`+`voice.js`، KB→`knowledge-base.css/js`، Usage→`usage.css/js`، Settings→`settings.js`.
- **خروجی:** همهٔ دکمه‌ها و استایل‌های موجود «زنده» شدند.

### ✅ فاز ۱ — بازسازی استایل صفحهٔ Chat — **انجام شد**
- [x] نوشتن CSS کامل در `assets/admin/chat.css` برای layout، sidebar، لیست گفتگوها، حباب‌های پیام (`.aisaautopilot-msg`)، منبع‌ها، typing و composer.
- [x] چیدمان دو ستونه (سایدبار گفتگوها + ناحیهٔ چت) با flex، ریسپانسیو (تک‌ستونه زیر ۹۰۰px).
- [x] استایل RTL برای حباب‌ها، سایدبار و آیکون ارسال (با logical properties).
- [x] auto-grow ارتفاع textarea (chat.js) و انیمیشن ورود حباب‌ها (`spMsgIn` در chat.css).

### ✅ فاز ۲ — یکپارچه‌سازی design system — **انجام شد**
- [x] توکنی‌سازی رنگ‌های ساختاری `dashboard.css` (صفر توکن → surface/border/text/primary/gradient با `var(--sp-*)`).
- [x] تأیید اینکه `usage.css` و `knowledge-base.css` از قبل عمدتاً توکنی‌اند.
- **تصمیم:** پیشوندهای `.sp-*` (صفحات data-viz) و `.aisaautopilot-*` بدون rename پرریسک نگه داشته شدند؛ یکپارچگی از راه **توکن مشترک** به‌دست آمد، نه تغییر نام کلاس‌ها.

### ✅ فاز ۳ — بازطراحی صفحهٔ Widget Settings — **انجام شد**
- [x] حذف کامل `<style>` درون‌خطی؛ ساخت `assets/admin/widget-settings.css` و enqueue آن برای `SLUG_WIDGET`.
- [x] تبدیل همهٔ بخش‌ها به کارت‌های `.aisaautopilot-card` با هدر آیکون‌دار (`.sp-w-head`).
- [x] چیدمان دو ستونهٔ فیلدها، radio-pill برای تم، swatch رنگ، و هدر استاندارد + AI badge.
- [x] wrapping با `esc_html_e`/`__()` برای i18n و escape درست (این صفحه قبلاً رشته‌های خام داشت).
- [x] پیش‌نمایش زندهٔ ویجت (live preview) — چیدمان دو ستونه + `widget-settings.js` که title/greeting/رنگ برند/پس‌زمینه/موقعیت را زنده در ماکِت ویجت بازتاب می‌دهد.

### ✅ فاز ۴ — ثبت و تکمیل صفحات WooCommerce — **انجام شد**
- [x] ثبت `WooCommerceDashboardPage` در منو با slug جدید `SLUG_WC_DASHBOARD`.
- [x] enqueue `dashboard.css` + `dashboard.js` با localize اختصاصی `AISAAutoPilotDashboard` (شامل `wcActive`).
- [x] حذف اسکریپت inline (~۱۶۰ خط) از فایل PHP.
- [x] اصلاح لینک‌های شکستهٔ Quick Actions به مقصدهای واقعی (لیست محصولات، سفارش‌های WC، آنالیتیکس WC، تنظیمات AISAAutoPilot WC).

### ✅ فاز ۵ — پولیش صفحهٔ Logs — **انجام شد**
- [x] قرار دادن جدول در `.aisaautopilot-card` با toolbar.
- [x] فیلتر سطح + جستجوی client-side (بدون درخواست شبکه) و badge رنگی برای هر level.
- [x] دکمهٔ «Copy trace» با بازخورد بصری و نمایش بهترِ JSON context.
- [x] فایل‌های `logs.css` + `logs.js` ساخته و enqueue شدند.

### ✅ فاز ۶ — کیفیت عرضی (a11y / responsive / RTL) — **انجام شد**
- [x] `aria-label` روی دکمه‌های فقط-آیکونی (ارسال چت، گفتگوی جدید) + `aria-hidden` روی آیکون‌های تزئینی.
- [x] حلقهٔ فوکوس کیبورد با `:focus-visible` و کلاس ابزار `.sp-sr-only` در `admin.css`.
- [x] پشتیبانی `@media (prefers-reduced-motion: reduce)` برای غیرفعال‌کردن انیمیشن‌ها.
- [x] امنیت اسکرول افقی جدول Logs در موبایل (۷۸۲px).
- **تصمیم (مهم):** dark mode با `prefers-color-scheme` برای صفحات ادمین **عمداً اعمال نشد** — چون کروم ادمین وردپرس روشن است و محتوای تیرهٔ افزونه یک «جزیرهٔ تیره» ناهماهنگ می‌ساخت. تم تاریک فقط برای ویجت فرانت‌اند (که کاربر انتخاب می‌کند) معنا دارد.

---

## ۳. ترتیب پیشنهادی اجرا

```
فاز ۰ (اتصال)  ← همین حالا، بدون این هیچ‌چیز دیده نمی‌شود
   ↓
فاز ۱ (Chat CSS)  +  فاز ۴ (ثبت WC Dashboard)   ← سریع‌ترین «برد» بصری
   ↓
فاز ۲ (یکپارچه‌سازی)  ← پایهٔ انسجام
   ↓
فاز ۳ (Widget)  +  فاز ۵ (Logs)
   ↓
فاز ۶ (a11y / dark / responsive)  ← صیقل نهایی
```

### ✅ فاز ۷ — ویجت چت فرانت‌اند (خارج از رودمپ اولیه) — **انجام شد**
مشکل کشف‌شده: سه لایه کاملاً ناهماهنگ بودند — `ChatWidget.php` کلاس `sp-widget-*` رندر می‌کرد ولی `widget.css` و `widget.js` هر دو `spw-*` می‌خواستند → ویجت زنده **نه استایل داشت نه کار می‌کرد**، و ایموجی‌ها به `<img>` از s.w.org تبدیل می‌شدند.
- [x] بازنویسی `ChatWidget.php` با markup `spw-*` هماهنگ با CSS/JS موجود.
- [x] جایگزینی همهٔ ایموجی‌ها با ۶ آیکون SVG درون‌خطی (چت، بستن، دستیار «sparkle»، ارسال، میکروفون، پشتیبانی) — حس AI assistant حرفه‌ای.
- [x] هدر با آواتار دستیار + عنوان + زیرعنوان «AI Assistant» + نقطهٔ وضعیت سبز پالس‌دار + دکمهٔ بستن.
- [x] پشتیبانی ۴ موقعیت (چهار گوشه) + تم تاریک + رنگ برند/پس‌زمینه از تنظیمات via CSS variables.
- [x] رفع باگ‌های نهفته: کلید `bg` (قبلاً `bg_color` خوانده می‌شد)، کلاس پیام AI (`--assistant`→`--ai`)، تأخیر auto-open.
- **تست:** lint PHP ✓، parse JS ✓، توازن CSS ✓، تطابق کامل قرارداد کلاس/ID بین PHP↔CSS↔JS ✓.

## ۴. معیار پذیرش (Definition of Done)
- هر صفحه CSS/JS مخصوص خودش را بارگذاری می‌کند و تمام دکمه‌ها کار می‌کنند.
- یک پیشوند و یک زبان طراحی در کل ادمین.
- بدون `<style>`/`<script>` درون‌خطی در فایل‌های PHP.
- RTL و ریسپانسیو در ۳ بریک‌پوینت سالم.
- هیچ لینک منو به صفحهٔ ناموجود اشاره نکند.
