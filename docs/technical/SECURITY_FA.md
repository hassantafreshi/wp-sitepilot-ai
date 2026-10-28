# امنیت

این سند می‌گوید افزونه از چه چیزی محافظت می‌کند و هر محافظت کجای کد است. هر تغییری که یکی از این قواعد را سست کند، پیش از ادغام به بازبینی امنیتی جدا نیاز دارد.

## اصل‌ها

1. **هوش مصنوعی فقط پیشنهاد می‌دهد.** خروجی مدل هرگز مستقیم اجرا نمی‌شود:
   - plan اعتبارسنجی می‌شود؛
   - انسان پیش‌نمایش را تأیید می‌کند؛
   - backend دوباره اعتبارسنجی و بعد اجرا می‌کند.
2. **پیش‌فرض غیرمخرب:** فرم پیش‌نویس است، Content Studio و WooCommerce فقط پیش‌نویس می‌سازند و هیچ مسیری انتشار، ویرایش گروهی، تغییر سفارش یا بازپرداخت ندارد.
3. **اسرار دیده نمی‌شوند:** کلید provider، کلید embeddings، توکن Telegram، اعتبار Twilio، توکن WhatsApp و Signal و نشانی webhook اسلک هرگز به DOM، پاسخ REST، لاگ، خروجی CSV یا خروجی آزمون نمی‌رسند. رابط فقط «ذخیره شده است» نمایش می‌دهد و فیلد خالی یعنی «تغییر نده».
4. **حداقل داده:**
   - صدای ضبط‌شده ذخیره نمی‌شود.
   - گفت‌وگوی ویجت فقط با روشن کردن صریح ثبت گفت‌وگو ذخیره می‌شود.
   - پرسش بی‌پاسخ بدون شناسهٔ پرسنده ثبت می‌شود.
   - پاسخ فرم بومی فقط ایمیل می‌شود.

## مجوز و nonce

