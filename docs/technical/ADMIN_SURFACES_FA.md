# سطح‌های مدیریت

همهٔ صفحه‌ها قابلیت `manage_options` می‌خواهند و با `Ui::page_header()` شروع می‌شوند. ترتیب منو ثابت است و طبق D-23 (۲۰۲۶-۰۹-۱۷) تعیین شده — دیگر ترتیب تخت موکاپ نیست، هرچند ظاهر تک‌تک صفحات همچنان از موکاپ پیروی می‌کند. slugها منجمد هستند.

## هشت صفحهٔ اصلی منو (D-23)

| ترتیب | برچسب | slug | کلاس | اسکریپت | tabها یا حالت‌ها | آزمون‌های اصلی |
|---|---|---|---|---|---|---|
| 1 | Overview | `aisaautopilot-home` | `HomePage` | — | چهار تب داخلی (زیر را ببینید)؛ حالت روز اول، کارهای منتظر، آمار هفته | `wordpress_overview_metrics_integration`، `wordpress_overview_states_integration`، `overviewHealthy` |
| 2 | Setup & Connection | `aisaautopilot-setup` | `SettingsPage::render_wizard()` | `setup-wizard.js` | ویزارد چهار مرحله‌ای: provider، کلید و آزمون، مدل، یکپارچه‌سازی‌ها | `wordpress_setup_verification_integration`، `setupHealthy` |
| 3 | Integrations | `aisaautopilot-integrations` | `IntegrationsPage` | `integrations.js` | چهار گروه و شش کارت قابلیت با dialog پیکربندی؛ هشدار «افزونهٔ چت یا AI دیگری فعال است» از `Core\ConflictDetector` با پنهان‌سازی برای هر مدیر (T31) | `wordpress_integrations_hub_integration`، `wordpress_conflict_detector_integration`، `conflict_browser_matrix`، `integrationsHealthy` |
| 4 | Site Knowledge | `aisaautopilot-kb` | `KnowledgeBasePage` | `knowledge-base.js` | Overview، Collections، Queue، Sources؛ tabهای پنهان `ask` و `entries` | `wordpress_knowledge_hub_integration`، `knowledgeHealthy` |
| 5 | Answer Desk | `aisaautopilot-conversations` | `AnswerConversationsPage` و برای tabها `AnswerDeskWidgetPage`، `AnswerDeskHandoffPage`، `AnswerDeskVoicePage` | `answer-widget.js`، `answer-handoff.js`، `answer-voice.js` | Conversations، Widget، Human handoff، Real-time voice | `wordpress_answer_*_integration`، `answer*Healthy` |
| 6 | Store Copilot | `aisaautopilot-wc-dashboard` | `StoreCopilotPage` | `woocommerce.js` | حالت واقعی بدون WooCommerce، محصولات فقط‌خواندنی، قابلیت‌ها | `wordpress_store_copilot_*_integration`، `storeCopilotHealthy` |
| 7 | Content Studio | `aisaautopilot-form-builder` | `CopilotPage` | `content-studio.js`؛ در History هیچ | Write، Templates، History؛ لینک قدیمی `mode=forms` هنوز کار می‌کند اما دیگر مقصد تازه‌ای برایش ساخته نمی‌شود (زیر: Form Studio) | `wordpress_content_studio_*_integration`، `contentStudioNavigationHealthy` |
| 8 | **Form Studio** (تا ۲۰۲۶-۰۹-۱۷: «Form Composer»، deep link زیر Content Studio) | `aisaautopilot-form-studio` | `CopilotPage::render_forms()` | `content-studio.js` | compose/review/result برای فرم‌های AI-محور | `wordpress_content_studio_navigation_integration`، `wordpress_form_composer_page_integration` |

برچسب فارسی Form Studio «استودیوی فرم» است (هم‌خانواده با «استودیوی محتوا»؛ ترجمه در T56 افزوده شد، چون تغییر نام T54 شش رشتهٔ نمایشی را در `fa_IR` انگلیسی گذاشته بود). کل IA (منو، هر ۸ صفحه، چهار تب Overview و لینک عمیق قدیمی) با `tests/admin_ia_browser_matrix.cjs` در مرورگر سنجیده می‌شود.

