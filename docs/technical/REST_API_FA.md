# REST API

همهٔ مسیرها در [`includes/Api/RestRoutes.php`](../../includes/Api/RestRoutes.php) زیر فضای `aisaautopilot/v1` ثبت می‌شوند. این نسخه 60 جفت مسیر و متد دارد که 7 تای آن عمومی است. فهرست زیر از ثبت واقعی مسیرها در یک وردپرس بارگذاری‌شده استخراج شده است.

## قراردادهای مشترک

- **احراز هویت مدیریت:** cookie وردپرس به‌علاوهٔ سربرگ `X-WP-Nonce` با nonce `wp_rest`. اسکریپت‌های مدیریت nonce را از شیء محلی (`AISAAutoPilotAI`، `AISAAutoPilotChat`، `AISAAutoPilotKB`) می‌گیرند.
- **قالب پاسخ:** شیء JSON با `ok` (بولی). در خطا `code` پایدار و `message` ترجمه‌شده برای نمایش می‌آید. دادهٔ موفق کنار همان کلیدها قرار می‌گیرد، مثلاً `conversations`، `items`، `answer` و `sources`.
- **کدهای وضعیت رایج:**

  | وضعیت | معنا |
  |---|---|
  | 400 | ورودی نامعتبر، مثل `invalid_status` یا `no_messages` |
  | 403 | قابلیت خاموش است، مثل `handoff_disabled` |
  | 404 | موجود نیست |
  | 429 | `rate_limited`، یا `usage_cap_reached` که پیام آن متن قابل‌نمایش سقف است |
- **هیچ پاسخی کلید یا اسرار ندارد.** اسرار handoff و کلید provider فقط به شکل «ذخیره شده است» گزارش می‌شوند.

## مدل مجوز

| callback | شرط |
|---|---|
| `can_manage` | `current_user_can( 'manage_options' )` |
| `can_manage_form_composer` | `can_manage` و `Settings::get( 'form_composer_enabled', true )` |
| `can_manage_content_studio` | `can_manage` و `content_studio_enabled` |
| `can_manage_efb_copilot` | `can_manage` و `efb_copilot_enabled` |
| `can_manage_woocommerce` | `can_manage`، WooCommerce فعال، `WooCommerceConnector::is_ai_enabled()` و `current_user_can_manage()` |
| `__return_true` | فقط هفت مسیر عمومی. دروازهٔ واقعی آن‌ها داخل کنترلر است: فعال بودن Answer Desk و ویجت، handoff آماده و rate limit. |

## محدودیت نرخ

`Security\RateLimiter::allow( $scope, $limit, $window )` محدودکنندهٔ پنجرهٔ ثابت با transientهای `aisaautopilot_rate_*` است.

| scope | سقف |
|---|---|
| `widget_text`، `public_text` | 20 درخواست در ساعت |
| `widget_handoff` | 5 درخواست در ساعت |
| `public_voice` | 8 درخواست در ساعت |
| `admin_voice` (تبدیل گفتار مدیر) | 30 درخواست در 10 دقیقه |

## مسیرها

### کنسول پرسش مدیر و دانش سایت

