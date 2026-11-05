# مدل داده

همهٔ نام‌ها پیشوند `aisaautopilot_` دارند و منجمد هستند. نام option یا جدولی را تغییر ندهید. داده‌ای که بعد از تغییر نام افزونه آمده از `sitepilot-ai` منتقل شده است (`RebrandMigrator`).

## optionها

### تنظیمات و اسرار

| option | محتوا | نکته |
|---|---|---|
| `aisaautopilot_settings` | تنظیمات عمومی (`Settings::defaults()`) و زیرآرایهٔ `widget` (`Settings::widget_defaults()`) | `Settings::all()` کلیدهای اسرار handoff را از `widget` حذف می‌کند. `Settings::update()` کلیدهای ارسال‌شده را روی مقدار موجود ادغام می‌کند. |
| `aisaautopilot_provider_key` | کلید provider، رمزشده با `KeyVault` | فقط جایگزین یا حذف می‌شود و هرگز خوانده و نمایش داده نمی‌شود. |
| `aisaautopilot_embedding_key` | کلید جدای embeddings، رمزشده | اختیاری است. خالی یعنی استفاده از کلید اصلی (جز Anthropic). |
| `aisaautopilot_handoff_secrets` | توکن Telegram، SID و توکن Twilio، توکن دسترسی WhatsApp، نشانی webhook اسلک، توکن اختیاری Signal | جدا از تنظیمات عمومی و رمزشده نگهداری می‌شوند. |
| `aisaautopilot_turnstile_secret` | کلید مخفی Cloudflare Turnstile، رمزشده با `KeyVault` | فقط جایگزین یا حذف می‌شود و در صفحه نشان داده نمی‌شود. کلید سایت در `turnstile_site_key` تنظیمات است. |
| `aisaautopilot_carts_recovered` | عدد سفارش‌هایی که پس از یادآوری سبد رهاشده ثبت شد | فقط یک عدد، بدون دادهٔ خریدار؛ در پایین کارت «Abandoned carts» نمایش داده می‌شود. |
| `aisaautopilot_wp_connector_key` | جایگاه کلید در Connectors API وردپرس 7 | با `key_management = wp_connectors` |
| `aisaautopilot_provider_verified` | زمان آخرین آزمون موفق همان پیکربندی ذخیره‌شده | با تغییر provider، endpoint، مدل، کلید یا روش نگهداری پاک می‌شود. |

کلیدهای مهم `aisaautopilot_settings`:

- **provider:** `provider`، `base_url`، `model`، `temperature`، `key_management`
- **embeddings:** `embeddings_enabled`، `embedding_model`، `embedding_base_url`
- **صدا:** `voice_model`، `voice_responses_enabled`
- **دستیار عمومی:** `public_assistant_enabled`
- **فرم‌ها:** `prefer_efb`، `default_form_status`، `form_notification_email`
- **دانش سایت:** `kb_auto_index`
- **سوئیچ قابلیت‌ها:** `form_composer_enabled`، `content_studio_enabled`، `efb_copilot_enabled`
- **WooCommerce:** زیرآرایهٔ `woocommerce`

کلیدهای مهم `widget`:

