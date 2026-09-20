# گزارش ممیزی قراردادها و preserve-list — T19 (فاز ۴)

تاریخ: ۲۰۲۶-۰۹-۱۸  
مبنای مقایسه: commit `56dd00b` (آخرین انتشار پیش از بازطراحی) در برابر worktree جاری.  
شواهد: `C:\files\projects\SitePilot AI\test-artifacts\contract-audit\20260918-t19\`

## نتیجه

**گیت T19 سبز است.** هیچ route یا قابلیتی بدون تصمیم مستند حذف نشده است:

- هر ۴۶ route پایه با همان method هنوز ثبت است.
- مجوز هیچ routeی سست‌تر نشده است.
- هر ۷ route عمومی همان‌هایی‌اند که در انتشار پیشین عمومی بودند.
- نقشهٔ قابلیت WooCommerce گسترده نشده است.
- شناسه‌های منجمد دست‌نخورده‌اند.

در کنار این، سه یافته برای taskهای بعدی ثبت شد (بخش ۵). این یافته‌ها گیت T19 را نمی‌شکنند، ولی T21 بدون آن‌ها کامل نمی‌شود.

این task کد را تغییر نداد و هیچ چیزی در سایت ننوشت. فقط اسکریپت‌های فقط‌خواندنی اجرا شد.

## ۱. inventory قرارداد REST

روش: `t19_rest_inventory.php` وردپرس را bootstrap می‌کند، `rest_get_server()->get_routes( 'aisaautopilot/v1' )` را می‌خواند و برای هر handler این‌ها را ثبت می‌کند: method، `permission_callback` و `callback`. خروجی در `rest-now.json` است. routeهای پایه از `git show 56dd00b:includes/Api/RestRoutes.php` استخراج شدند (`rest-old.tsv`) و مقایسهٔ ردیف‌به‌ردیف در `route-compare.txt` است.

| سنجه | 56dd00b | اکنون |
|---|---|---|
| route | 46 | **65** (66 handler؛ `/kb/answers` هم GET دارد هم POST) |
| route حذف‌شده | — | **0** |
| route با method تغییرکرده | — | **0** |
| route عمومی (`__return_true`) | 7 | **7**، همان هفت route |
| route بدون `permission_callback` | 0 | **0** |

### مجوزهای تغییرکرده: فقط سخت‌تر شده‌اند

هر callback تازه هنوز `current_user_can( 'manage_options' )` را می‌خواهد و فقط یک شرط «این قابلیت روشن است» به آن اضافه می‌کند (`includes/Api/RestRoutes.php:726` تا `:752`):

| route پایه | پیش‌تر | اکنون |
|---|---|---|
| `/forms/plan`، `/forms/execute`، `/forms/voice/transcribe` | `can_manage` | `can_manage_form_composer` (+ `form_composer_enabled`) |
| `/efb/plan`، `/efb/create`، `/efb/chat`، `/efb/help`، `/efb/customize`، `/efb/log`، `/efb/save`، `/efb/voice/transcribe` | `can_manage` | `can_manage_efb_copilot` (+ `efb_copilot_enabled`) |

این رفتار عمدی است: کلید روشن و خاموش هر قابلیت در Integrations به مسیر REST همان قابلیت هم می‌رسد.

### routeهای عمومی (بدون تغییر نسبت به انتشار پیشین)

`POST /public/chat`، `POST /public/voice`، `GET /widget/settings`، `POST /widget/ask`، `POST /widget/conversation`، `POST /widget/handoff` و `GET /widget/handoff/status`. محدودیت نرخ و دروازه‌های هر کدام در [`docs/technical/REST_API_FA.md`](technical/REST_API_FA.md) و [`docs/technical/SECURITY_FA.md`](technical/SECURITY_FA.md) آمده است. بازبینی امنیتی آن‌ها کار T20 است.

### ۱۹ route تازه، همگی مدیریتی

| route | method | مجوز |
|---|---|---|
| `/content/plan`، `/content/execute` | POST | `can_manage_content_studio` |
| `/health/diagnostics` | POST | `can_manage` |
| `/activity` | GET | `can_manage` |
| `/handoffs`، `/handoffs/{id}` | GET | `can_manage` |
| `/handoffs/{id}/status`، `/handoffs/{id}/archive`، `/handoffs/archive-resolved` | POST | `can_manage` |
| `/widget/conversations`، `/widget/conversations/{id}` | GET | `can_manage` |
| `/woocommerce/smart-search` | POST | `can_manage_woocommerce` |
| `/kb/answers` | GET و POST | `can_manage` |
| `/kb/answers/delete` | POST | `can_manage` |
| `/widget/admin-settings`، `/handoff/admin-settings`، `/handoff/visitor-fields`، `/voice/admin-settings`، `/woocommerce/admin-settings` | POST | `can_manage` |

- هیچ route عمومی تازه‌ای افزوده نشده است. فضای نام همچنان `aisaautopilot/v1` است.
- `/woocommerce/admin-settings` عمداً `can_manage` است، نه `can_manage_woocommerce`. دلیلش این است که همین route اتصال Store Copilot را روشن می‌کند، و اگر مجوز روشن بودن همان اتصال را می‌خواست، پس از خاموش شدن دیگر نمی‌شد روشنش کرد.

## ۲. قرارداد WooCommerce (draft-only)

`git diff 56dd00b -- includes/Connectors/WooCommerceConnector.php` (در `woocommerce-connector.diff`):

- **`get_capabilities()`:** ردیف‌های نقشه دست نخوردند. `publish`، `edit`، `bulk_edit`، `change`، `refund` و `content.generate/publish` همچنان `false` هستند. تنها تغییر، سخت‌تر شدن `$available` است: `is_active() && is_ai_enabled()` اکنون `&& current_user_can_manage()` هم دارد.
- **`get_settings()`** (تازه): `auto_draft` همیشه `true` و `enable_edit` همیشه `false` است، مستقل از آنچه در option ذخیره شده باشد.
- **`recent_products()`** (تازه): فقط خواندن است، با سقف 20 ردیف و بدون هیچ کنش ویرایش.
- **`/woocommerce/smart-search`** (تازه): `get_posts` فقط‌خواندنی و بدون فراخوان provider. پشت `can_manage_woocommerce` و تنظیم صریح `enable_search` است، و بدون آن 403 `feature_disabled` برمی‌گرداند.
- **متدهای نوشتنی خفته:** `wc_create_refund` در `WooCommerceOrderSkill.php:281` و `$product->save()` در `WooCommerceContentSkill.php:375` از پیش در 56dd00b بودند. از `WooCommerceOrderController` فقط `recent` (GET) ثبت است و `WooCommerceContentController` اصلاً route ندارد. inventory runtime هیچ route سفارش، refund یا ویرایش محصول نشان نمی‌دهد. این وضعیت با [`REST_API_FA.md:134`](technical/REST_API_FA.md) و T48 می‌خواند.

## ۳. شناسه‌های منجمد

| شناسه | وضعیت | شاهد |
|---|---|---|
| namespace PHP `AISAAutoPilot\` | بدون تغییر | همهٔ فایل‌های `includes/` |
| REST namespace | `aisaautopilot/v1` | `RestRoutes.php:12` |
| Text Domain | `ai-site-assistant-autopilot`، برابر 56dd00b | `frozen.txt` |
| `KeyVault::KEY_DERIVATION_SALT` | `'sitepilot-ai\|'`؛ `KeyVault.php` صفر خط diff با 56dd00b | `frozen.txt` |
| `RebrandMigrator` | صفر خط diff با 56dd00b، پس نام‌های قدیمی دست‌نخورده‌اند | `frozen.txt` |
| slugهای مدیریتی | هر ۱۵ slug پایه هنوز ثبت‌اند: ۸ تا در منوی D-23 و ۱۱ تا alias پنهان. دو slug تازه هم هست: `aisaautopilot-integrations` و `aisaautopilot-form-studio` | `local_admin_menu_smoke.php` در `test-artifacts/gates/20260918-202017-smoke/menu-smoke.log` (5/0)، و matrix T56 با 652/0 |
| optionها و جدول‌ها | فقط اضافه شده‌اند: جدول‌های `aisaautopilot_kb` و `aisaautopilot_chats` همان‌اند و `aisaautopilot_handoffs` و `aisaautopilot_widget_chats` تازه‌اند. literalهای `aisaautopilot*` از 58 به 140 رسیده‌اند | `identifiers.txt` |
| shortcodeها | `[aisaautopilot_form]` (`FormShortcode.php:24`) و `[aisaautopilot_chat]` (`Plugin.php:64`) | کد |

چهار literal پایه دیگر در کد نیستند. هیچ‌کدام option ماندگار یا شناسهٔ عمومی به معنای قاعدهٔ انجماد نیست:

- `aisaautopilot_logs_clear` و `aisaautopilot_logs_nonce`: nonce فرم «Clear log» قدیمی در `LogsPage.php`. این فرم با تصمیم مستند A2.10 برداشته شد (یافتهٔ ۲).
- `aisaautopilot-dashboard`: handle اسکریپت داشبورد قدیمی. `assets/admin/dashboard.js` هنوز در tree هست ولی enqueue نمی‌شود، که در [`STORE_COPILOT_A2_6_EXECUTION_REPORT_FA.md:47`](STORE_COPILOT_A2_6_EXECUTION_REPORT_FA.md) مستند است.
- `aisaautopilot_last_diagnostics`: option مُهر زمانی تشخیص در `ConnectionsPage` قدیمی، که جایش را `aisaautopilot_health_diagnostics` گرفت (یافتهٔ ۱).

## ۴. preserve-list

منابع: §۶ [`MOCKUP_REDESIGN_WORKFLOW_FA.md`](MOCKUP_REDESIGN_WORKFLOW_FA.md) و §۱۰ `mockups/design_handoff_admin_redesign/REDESIGN_PROMPT.md`.

| # | مورد | کجا در دسترس است | شاهد | حکم |
|---|---|---|---|---|
| 1 | approval، TTL و action یک‌بارمصرف | `Execution/ActionStore.php` (TTL 900 ثانیه، مصرف هنگام تأیید). همهٔ planها `expires_at` دارند: فرم، EFB، محتوا و محصول | `wordpress_phase1_integration.php` (45/0) و `wordpress_content_studio_contract_integration.php` | حفظ |
| 2 | KeyVault و وضعیت masked کلید | `Security/KeyVault.php` بدون تغییر. Setup با placeholder «Saved securely» و بدون بازتاب کلید | `wordpress_setup_verification_integration.php`، `wordpress_advanced_settings_integration.php` | حفظ |
| 3 | voice در فرم، کنسول، EFB و ویجت | هر ۴ route: `forms/voice/transcribe` از `form-builder.js:599`، `chat/voice/transcribe` از `voice.js:103`، `efb/voice/transcribe` از `efb-copilot.js:70`، و `public/voice` از `widget.js:853` | `local_admin_assets_smoke.php` (20/0)، `wordpress_front_chat_widget_render_integration.php` (96/0) و matrix ویجت (425/0) | حفظ. Content Studio › Write میکروفن ندارد، چون §۱۰ میکروفن را برای prompt فرم خواسته بود و آن در Form Studio هست |
| 4 | Native Forms، shortcode و delivery test | `aisaautopilot-forms` (alias)، Form Studio، `[aisaautopilot_form]` و `POST /forms/delivery/test` | route در `wordpress_phase1_integration.php` (45/0) و `browser_release_check.cjs`؛ ارسال ایمیل فرم در `wordpress_form_delivery_integration.php`؛ صفحه در `wordpress_form_composer_page_integration.php` | حفظ |
| 5 | کنسول AI Chat و همهٔ routeهای `/chat/*` | `aisaautopilot-chat` (alias زیر Site Knowledge). `/chat/ask`، `create`، `list`، `get`، `delete` و `voice/transcribe` با همان method | `wordpress_chat_console_integration.php` و `route-compare.txt` | حفظ |
| 6 | handoff با Telegram و Twilio و نگهداری اسرار | Answer Desk › Handoff. `GET /widget/test/telegram` و `/widget/test/twilio` (`can_manage`)، `Settings::HANDOFF_SECRET_KEYS` و `migrate_legacy_handoff_secrets()` که در `Plugin.php:46` صدا زده می‌شود | `wordpress_answer_handoff_settings_integration.php` و `wordpress_widget_handoff_flow_integration.php` | حفظ |
| 7 | یکی از دو launcher عمومی، نه هر دو | `Settings.php:613`: `answer_desk_enabled()` برابر است با `public_assistant_enabled` و `widget.enabled`. فقط `ChatWidget` رندر می‌شود | `wordpress_answer_desk_lifecycle_integration.php` و گزارش S3.3 | حفظ، با تغییر مستند: renderer قدیمی `WidgetRenderer.php` و `chat-widget.js` hook ندارند ([`FRONT_CHAT_WIDGET_S3_3_EXECUTION_REPORT_FA.md`](FRONT_CHAT_WIDGET_S3_3_EXECUTION_REPORT_FA.md)). هیچ مسیری دو launcher نمی‌سازد |
| 8 | `kb_auto_index` | `ContentHooks.php:71`، کلید در Integrations و Setup | `wordpress_content_hooks_lifecycle_integration.php` | حفظ |
| 9 | بودجه و reset مصرف | `POST /usage/budget` و `/usage/reset`، تب Usage & Cost | `wordpress_usage_budget_api_integration.php` و `wordpress_usage_page_integration.php` | حفظ |
| 10 | خروجی transcript به شکل PDF | `Support/PdfExporter.php`، که `HandoffService.php:26` صدا می‌زند | `wordpress_widget_handoff_flow_integration.php` | حفظ |
| 11 | قرارداد Woo، draft-only و قفل Phase 2 | بخش ۲ همین گزارش. ردیف‌های Phase 2 در Store Copilot قفل‌اند | `wordpress_store_copilot_page_integration.php` و `wordpress_store_copilot_settings_integration.php` | حفظ |
| 12 | redaction، nonce، capability و rate limit | `Security/Redactor.php` و `Security/RateLimiter.php`. همهٔ ۵۹ handler مدیریتی مجوز `manage_options` دارند | `route-compare.txt` و `rest-now.json`. بازبینی عمیق در T20 | حفظ |
| 13 | اعلام RTL در ویجت، فرم‌ها و PDF | `ChatWidget` با `dir` ریشه، `PdfExporter.php:21` و `WidgetRenderer.php:65` | matrix ویجت در fa_IR (212/0) | حفظ |
| 14 | slugها و bookmarkهای قدیمی | هر ۱۵ slug پایه (بخش ۳) | menu smoke 5/0، render smoke 18/0 و matrix T56 | حفظ |

## ۵. یافته‌ها برای taskهای بعدی

| ID | یافته | اثر | مقصد |
|---|---|---|---|
| Q19-1 | **`uninstall.php` از تغییرات پس از 1.0.4 عقب مانده است.** دو جدول تازه، `aisaautopilot_handoffs` و `aisaautopilot_widget_chats`، هنگام حذف افزونه **drop نمی‌شوند**. این دو جدول متن گفتگوی بازدیدکننده و اطلاعات تماس درخواست انتقال را نگه می‌دارند. این optionها هم پاک نمی‌شوند: `aisaautopilot_health_diagnostics`، `aisaautopilot_handoff_inbox_schema`، `aisaautopilot_widget_chat_schema`، `aisaautopilot_voice_usage_log`، `aisaautopilot_usage_period`، `aisaautopilot_usage_period_contexts`، `aisaautopilot_usage_daily`، `aisaautopilot_usage_historical_totals`، `aisaautopilot_usage_historical_log` و `aisaautopilot_usage_period_archive`. option قدیمی `aisaautopilot_last_diagnostics` نسخهٔ 1.0.4 هم پس از ارتقا یتیم می‌ماند | دادهٔ شخصی بازدیدکننده پس از حذف افزونه در پایگاه داده می‌ماند. این با ادعای readme دربارهٔ پاک‌سازی نمی‌خواند | **T21** (inventory حذف)، و در **T20** به‌عنوان مورد حریم خصوصی |
| Q19-2 | «Clear log» در Activity Log با تصمیم مستند A2.10 برداشته شد ([`ACTIVITY_LOG_A2_10_AUDIT_AND_PLAN_FA.md:44`](ACTIVITY_LOG_A2_10_AUDIT_AND_PLAN_FA.md)). `Logger::clear()` اکنون هیچ فراخوانی ندارد. `Logger` همچنان فقط 200 رکورد آخر را نگه می‌دارد (`Logger.php:15`) | این حذف در plan A2.10 مستند است، پس گیت T19 را نمی‌شکند. ولی `GUIDE_PLAN_FA.md` (F40 و G32) هنوز «پاک‌سازی لاگ» را قابلیت می‌شمارد | راهنمای کاربر (یافتهٔ تازه در `GUIDE_PLAN_FA.md`)، و T21 برای readme |
| Q19-3 | `assets/admin/dashboard.js` و renderer قدیمی `WidgetRenderer.php`، `assets/frontend/chat-widget.js` و `.css` هیچ hookی ندارند ولی در ZIP می‌روند | کد مرده در بستهٔ انتشار. خطر امنیتی ندارد، چون enqueue نمی‌شوند | **T22** (فهرست فایل‌های ZIP) و **T23** (Plugin Check) |

## ۶. شواهد

| فایل | محتوا |
|---|---|
| `rest-now.json` | inventory runtime: 66 handler در 65 route |
| `rest-old.tsv` | ۴۶ route پایه با method و `permission_callback` |
| `route-compare.txt` | مقایسهٔ ردیف‌به‌ردیف پایه و اکنون |
| `identifiers.txt` | literalهای `aisaautopilot*` در پایه و اکنون، و ثابت‌های slug، option، جدول و cron |
| `woocommerce-connector.diff` | diff کامل connector نسبت به 56dd00b |
| `frozen.txt` | diff صفر `KeyVault` و `RebrandMigrator`، salt و Text Domain |
| `t19_rest_inventory.php`، `t19_old_routes.ps1`، `t19_identifiers.ps1` | اسکریپت‌های فقط‌خواندنی، برای تکرار |

preflight در شروع (20:59) WARNINGS 0 و BLOCKERS 0 داشت. هیچ fixture، حساب QA یا نوشتنی در option انجام نشد.
