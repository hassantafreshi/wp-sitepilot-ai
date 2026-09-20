# معماری

## ساختار پوشه‌ها

| مسیر | مسئولیت |
|---|---|
| `ai-site-assistant-autopilot.php` | سربرگ افزونه، ثابت‌ها (`AISAAUTOPILOT_VERSION`، `_FILE`، `_DIR`، `_URL`، `_BASENAME`)، autoloader و hookهای فعال‌سازی |
| `includes/Core` | `Plugin` (راه‌اندازی)، `Container`، `Settings`، `Activator` و `Deactivator`، `RebrandMigrator`، `WpConnectors`، `SiteLanguage` |
| `includes/Admin` | همهٔ صفحه‌های مدیریت، `AdminMenu`، کتابخانهٔ `Ui`، `ConnectionChip`، ناوبری Answer Desk و Content Studio، خروجی CSV و `HealthDiagnostics` |
| `includes/Api` | `RestRoutes` و کنترلرهای REST |
| `includes/Providers` | `ProviderManager`، providerهای OpenAI-compatible و Anthropic، `UsageCappedProvider`، trait `SafeHttp` |
| `includes/Content` | `KnowledgeBase`، `ContentIndexer`، `ContentSummarizer`، `VectorSearch`، `KbCache`، `KbGaps`، `ContentHooks`، read model محتوای Content Studio |
| `includes/Chat` | `ChatEngine` (RAG)، `ConversationManager` (کنسول مدیر)، `VisitorConversationStore` (ثبت گفت‌وگوی ویجت) |
| `includes/Support` | `HandoffService`، کانال‌های Email و Telegram و Twilio، `HandoffInbox`، `PdfExporter` |
| `includes/Skills` و `includes/Execution` | skillهای تبدیل درخواست به plan، schema و validatorها، `ActionStore` و executorهای بعد از تأیید |
| `includes/Connectors` | قرارداد connector، connector بومی و Easy Form Builder، WooCommerce و skillهای آن |
| `includes/Efb` | دکمهٔ Copilot روی ویرایشگر EFB، skillهای گفت‌وگو و ساخت و ویرایش، مدل و کاتالوگ قابلیت‌ها (`efb-capability-catalog.json`) |
| `includes/Frontend` | ویجت بازدیدکننده (`ChatWidget`)، shortcode فرم، صفحهٔ پیشرفتهٔ ویجت (`ChatWidgetSettings`) |
| `includes/Usage`، `includes/Voice`، `includes/Security`، `includes/Logging`، `includes/Activity`، `includes/Forms` | شمارش مصرف، تبدیل گفتار، رمزگذاری و پاک‌سازی و rate limit، لاگ، read model فعالیت، اعتبارسنجی ارسال فرم |
| `assets/admin` | `aisaautopilot-ui.css` (یگانه stylesheet مدیریت)، `shell.js` و اسکریپت هر صفحه |
| `assets/frontend` | `widget.js` و `widget.css` (ویجت Answer Desk)، `form.css` |
| `languages` | POT، PO و MO فارسی |
| `tests` | PHPUnit، integrationها، smokeها، browser checkها، matrixها و fixtureهای محلی |

`includes/Frontend/WidgetRenderer.php` و فایل‌های `assets/frontend/chat-widget.*` در هیچ مسیر فعالی استفاده نمی‌شوند و باقی‌ماندهٔ رابط قدیمی‌اند. در ممیزی انتشار (T19 و T21) باید تصمیم حذف یا نگهداری‌شان ثبت شود.

## راه‌اندازی

### فعال‌سازی و غیرفعال‌سازی

`Activator::activate()` این کارها را به ترتیب انجام می‌دهد:

1. اگر PHP کمتر از 8.1 باشد، افزونه را غیرفعال و پیام خطا نمایش می‌دهد.
2. `RebrandMigrator::run()` دادهٔ نام قدیمی `sitepilot-ai` را منتقل می‌کند.
3. post typeهای فرم بومی را ثبت و rewrite rules را تازه می‌کند.
4. اگر option تنظیمات نباشد، مقدار پیش‌فرض `Settings::defaults()` را می‌گذارد.
5. جدول‌های `aisaautopilot_kb` و `aisaautopilot_chats` را می‌سازد و schema صندوق انتقال و گفت‌وگوی ویجت را تضمین می‌کند.