برچسب Answer Desk وقتی درخواست انتقال با وضعیت `new` باشد، حباب شمار وردپرس (`awaiting-mod`) می‌گیرد (`AdminMenu::answer_desk_menu_label()`). `handoff-inbox.js` پس از تغییر وضعیت، همان حباب را بدون بارگذاری به‌روز می‌کند.

## چهار تب Overview (D-23، ۲۰۲۶-۰۹-۱۷)

Usage & Cost، Health و Activity Log تا ۲۰۲۶-۰۹-۱۷ سه آیتم مستقل منو بودند؛ اکنون هرکدام alias پنهانی‌اند که `AdminMenu::ALIAS_OWNERS` به Overview نگاشت می‌کند، و `OverviewNavigation` نوار تب مشترک را بالای هر چهار صفحه (شامل خود Overview) رندر می‌کند. slug، کلاس و اسکریپت هرکدام دست‌نخورده مانده — فقط جایگاهشان در منو تغییر کرده.

| تب | slug | کلاس | اسکریپت | آزمون‌های اصلی |
|---|---|---|---|---|
| Overview | `aisaautopilot-home` | `HomePage` | — | (جدول بالا) |
| Usage & Cost | `aisaautopilot-usage` | `UsagePage` | `usage.js` | `wordpress_usage_page_integration`، `wordpress_usage_*_integration`، `usageHealthy` |
| Health | `aisaautopilot-connections` | `ConnectionsPage` | `connections.js` | `wordpress_health_*_integration`، `healthHealthy` |
| Activity Log | `aisaautopilot-logs` | `LogsPage` | — | `wordpress_activity_log_*_integration`، `activity_log_browser_matrix` |

## یازده alias پنهان

هر alias با الگوی parent خالی وردپرس ثبت می‌شود. `AdminMenu::ALIAS_OWNERS` آیتم مالک را در منو باز و برجسته نگه می‌دارد.

| slug | کلاس | مالک در منو | اسکریپت | کاربرد | آزمون |
|---|---|---|---|---|---|
| `aisaautopilot-settings` | `SettingsPage::render_advanced()` | Setup & Connection | `settings.js` | تنظیمات پیشرفته: جست‌وجوی معنایی، صدا، فرم‌ها و تحویل، دستیار عمومی. اتصال فقط‌خواندنی است. | `wordpress_advanced_settings_integration`، `advanced_settings_browser_matrix` |
| `aisaautopilot-forms` | `NativeFormsPage` | Content Studio | `native-forms.js` | فهرست فرم‌های بومی، shortcode با دکمهٔ کپی، آزمون تحویل ایمیل. دکمهٔ «Create a form» به Form Studio می‌رود | `wordpress_phase1_integration` |
| `aisaautopilot-history` | `HistoryPage` | Content Studio | — | تاریخچهٔ پیش‌نویس‌های ساخته‌شده و خروجی CSV | `wordpress_content_studio_history_*_integration` |
| `aisaautopilot-chat` | `ChatPage` | Site Knowledge | `chat.js`، `voice.js` | کنسول «Ask your knowledge base» برای آزمودن پاسخ‌ها | `wordpress_chat_console_integration`، `chat_console_browser_matrix` |
| `aisaautopilot-efb` | `EasyFormBuilderPage` | Integrations | — | جزئیات Copilot فرم‌ساز EFB: عملیات مجاز، حوزه‌های ممنوع، آخرین تغییر | `wordpress_efb_details_integration`، `efb_details_browser_matrix` |
| `aisaautopilot-woocommerce` | `WooCommerceSettings` | Store Copilot | `woocommerce.js` | همان قرارداد قابلیت‌های Store Copilot | `wordpress_phase1_integration` |
| `aisaautopilot-widget` | `ChatWidgetSettings` | Answer Desk | `widget-settings.js`، `handoff-inbox.js` | سفارشی‌سازی ویجت و ثبت گفت‌وگو و حریم خصوصی | `wordpress_widget_customise_integration`، `widget_customise_browser_matrix` |
| `aisaautopilot-handoffs` | `HandoffInboxPage` | Answer Desk | `handoff-inbox.js` | صندوق درخواست‌های انتقال به انسان | `wordpress_handoff_inbox_integration`، `handoff_inbox_browser_matrix` |
| `aisaautopilot-usage` | `UsagePage` | Overview (تب) | `usage.js` | همان محتوای جدول تب‌های Overview بالا | همان‌جا |
| `aisaautopilot-connections` | `ConnectionsPage` | Overview (تب) | `connections.js` | همان محتوای جدول تب‌های Overview بالا | همان‌جا |
| `aisaautopilot-logs` | `LogsPage` | Overview (تب) | — | همان محتوای جدول تب‌های Overview بالا | همان‌جا |