- **نمایش:** `enabled` (کلید روشن و خاموش در برگهٔ Widget)، و قاعده‌های صفحه که کارت «Pages where the chat appears» در تنظیمات پیشرفتهٔ ویجت می‌نویسد:
  - `widget_visibility`: `all` (پیش‌فرض، همه‌جا) یا `exclude` (همه‌جا جز موارد انتخاب‌شده). در حالت `all` قاعده‌ها نگه داشته می‌شوند ولی اثری ندارند.
  - `exclude_types`: slug نوع‌های عمومی (بدون `attachment`)؛ گفت‌وگو در هر مورد تکی آن نوع و در بایگانی همان نوع پنهان است.
  - `exclude_cats`: شناسهٔ دسته‌های `category`؛ در نوشته‌های آن دسته و در صفحهٔ خود دسته پنهان است.
  - `exclude_ids`: شناسهٔ برگه، نوشته یا محصول، حداکثر 200 (`Settings::WIDGET_EXCLUDE_IDS_MAX`)؛ فقط با شیء `WP_Post` صفحهٔ جاری مقایسه می‌شود، پس شناسهٔ یک دسته هرگز با آن اشتباه نمی‌شود.
  - `exclude_pages` و `widget_exclude_ids` (کلید قدیمی خواننده، بیرون از پیش‌فرض‌ها) فقط خوانده می‌شوند: `Settings::widget_exclusions()` هر سه کلید شناسه را یکی می‌کند. ذخیرهٔ کارت همه را در `exclude_ids` می‌نویسد و آن دو را خالی می‌کند تا موردی که مدیر حذف کرد برنگردد.
  - ورودی‌ها در ذخیره اعتبارسنجی می‌شوند: نوع ثبت‌شده، دستهٔ موجود، و مورد موجودِ بیرون از سطل زباله با صفحهٔ عمومی. `Settings::positive_id()` عدد منفی یا آرایه را صفر می‌کند (برخلاف `absint()` که ‎-3 را 3 می‌کرد). کد کوتاه `[aisaautopilot_chat]` از همین قاعده‌ها پیروی می‌کند.
- **ظاهر:** `position`، `theme`، `color`، `custom_colors`، `colors`، `icons`، `panel_radius` (پیش‌فرض 20)، `msg_radius`، `bubble_radius`، `window_width` و `window_height` (پیش‌فرض 392 و 624، بدون UI)
- **متن:** `title`، `subtitle`، `greeting`، `suggestions`. پیش‌فرض‌ها انگلیسی ذخیره می‌شوند و `Settings::widget_copy()` آن‌ها را به زبان جاری نمایش می‌دهد.
- **رفتار:** `auto_open` (ثانیه، پیش‌فرض 0 یعنی هرگز؛ سقف 120)
- **صدا:** `voice_enabled`، `voice_auto` (پیش‌فرض `false` از T08)
- **بازبینی پیش‌فرض‌ها:** `defaults_revision` (عدد صحیح، اکنون 2). `Settings::migrate_widget_defaults()` در boot فقط وقتی این نشان کمتر از 2 است مقدارهای قدیمیِ انتخاب‌نشده را جابه‌جا می‌کند: `auto_open` 5 به 0، `window_width` 380 به 392 و `window_height` 550 به 624. option تازه‌ای نمی‌سازد؛ حذف در uninstall همراه خود `aisaautopilot_settings` است.
- **ثبت گفت‌وگو:** `conversation_logging_enabled`، `conversation_notice`
- **انتقال به انسان:** `handoff_enabled`، `handoff_text`، `handoff_hook`، `handoff_email_*`، `handoff_telegram_*`، `handoff_twilio_*`، `handoff_whatsapp_*` (`enabled`، `phone_id`، `to`، `template`، `language` با پیش‌فرض `en_US`)، `handoff_slack_enabled`، `handoff_signal_*` (`enabled`، `url`، `from`، `to`)، `handoff_fields` (کلیدهای `name`، `email`، `phone`، `website` با مقدار `off`، `optional` یا `required`؛ پیش‌فرض همه `off`)
- **مهلت نگهداری:** `retention` با `conversations_days` (۳۰)، `handoffs_days` (۱۸۰) و `questions_days` (۹۰)؛ هر کدام ۰ تا ۳٬۶۵۰ و ۰ یعنی نگه‌داشتن. `Retention::periods()` کلید غایب را با پیش‌فرض پر می‌کند؛ فقط فرم Advanced settings آن را می‌نویسد.
- **کلیدهای بازنشسته (F-12، ۲۰۲۶-۰۹-۱۸):** دوازده کلید از طرح نخست ویجت در `Settings::WIDGET_RETIRED_DEFAULTS` جمع شده‌اند. هیچ کدی آن‌ها را نمی‌خواند و هیچ فرمی نمی‌نویسد؛ فقط چون `Settings::update()` روی هر سایتی که یک‌بار ذخیره کرده آن‌ها را نوشته است، در پیش‌فرض‌ها می‌مانند و با خود `aisaautopilot_settings` در uninstall حذف می‌شوند. به آن‌ها سیم‌کشی نشود: شش تای اول کار یک کنترل زنده را تکرار می‌کنند و دو کنترل برای یک کار مدیر را گیج می‌کند.

  | کلید بازنشسته | کنترل زنده‌ای که همین کار را می‌کند |
  |---|---|
  | `btn_color` | `colors.bubble` (رنگ Floating button) یا رنگ برند `color` |
  | `btn_icon` | `icons.float` (Floating button icon) |
  | `btn_shape` | `bubble_radius` (Button shape: Circle 30، Rounded 16، Square 6) |
  | `window_border_radius` | `panel_radius` (Panel corners) |
  | `window_accent_color` | `color` (رنگ برند) |
  | `window_header_color` | `colors.head_from` و `colors.head_to` |
  | `btn_size` | ندارد؛ اندازهٔ دکمه ثابت 62px (`--spw-bubble-size`) با ناحیهٔ لمس کافی |
  | `btn_animation` | ندارد؛ حلقهٔ launcher ثابت است و زیر `prefers-reduced-motion` خاموش می‌شود |
  | `btn_offset_x`، `btn_offset_y` | ندارد؛ فاصله از لبه ثابت 26px با احترام به `safe-area-inset` |
  | `window_font_size` | ندارد؛ پنل از stack فونت سیستم و اندازه‌های موکاپ پیروی می‌کند |
  | `window_shadow` | ندارد؛ سایهٔ پنل ثابت موکاپ است |

  اگر روزی «فاصله از لبه» یا «اندازهٔ دکمه» لازم شد، قابلیت تازه با کلید تازه، UI و آزمون ساخته شود، نه با زنده کردن این کلیدها: مقدارهای ذخیره‌شده‌شان (مثلاً 20px) را کسی انتخاب نکرده است، همان تله‌ای که `auto_open` در D-25 داشت.