`Deactivator::deactivate()` فقط rewrite rules را تازه می‌کند و هیچ داده‌ای را پاک نمی‌کند.

### `Plugin::boot()`

| مرحله | جزئیات |
|---|---|
| ترجمه | `load_translations()` فایل MO همین افزونه را برای `determine_locale()` با `load_textdomain()` بار می‌کند، چون MO فارسی داخل بستهٔ افزونه است. |
| مهاجرت | `Settings::migrate_legacy_handoff_secrets()` اسرار قدیمی handoff را از تنظیمات عمومی به option جدا منتقل می‌کند. |
| سرویس‌ها | `ConnectorRegistry` با connector EFB و بومی ساخته و در container گذاشته می‌شود. |
| `init` | ثبت post typeها، `HandoffInbox::ensure_schema()`، `VisitorConversationStore::ensure_schema()` و shortcode فرم |
| `admin_menu` و `admin_enqueue_scripts` | `AdminMenu::register()` و `AdminMenu::enqueue_assets()` |
| `rest_api_init` | `RestRoutes::register()` |
| خروجی CSV | `LogExport` و `ContentStudioHistoryExport` روی `admin_post_*` |
| ویجت | shortcode `[aisaautopilot_chat]`، `wp_enqueue_scripts` و `wp_footer`. هر سه همیشه ثبت می‌شوند و خود renderer دروازهٔ Answer Desk را می‌سنجد، تا تغییر تنظیمات بدون بارگذاری دوبارهٔ افزونه اثر کند. |
| EFB | در مدیریت، `EfbBuilderButton` دکمه و پنل Copilot را روی صفحه‌های EFB تزریق می‌کند. |
| دانش سایت | `ContentHooks` هم ایندکس اختیاری هنگام انتشار و هم پاک‌سازی اجباری هنگام حذف یا زباله‌دان را ثبت می‌کند. |
| کلید وردپرس | `WpConnectors::register()` جایگاه کلید را در Connectors API وردپرس 7 ثبت می‌کند. |

`Container` یک container تنبل و کوچک است و فقط اشیای مشترک را نگه می‌دارد.

## سیستم UI مدیریت

### اجزای مشترک

- **`Admin\Ui`:** `page_header()` (عنوان، زیرعنوان، chip اتصال، دکمهٔ اصلی، subtabs)، `alert()`، `banner()`، `icon()` و `solid()`، `pill()`، `kv()`، `slot()` (بارگذاری PNG با پیش‌نمایش)، `json_script()` (دادهٔ امن JSON با hex-escape) و `overlays()` (میزبان toast و busy).
- **`ConnectionChip::derive()`:** وضعیت هدر را از دادهٔ واقعی می‌سازد: «provider · model»، «سقف مصرف رسیده» یا «سرویس هوش مصنوعی ندارید».
- **ناوبری:** `AnswerDeskNavigation` و `ContentStudioNavigation` tabها و آدرس‌ها را تعریف می‌کنند.
- **stylesheet:** `assets/admin/aisaautopilot-ui.css` تنها stylesheet مدیریت است. tokenها روی `:root` تعریف شده‌اند (`--sp-ink #14152b`، `--sp-primary #202a8d`، `--sp-line #e4e6f0`، `--sp-line-soft`، `--sp-muted #5b5e7d`، `--sp-faint #686b8a` (از T17؛ پیش‌تر `#9ca3af` با 2.54:1)، `--sp-err #ff455f` و دیگران) و فونت‌ها Plus Jakarta Sans و JetBrains Mono هستند. قواعد با propertyهای منطقی (`inline-size`، `padding-inline`، `border-inline-start`) نوشته شده‌اند تا RTL بدون قاعدهٔ جدا کار کند. استثناها فقط برای letter-spacing عنوان‌های uppercase و قرینه‌سازی آیکون‌های جهت‌دار با `html[dir="rtl"]` است.
- **شکست‌گاه‌ها:** 782px (ستونی شدن و پنهان شدن سربرگ جدول‌ها)، و 960px یا 1100px برای layoutهای دو ستونی.

