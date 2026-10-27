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

1. **شروع:**
   - `ContentIndexer::process_batch()` نوشته‌ها، برگه‌ها و CPTهای منتشرشدهٔ عمومی را در دسته‌های ۲۵تایی می‌خواند. وقتی semantic search روشن است، دسته‌ها ۴تایی‌اند.
   - نوشتهٔ رمزدار یا غیرعمومی (`KnowledgeExtractor::is_indexable()` و فیلتر `aisaautopilot_kb_should_index`) ایندکس نمی‌شود و هر ردیف قبلی‌اش پاک می‌شود.
   - محتوایی که `content_hash` آن تغییر نکرده رد می‌شود. hash شامل قالب ایندکس، `KnowledgeText::VERSION`، عنوان، متن، چکیده و واقعیت‌های محصول است.
2. **استخراج:** `KnowledgeExtractor::extract()` در PHP و بدون هیچ درخواست AI:
   - HTML را (پس از فیلتر `aisaautopilot_kb_post_html`) به بخش‌های زیر عنوان‌ها می‌شکند.
   - برای محصول WooCommerce یک بند واقعیت می‌سازد: قیمت از `get_price_html()`، موجودی، SKU، ویژگی‌ها، وزن و دسته‌ها.
3. **پاره‌ها:**
   - `KnowledgeChunker::chunk()` پاره‌های حدود ۹۰۰ نویسه می‌سازد (کمینه ۲۵۰، بیشینه ۱۴۰۰) و واقعیت‌ها و چکیده پارهٔ اول‌اند.
   - `KnowledgeIndex::replace_post()` پاره‌ها را در `{prefix}aisaautopilot_kb_chunks` و واژه‌ها را در `{prefix}aisaautopilot_kb_terms` می‌نویسد.
   - واژه‌ها از `KnowledgeText::frequencies()` می‌آیند؛ واژهٔ عنوان وزن ۳ و سرتیتر وزن ۲ دارد.
   - ردیف `{prefix}aisaautopilot_kb` برای صفحه‌های مدیریت همچنان نوشته می‌شود: خلاصه = ۳۰۰ نویسهٔ اول، کلیدواژه‌ها = ۸ واژهٔ پرتکرار، و نوع (product، faq، page یا article).
4. **embedding:**
   - فقط اگر `embeddings_enabled` روشن باشد: عنوان و خط‌های اول هر نوشته یک embedding می‌گیرد.
   - فقط همین مرحله به provider می‌رود و زیر سقف مصرف است. سقف پرشده دسته را با cursor دست‌نخورده متوقف می‌کند.
   - نوشتهٔ بی‌تغییری که embedding ندارد، بدون بازایندکس کامل embedding می‌گیرد.
5. **هم‌گام ماندن:**
   - `ContentHooks` پس از انتشار یا به‌روزرسانی، و فقط وقتی `kb_auto_index` روشن است، cron `aisaautopilot_index_post` را ۵ ثانیه بعد زمان‌بندی می‌کند.
   - خروج از انتشار، زباله‌دان و حذف همیشه مدخل و پاره‌ها را پاک می‌کنند (revisionها نادیده‌اند).
6. **ارتقا:**
   - وقتی option `aisaautopilot_kb_index_format` با `KnowledgeIndex::format()` نخواند، `ContentHooks::maybe_start_rebuild()` (روی `init`) جدول‌ها را می‌سازد.
   - اگر `kb_auto_index` روشن باشد، cron `aisaautopilot_kb_rebuild` هر ۳۰ ثانیه یک دسته را بازمی‌سازد (در سقف پرشده، یک ساعت بعد). cursor در `aisaautopilot_kb_rebuild` است.
7. **رابط:** دانش سایت ایندکس را در مرورگر و با `/kb/index/batch` پیش می‌برد. پیام هر سطر «Indexed "…" (N passages).» است. صف پایدار سمت سرور برای ایندکس دستی هنوز وجود ندارد (T44).

### بازیابی و پاسخ

- **بازیابی:** `KnowledgeSearch::retrieve( $question, $prior_turn )`:
  - پرسش را با همان قاعدهٔ ایندکس واژه می‌کند.
  - واژهٔ ناشناخته را با شکل بی‌ضمیرش هم امتحان می‌کند (`KnowledgeText::variants()`، فقط سمت پرسش).
  - پاره‌ها را با BM25 رتبه می‌دهد.
  - اگر embedding آماده باشد، رتبهٔ `VectorSearch` (امتیاز ≥ 0.3) با RRF (k=60) ترکیب می‌شود.
  - خروجی: حداکثر ۸ پاره، از هر نوشته حداکثر ۳، در کل ۹۰۰۰ نویسه، با قالب «[n] عنوان › سرتیتر» و متن.
  - هر پاره پیش از نقل با `still_public()` دوباره بررسی می‌شود.
  - اگر پوشش بهترین پاره از وزن پرسش کمتر از `MIN_COVERAGE` (0.2) باشد، `found` نادرست است.
  - پرسش پیگیر: واژه‌های پرسش قبلی با وزن 0.5 می‌آیند و نیمی از پوشش آن‌ها هم حساب می‌شود.
  - دلیل‌ها: `not_ready`، `empty_index`، `no_terms`، `no_match`، `low_coverage`، `ok`.
  - تا ایندکس پاره‌ها ساخته نشده (`not_ready` یا `empty_index`)، `ChatEngine` همان مسیر قدیم را به کار می‌برد: `VectorSearch`، یا `get_context_with_refs()`.