### مصرف

| option | محتوا |
|---|---|
| `aisaautopilot_usage_log`، `aisaautopilot_usage_totals` | رکوردها و جمع دورهٔ جاری |
| `aisaautopilot_monthly_budget` | سقف ماهانهٔ توکن (0 یعنی نامحدود) |
| `aisaautopilot_usage_period`، `aisaautopilot_usage_period_contexts` | دورهٔ جاری و بافت عملیات |
| `aisaautopilot_usage_daily` | مصرف روزانه برای نمودار هفت روز |
| `aisaautopilot_usage_historical_totals`، `aisaautopilot_usage_historical_log`، `aisaautopilot_usage_period_archive` | بایگانی دوره‌های قبل |
| `aisaautopilot_voice_usage_log` | فقط مدت تبدیل گفتار. صدا و متن ذخیره نمی‌شوند. |

### دانش سایت، لاگ و سلامت

| option | محتوا |
|---|---|
| `aisaautopilot_kb_gaps` | پرسش‌های بی‌پاسخ به تفکیک hash: `question`، `language`، `count`، `first_seen`، `last_seen` (ISO)، `status`، `reason` (`no_context`، `model_not_found`، `empty_answer`، `no_citation` یا خالی)، `draft_post_id`. ورودی‌های قدیمی `first` و `last` دارند. پرسنده ثبت نمی‌شود. نوشتن با `OptionLock`. |
| `aisaautopilot_kb_saved_answers` | پاسخ‌های ذخیره‌شدهٔ مدیر به تفکیک `SavedAnswers::key()`: `question`، `answer`، `created`، `updated`، `hits`، `last_used`. حداکثر 200. نوشتن با `OptionLock`. |
| `aisaautopilot_kb_answer_cache` | کش پاسخ `ChatEngine`. با تغییر نسخهٔ دانش سایت باطل می‌شود. |
| `aisaautopilot_logs` | حداکثر 200 رویداد. نوشتن زیر قفل `GET_LOCK` است و متن از `Redactor` می‌گذرد. |
| `aisaautopilot_health_diagnostics` | نتیجهٔ امن آخرین اجرای Health |
| `aisaautopilot_handoff_inbox_schema`، `aisaautopilot_widget_chat_schema`، `aisaautopilot_form_outbox_schema`، `aisaautopilot_cart_funnel_schema`، `aisaautopilot_carts_schema` | نسخهٔ schema جدول‌های تازه، برای ساخت بدون فعال‌سازی دوباره |
| `aisaautopilot_kb_schema` | نسخهٔ ستون‌های `{prefix}aisaautopilot_kb`. وقتی برابر نسخهٔ کد است، `KnowledgeBase::ensure_schema()` هیچ `SHOW TABLES` یا `SHOW COLUMNS` اجرا نمی‌کند؛ پیش‌تر هر پرسش بازدیدکننده چهار پرس‌وجوی schema داشت. |
| `aisaautopilot_kb_index_format` | قالب ایندکس پاره‌ها (`schema\|نسخهٔ متن\|ContentIndexer::FORMAT`). نابرابری یعنی بازسازی پس‌زمینه لازم است. |
| `aisaautopilot_kb_rebuild` | مکان‌نمای cron `aisaautopilot_kb_rebuild` تا پایان بازسازی؛ سپس حذف می‌شود. |
| `aisaautopilot_retention_last_run` | آخرین اجرای پاک‌سازی نگهداری (`Retention::LAST_RUN_OPTION`): `time`، شمار حذف‌شدهٔ `conversations`، `handoffs`، `questions` و `undelivered`، و `complete` (false یعنی دسته‌ها به سقف رسید و بقیه در اجرای بعد). |
| `aisaautopilot_site_setup` | نشان راه‌اندازی این سایت (`Activator::SITE_OPTION`، autoload): جدول‌ها و تنظیمات پیش‌فرض ساخته شده‌اند. `Activator::maybe_setup_site()` در `init` اگر آن را نبیند `setup_site()` را اجرا می‌کند؛ این راه سایت تازهٔ شبکه و سایت‌های شبکهٔ بزرگ است. |
| `aisaautopilot_rebrand_migrated` | نشانهٔ پایان مهاجرت نام |

