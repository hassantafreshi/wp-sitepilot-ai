# گزارش بازبینی امنیتی بازطراحی — T20 (فاز ۴)

تاریخ: ۲۰۲۶-۰۹-۱۸ تا ۱۹  
دامنه: `git diff --name-only 56dd00b` به‌همراه فایل‌های untracked در `includes/` و `assets/`: **۱۲۴ فایل** (۱۱۸ تغییریافته و ۶ تازه؛ فهرست در `t20-scope.txt`).  
شواهد: `C:\files\projects\SitePilot AI\test-artifacts\security-review\20260918-t20\`

## نتیجه

**گیت T20 سبز است:** هر یافته یا با کد و آزمون رفع شده، یا با شاهد به‌عنوان مثبت کاذب بسته شده است. هیچ یافته‌ای برای «پذیرش با ریسک» به مالک واگذار نشد؛ تنها موردی که پیشنهاد پذیرش داشت (R-1، محتوای فایل صوتی) به خواست مالک همان‌جا رفع شد.

چهار نقص واقعی پیدا و رفع شد. هیچ‌کدام از راه دور و بدون پیش‌شرط قابل بهره‌برداری نبود، ولی هر چهار مورد پیش از انتشار باید بسته می‌شدند:

| ID | شدت | نقص | رفع | آزمون |
|---|---|---|---|---|
| S20-1 | متوسط | **XSS ذخیره‌شده در کنسول «Ask your knowledge base».** `escHtml()` در `assets/admin/chat.js` با `textContent → innerHTML` فقط `<`، `>` و `&` را escape می‌کرد، نه نقل‌قول را، و همان خروجی داخل `href="…"` می‌رفت. پاسخی که روی محتوای سایت تزریق شده باشد (مثلاً نوشته‌ای که یک نویسنده با نقش پایین‌تر منتشر کرده و ایندکس شده) می‌توانست با `[x](https://a/"onmouseover="…)` از `href` بیرون بیاید و به پیوند یک event handler بچسباند، که با کلیک یا hover مدیر (`manage_options`) اجرا می‌شد. این الگو از 56dd00b وجود داشت | `escHtml()` نقل‌قول‌ها را به `&quot;` و `&#39;` تبدیل می‌کند و الگوی پیوند نشانی‌ای را که نقل‌قول یا `&` خام دارد لینک نمی‌کند (`&amp;` در query string همچنان مجاز است) | `tests/admin_chat_markdown_check.cjs` (۷ سنجه، آفلاین با DOM ساختگی)؛ روی `chat.js` نسخهٔ 56dd00b شکست می‌خورد (`negative-control-chat-baseline.txt`) |
| S20-2 | متوسط | **حذف افزونه دادهٔ بازدیدکننده را جا می‌گذاشت** (یافتهٔ Q19-1 از T19). `uninstall.php` جدول‌های `aisaautopilot_widget_chats` (متن گفتگوها) و `aisaautopilot_handoffs` (متن و اطلاعات تماس درخواست انتقال) را drop نمی‌کرد و ۱۱ option، از جمله کلید provider ذخیره‌شده از راه WordPress Connectors (`aisaautopilot_wp_connector_key`)، را پاک نمی‌کرد | هر دو جدول drop می‌شوند، ۱۲ option (شامل `aisaautopilot_last_diagnostics` یتیم‌ماندهٔ 1.0.4) پاک می‌شوند و قفل‌های `aisaautopilot_action_claim_*` با `DELETE … LIKE` می‌روند | `tests/UninstallInventoryTest.php` هر ثابت `*OPTION*`/`*TABLE*` در `includes/` را در `uninstall.php` می‌جوید (۳ آزمون)؛ روی `uninstall.php` نسخهٔ 56dd00b ۲ شکست می‌دهد (`negative-control-uninstall-baseline.txt`) |
| S20-3 | کم | **توکن Telegram در لاگ.** الگوی `Redactor` توکن ربات را با `\b` در آغاز می‌گرفت، ولی در نشانی API توکن بی‌فاصله پس از `/bot` می‌آید (`…/bot123456:AAH…`) و بین `t` و رقم مرز کلمه نیست، پس پیام خطایی که URL را داشت توکن را سالم در لاگ می‌نوشت. پیام‌های فعلی `HandoffTelegram` نشانی را لاگ نمی‌کنند، ولی `get_error_message()` وردپرس گاهی URL را دارد | `(?<![0-9])` به‌جای `\b` | `tests/RedactorTest.php::test_telegram_token_redacted_inside_api_url` |
| S20-4 | کم | **نوع فایل صوتی از کلاینت پذیرفته می‌شد.** هر چهار مسیر تبدیل گفتار، از جمله `/public/voice` که ناشناس است، فقط اندازه (۵MB) و مدت اعلام‌شده را می‌سنجیدند و `Content-Type` و نام فایل کلاینت را به provider می‌فرستادند. هر بایتی تا ۵MB به provider می‌رفت | `VoiceTranscriber::detect_mime()` ۱۲ بایت آغازین را با امضای WebM، Ogg، MP4 و WAV می‌سنجد؛ `validate_file()` غیر از این چهار را با `invalid_audio` رد می‌کند و نام و نوع فایلی که به provider می‌رود از بایت‌ها ساخته می‌شود، نه از سربرگ کلاینت | `tests/LanguageAndVoiceTest.php` (+۲ آزمون: چهار container با `Content-Type` نادرست می‌گذرند؛ متن، PHP، PNG، `RIFF` بدون `WAVE` و فایل کوتاه با `Content-Type: audio/webm` رد می‌شوند) |

## روش

1. **sniffهای امنیتی PHPCS** با استاندارد کامل `WordPress` (نه فقط `WordPress-Core` که ruleset پروژه است) روی ۱۰۳ فایل PHP دامنه: `EscapeOutput`، `NonceVerification`، `ValidatedSanitizedInput`، `SafeRedirect`، `PreparedSQL`، `PreparedSQLPlaceholders`، `AlternativeFunctions`، `PluginMenuSlug`. خروجی: ۱۸ خطا و ۵ هشدار در ۹ فایل (`t20-security-sniffs.txt`)؛ هر مورد در بخش ۳ حکم گرفته است.
2. **ممیزی ۱۷۳ استثنای `phpcs:ignore`** دامنه به تفکیک sniff: ۱۲۰ مورد `EscapeOutput` که ۸۵ تای آن آیکون SVG ثابت `Ui::icon()` و مانند آن است؛ ۴۷ مورد غیرآیکون یکی‌یکی خوانده شد (بخش ۴).
3. **خواندن دستی** مسیرهای پرخطر: هر `$_POST` در برابر nonce و capability، هر `wp_remote_*` در برابر SSRF، هر خواندن secret در برابر پاسخ REST و DOM، هر `innerHTML` در JS، هر `fputcsv`، هر SQL درون‌یابی‌شده، مسیرهای عمومی در برابر rate limit و دروازهٔ داخلی.
4. **کنترل منفی** برای هر آزمون تازه: همان آزمون روی نسخهٔ 56dd00b باید شکست بخورد، و خورد.

## ۳. حکم یافته‌های sniff

| فایل:خط | sniff | حکم |
|---|---|---|
| `AnswerDeskHandoffPage.php:251, 255, 382, 386` | `EscapeOutput` روی `$this->card_class()` و `self::state_class()` | **مثبت کاذب.** هر دو تابع فقط یکی از چهار رشتهٔ ثابت ` is-ready`، ` is-needs-setup`، ` is-paused`، ` is-off` را برمی‌گردانند (`:424` تا `:436`). ورودی بولی است |
| `ContentStudioNavigation.php:83` | `InputNotSanitized` روی `$_GET['brief']` | **مثبت کاذب.** مقدار خام فقط به `wp_check_invalid_utf8( wp_unslash() )`، `wp_strip_all_tags()` و حذف کاراکترهای کنترلی می‌رسد (`:88` تا `:90`)، ذخیره نمی‌شود و فقط composer را پر می‌کند؛ مسیر REST بعدی خودش اعتبارسنجی ۶٬۰۰۰ کاراکتری دارد |
| `IntegrationsPage.php:402` | `$_SERVER['REQUEST_METHOD']` | **مثبت کاذب.** فقط با `'POST'` مقایسه می‌شود |
| `IntegrationsPage.php:488, 492` | `NonceVerification.Missing` در `post_value()` | **مثبت کاذب.** `post_value()` یک helper است که فقط از `maybe_save()` پس از `current_user_can( 'manage_options' ) && wp_verify_nonce()` (`:407`) صدا زده می‌شود |
| `SettingsPage.php:185` | `NonceVerification.Recommended` روی `$_GET['page']` | **مثبت کاذب.** فقط‌خواندنی: تشخیص alias صفحه با `sanitize_key()` |
| `WooCommerceSettings.php:18` | `InputNotSanitized` روی `$_GET['tab']` | **مثبت کاذب.** مقدار خام هرگز خوانده یا چاپ نمی‌شود؛ فقط در `finally` سر جایش برمی‌گردد. `StoreCopilotPage` مقدار `'capabilities'` ثابت را می‌خواند |
| `UsageCappedProvider.php:66` | `ExceptionNotEscaped` | **مثبت کاذب.** پیام از راه REST به JSON می‌رود و در PHP چاپ نمی‌شود، همان قاعده‌ای که `VoiceTranscriber.php:12` مستند کرده است |
| `ContentStudioHistoryExport.php:99`، `LogExport.php:92` | `fclose()` | **مثبت کاذب.** `php://output` |
| `VoiceTranscriber.php:56` | `file_get_contents()` | **مثبت کاذب.** فایل موقت آپلود PHP، نه URL |

## ۴. بررسی‌های بدون یافته

| حوزه | چه چیزی سنجیده شد | نتیجه |
|---|---|---|
| مجوز و nonce | ۴ handler `$_POST` (`IntegrationsPage::maybe_save`، `SettingsPage::maybe_save` که ۴۲ خواندن `$_POST` پس از آن است، `ChatWidgetSettings::save`، `FormShortcode::handle_submit` عمومی با nonce به‌ازای فرم) و ۲ export `admin-post` با `current_user_can( 'manage_options' )` + `check_admin_referer()` | همه پیش از نخستین خواندن، هم capability و هم nonce دارند |
| REST | ۶۶ handler از inventory T19: ۵۹ مدیریتی با `manage_options`، ۷ عمومی؛ پارامترهای ۱۱ controller تازه (`absint`، `sanitize_key`، `rest_sanitize_boolean`، `sanitize_hex_color`، `is_email`، allow-list) | هیچ پارامتر خامی به ذخیره یا خروجی نمی‌رسد؛ `ActivityController` `level` و `search` را به `LogsPage`ای می‌دهد که خودش allow-list و `esc_html` دارد |
| مسیرهای عمومی | `/public/chat`، `/public/voice`، `/widget/ask`، `/widget/handoff`: دروازهٔ «روشن است؟» پیش از `RateLimiter::allow()` با HMAC از `REMOTE_ADDR` (نه `X-Forwarded-For`)؛ `/widget/conversation` عمداً هیچ دادهٔ درخواست را بازتاب نمی‌دهد؛ `/widget/settings` allow-list ۱۵ کلید بدون secret و بدون قواعد استثنا | مطابق `SECURITY_FA.md` |
| اسرار | هر خوانندهٔ `get_effective_api_key()` و `get_handoff_secret()`: `SettingsPage::provider_test_signature()` و `HealthDiagnostics::provider_fingerprint()` فقط HMAC-SHA256 با `wp_salt( 'auth' )` ذخیره می‌کنند؛ `AdminHandoffSettingsController` فقط `has_*` بولی برمی‌گرداند؛ `CopilotController::requested_config()` کلید را فقط برای همان تماس test می‌خواند و آن را برنمی‌گرداند | هیچ secretی به DOM، REST، CSV یا لاگ نمی‌رسد. `Redactor` روی `Logger` اجباری است |
| SSRF | ۱۱ فراخوان `wp_remote_*`: provider و صدا از `SafeHttp`/`is_safe_endpoint()` (فقط https، بدون userinfo، `redirection => 0`، `reject_unsafe_urls`)؛ Telegram و Twilio میزبان ثابت با `reject_unsafe_urls`؛ `VisitorConversationStore` با `wp_http_validate_url` | بدون یافته |
| SQL | ۱۳ درون‌یابی `{$this->table()}`/`{$table}` با `phpcs:ignore`: همه نام جدول ثابت از `$wpdb->prefix` + suffix ثابت؛ `HandoffInbox::list()` `$where` را از یک بولی به دو رشتهٔ ثابت می‌سازد؛ `EasyFormBuilderConnector::has_required_schema()` نام را با `/^[A-Za-z0-9_]+$/` می‌سنجد | همهٔ مقدارهای متغیر با `prepare()` |
| CSV | `LogExport` و `ContentStudioHistoryExport` هر دو از `ContentStudioHistoryExport::csv_cell()`: `wp_strip_all_tags`، حذف CR/LF، و پیشوند `'` برای سلولی که با `=`، `+`، `-` یا `@` شروع می‌شود | formula injection بسته |
| `innerHTML` در JS | ۱۰ نقطه در ۲۰ فایل دامنه: ۸ مورد آیکون SVG ثابت یا `data-fallback` که خود صفحه از markup سرور برداشته؛ `widget.js` فقط `BOOK_ICON`؛ `chat.js` → **S20-1** | یک یافته، رفع شد |
| ایمیل و Telegram | `HandoffEmail::build_messages_html()` محتوا را `esc_html` می‌کند و `$role` فقط `user`/`ai` است؛ `HandoffTelegram` با `htmlspecialchars( …, ENT_QUOTES )` برای `parse_mode=HTML`؛ `HandoffService` رونوشت را از `PdfExporter::export_text()` می‌سازد | بدون یافته |
| بارگذاری صدا | اندازه، مدت، رمز و rate limit موجود بود؛ محتوا نبود → **S20-4** | رفع شد |

## ۵. یادداشت‌های باز (بدون ریسک امنیتی، برای taskهای بعدی)

- **کد مرده در ZIP (T22، T23):** `assets/admin/dashboard.js`، `includes/Frontend/WidgetRenderer.php`، `assets/frontend/chat-widget.js|css` (از T19) و اکنون `includes/Voice/VoiceService.php` که هیچ فراخوانی جز docblock خودش ندارد. هیچ‌کدام enqueue یا instantiate نمی‌شوند، پس سطح حمله نیستند؛ حذف‌شان تصمیم مالک است.
- **rate limit پشت NAT:** شناسه `REMOTE_ADDR` است، پس بازدیدکنندگان پشت یک NAT یک سهمیه دارند. این طراحی عمدی و مستند است (`SECURITY_FA.md`، «محدودیت نرخ») و جایگزین امن‌تری بدون پیکربندی proxy معتمد ندارد.
- **T21:** inventory حذف اکنون در `uninstall.php` و آزمون آن است؛ برای T21 فقط جدول «چه چیزی پاک می‌شود» در readme و `MANUAL-TESTING.md` می‌ماند.

## ۶. گیت‌ها

| گیت | نتیجه | شاهد |
|---|---|---|
| static | **5/5**: php-lint 234/234، node-check 43/43، PHPCS 134/134 با 0 خطا و 0 هشدار، PHPStan 0، PHPUnit **155 / 544** (پیش از T20: 149 / 514؛ +۶ آزمون: ۱ Redactor، ۳ UninstallInventory، ۲ صدا) | `test-artifacts/gates/20260919-003345-static` (اجرای میانی پیش از S20-4: `20260918-222744-static`، 153 / 526) |
| Phase 1 integration | **45 / 0** (شامل قرارداد صدا و نبود secret در `/widget/settings`) | `…/20260918-t20/phase1-integration.txt` |
| widget render integration | **97 / 0** (شامل 403 `voice_disabled` پیش از rate limiter و 400 `invalid_audio` روی `/public/voice`) | `…/20260918-t20/widget-render-integration.txt` |
| chat console integration | **14 / 0** | `…/20260918-t20/chat-console-integration.txt` |
| آزمون Markdown کنسول | **7 PASS / 0 FAIL**؛ روی 56dd00b: `AssertionError`، exit 1 | `admin-chat-markdown-check.txt`، `negative-control-chat-baseline.txt` |
| Redactor و Uninstall | PHPUnit **8 / 20**؛ Uninstall روی `uninstall.php` نسخهٔ 56dd00b: 2 شکست، exit 1 | `phpunit-redactor-uninstall.txt`، `negative-control-uninstall-baseline.txt` |
| sniffهای امنیتی | 18 خطا و 5 هشدار در 9 فایل، همه با حکم در بخش ۳ | `t20-security-sniffs.txt` |
| پاک‌سازی | preflight پایانی: `settings_stored=False`، WARNINGS 0، BLOCKERS 0 | `preflight-final.txt` |

هیچ browser job اجرا نشد: هر چهار رفع با آزمون واحد یا integration بدون مرورگر اثبات‌پذیر بود و پنجرهٔ مرورگر در دست نشست‌های T15 و T73 بود.

## ۷. فایل‌های تغییریافته

| فایل | تغییر |
|---|---|
| `assets/admin/chat.js` | S20-1: `escHtml()` نقل‌قول‌ها را escape می‌کند؛ الگوی پیوند |
| `includes/Security/Redactor.php` | S20-3: lookbehind به‌جای `\b` |
| `includes/Voice/VoiceTranscriber.php` | S20-4: `CONTAINERS`، `detect_mime()`، `validate_file()`، نام و نوع فایل از بایت‌ها |
| `uninstall.php` | S20-2: ۲ جدول، ۱۲ option، قفل‌های claim |
| `tests/admin_chat_markdown_check.cjs` | تازه |
| `tests/UninstallInventoryTest.php` | تازه |
| `tests/RedactorTest.php`، `tests/LanguageAndVoiceTest.php` | +۱ و +۲ آزمون |
| `docs/technical/SECURITY_FA.md` | بخش تازهٔ «بارگذاری صدا»، ردیف Redactor، بند کنسول مدیر |
| `docs/technical/REST_API_FA.md` | قرارداد `invalid_audio` |
| `docs/technical/QUALITY_AND_RELEASE_FA.md` | دو لایهٔ آزمون تازه، خط مبنا |