## قرارداد سطح‌های بازطراحی‌شده در بستهٔ «سطح‌های بیرون از موکاپ»

| سطح | ریشه و قلاب‌های پایدار | رفتار کلیدی |
|---|---|---|
| کنسول پرسش | `[data-sp-chat]` با `data-sp-chat-ready`، `[data-sp-chat-console]`، `#sp-chat-list`، `#sp-chat-messages` (`role="log"`)، `[data-sp-chat-welcome] [data-question]`، `[data-sp-chat-notice="provider\|knowledge"]`، `[data-sp-chat-lock-actions]`، `#sp-chat-input`، `#sp-mic-btn`، `#sp-chat-send`، `#sp-chat-status`، `[data-sp-chat-gap]`، `[data-sp-chat-retry]` | پیشنهادها و گزینه‌های clarify فقط composer را پر می‌کنند. پاسخ «not_found» پیوند به `#sp-kb-gaps` دارد. حذف گفت‌وگو با confirm است. تا اتصال سرویس و وجود محتوای ایندکس‌شده، نوار «Asking is locked» علت واقعی را می‌گوید و composer، میکروفون و پیشنهادها قفل‌اند. پاسخ ناموفق کارت خطا می‌گیرد و متن پرسش به composer برمی‌گردد. |
| دانش سایت › مدخل‌ها | `#sp-kb-rows`، `.sp-kb-entry`، `.sp-kb-delete`، `[data-sp-kb-danger]`، `#sp-kb-reindex`، `#sp-kb-pagination`، `[data-sp-kb-list-loading]`، `[data-sp-kb-list-error]`، `[data-sp-kb-list-retry]` | ستون‌های Title، Type، Category، Status، Last read و Actions. حذف تکی و بازایندکس کامل با confirm است. بارگذاری ردیف‌های skeleton دارد. درخواست ناموفق یا ردشدهٔ `/kb/list` کارت «The list could not be loaded» با Try again نشان می‌دهد. |
| دانش سایت › پرسش‌های بی‌پاسخ و پاسخ‌های ذخیره‌شده | `[data-sp-kb-gaps]`، `[data-sp-kb-gaps-window="all\|7d"]`، `[data-sp-kb-gap]`، `[data-sp-kb-gap-write]`، `[data-sp-kb-gap-draft]`، `[data-sp-kb-gap-answer]`، `[data-sp-kb-gap-dismiss]`، `[data-sp-kb-answers]`، `[data-sp-kb-answer]`، `[data-sp-kb-answer-edit]`، `[data-sp-kb-answer-delete]`، `[data-sp-kb-answer-add]`، `[data-sp-kb-answer-form]` | دلیل و «Asked N times» از سرور می‌آیند. «Write it» فقط Content Studio را با brief باز می‌کند و «Open draft» فقط برای پیش‌نویس قابل ویرایش است. «Answer it» و «Edit» ویرایشگر درون‌خطی با Escape دارند. حذف با confirm است. هر دو کارت در حالت خالی دانش سایت هم هستند. |
| Content Studio › Write از پرسش بی‌پاسخ | `[data-content-compose][data-content-source-gap]`، `[data-content-source-note]` | خط «Drafting an answer to» فقط برای پرسش باز. هش همراه `prompt` به `/content/plan` می‌رود و پس از تأیید پیش‌نویس پیوند می‌خورد. |
| Answer Desk › Human handoff › اطلاعات تماس | `[data-sp-handoff-details-form]`، `[data-sp-handoff-detail="name\|email\|phone\|website"]` (`role="radiogroup"`)، `[data-sp-handoff-details-save]`، `[data-sp-handoff-details-status]` | هر مورد سه radio «Off»، «Optional» و «Required» دارد و با arrow key عوض می‌شود. «Save details» هر چهار مورد را با `POST /handoff/visitor-fields` ذخیره می‌کند. برچسب وضعیت کانال‌ها در JS از رشته‌های ترجمه‌شده می‌آید. |
| Answer Desk › Human handoff › نوار صندوق | `[data-sp-handoff-inbox-strip="waiting\|quiet\|unavailable"]`، `[data-sp-handoff-strip-new]`، `[data-sp-handoff-strip-failed]`، `[data-sp-handoff-inbox-link]` | شمارها از `HandoffInbox::count_new()` و `count_failed()` خوانده می‌شوند. بدون `answer_desk_handoff_available()` نوار می‌گوید به بازدیدکننده گزینهٔ گفت‌وگو با انسان داده نمی‌شود. کارت‌های کانال موکاپ اصلی زیر آن می‌مانند. |
| صندوق انتقال | `[data-sp-handoff-inbox-list]`، `[data-sp-handoff-filter]` (همراه `archived`)، `[data-sp-handoff-item][data-status][data-archived]`، `[data-sp-handoff-open]`، `[data-sp-handoff-drawer]`، `[data-sp-handoff-set]`، `[data-sp-handoff-archive]`، `[data-sp-handoff-archive-resolved]`، `[data-sp-handoff-d="contact\|archived"]`، `[data-sp-handoff-channel-state]` | فیلتر در مرورگر روی ردیف‌های سرور؛ «Archived» فقط بایگانی‌شده‌ها و بقیه فقط گفت‌وگوهای داخل صندوق را نشان می‌دهند. کشو با focus trap است. وضعیت با `POST /handoffs/{id}/status` و بایگانی با `POST /handoffs/{id}/archive` ذخیره می‌شود؛ «Archive resolved» پیش از ارسال confirm می‌خواهد. پاسخ ایمیلی فقط برای ایمیل معتبر با mailto است؛ موبایل `tel:` و وب‌سایت فقط `http(s)` پیوند می‌شوند. |
| سفارشی‌سازی ویجت | `[data-sp-widget]`، `[data-sp-widget-form]`، `#sp-w-stage` با `data-pos`، `data-theme`، `data-device`، `[data-sp-radio-group]`، `[data-sp-brand]`، `[data-sp-color]`، `[data-sp-recording]`، `[data-sp-savebar]`، `[data-sp-discard]` | ذخیره فقط کلیدهای فرم را تغییر می‌دهد. نمایش ویجت، صدا، کانال‌ها و اسرار دست‌نخورده می‌مانند. |
| تنظیمات پیشرفته | `[data-sp-advanced-settings]`، `[data-sp-advanced-form]` با `advanced_settings=1`، `.sp-adv__nav`، `#sp-adv-*`، `#sp-test-form-delivery`، `#sp-form-notification-email`، کارت `#sp-adv-retention` (`retention_*_days`، `[data-sp-retention-run]`، `[data-sp-retention-summary]`، `.sp-adv-cron` با `[data-sp-copy]`) | `save_advanced()` هرگز provider، endpoint، مدل، روش نگهداری کلید، temperature یا تأیید اتصال را تغییر نمی‌دهد. دکمهٔ پاک‌سازی و Copy دکمه‌اند و نوار «Unsaved changes» را روشن نمی‌کنند. |
| جزئیات EFB | `[data-sp-efb]` با `data-sp-efb-state`، `[data-sp-efb-status]`، `[data-sp-efb-op]`، `[data-sp-efb-blocked]`، `[data-sp-efb-last]`، `[data-sp-efb-writable]` | عملیات از `EfbCustomizeSkill::OPS`، شمارها و حوزه‌های ممنوع از کاتالوگ، آخرین تغییر از لاگ `efb.apply*` خوانده می‌شوند. |
| Usage › reset | `[data-sp-usage-danger]`، `[data-sp-usage-reset]` | reset فقط شمار دورهٔ جاری است و با confirm انجام می‌شود. سقف و ماه‌های قبل می‌مانند. |
| فرم‌های بومی | `[data-sp-form-delivery]`، `[data-sp-form-delivery-test]`، `[data-sp-form-copy]`، `[data-sp-native-forms-empty]` | کپی shortcode با fallback و toast. آزمون تحویل با `POST /forms/delivery/test` به نشانی ذخیره‌شده. |