## transientها

| کلید | عمر | کاربرد |
|---|---|---|
| پیشنهاد `ActionStore` | 900 ثانیه | plan اعتبارسنجی‌شدهٔ منتظر تأیید، مخصوص کاربر سازنده. قفل claim به‌صورت option غیر-autoload یک روز می‌ماند تا از تکرار جلوگیری کند. |
| `aisaautopilot_provider_test_*` | 15 دقیقه | آزمون موفق ذخیره‌نشده، مخصوص همان مدیر. کلید خام فقط در HMAC به کار می‌رود. |
| `aisaautopilot_rate_*` | پنجرهٔ هر scope | شمارندهٔ rate limit |
| `aisaautopilot_form_rate_<form>_<hmac>` | 600 ثانیه | سقف ارسال فرم عمومی. شناسهٔ فرستنده فقط به‌صورت HMAC از IP و nonce salt ذخیره می‌شود، نه IP خام. |
| `aisaautopilot_form_delivery_test_<user>` | 300 ثانیه | فاصلهٔ بین دو «آزمون تحویل ایمیل» در صفحهٔ فرم‌های بومی |

## جدول‌ها

| جدول | ستون‌ها | کاربرد |
|---|---|---|
| `{prefix}aisaautopilot_kb` | `id`، `post_id` (یکتا)، `post_type`، `title`، `summary`، `keywords`، `category`، `content_type`، `embedding` (LONGTEXT)، `embedding_model`، `content_hash`، `status`، `token_count`، `indexed_at`، `updated_at` | یک ردیف برای هر نوشته، برای صفحه‌های مدیریت و embedding. `KnowledgeBase::ensure_schema()` ستون‌های تازه را یک بار اضافه می‌کند. جست‌وجوی معنایی بردارها را ۲۰۰تایی و به ترتیب `id` می‌خواند (`vector_page()`)، و `/kb/list` ستون `embedding` را نمی‌فرستد. |
| `{prefix}aisaautopilot_kb_chunks` | `id`، `post_id`، `ordinal`، `heading`، `content` (TEXT)، `token_count`؛ کلید `post_ordinal` | پاره‌های حدود ۹۰۰ نویسه‌ای هر نوشته (`KnowledgeIndex`). |
| `{prefix}aisaautopilot_kb_terms` | `term` (VARBINARY(160))، `chunk_id`، `post_id`، `tf`؛ کلید اصلی (`term`, `chunk_id`) | فهرست وارونهٔ واژه‌ها برای BM25، با یکسان‌سازی `KnowledgeText`. |
| `{prefix}aisaautopilot_chats` | `id`، `user_id`، `title`، `messages` (JSON)، `message_count`، `created_at`، `updated_at` | گفت‌وگوهای کنسول مدیر. هر خواندن و حذف به کاربر جاری محدود است. |
| `{prefix}aisaautopilot_widget_chats` | `id`، `session_token` (یکتا)، `messages`، `message_count`، `created_at`، `updated_at` | فقط وقتی ثبت گفت‌وگوی ویجت روشن است. |
| `{prefix}aisaautopilot_handoffs` | `id`، `status`، `visitor_name`، `visitor_email`، `visitor_phone`، `visitor_website`، `messages`، `created_at`، `updated_at`، `archived_at` (NULL تا بایگانی) | صندوق انتقال. وضعیت‌ها در `HandoffInbox::STATUSES`. schema نسخهٔ 2 از ۲۰۲۶-۰۹-۱۶؛ `ensure_schema()` ستون‌های تازه را با `dbDelta` روی جدول موجود اضافه می‌کند. |
| `{prefix}aisaautopilot_form_outbox` | `id`، `form_id`، `subject`، `body` (متن کامل ایمیل با پاسخ‌ها)، `headers` (JSON)، `attempts`، `last_error`، `created_at`، `updated_at` | پاسخ فرم بومی که ایمیلش فرستاده نشد (`Forms\FormOutbox`). فقط وقتی پر می‌شود که `forms_keep_undelivered` روشن باشد (پیش‌فرض خاموش). با ارسال موفق یا حذف مدیر پاک می‌شود و پاک‌سازی نگهداری ردیف‌های قدیمی‌تر از `undelivered_days` (پیش‌فرض ۳۰) را بر اساس `created_at` حذف می‌کند. cron `aisaautopilot_form_outbox_retry` پس از ۱۵ دقیقه و سپس ساعتی تا ۵ تلاش. |
| `{prefix}aisaautopilot_cart_funnel` | `day` (DATE)، `stage` (`cart` یا `checkout`)، `hits`؛ کلید اصلی `(day, stage)` | شمار بی‌نام روزانهٔ سبدهای ساخته‌شده و رسیده به تسویه‌حساب (`Connectors\CartFunnel`). فقط وقتی پر می‌شود که `woocommerce.cart_funnel` روشن باشد (پیش‌فرض خاموش). هیچ دادهٔ خریدار ندارد؛ هر نشست ووکامرس با پرچم `aisaautopilot_funnel_*` در session خود ووکامرس یک بار برای هر گام شمرده می‌شود. افزایش اتمی با `INSERT … ON DUPLICATE KEY UPDATE`. روزهای قدیمی‌تر از ۱۲۰ روز در cron نگهداری روزانه حذف می‌شوند. |
| `{prefix}aisaautopilot_carts` | `id`، `token` (۳۲ هگز، یکتا)، `email`، `user_id`، `contents` (JSON: `product_id`، `variation_id`، `quantity`، `name`، `total`)، `total`، `currency`، `status` (`open` یا `reminded`)، `reminded_at`، `created_at`، `updated_at` | سبد رهاشده با ایمیلی که خریدار در تسویه‌حساب وارد کرده است (`Connectors\CartRecovery`). فقط وقتی پر می‌شود که `woocommerce.cart_recovery` روشن باشد (پیش‌فرض خاموش). شناسهٔ ردیف در session ووکامرس (`aisaautopilot_cart_row`) نگه داشته می‌شود. حذف با سفارش (همان نشست یا همان ایمیل)، «Do not keep my cart»، خالی شدن سبد، مدیر، eraser، و پاک‌سازی `carts_days` (پیش‌فرض ۳۰) بر اساس `updated_at`. |