| مسیر | متد | مجوز | کاربرد |
|---|---|---|---|
| `/chat/ask` | POST | `can_manage` | پرسش در یک گفت‌وگو (`question` تا 2000 نویسه، `conversation_id`). پاسخ `answer`، `sources`، `status` (`answered`، `clarify`، `not_found`)، `clarify_options`، `handoff_offer` و `conversation_id`. پیام‌ها و `status` در گفت‌وگو ذخیره می‌شوند. |
| `/chat/create` | POST | `can_manage` | ساخت گفت‌وگوی خالی |
| `/chat/list` | GET | `can_manage` | 50 گفت‌وگوی تازهٔ همان کاربر |
| `/chat/get` | GET | `can_manage` | یک گفت‌وگو با پیام‌های decode‌شده (`id`) |
| `/chat/delete` | POST | `can_manage` | حذف گفت‌وگوی همان کاربر (`id`) |
| `/chat/voice/transcribe` | POST | `can_manage` | تبدیل گفتار کنسول. متن فقط در composer قرار می‌گیرد. |
| `/kb/index/batch` | POST | `can_manage` | پردازش یک دستهٔ ایندکس با offset. در سقف مصرف، توقف قابل‌ادامه برمی‌گرداند. |
| `/kb/truncate` | POST | `can_manage` | پاک کردن همهٔ مدخل‌ها و پاره‌ها پیش از بازایندکس کامل. 429 `usage_cap_reached` فقط وقتی semantic search روشن و سقف پر است |
| `/kb/list` | GET | `can_manage` | فهرست صفحه‌بندی‌شده با جست‌وجو و فیلتر |
| `/kb/stats` | GET | `can_manage` | آمار دانش سایت |
| `/kb/delete` | POST | `can_manage` | حذف یک مدخل |
| `/kb/chat` | POST | `can_manage` | پرسش RAG بدون گفت‌وگوی ذخیره‌شده، روی پاره‌های `KnowledgeSearch`. اگر هیچ پاره‌ای مرتبط نباشد، `ok: true` با `status: not_found` و بدون درخواست AI |
| `/kb/gaps` | GET | `can_manage` | پرسش‌های بی‌پاسخ. `window` برابر `all` (پیش‌فرض) یا `7d`. هر مورد `reason`، `reason_label`، `asked_label`، `draft` و `write_url` دارد؛ پاسخ `total` و `window` هم دارد. |
| `/kb/gaps/dismiss` | POST | `can_manage` | کنار گذاشتن یک پرسش با `hash`، یا همه وقتی hash خالی است |
| `/kb/answers` | GET | `can_manage` | پاسخ‌های ذخیره‌شده (`items`، `limit`) |
| `/kb/answers` | POST | `can_manage` | ذخیرهٔ پاسخ: `answer` با `gap` (پرسش از سرور و dismiss پس از ذخیره)، یا `question`، یا `id` برای ویرایش. کد `created` یا `updated`؛ خطاها `invalid_question`، `invalid_answer`، `duplicate_question`، `saved_answers_full` (400) و `gap_not_found` (404). |
| `/kb/answers/delete` | POST | `can_manage` | حذف یک پاسخ با `id`؛ پاسخ ناموجود 404 `not_found` |

### مصرف، اتصال و سلامت

| مسیر | متد | مجوز | کاربرد |
|---|---|---|---|
| `/usage/report` | GET | `can_manage` | گزارش مصرف و وضعیت سقف |
| `/usage/budget` | POST | `can_manage` | تنظیم سقف ماهانهٔ توکن (0 یعنی نامحدود) |
| `/usage/reset` | POST | `can_manage` | صفر کردن شمار دورهٔ جاری |
| `/provider/test` | POST | `can_manage` | آزمون کوتاه اتصال با مقدارهای ذخیره‌نشدهٔ فرم |
| `/provider/models` | POST | `can_manage` | فهرست مدل‌هایی که کلید اجازه دارد |
| `/health/diagnostics` | POST | `can_manage` | اجرای تنها درخواست مجاز provider در Health و ذخیرهٔ نتیجهٔ امن |
| `/activity` | GET | `can_manage` | خوراک فعالیت پاک‌سازی‌شده (`limit`، `level`، `search`) |

### Answer Desk

