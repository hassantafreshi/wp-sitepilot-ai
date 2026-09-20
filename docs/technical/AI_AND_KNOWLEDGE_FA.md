# هوش مصنوعی و دانش سایت

## providerها

- **`ProviderManager::is_configured()`:** سه شرط دارد: کلید مؤثر (از `WpConnectors::get_effective_api_key()`)، `base_url` و `model`.
- **`ProviderManager::make()`:** provider را در `UsageCappedProvider` می‌پیچد، تا سقف مصرف پیش از هر عملیات هزینه‌دار سنجیده شود.
- **نوع provider:**
  - `OpenAiCompatibleProvider` با هر endpoint سازگار با Chat Completions کار می‌کند؛ OpenAI، Gemini، DeepSeek، Qwen، Mistral، Groq، OpenRouter و endpoint سفارشی.
  - `AnthropicProvider` با Messages API کار می‌کند.
- **presetها:** از `Settings::presets()` می‌آیند و با filter `aisaautopilot_provider_presets` قابل گسترش‌اند. هر preset این کلیدها را دارد: `label`، `group` (مستقیم یا gateway)، `api`، `baseUrl`، `model`، `models`، `embeddings`، `note` و `keyUrl`.
- **خطا:** `ProviderException` کد پایدار و امن دارد و هرگز متن کلید در آن نیست. تشخیص‌های تنظیمات این‌ها هستند: `no_api_key`، `provider_unauthorized`، `provider_payment_required`، `provider_rate_limited`، `provider_unreachable`، `model_not_found`، `invalid_config`.
- **قواعد HTTP در trait `SafeHttp`:**
  - فقط HTTPS؛ HTTP ساده فقط با filter `aisaautopilot_allow_insecure_provider_url` برای شبکهٔ خصوصی مورد اعتماد.
  - URL نباید نام کاربری یا گذرواژه در بخش authority داشته باشد.
  - `redirection` برابر 0 و `reject_unsafe_urls` برابر true.

## سقف مصرف

- **ذخیرهٔ مصرف:** `UsageTracker` مصرف توکن هر عملیات را با بافتش ذخیره می‌کند. جمع دوره، مصرف روزانه، درصد سقف و هشدار آستانه را هم می‌سازد.
- **رسیدن به سقف:** وقتی `is_exhausted()` صادق است:
  - عملیات هزینه‌دار رد می‌شود؛
  - REST پاسخ 429 با `code: usage_cap_reached` می‌دهد؛
  - chip هدر «Generation paused — cap reached» نمایش می‌دهد.
- **ایندکس در سقف:** ایندکس دانش سایت در سقف، توقف قابل‌ادامه با `offset` برمی‌گرداند و ایندکس‌کننده cursor را جلو نمی‌برد.
- **reset:** `reset()` فقط دورهٔ جاری را صفر می‌کند و سقف و ماه‌های قبل می‌مانند.

## دانش سایت

### ایندکس

1. **شروع:** `ContentIndexer::process_batch()` نوشته‌ها و برگه‌ها و CPTهای منتشرشدهٔ عمومی را در دسته‌های کوچک می‌خواند. محتوایی که `content_hash` آن تغییر نکرده رد می‌شود.
2. **خلاصه:** `ContentSummarizer` متن را به provider می‌فرستد و JSON ساختاریافته برمی‌گرداند: `title`، `summary`، `keywords`، `category`. نتیجه با `KnowledgeBase::upsert()` بر اساس `post_id` ذخیره می‌شود.
3. **embedding:** اگر `embeddings_enabled` روشن باشد، embedding با `embedding_model` ساخته و در ستون `embedding` ذخیره می‌شود.
4. **هم‌گام ماندن:** `ContentHooks` پس از انتشار یا به‌روزرسانی، و فقط وقتی `kb_auto_index` روشن است، cron `aisaautopilot_index_post` را 5 ثانیه بعد زمان‌بندی می‌کند. خروج از انتشار، زباله‌دان و حذف همیشه مدخل را پاک می‌کنند.
5. **رابط:** دانش سایت ایندکس را در مرورگر و با `/kb/index/batch` پیش می‌برد و تب باید باز بماند. صف پایدار سمت سرور هنوز وجود ندارد (T44).

### بازیابی و پاسخ