## post typeها و actionها

| نام | نوع | کاربرد |
|---|---|---|
| `aisaautopilot_form` | post type | فرم بومی با plan تأییدشده و تغییرناپذیر در meta (`NativeFormConnector::META_KEY`) |
| `aisaautopilot_entry` | post type | ثبت می‌شود، اما مسیر فعلی ارسال فرم پاسخ را فقط ایمیل می‌کند و ذخیره نمی‌کند. |
| `aisaautopilot_form_submit` | `admin_post` و `admin_post_nopriv` | ارسال فرم عمومی. اعتبارسنجی با `NativeFormSubmission` و ارسال با ایمیل. |
| `aisaautopilot_export_logs` | `admin_post` | خروجی CSV لاگ |
| `aisaautopilot_export_content_studio_history` | `admin_post` | خروجی CSV تاریخچهٔ Content Studio |
| `aisaautopilot_index_post` | cron تک‌رویداد، 5 ثانیه پس از ذخیره | ایندکس یک نوشته بدون مسدود کردن درخواست ذخیره |

## حذف افزونه

`uninstall.php` تنها مسیر پاک‌سازی است؛ غیرفعال کردن افزونه هیچ داده‌ای را حذف نمی‌کند. فهرست زیر در T21 (۲۰۲۶-۰۹-۲۰) با grep کامل `add_option`، `update_option`، `set_transient`، `CREATE TABLE`، `register_post_type` و کلیدهای meta در `includes/` بازساخته شد و `tests/UninstallInventoryTest.php` آن را نگه می‌دارد.