| مسیر | متد | مجوز | کاربرد |
|---|---|---|---|
| `/widget/settings` | GET | عمومی | تنظیمات زمان اجرای ویجت، بدون اسرار. از T10 خود `widget.js` آن را صدا نمی‌زند (همین داده‌ها در `AISAAutoPilotWidget` هستند)؛ route برای سازگاری حفظ شده است. |
| `/widget/ask` | POST | عمومی | پرسش بازدیدکننده. rate limit دارد و از `ChatEngine` عبور می‌کند. `history` اختیاری: آخرین ۶ نوبت `{role, text}` (هر کدام تا ۱۲۰۰ نویسه)، فقط برای پرسش پیگیر، و ذخیره نمی‌شود. موفقیت: `ok: true` به‌اضافهٔ `answer`، `sources`، `status`، `clarify_options`، `handoff_offer` و در صورت logging `conversation_id`. هر رد (403 `widget_disabled`، 400 `empty` و `question_too_long`، 429 `rate_limited` و `usage_cap_reached`، 503 `assistant_unavailable`) `ok: false`، `code` و `message` دارد و هرگز `answer` ندارد؛ 400 `empty` تا T10 کلید `answer` داشت. |
| `/widget/conversation` | POST | عمومی | افزودن نوبت به گفت‌وگوی ثبت‌شده، فقط وقتی ثبت گفت‌وگو روشن است |
| `/widget/handoff` | POST | عمومی | درخواست انتقال به انسان. به کانال‌های آماده fan-out می‌شود و در صندوق با وضعیت `new` یا `failed` ثبت می‌شود. `name`، `email`، `phone` و `website` فقط وقتی خوانده می‌شوند که در `handoff_fields` روشن باشند. خطاها: `missing_contact` و `invalid_name`، `invalid_email`، `invalid_phone` یا `invalid_website` (400، با `fields`). |
| `/widget/handoff/status` | GET | عمومی | آیا انتقال اکنون در دسترس است |
| `/public/chat` | POST | عمومی | نسخهٔ فقط‌خواندنی دستیار عمومی |
| `/public/voice` | POST | عمومی | تبدیل گفتار دستیار عمومی. از T12 **دو دروازه** دارد: `answer_desk_enabled()` (403 `assistant_disabled`) و `widget.voice_enabled` (403 `voice_disabled`). هر دو **پیش از rate limiter** سنجیده می‌شوند، پس سایتی که میکروفن را خاموش کرده نه سهمیهٔ `public_voice` بازدیدکننده را خرج می‌کند و نه فراخوان provider. پس از آن: 429 `rate_limited`، 400 خطاهای `VoiceTranscriber` (`invalid_audio` — از T20 شامل فایلی که بایت‌های آغازینش WebM، Ogg، MP4 یا WAV نیست، هرچه `Content-Type` کلاینت باشد؛ `audio_too_large`؛ `audio_too_long`؛ `provider_not_configured`) و 503 `voice_unavailable`. موفقیت: `ok: true` با `transcript` و `language`. |
| `/widget/admin-settings` | POST | `can_manage` | به‌روزرسانی محدود tab Widget (نمایش، رنگ) |
| `/handoff/admin-settings` | POST | `can_manage` | به‌روزرسانی کانال‌های انتقال. `handoff_enabled` از وجود کانال آماده مشتق می‌شود. |
| `/handoff/visitor-fields` | POST | `can_manage` | ذخیرهٔ `fields`: هر چهار کلید `name`، `email`، `phone` و `website` با `off`، `optional` یا `required`. مجموعهٔ ناقص، کلید ناشناخته یا مقدار نامعتبر 400 `handoff_fields_invalid`. |
| `/voice/admin-settings` | POST | `can_manage` | کنترل‌های واقعی صدای مرورگر |
| `/widget/test/telegram` | GET | `can_manage` | بررسی توکن ربات با `getMe`، بدون ارسال پیام انتقال |
| `/widget/test/twilio` | GET | `can_manage` | بررسی اعتبار حساب Twilio، بدون ارسال پیامک انتقال |
| `/widget/conversations` | GET | `can_manage` | فهرست گفت‌وگوهای ثبت‌شدهٔ ویجت |
| `/widget/conversations/{id}` | GET | `can_manage` | یک گفت‌وگوی ثبت‌شده |
| `/handoffs` | GET | `can_manage` | فهرست صندوق انتقال (`limit`، `offset`، و `archived` برای فهرست بایگانی‌شده‌ها) |
| `/handoffs/{id}` | GET | `can_manage` | یک درخواست انتقال |
| `/handoffs/{id}/status` | POST | `can_manage` | تغییر وضعیت به `new`، `in_progress`، `resolved` یا `failed`. وضعیت ناشناخته 400 `invalid_status` و شناسهٔ ناموجود 400 `update_failed` می‌دهد. |
| `/handoffs/{id}/archive` | POST | `can_manage` | بایگانی (`archived` صادق) یا برگرداندن به صندوق؛ وضعیت حفظ می‌شود. پاسخ `archived` و `archived_on`؛ شناسهٔ ناموجود 400 `update_failed`. |
| `/handoffs/archive-resolved` | POST | `can_manage` | بایگانی همهٔ گفت‌وگوهای حل‌شدهٔ داخل صندوق؛ پاسخ `count` و `archived_on` |

### فرم‌ها و Easy Form Builder