- **بازیابی:** `VectorSearch::is_ready()` وقتی embedding برای مدل فعلی باشد، رتبه‌بندی معنایی در PHP انجام می‌دهد. در غیر این صورت `KnowledgeBase::search()` جست‌وجوی کلیدواژه‌ای دارد.
- **`ChatEngine::ask( $question, $history )`:**
  0. **پاسخ ذخیره‌شده:** برای پرسش مستقل (بدون `$history`)، `SavedAnswers::find()` پیش از هر چیز دیگر بررسی می‌شود. اگر جور باشد، متن مدیر با `status` برابر `answered`، `sources` خالی و `saved_answer` برابر `true` برمی‌گردد و `record_use()` یک استفاده می‌شمارد. هیچ درخواستی به هوش مصنوعی نمی‌رود، پس بدون provider هم کار می‌کند و از سقف مصرف کم نمی‌کند.
  1. **کش:** `KbCache` پاسخ کش‌شده را بررسی می‌کند.
  2. **بافت:** بافت شماره‌گذاری‌شده با نگاشت مرجع از `get_context_with_refs()` ساخته می‌شود. بافت خالی یعنی `not_found` و ثبت پرسش در `KbGaps` با دلیل `no_context`.
  3. **تصمیم:** مدل تصمیم JSON برمی‌گرداند: `answered`، `clarify` با `clarify_options`، یا `not_found`.
  4. **منبع:** مرجع‌های واقعی به `sources` با عنوان و آدرس نگاشت می‌شوند.
  5. **ثبت کمبود:** `not_found` مدل با دلیل `model_not_found`، پاسخ خالی با `empty_answer` و پاسخ بدون منبع با `no_citation` در `KbGaps` ثبت می‌شوند.
  6. **کش پاسخ:** پاسخ موفق غیر-پیگیری در کش ذخیره می‌شود.
  7. **پیشنهاد انتقال:** اگر انتقال به انسان آماده باشد، `handoff_offer` صادق است.
- **زبان:** `SiteLanguage` زبان آغازین را با زبان سایت هم‌تراز می‌کند، و هر نوبت با زبان پرسش بازدیدکننده پاسخ می‌گیرد.

### پرسش‌های بی‌پاسخ و کش

- **`KbGaps`:** پرسش را با hash نگه می‌دارد، بدون شناسهٔ پرسنده. هر ورودی `count`، `first_seen` و `last_seen` (ISO)، `reason` و `draft_post_id` دارد. ورودی‌های قدیمی با `first` و `last` (MySQL GMT) و بدون دلیل با `normalize_entry()` در همین شکل خوانده می‌شوند و مهاجرت مخربی لازم ندارند.
- **نوشتن هم‌زمان:** `record()`، `dismiss()` و `link_draft()` از `Support\OptionLock::mutate()` استفاده می‌کنند. این تابع زیر قفل نام‌دار `GET_LOCK` ردیف option را مستقیم از پایگاه‌داده می‌خواند، نه از کش درخواست، و سپس می‌نویسد. پس پرسشی که درخواست هم‌زمان دیگری ثبت کرده از دست نمی‌رود.
- **فهرست:** `open_list( $limit, $window )` بر اساس بیشترین تکرار مرتب می‌شود. `window` یکی از `all` یا `7d` است (`last_seen` در ۷ روز گذشته). هر مورد `reason_label`، `asked_label` («Asked N times») و `draft` دارد. `draft` فقط وقتی پر است که پست هنوز هست، نشانهٔ `_aisaautopilot_generated` دارد و کاربر جاری `edit_post` دارد.
- **حذف:** `dismiss( $hash )` یک پرسش را کنار می‌گذارد و `clear()` همه را پاک می‌کند.

### «Write it»: پیش‌نویس برای پرسش بی‌پاسخ (T30)

1. `GET /kb/gaps` برای هر مورد `write_url` می‌سازد: `ContentStudioNavigation::write_url( $brief, $hash )`، با brief ویرایش‌پذیر «Write a help article that answers this question from a site visitor: …» به زبان مدیر و `source_gap`.
2. صفحهٔ Write با `write_source_gap()` هش را می‌خواند. اگر پرسش هنوز باز باشد، خط «Drafting an answer to: …» نشان داده می‌شود و `content-studio.js` هش را همراه `prompt` به `/content/plan` می‌فرستد. هیچ درخواستی خودکار فرستاده نمی‌شود.
3. `ContentController::plan` پیش از بررسی سقف مصرف و provider، هشِ نامعتبر یا بسته را با 400 `invalid_source_gap` رد می‌کند. `ActionStore::create()` هش معتبر را در `execution_context.source_gap` proposal نگه می‌دارد و هر مقدار دیگری را خالی می‌کند.
4. پس از تأیید، `ContentDraftExecutor` با `KbGaps::link_draft()` پیش‌نویس را به پرسش پیوند می‌دهد و meta `_aisaautopilot_source_gap` را می‌نویسد. کلاینت هرگز `post_id` را برای پیوند نمی‌فرستد.
5. پرسش تا «Dismiss» باز می‌ماند و در فهرست به‌جای «Write it»، «Open draft» و نشان «Draft ready» دارد.

### پاسخ‌های ذخیره‌شده (`Content\SavedAnswers`)