### اسکریپت‌ها و ترجمهٔ آن‌ها

- **`shell.js`:** روی هر صفحه بار می‌شود و این API را می‌دهد:
  - `AISAAutoPilotShell.toast(message)`
  - `AISAAutoPilotShell.job(...)` برای نوار کار طولانی
  - `AISAAutoPilotShell.confirm({ title, body, confirmLabel, cancelLabel, danger })`، که `Promise<boolean>` برمی‌گرداند

  متن‌های پوسته در `AISAAutoPilotShellConfig` محلی‌سازی می‌شوند.
- **`AdminMenu::SCREEN_SCRIPTS`:** هر slug را به فهرست `[فایل، نام شیء محلی]` نگاشت می‌کند. شیء محلی شامل `restUrl`، `nonce` (`wp_rest`)، `usage` (هشدار آستانه) و `i18n` از `script_strings()` است.
- **متن مخصوص صفحه:** با `Ui::json_script()` در خود صفحه قرار می‌گیرد و اسکریپت با `JSON.parse` می‌خواند. نمونه‌ها: `sp-kb-i18n`، `sp-chat-i18n` و `sp-chat-config`، `sp-handoff-inbox-data`، `sp-wz-i18n`، `sp-adv-i18n`. هیچ رشتهٔ نمایشی در JS سخت‌کد نمی‌شود، جز fallback انگلیسی.

## الگوهای رابط که باید تکرار شوند