| مسیر ورود | کنترل |
|---|---|
| صفحه‌های مدیریت | قابلیت `manage_options` در ثبت منو |
| REST مدیریت | `permission_callback` صریح ([REST_API_FA.md](REST_API_FA.md#مدل-مجوز)) و nonce `wp_rest` در سربرگ `X-WP-Nonce` |
| REST عمومی | `__return_true` فقط برای هفت مسیر. هر کنترلر دروازهٔ Answer Desk (`Settings::answer_desk_enabled()`)، آماده بودن handoff و rate limit را خودش می‌سنجد. |
| فرم تنظیمات Setup و Advanced | `wp_nonce_field( 'aisaautopilot_settings_save', 'aisaautopilot_settings_nonce' )` و `wp_verify_nonce` در `SettingsPage::maybe_save()` |
| فرم سفارشی‌سازی ویجت | nonce `sp_widget_save` در `ChatWidgetSettings::save()` |
| dialogهای Integrations | `manage_options` و nonce `aisaautopilot_integrations` |
| خروجی CSV | `manage_options` و `check_admin_referer()` در `LogExport` و `ContentStudioHistoryExport` |
| ارسال فرم عمومی | nonce مخصوص هر فرم (`aisaautopilot_form_submit_{id}`)، honeypot و rate limit (پایین) |

همهٔ ورودی‌ها با `wp_unslash` و sanitize مناسب نوع خوانده می‌شوند و همهٔ خروجی‌ها escape می‌شوند. استثناهای `phpcs:ignore` دلیل مکتوب کنار همان خط دارند.

پاسخ‌های ذخیره‌شدهٔ مدیر (`SavedAnswers`) هم با همین قرارداد متن ساده نگهداری می‌شوند و ویجت و کنسول مدیر آن‌ها را escape می‌کنند.

اطلاعات تماس انتقال (`HandoffContact`):
- فقط موردهایی که مدیر روشن کرده از درخواست خوانده می‌شوند.
- ایمیل با `is_email` و موبایل با الگوی رقم پذیرفته می‌شوند.
- وب‌سایت فقط `http` یا `https`، بدون user و password است. `esc_url_raw` هنگام ذخیره آن را دوباره محدود می‌کند.
- صندوق فقط نشانی‌های `http(s)` را پیوند می‌کند، با `rel="noopener noreferrer nofollow"`.
- `Reply-To` ایمیل فقط از ایمیل معتبر ساخته می‌شود؛ `sanitize_email` خط تازه را حذف می‌کند و تزریق سرآیند ممکن نیست.

متن گفت‌وگوی انتقال به انسان استثنای آگاهانه است. این متن ساده ذخیره می‌شود و فقط UTF-8 نامعتبر و نویسه‌های کنترلی آن حذف می‌شوند. escape در هر خروجی (صندوق، ایمیل، Telegram) انجام می‌شود، نه پیش از ذخیره. escape پیش از ذخیره متن را در Telegram دوبار encode می‌کرد. جزئیات: [`ANSWER_DESK_FA.md`](ANSWER_DESK_FA.md#انتقال-به-انسان).

## نگهداری اسرار: `Security\KeyVault`

- **کلید رمزگذاری:** از `hash( 'sha256', KEY_DERIVATION_SALT . wp_salt( 'secure_auth' ) . wp_salt( 'auth' ), true )` مشتق می‌شود. اگر saltها نباشند، `AUTH_KEY` جایگزین است.
- **`KEY_DERIVATION_SALT`:** برابر `'sitepilot-ai|'` است و **هرگز تغییر نمی‌کند**. تغییرش هر کلید ذخیره‌شده روی سایت‌های موجود را غیرقابل رمزگشایی می‌کند.
- **رمزگذاری:** با `sodium_crypto_secretbox` (پیشوند `sp1`) انجام می‌شود، و در نبود sodium با `openssl` در حالت `aes-256-gcm` با IV و tag (پیشوند `sp2`). token به‌صورت base64 در option ذخیره می‌شود.
- **تغییر salt:** اگر saltهای وردپرس عوض شوند، رمزگشایی شکست می‌خورد و رابط درخواست واردکردن دوبارهٔ کلید را نمایش می‌دهد. متن کلید در خطا نمی‌آید.
- **جایگاه‌ها:** کلید provider در `aisaautopilot_provider_key`، کلید embeddings در `aisaautopilot_embedding_key` و اسرار handoff در `aisaautopilot_handoff_secrets` است. در وردپرس 7 می‌توان کلید را به Connectors API سپرد (`WpConnectors`).
- **آزمون ذخیره‌نشده:** آزمون موفق مقدارهای ذخیره‌نشده فقط HMAC پیکربندی را در transient همان مدیر نگه می‌دارد، نه کلید را.

## پاک‌سازی: `Security\Redactor`

`Logger` هر پیام را از `Redactor::text()` و هر context را از `Redactor::array()` عبور می‌دهد.

| لایه | الگو |
|---|---|
| متن | `Bearer …`، `sk-…`، `gsk_…` و `rk_…`، توکن ربات Telegram (`123456:…`، از T20 حتی وقتی بی‌فاصله پس از `/bot` در نشانی API آمده باشد)، SID و کلید Twilio (`AC…` و `SK…`)، مسیر webhook اسلک (`hooks.slack.com/services/…`) و توکن متا (`EAA…`) با `[REDACTED]` جایگزین می‌شوند. کلیدهای `access_token`، `webhook` و `webhook_url` هم در آرایه‌ها پنهان می‌شوند. |
| کلیدهای آرایه | `authorization`، `api_key`، `apikey`، `key`، `provider_key`، `password`، `token`، `secret`، `sid`، `account_sid`، `auth_token`، `bot_token` بدون نگاه به مقدار با `[REDACTED]` جایگزین می‌شوند و آرایه‌های تو در تو بازگشتی پاک می‌شوند. |

لاگ حداکثر 200 رکورد دارد و نوشتن زیر قفل `GET_LOCK` MySQL انجام می‌شود تا درخواست‌های هم‌زمان رکورد یکدیگر را پاک نکنند.

## HTTP بیرونی: trait `Providers\SafeHttp`

| قاعده | اثر |
|---|---|
| فقط `https` | endpoint ساده فقط با filter `aisaautopilot_allow_insecure_provider_url` مجاز است. |
| بدون userinfo | URLی که نام کاربری یا گذرواژه دارد رد می‌شود. |
| `redirection => 0` | redirect به مقصد دیگر دنبال نمی‌شود و کلید به میزبان ناخواسته نمی‌رسد. |
| `reject_unsafe_urls => true` | `wp_http_validate_url` مقصدهای محلی و ناامن را رد می‌کند. |

کانال‌های handoff (Telegram، Twilio، WhatsApp و Slack) و تبدیل گفتار هم از مسیرهای HTTP با همین سیاست استفاده می‌کنند. webhook اسلک افزون بر این فقط روی میزبان‌های `hooks.slack.com` و `hooks.slack-gov.com` با مسیر `/services/` پذیرفته می‌شود. **استثنای آگاهانه:** Signal با `reject_unsafe_urls => false` درخواست می‌دهد، چون سرور signal-cli-rest-api مالک معمولاً روی localhost یا شبکهٔ محلی است. نشانی را فقط مدیر (`manage_options`) تعیین می‌کند و هرگز از ورودی بازدیدکننده نمی‌آید؛ redirect دنبال نمی‌شود و URL با userinfo، query یا fragment رد می‌شود. خطاها کد پایدار و امن دارند و بدنهٔ پاسخ provider بی‌پردازش نمایش داده نمی‌شود.

## محدودیت نرخ

`Security\RateLimiter::allow()`:

- **شناسه:**
  - scopeهای `admin_*` برای کاربر واردشده با `user:{id}` شناسایی می‌شوند؛
  - بقیه با `REMOTE_ADDR`. سربرگ‌های `X-Forwarded-For` و `X-Real-IP` عمداً پذیرفته نمی‌شوند، چون در دست کلاینت‌اند.
- **ذخیره:** فقط HMAC-SHA256 شناسه با `wp_salt( 'auth' )` در کلید transient می‌آید و IP خام ذخیره نمی‌شود.
- **سقف‌ها:**

  | ورودی | سقف |
  |---|---|
  | پرسش ویجت و دستیار عمومی | 20 در ساعت |
  | انتقال به انسان | 5 در ساعت |
  | صدای عمومی | 8 در ساعت |
  | صدای مدیر | 30 در 10 دقیقه |
  | ارسال هر فرم بومی | 5 در 600 ثانیه (`FormShortcode::RATE_LIMIT` و `RATE_WINDOW`)، با HMAC از شناسهٔ فرم و IP معتبر |

- **ترتیب دروازه‌ها:** هر دروازهٔ «این قابلیت اصلاً روشن است؟» **پیش از** شمارندهٔ نرخ اجرا می‌شود، تا یک بازدیدکننده نتواند با کوبیدن به قابلیتی که خاموش است سهمیهٔ خودش را بسوزاند یا سایت را وادار به کاری کند. نمونه از T12: `POST /public/voice` نخست `answer_desk_enabled()` و سپس `widget.voice_enabled` را می‌سنجد (403 `assistant_disabled` و 403 `voice_disabled`) و تنها بعد از آن `RateLimiter::allow( 'public_voice', 8, HOUR )` را صدا می‌زند. پیش از T12 این route فقط Answer Desk را می‌سنجید، پس سایتی که میکروفن ویجت را خاموش کرده بود همچنان برای هر فراخوان ناشناس صدا را به provider می‌فرستاد و هزینه می‌داد.

## بارگذاری صدا: `Voice\VoiceTranscriber`

هر چهار مسیر تبدیل گفتار (`/public/voice`، `/chat/voice/transcribe`، `/forms/voice/transcribe` و `/efb/voice/transcribe`) پیش از هر تماس با provider از `validate_file()` می‌گذرند:

| سنجه | قاعده | کد خطا |
|---|---|---|
| وجود فایل | `error` صفر، `tmp_name` خوانا | `invalid_audio` |
| اندازه | `1` تا `MAX_BYTES` (5,000,000 بایت) | `audio_too_large` |
| مدت | `1` تا `MAX_DURATION_MS` (45,000 میلی‌ثانیه) | `audio_too_long` |
| محتوا (از T20) | ۱۲ بایت نخست باید یکی از چهار container ضبط مرورگر باشد: WebM (`1A 45 DF A3`)، Ogg (`OggS`)، MP4 (`ftyp` در offset 4) یا WAV (`RIFF` + `WAVE`). `Content-Type` و نام فایلی که کلاینت فرستاده هیچ نقشی ندارند | `invalid_audio` |

نام و نوع فایلی که به provider می‌رود (`voice.webm`، `voice.ogg`، `voice.mp4` یا `voice.wav`) از همین بایت‌ها ساخته می‌شود، نه از سربرگ کلاینت. فایل فقط از مسیر موقت PHP خوانده می‌شود و هیچ‌جا ذخیره نمی‌شود. آزمون: `tests/LanguageAndVoiceTest.php`.

## فرم عمومی

- **honeypot:** اگر فیلد پنهان `sp_website` پر باشد، redirect موفقِ بی‌اثر انجام می‌شود. ایمیل، لاگ یا وضعیت rate limit برای دادهٔ bot ساخته نمی‌شود.
- **اعتبارسنجی:** `NativeFormSubmission::validate()` فقط فیلدهای plan ذخیره‌شده را می‌پذیرد. نوع، گزینهٔ مجاز و طول بیشینه را می‌سنجد و به WordPress وابسته نیست تا در PHPUnit آزموده شود.
- **سربرگ ایمیل:** فقط از plan و دادهٔ معتبر ساخته می‌شوند تا تزریق header ممکن نباشد.

## زنجیرهٔ تأیید: `Execution\ActionStore`

- **ذخیرهٔ پیشنهاد:** `create()` پیشنهاد را با `user_id` سازنده در transient با TTL برابر 900 ثانیه نگه می‌دارد.
- **claim اتمی:** `claim()` فقط برای همان کاربر کار می‌کند و با `add_option()` روی `aisaautopilot_action_claim_*` انجام می‌شود. فقط یک درخواست موفق می‌شود و دو کلیک یا دو تب هم‌زمان دو فرم یا دو پیش‌نویس نمی‌سازند.
- **پاک‌سازی:** claimهای قدیمی با `purge_stale_claims()` پاک می‌شوند.
- **اجرا:** executor پیش از اجرا plan را دوباره با validator می‌سنجد و قابلیت connector را بررسی می‌کند.

## امنیت رابط

- **متن مدل:**
  - در کنسول مدیر به‌صورت امن رندر می‌شود: HTML escape، Markdown محدود و فقط پیوند `http` و `https`. از T20 `escHtml()` در `chat.js` نقل‌قول‌ها را هم به `&quot;` و `&#39;` تبدیل می‌کند و الگوی پیوند نشانی‌ای را که نقل‌قول دارد اصلاً لینک نمی‌کند؛ پیش از آن پاسخی که روی محتوای سایت تزریق شده بود می‌توانست از `href` بیرون بیاید و به پیوند یک event handler بچسباند. آزمون آفلاین: `tests/admin_chat_markdown_check.cjs`.
  - در EFB Copilot از `textContent` استفاده می‌شود.
  - در ویجت بازدیدکننده (از T10) `renderAnswer()` زیرمجموعهٔ Markdown پاسخ را فقط با `createElement` و `textContent` می‌سازد؛ `innerHTML` در `widget.js` فقط برای آیکون SVG ثابت `BOOK_ICON` به کار می‌رود و پیوند درون پاسخ، مانند پیوند منبع، از `safeLink()` می‌گذرد که پروتکل را از خود عنصر می‌خواند و جز `http` و `https` را رد می‌کند. گزینه‌های `clarify_options` که مدل نوشته فقط composer را پر می‌کنند.
- **پیوند منبع:** با `target="_blank"` و `rel="noopener noreferrer"` باز می‌شود.
- **دادهٔ JSON صفحه:** با `Ui::json_script()` و flagهای `JSON_HEX_TAG`، `JSON_HEX_AMP`، `JSON_HEX_APOS` و `JSON_HEX_QUOT` چاپ می‌شود.
- **جزئیات Health:** با `textContent` اعمال می‌شود.
- **پاسخ ایمیلی در صندوق انتقال:** فقط وقتی `is_email()` معتبر است mailto می‌سازد.

## حریم خصوصی: `Support\PrivacyTools`

- **خروجی و حذف دادهٔ شخصی:** exporter و eraser وردپرس (`wp_privacy_personal_data_exporters` و `..._erasers`، شناسهٔ `aisaautopilot`) با نشانی ایمیل این‌ها را پیدا می‌کنند:
  - درخواست‌های انتقال با `visitor_email` برابر (بی‌حساسیت به حروف)؛
  - گفت‌وگوهای کنسول کاربر وردپرسی با همان ایمیل؛
  - گفت‌وگوهای ثبت‌شدهٔ بازدیدکننده که آن ایمیل در متنشان آمده است (`LIKE`، چون بازدیدکننده جز این ناشناس است).
- **حذف:** ردیف‌ها را پاک می‌کند، نه ناشناس، چون درخواست یا گفت‌وگوی بی‌صاحب کاربردی ندارد.
- **متن پیشنهادی سیاست حریم خصوصی:** با `wp_add_privacy_policy_content` روی `admin_init`، و در Settings → Privacy → Policy Guide دیده می‌شود.
- **محدودیت:** مهلت نگهداری و پاک‌سازی خودکار هنوز نیست؛ تصمیمش با مالک است.

## چک‌لیست بازبینی تغییر امنیتی

- [ ] مسیر REST تازه `permission_callback` صریح دارد و اگر عمومی است، rate limit و دروازهٔ داخلی دارد.
- [ ] هیچ کلید، توکن یا اعتباری در پاسخ، DOM، لاگ، CSV یا خروجی آزمون نیست. آزمون integration آن را با رشتهٔ واقعی می‌سنجد.
- [ ] ورودی sanitize، خروجی escape و استثنای `phpcs:ignore` مستند است.
- [ ] عملیات نوشتنی از `ActionStore` و تأیید انسان عبور می‌کند و پیش‌فرضش غیرمخرب است.
- [ ] درخواست بیرونی از `SafeHttp` استفاده می‌کند و redirect ندارد.
- [ ] داده‌ای که ذخیره می‌شود لازم است، در [DATA_MODEL_FA.md](DATA_MODEL_FA.md) ثبت و در `uninstall.php` پاک می‌شود.