| مسیر | متد | مجوز | کاربرد |
|---|---|---|---|
| `/forms/plan` | POST | `can_manage_form_composer` | تبدیل درخواست به plan اعتبارسنجی‌شده و ذخیرهٔ پیشنهاد در `ActionStore` برای 15 دقیقه |
| `/forms/execute` | POST | `can_manage_form_composer` | تأیید و اجرای پیشنهاد: claim اتمی، اعتبارسنجی دوباره، connector و لاگ |
| `/forms/voice/transcribe` | POST | `can_manage_form_composer` | تبدیل گفتار Form Composer |
| `/forms/delivery/test` | POST | `can_manage` | ایمیل آزمون به نشانی ذخیره‌شدهٔ دریافت پاسخ فرم |
| `/efb/chat` | POST | `can_manage_efb_copilot` | هماهنگ‌کنندهٔ گفت‌وگوی Copilot در ویرایشگر EFB |
| `/efb/help` | POST | `can_manage_efb_copilot` | پاسخ «چطور…» از دانش گردآوری‌شدهٔ EFB |
| `/efb/plan` | POST | `can_manage_efb_copilot` | plan فرم برای EFB |
| `/efb/create` | POST | `can_manage_efb_copilot` | ساخت فرم EFB از plan تأییدشده |
| `/efb/customize` | POST | `can_manage_efb_copilot` | تبدیل فرمان ویرایش به عملیات اعتبارسنجی‌شده که مرورگر روی فرم باز اعمال می‌کند |
| `/efb/save` | POST | `can_manage_efb_copilot` | مسیر ذخیرهٔ صریح برای مصرف‌کننده‌های یکپارچه |
| `/efb/log` | POST | `can_manage_efb_copilot` | ثبت رویداد پاک‌سازی‌شدهٔ کلاینت |
| `/efb/voice/transcribe` | POST | `can_manage_efb_copilot` | تبدیل گفتار Copilot |

### Content Studio و Store Copilot

| مسیر | متد | مجوز | کاربرد |
|---|---|---|---|
| `/content/plan` | POST | `can_manage_content_studio` | plan پیش‌نویس نوشته یا برگه. `source_gap` اختیاری باید hash پرسش بی‌پاسخِ باز باشد، وگرنه پیش از تماس با AI خطای 400 `invalid_source_gap` برمی‌گردد. |
| `/content/execute` | POST | `can_manage_content_studio` | ساخت فقط پیش‌نویس وردپرس از plan تأییدشده |
| `/woocommerce/admin-settings` | POST | `can_manage` | دو ترجیح غیرمخرب Store Copilot |
| `/woocommerce/check` | GET | `can_manage_woocommerce` | سلامت و قابلیت‌ها |
| `/woocommerce/plan` | POST | `can_manage_woocommerce` | plan محصول |
| `/woocommerce/execute` | POST | `can_manage_woocommerce` | ساخت فقط پیش‌نویس محصول از plan تأییدشده |
| `/woocommerce/smart-search` | POST | `can_manage_woocommerce` | جست‌وجوی فقط‌خواندنی محصول، وقتی ترجیح آن روشن است |
| `/woocommerce/categories` | GET | `can_manage_woocommerce` | دسته‌های محصول |
| `/woocommerce/analytics/dashboard` | GET | `can_manage_woocommerce` | خلاصهٔ فقط‌خواندنی |
| `/woocommerce/orders/recent` | GET | `can_manage_woocommerce` | سفارش‌های اخیر، فقط‌خواندنی |

متدهای دیگر `WooCommerceAnalyticsController`، `WooCommerceContentController` و `WooCommerceOrderController`، از جمله تغییر وضعیت سفارش، refund و bulk، **ثبت نشده‌اند** و نباید بدون بستهٔ امنیتی مستقل Phase 2 (T48) فعال شوند.

## افزودن مسیر تازه

1. مسیر را در `RestRoutes::register()` با `permission_callback` صریح ثبت کنید. `__return_true` فقط برای مسیر عمومی با rate limit و دروازهٔ داخلی مجاز است.
2. `args` را با `type` و `required` تعریف کنید و در کنترلر دوباره sanitize و اعتبارسنجی کنید.
3. پاسخ را با `ok` و `code` بدهید و هیچ داده‌ای از اسرار برنگردانید.
4. integration آزمون کنترلر و ردیف همین جدول را در همان تغییر اضافه کنید.