### حذف می‌شود

| دسته | موارد |
|---|---|
| جدول‌ها | `{prefix}aisaautopilot_kb`، `{prefix}aisaautopilot_kb_chunks`، `{prefix}aisaautopilot_kb_terms`، `{prefix}aisaautopilot_chats`، `{prefix}aisaautopilot_widget_chats` ، `{prefix}aisaautopilot_handoffs`، `{prefix}aisaautopilot_form_outbox`، `{prefix}aisaautopilot_carts` (این چهار دادهٔ بازدیدکننده دارند) و `{prefix}aisaautopilot_cart_funnel` (فقط شمار بی‌نام) |
| تنظیمات و اسرار | `aisaautopilot_settings`، `aisaautopilot_provider_key`، `aisaautopilot_embedding_key`، `aisaautopilot_handoff_secrets`، `aisaautopilot_wp_connector_key`، `aisaautopilot_provider_verified` |
| لاگ و مصرف | `aisaautopilot_logs`، `aisaautopilot_usage_log`، `aisaautopilot_usage_totals`، `aisaautopilot_monthly_budget`، `aisaautopilot_usage_period`، `aisaautopilot_usage_period_contexts`، `aisaautopilot_usage_daily`، `aisaautopilot_usage_historical_totals`، `aisaautopilot_usage_historical_log`، `aisaautopilot_usage_period_archive`، `aisaautopilot_voice_usage_log` |
| دانش سایت | `aisaautopilot_kb_gaps`، `aisaautopilot_kb_saved_answers`، `aisaautopilot_kb_answer_cache`، `aisaautopilot_kb_version`، `aisaautopilot_kb_index_format`، `aisaautopilot_kb_rebuild` |
| سلامت و schema | `aisaautopilot_health_diagnostics`، `aisaautopilot_handoff_inbox_schema`، `aisaautopilot_widget_chat_schema`، `aisaautopilot_kb_schema`، `aisaautopilot_site_setup`، `aisaautopilot_retention_last_run` |
| قدیمی | `aisaautopilot_setup_step` (مکان‌نمای ویزارد نسخه‌های پیشین) و `aisaautopilot_last_diagnostics` (مهر صفحهٔ Connections در 1.0.4) |
| مهاجرت | `aisaautopilot_rebrand_migrated` — عمداً پاک می‌شود تا نصب دوباره در کنار ردیف‌های قدیمی، مهاجرت را دوباره اجرا کند |
| قفل‌ها | هر `aisaautopilot_action_claim_*` با `DELETE ... LIKE` |
| transientها | `_transient_aisaautopilot_%` و `_transient_timeout_aisaautopilot_%` با `DELETE ... LIKE` |
| جاروی پایانی | هر ردیف باقی‌ماندهٔ `aisaautopilot_%` در جدول options |
| post meta | `delete_post_meta_by_key( '_aisaautopilot_previous_description' )`: متن توضیحی که تأیید Store Copilot جایگزینش کرد، فقط برای Undo. توضیح تازه خودش محتوای سایت است و می‌ماند. |
| cron | `wp_unschedule_hook()` برای `aisaautopilot_index_post`، `aisaautopilot_kb_rebuild`، `aisaautopilot_retention_cleanup` و `aisaautopilot_form_outbox_retry` (غیرفعال کردن هم این آخری را برمی‌دارد). `wp_clear_scheduled_hook()` فقط رویدادهای بی‌آرگومان را برمی‌داشت و رویداد هر برگه (با شناسهٔ نوشته) می‌ماند. |