- **جای نگهداری:** option `aisaautopilot_kb_saved_answers`، حداکثر 200 پاسخ. هر پاسخ `question` (تا 300 نویسه)، `answer` (متن ساده تا 2000 نویسه)، `created`، `updated`، `hits` و `last_used` دارد. همهٔ نوشتن‌ها با `OptionLock` است.
- **کلید و جور شدن:** `SavedAnswers::key()` روی متن پرسش این کارها را انجام می‌دهد و سپس md5 می‌گیرد:
  - decode کردن entityهای HTML؛
  - کوچک کردن حروف؛
  - تبدیل رقم فارسی و عربی به لاتین، و «ي»، «ى»، «ك»، «ة»، «أ» و «إ» به شکل فارسی؛
  - حذف نشانه‌گذاری، نمادها، فاصله‌ها، نویسه‌های کنترلی و بی‌عرض (مثل نیم‌فاصله) و اعراب.

  پس «Do you ship to Canada?» با «do you ship to canada» جور می‌شود، اما «Can you ship to Canada?» جور نمی‌شود. متن رابط مدیر همین محدودیت را صریح می‌گوید.
- **ذخیره از پرسش بی‌پاسخ:** `POST /kb/answers` با `gap`، متن پرسش را از سرور می‌گیرد و پس از ذخیره آن پرسش را dismiss می‌کند.
- **ویرایش:** با `id`. اگر متن پرسش عوض شود، پاسخ به کلید تازه منتقل می‌شود و `hits` و `created` حفظ می‌شوند. اگر کلید تازه مال پاسخ دیگری باشد، 400 `duplicate_question` برمی‌گردد.
- **خطاها:** `invalid_question`، `invalid_answer`، `duplicate_question`، `saved_answers_full` و `gap_not_found`.
- **رابط (Site Knowledge › Overview):**
  - هر پرسش بی‌پاسخ دلیل، «Asked N times» یا «Draft ready»، و دکمه‌های «Write it» یا «Open draft»، «Answer it» و «Dismiss» دارد.
  - «Answer it» ویرایشگر درون‌خطی باز می‌کند. Escape آن را می‌بندد و فوکوس به دکمه برمی‌گردد. پس از ذخیره، فوکوس روی «Edit» پاسخ تازه می‌رود.
  - کارت «Saved answers» فهرست پاسخ‌ها با «Used N times»، «Edit»، «Delete» (با تأیید) و «Add an answer» را دارد.
  - فیلتر «All time» و «Last 7 days» درخواست `kb/gaps?window=` می‌فرستد.
  - هر دو کارت در حالت خالی دانش سایت هم نمایش داده می‌شوند.
- **زبان پاسخ REST:** برچسب‌های ساخت سرور (دلیل، «Asked N times» و brief) فقط وقتی به زبان مدیرند که درخواست `_locale=user` داشته باشد. WordPress این پارامتر را فقط برای درخواست JSON می‌پذیرد، پس `apiGet` در `knowledge-base.js` سرآیند `Accept: application/json` می‌فرستد.
- **آزمون‌ها:** `tests/wordpress_kb_gaps_write_it_integration.php` (19 بررسی)، `tests/Unit/UnansweredQuestionsTest.php`، و `kb_answers_browser_matrix.cjs` با `local_kb_answers_browser_fixture.php` در `en_US` و `fa_IR`، 1440 و 390 پیکسل.
- **کش پاسخ:** `KbCache` با `bump_version()` کل کش پاسخ را پس از تغییر دانش سایت باطل می‌کند.

## تبدیل گفتار

- **`VoiceTranscriber`:**
  - تبدیل کوتاه‌مدت و سازگار با Whisper با مدل `voice_model` (پیش‌فرض `whisper-1`)؛
  - اعتبارسنجی فایل و مدت؛
  - HTTP امن مشترک، با همان filter HTTP برای endpoint سفارشی.
- **`VoiceUsageTracker`:** فقط مدت را ثبت می‌کند. صدا و متن ذخیره نمی‌شوند.
- **مسیرهای REST:** مدیر از `/chat/voice/transcribe`، `/forms/voice/transcribe` و `/efb/voice/transcribe` استفاده می‌کند، و عموم از `/public/voice`. همه rate limit دارند.
- **اصل رابط:** متن پیاده‌شده در composer قرار می‌گیرد و کاربر ارسال می‌کند. هیچ مسیری خودکار ارسال نمی‌کند.
- **خواندن پاسخ:** با `speechSynthesis` مرورگر انجام می‌شود (`voice_responses_enabled` و `widget.voice_auto`). `VoiceService::synthesize()` (MP3) در هیچ مسیر REST ثبت‌شده‌ای استفاده نمی‌شود.

## تشخیص سلامت

- **`HealthDiagnostics::report()`:** هشت بررسی را از وضعیت فعلی سایت می‌سازد.
- **`run()`:** تنها درخواست مجاز provider را اجرا و نتیجهٔ امن را در `aisaautopilot_health_diagnostics` ذخیره می‌کند.
- **جزئیات:** در مرورگر به‌صورت متن، نه HTML، اعمال می‌شوند.