| الگو | قاعده | نمونه در کد |
|---|---|---|
| تأیید کار مخرب | همیشه `AISAAutoPilotShell.confirm` با عنوان و متنی که دقیقاً می‌گوید چه تغییر می‌کند و چه نمی‌کند. دکمهٔ Cancel اول focus می‌گیرد، Escape لغو می‌کند و focus به کنترل آغازگر برمی‌گردد. | حذف مدخل دانش سایت، reset مصرف، حذف گفت‌وگوی کنسول، Discard تنظیمات |
| overlay تمام‌صفحه | `.sp-screen` انیمیشن transform دارد و عنصر `position: fixed` داخلش در همان بخش حبس می‌شود. کشو و مودال باید مستقیم زیر `.sp-app` قرار بگیرند. | `shell.js` مودال را به `.sp-app` می‌افزاید و `handoff-inbox.js` کشو را به همان‌جا منتقل می‌کند. |
| نوار تغییرات ذخیره‌نشده | کارت سفید روشن (`.sp-wz-savebar__inner`) با `position: sticky` در انتهای فرم، نه fixed. متن نوار می‌گوید چه چیزی ذخیره نشده است. پس از اولین تغییر واقعی ظاهر می‌شود، Save و Discard دارد و Discard با GET بارگذاری می‌کند تا فرم دوباره ارسال نشود. هشدار `beforeunload` فقط هنگام تغییر ذخیره‌نشده فعال است. | `ChatWidgetSettings` و `SettingsPage::render_advanced()` |
| ذخیرهٔ محدود به فرم | فقط کلیدهایی که فرم فرستاده ذخیره می‌شوند. هر switch پیش از checkbox یک `hidden` با مقدار `0` دارد. کلیدهای صفحه‌های دیگر هرگز صفر نمی‌شوند. | `ChatWidgetSettings::save()` و `SettingsPage::save_advanced()` |
| پیشنهاد آماده و گفتار | متنی که AI ساخته (chip پیشنهادی، گزینهٔ clarify، طرح پیشنهادی) و متن پیاده‌شدهٔ صدا فقط composer را پر و focus می‌کنند. هیچ درخواستی خودکار فرستاده نمی‌شود. **استثنای D-24:** پرسش‌های پیشنهادی ویجت بازدیدکننده را مدیر سایت نوشته، نه AI، عمل مخربی ندارند و با کلیک ارسال می‌شوند (هزینه: یک پرسش از سقف 20 در ساعت). این استثنا فقط برای متن نوشتهٔ انسان است؛ گزینه‌های clarify همان ویجت پر‌کننده می‌مانند. | `chat.js`، `voice.js`، Form Studio، EFB Copilot؛ ویجت: `[data-spw-ask]` ارسال و `[data-spw-clarify-option]` پر کردن |
| حالت واقعی | chip، شمار و وضعیت از دادهٔ واقعی ساخته می‌شوند. آنچه backend ندارد قفل یا «planned» است و در سند گزارش می‌شود. متن وضعیتی که سرور رندر می‌کند و کنترل‌های همان صفحه آن را درجا تغییر می‌دهند، با همان اسکریپت به‌روز می‌شود تا صفحه با خودش تناقض نداشته باشد: نوار صندوق در `answer-handoff.js` با `data-body-ready` و `data-body-unavailable`. | کارت‌های WhatsApp و Slack در handoff، `EasyFormBuilderPage` |
| قرارداد آزمون | هر سطح `data-sp-*` پایدار دارد و آزمون‌ها روی همان، نه روی متن یا class ظاهری، می‌سنجند. یادداشت‌های صادقانه‌ای که آزمون مرورگر می‌سنجد هم hook دارند و متنشان به زبان رابط بررسی می‌شود: در `fa_IR` خط فارسی و در انگلیسی متن دقیق. | `data-sp-chat-console`، `data-sp-handoff-item`، `data-sp-widget-form`، `data-sp-advanced-form`، `data-sp-kb-queue-scope`، `data-sp-handoff-delivery-mode` |
| کنتراست متن (T17) | متن کم‌رنگ فقط از `--sp-faint` (`#686b8a`، 4.95:1 روی سفید) یا تیره‌تر. هیچ رنگ خاکستری روشن سخت‌کد، و هیچ `opacity` روی متنی که هنوز فعال است، برای «کم‌رنگ» استفاده نمی‌شود. فقط کنترل واقعاً `:disabled` از 4.5:1 معاف است. | `--sp-faint` در `aisaautopilot-ui.css`؛ `.sp-wz-card__grid.is-muted` که به‌جای opacity پس‌زمینهٔ فیلد را رنگ می‌کند |
| نشانگر focus | هر کنترل با کیبورد نشانگر مرئی دارد. قاعدهٔ `:focus` باید از قاعدهٔ حالت عادی همان عنصر specificity بیشتری داشته باشد؛ `.sp-app input[type="text"].sp-input` (0,3,1) قاعدهٔ `.sp-app .sp-input:focus` (0,3,0) را خنثی می‌کرد و هیچ فیلد متنی focus نشان نمی‌داد، پس قاعدهٔ focus کلاس را تکرار می‌کند. | `.sp-app .sp-input.sp-input:focus` |
| جهت و تراز در RTL | در سربرگ صفحه و بلوک‌های متنی `text-align: start` پیش‌فرض بماند. `text-align: end` در RTL یعنی چپ و عنوان را از لبهٔ راست جدا می‌کرد (F15-1). متنی که مدیر یا مدل نوشته (`dir="auto"`) ترازش را از جهت ریشهٔ سطح می‌گیرد، نه از خط خودش، مثل ویجت. | `.sp-pagebar__titles`؛ `.sp-answer-preview__widget[dir] [dir="auto"]`؛ پاسخ و پیام کاربر در `efb-copilot.js` |
| مقدار بریده | مقدار داده (مدل، endpoint، محل کلید، نام رنگ) با ellipsis بریده نمی‌شود، چون در RTL ابتدای متن لاتین حذف می‌شد (F15-3)؛ `overflow-wrap: anywhere` می‌شکند. ellipsis فقط وقتی مجاز است که `title` کل مقدار را داشته باشد. | `.sp-adv-facts dd`، `.sp-wz-part__label` |
| اندازهٔ هدف | مدیریت: WCAG 2.2 AA 2.5.8، یعنی 24×24 یا فاصلهٔ کافی از هدف‌های دیگر. ویجت بازدیدکننده: 44px برای هر دکمهٔ آیکونی، و در ≤640px برای chip، دکمه‌های کارت انتقال و پیوند انتقال پانویس (پیوند با padding و margin منفی رو به پایین، تا روی composer نیفتد). | `tests/admin_a11y_browser_check.cjs`، `assets/frontend/widget.css` |