**چرا جاروی پایانی لازم است:** `RebrandMigrator::migrate_options()` هر option با پیشوند `sitepilot_` را با تغییر پیشوند به `aisaautopilot_` منتقل می‌کند و فهرست را از دیتابیس کشف می‌کند، نه از یک آرایهٔ ثابت. پس سایتی که از نام قدیمی ارتقا یافته می‌تواند ردیف‌های `aisaautopilot_*` داشته باشد که نام‌شان در این نسخه اصلاً اعلام نشده است و فهرست صریح به آن‌ها نمی‌رسد. پیشوند منجمد و مخصوص همین افزونه است، پس جارو به دادهٔ افزونهٔ دیگری نمی‌رسد.

### عمداً می‌ماند

| مورد | دلیل |
|---|---|
| نوشته‌های `aisaautopilot_form` و meta `_aisaautopilot_form_plan` | فرم‌های خود مدیر |
| نوشته‌های `aisaautopilot_entry` | اگر نسخه‌ای قدیمی ساخته باشد؛ مسیر فعلی ارسال فرم چیزی ذخیره نمی‌کند |
| نوشته، برگه و پیش‌نویس محصول ساخته‌شده با افزونه و metaهای `_aisaautopilot_generated`، `_aisaautopilot_meta_description`، `_aisaautopilot_source_gap` و `_aisaautopilot_content_tone` (لحن Content Studio، T32) | محتوای خود سایت |
| فرم‌های Easy Form Builder | مال آن افزونه است |

### چندسایته