- **تاریخچه:**
  - ویجت آخرین ۶ نوبت را با `history` می‌فرستد.
  - `Chat\ConversationHistory::clean()` هر نوبت را پاک‌سازی و به ۱۲۰۰ نویسه محدود می‌کند، و `context()` آن را به‌صورت خط‌های «Visitor:/Assistant:» با هشدار «untrusted reference text» درمی‌آورد.
  - بی‌تاریخچه، رشتهٔ خالی برمی‌گردد تا پرسش مستقل از کش استفاده کند.
  - `ChatEngine::last_user_turn()` پرسش قبلی را برای جست‌وجو برمی‌دارد.
- **`ChatEngine::ask( $question, $history )`:**
  0. **پاسخ ذخیره‌شده:**
     - برای پرسش مستقل (بدون `$history`)، `SavedAnswers::find()` پیش از هر چیز دیگر بررسی می‌شود.
     - اگر جور باشد، متن مدیر با `status` برابر `answered`، `sources` خالی و `saved_answer` برابر `true` برمی‌گردد، و `record_use()` یک استفاده می‌شمارد.
     - هیچ درخواستی به هوش مصنوعی نمی‌رود، پس بدون provider هم کار می‌کند و از سقف مصرف کم نمی‌کند.
  1. **کش:** `KbCache` پاسخ کش‌شده را بررسی می‌کند.
  2. **بافت:** پاره‌های بالا با نگاشت مرجع (`post_id`، عنوان، `chunk_id`، امتیاز). بافت خالی یعنی `not_found` بدون هیچ درخواست AI، و ثبت پرسش در `KbGaps` با دلیل `no_context`.
  3. **تصمیم:** مدل تصمیم JSON برمی‌گرداند: `answered`، `clarify` با `clarify_options`، یا `not_found`. prompt می‌گوید رقم‌ها را همان‌طور که نوشته شده نقل کند.
  4. **منبع:**
     - فقط مرجع‌هایی که مدل واقعاً به کار برده به `sources` با عنوان، آدرس و `relevance` (امتیاز نسبی پاره) نگاشت می‌شوند.
     - منبع جایگزین ساختگی دیگر وجود ندارد.
  5. **ثبت کمبود:** `not_found` مدل با دلیل `model_not_found`، پاسخ خالی با `empty_answer` و پاسخ بدون منبع با `no_citation` در `KbGaps` ثبت می‌شوند.
  6. **کش پاسخ:** پاسخ موفق غیر-پیگیری در کش ذخیره می‌شود.
  7. **«پیدا نشد»** (`not_found_result()`)، بر اساس تنظیم مدیر:
     - `widget.not_found_action`:
       - `message` پیش‌فرض است؛
       - `handoff` فقط وقتی کانال انتقال آماده باشد `handoff_offer` را صادق می‌کند.
     - `widget.not_found_message`: متن خود مدیر، تا ۳۰۰ نویسه.
     - بی‌متن مدیر:
       - اگر مدل یادداشت «پیدا نشد» نوشته باشد، همان به زبان بازدیدکننده نشان داده می‌شود؛
       - وگرنه متن پیش‌فرض افزونه.
     - متن پیش‌فرض به زبان بازدیدکننده است وقتی خطّ پرسش با خطّ سایت فرق دارد و وردپرس آن زبان را دارد (`SiteLanguage::visitor_locale()` و `switch_to_locale()`). مثلاً پرسش لاتین در سایت فارسی انگلیسی می‌گیرد، و پرسش فارسی در سایت انگلیسی فارسی می‌گیرد اگر fa_IR نصب باشد.
- **کنسول مدیر (`/kb/chat`):** همان `KnowledgeSearch`؛ اگر هیچ پاره‌ای مرتبط نباشد، پاسخ «not_found» بدون درخواست AI است.
- **بازایندکس کامل (`/kb/truncate`):** فقط وقتی semantic search روشن است سقف پرشده آن را رد می‌کند، چون ایندکس متن هزینه‌ای ندارد.
- **زبان:** `SiteLanguage` زبان آغازین را با زبان سایت هم‌تراز می‌کند، و هر نوبت با زبان پرسش بازدیدکننده پاسخ می‌گیرد.
- **سنجش:** `docs/KB_RETRIEVAL_BENCHMARK_FA.md`.

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