هر سایت شبکه جدول‌ها (با پیشوند خودش، مثل `wp_2_`) و optionهای خودش را دارد. افزونه هیچ دادهٔ مشترک شبکه ندارد.

- **فعال‌سازی برای کل شبکه:** `Activator::activate( true )` با `Network::for_each_site()` سایت‌های همان شبکه را صد‌تا صد‌تا می‌خواند و روی هر کدام `setup_site()` را اجرا می‌کند: مهاجرت نام، تنظیمات پیش‌فرض، شش جدول و نشان `aisaautopilot_site_setup`. در شبکه‌ای که وردپرس «بزرگ» می‌شمارد (`wp_is_large_network()`، پیش‌فرض بیش از ۱۰٬۰۰۰ سایت) این حلقه اجرا نمی‌شود، چون در یک درخواست تمام نمی‌شود.
- **سایت تازه و سایت‌های شبکهٔ بزرگ:** `Activator::maybe_setup_site()` در `init` نشان سایت را می‌خواند (یک option با autoload، بی‌پرس‌وجو) و اگر نباشد همان `setup_site()` را اجرا می‌کند. پس هر سایت در نخستین درخواستش آماده می‌شود. پیش‌تر زیرسایت‌ها هرگز جدول دانش و گفت‌وگوی مدیر را نمی‌گرفتند.
- **حذف یک سایت:** `Network::site_tables()` روی `wpmu_drop_tables` شش جدول افزونه را به جدول‌هایی که وردپرس با سایت می‌اندازد اضافه می‌کند. این فیلتر فقط وقتی اجرا می‌شود که افزونه در صفحهٔ شبکه بارگذاری شده باشد، یعنی برای کل شبکه یا برای سایت اصلی فعال باشد.
- **حذف افزونه:** `uninstall.php` روی هر سایت همهٔ شبکه‌ها `aisaautopilot_uninstall_site()` را اجرا می‌کند. سپس جدول‌های افزونهٔ سایت‌هایی را که دیگر وجود ندارند می‌اندازد. این حالتی است که افزونه فقط روی همان سایت فعال بود و فیلتر حذف سایت اجرا نشد. فقط شش نام جدول افزونه با الگوی `{base_prefix}{id}_aisaautopilot_…` و شناسه‌ای که `get_site()` پیدا نمی‌کند انداخته می‌شوند.
- **آزمون:** `tests/wordpress_multisite_integration.php` (۷ بررسی) هر گام را در یک فرایند جدای WP-CLI روی یک شبکهٔ آزمایشی اجرا می‌کند.

### محدودیت‌های شناخته‌شده

- افزونه **هیچ option شبکه‌ای (`update_site_option`) نمی‌نویسد**. user metaهای آن دو تاست. اولی `{prefix}aisaautopilot_dismissed_conflicts` است، یعنی هشدارهای Integrations دربارهٔ افزونهٔ چت یا AI دیگر که هر مدیر روی همان سایت پنهان کرده. این مقدار با `update_user_option` ذخیره می‌شود و پیشوند جدول سایت را دارد. `uninstall.php` آن را برای هر سایت پاک می‌کند (T31). user meta دوم `{prefix}aisaautopilot_content_tone` است: آخرین لحن Content Studio که هر مدیر روی همان سایت انتخاب کرده (`site`، `formal` یا `plain`)، با همان روش ذخیره و پاک می‌شود (T32).
- با object cache پایدار، transientها در کش می‌نشینند نه در جدول options؛ از قلاب uninstall در دسترس نیستند و خودشان حداکثر تا یک ساعت (بلندترین پنجرهٔ rate limit) منقضی می‌شوند. هیچ‌کدام دادهٔ شخصی ندارند.
- افزونه `wp_cache_set`/`wp_cache_add` با کلید اختصاصی ندارد؛ تنها استفاده از object cache یک `wp_cache_delete` روی گروه `options` در `OptionLock` است که فقط کپی کهنهٔ خود وردپرس را می‌اندازد.