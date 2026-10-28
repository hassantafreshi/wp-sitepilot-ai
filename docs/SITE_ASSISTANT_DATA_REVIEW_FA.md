# بررسی دستیار سایت: ساختار پاسخ، ذخیره‌سازی و مدل داده

- **وضعیت:** فقط بررسی، به درخواست مالک: «ساختار پاسخ برای اسیستنت سایت هم مدل ذخیره سازی و مدل سازی اصلا خوب نیست. اون هم یک بررسی کن». هیچ کدی تغییر نکرده است.
- **تاریخ:** ۲۰۲۶-۱۰-۲۷.
- **دامنه:** دستیاری که در سایت به پرسش بازدیدکننده جواب می‌دهد (ویجت چت، میز پاسخ‌گویی و دانش سایت).
- **روش:** یک خواندن کامل کد. پنج ادعای مهم را جداگانه دوباره با کد تطبیق دادم و همه درست بودند؛ این‌ها با ✔ مشخص شده‌اند.
- **عددها:** عددهای «تخمین» محاسبه‌اند، نه اندازه‌گیری روی سایت واقعی. اندازهٔ هر بردار ۱۵۳۶‌بعدی (حدود ۳۲ کیلوبایت به‌صورت JSON) با PHP همین محیط اندازه‌گیری شد.

## خلاصه

بزرگ‌ترین مشکل در **بازیابی** است، نه ذخیره‌سازی. با تنظیم پیش‌فرض، مدل فقط خلاصهٔ تازه‌ترین نوشته‌ها را می‌بیند و خودِ پرسش در انتخاب آن‌ها هیچ نقشی ندارد. پس روی سایتی با چند صد نوشته، بیشتر محتوا عملاً در دسترس دستیار نیست.

بعد از آن به ترتیب:
1. ذخیرهٔ گفت‌وگو به‌صورت یک تکهٔ JSON برای هر گفت‌وگو.
2. نبود حافظهٔ گفت‌وگو در ویجت.
3. نمایه شدن نوشته‌های رمزدار.
4. نگه‌داری دادهٔ شخصی بدون مهلت حذف.
5. ساختار پاسخ بدون نوع ثابت.

## ۱. مدل دادهٔ فعلی

| محل | چه نگه می‌دارد | ایراد اصلی |
|---|---|---|
| جدول `aisaautopilot_kb` (`KnowledgeBase.php:36-58`) | یک ردیف برای هر نوشته: عنوان، **خلاصهٔ ۲ تا ۴ جمله‌ای**، کلیدواژه، بردار به‌صورت JSON در `LONGTEXT` | متن اصلی ذخیره نمی‌شود و تکه‌بندی (chunk) ندارد |
| جدول `aisaautopilot_widget_chats` (`VisitorConversationStore.php:30-45`) | کل گفت‌وگوی بازدیدکننده در **یک ستون JSON** (فقط ۴۰ پیام آخر) | پیام‌ها ردیف جدا ندارند؛ قابل جست‌وجو یا فیلتر نیست |
| جدول `aisaautopilot_chats` (`ConversationManager.php:35-46`) | گفت‌وگوهای کنسول مدیر، همان شکل JSON | همان |
| جدول `aisaautopilot_handoffs` (`HandoffInbox.php:46-61`) | درخواست‌های ارجاع به انسان با ستون‌های تماس درست | متن گفت‌وگو را مرورگر می‌فرستد و به گفت‌وگوی ذخیره‌شده وصل نیست |
| گزینهٔ `aisaautopilot_kb_gaps` | حداکثر ۲۰۰ پرسش بی‌پاسخ، به‌صورت آرایهٔ سریال‌شده | با هر بار نوشتن کل آرایه بازنویسی می‌شود |
| گزینهٔ `aisaautopilot_kb_saved_answers` | حداکثر ۲۰۰ پاسخ ذخیره‌شده | همان |
| گزینهٔ `aisaautopilot_kb_answer_cache` | حداکثر ۳۰۰ پاسخ در حافظهٔ موقت | بدون قفل؛ نوشتن هم‌زمان گم می‌شود |

این سه گزینه هرکدام پرسش را با روش جداگانه‌ای یکسان‌سازی می‌کنند و به هم پیوند ندارند. **بازخورد** («این پاسخ مفید بود؟») هیچ جایی ذخیره نمی‌شود و پیام‌ها شناسه ندارند.

## ۲. ساختار پاسخ

**چیزی که از مدل خواسته می‌شود** (`ChatEngine.php:346-367`):

```json
{"status":"answered|clarify|not_found","answer":"…","clarify_options":[],"sources":[1,3]}
```

**چیزی که ویجت می‌گیرد.** خروجی موتور مستقیماً در پاسخ REST ادغام می‌شود (`ChatWidgetController.php:116`). ایرادها:

- ✔ خطاها و حالت «پیکربندی نشده» با `ok:true` و کد ۲۰۰ برمی‌گردند و متن عذرخواهی در `answer` می‌نشیند (`ChatEngine.php:76-82`، `140-155`). ویجت آن را پاسخ واقعی نشان می‌دهد و اگر ثبت گفت‌وگو روشن باشد، به‌عنوان پاسخ ذخیره هم می‌شود.
- `relevance` منبع همیشه ۱٫۰ است (`ChatEngine.php:300`). میزان اطمینانی وجود ندارد.
- متن پیشنهاد ارجاع به انسان به خود پاسخ چسبانده می‌شود (`ChatEngine.php:217-220`)، پس در حافظهٔ موقت و گفت‌وگوی ذخیره‌شده هم می‌ماند.
- `conversation_id` در ویجت رشتهٔ UUID است و در کنسول مدیر عدد.
- اگر JSON مدل خراب باشد، یک درخواست پولی دوم فرستاده می‌شود (`ChatEngine.php:122-133`).

## ۳. ایرادها به ترتیب اثر

اثر هر ایراد برای سایتی با ۲٬۰۰۰ نوشته و ۵۰ هزار گفت‌وگو تخمین زده شده است.

1. **بحرانی، ✔ بازیابی پیش‌فرض پرسش را نادیده می‌گیرد.**
   - جست‌وجوی معنایی (embedding) به‌طور پیش‌فرض خاموش است (`Settings.php:444`).
   - در این حالت زمینهٔ مدل همیشه ۴۰ نوشتهٔ تازه‌تر است، تا سقف ۱۲٬۰۰۰ **بایت** (`ChatEngine.php:181`، `KnowledgeBase.php:351-364`). خود پرسش در انتخاب نقشی ندارد.
   - سقف بر حسب بایت است، پس در متن فارسی (دو بایت برای هر حرف) عملاً فقط حدود ۶ تا ۱۰ خلاصه جا می‌شود.
   - نتیجه: بیشتر محتوای سایت دیده نمی‌شود، پاسخ «پیدا نشد» زیاد می‌آید، و فهرست پرسش‌های بی‌پاسخ با موارد نادرست پر می‌شود.
2. **بحرانی، فقط خلاصه، بدون تکه.**
   - هر نوشته به یک خلاصهٔ حداکثر ۵۰۰ نویسه تبدیل می‌شود و فقط ۸٬۰۰۰ نویسهٔ اول متن خوانده می‌شود (`ContentSummarizer.php:18, 44-46`).
   - قیمت، ساعت کاری و مراحل دقیق معمولاً در خلاصه نمی‌مانند.
3. **بالا، ✔ جست‌وجوی برداری همهٔ بردارها را در PHP بار می‌کند.**
   - تا ۲٬۰۰۰ ردیف با بردار JSON در هر پرسش خوانده و decode می‌شود (`KnowledgeBase.php:463-497`).
   - تخمین: حدود ۶۵ مگابایت خواندن و بیش از ۱۰۰ مگابایت حافظه. روی هاست با سقف ۱۲۸ مگابایت شکست می‌خورد.
   - نوشته‌های بعد از ۲٬۰۰۰ بی‌صدا حذف می‌شوند.
   - آستانهٔ امتیاز ندارد، پس ۱۰ نتیجهٔ اول همیشه «مرتبط» حساب می‌شوند.
4. **بالا، ✔ ویجت حافظهٔ گفت‌وگو ندارد.**
   - ویجت فقط پرسش و شناسه را می‌فرستد (`widget.js:518`) و سرور `ask( $question )` را بدون تاریخچه صدا می‌زند (`ChatWidgetController.php:96`).
   - «قیمتش چند است؟» بدون دانستن پرسش قبلی جواب داده می‌شود.
   - همین پاسخ برای همهٔ بازدیدکنندگان در حافظهٔ موقت می‌ماند.
5. **بالا، ✔ نوشته‌های رمزدار نمایه می‌شوند.** پرس‌وجوی نمایه فقط `post_status = publish` را وارسی می‌کند (`ContentIndexer.php:105-113`) و هیچ‌جا `post_password` وارسی نمی‌شود. محتوای نوشتهٔ رمزدار خلاصه می‌شود و به بازدیدکنندهٔ ناشناس داده می‌شود.
6. **بالا، هر گفت‌وگو یک تکهٔ JSON است.**
   - با هر نوبت، کل گفت‌وگو بدون قفل خوانده و بازنویسی می‌شود؛ نوبت‌های هم‌زمان ممکن است گم شوند (`VisitorConversationStore.php:48-98`).
   - نتیجه (پاسخ داده شد، پیدا نشد، خطا)، مدل و توکن ذخیره نمی‌شوند.
   - شناسهٔ گفت‌وگو فقط در حافظهٔ صفحه است، پس هر بار باز کردن صفحه یک ردیف تازه می‌سازد.
   - شمارندهٔ «پاسخ‌ها» در نمای کلی خطاها را هم پاسخ حساب می‌کند (`HomePage.php:391`).
7. **بالا (حریم خصوصی)، بدون مهلت حذف.**
   - اطلاعات تماس ارجاع‌ها و گفت‌وگوها برای همیشه می‌مانند.
   - ابزار خروجی و پاک‌سازی حریم خصوصی وردپرس (`wp_privacy_*`) ثبت نشده است.
   - متن پرسش‌ها حتی وقتی ثبت گفت‌وگو خاموش است، در گزینه‌های «پرسش‌های بی‌پاسخ» و «حافظهٔ موقت» نگه داشته می‌شود (`KbGaps.php:53`، `KbCache.php:74`).
8. **متوسط، منبع ساختگی.** وقتی مدل به هیچ منبعی ارجاع نمی‌دهد، نتیجه‌های جست‌وجوی کلیدواژه باز هم به‌عنوان «منبع» نشان داده می‌شوند (`ChatEngine.php:228-231`).
9. **متوسط، حافظهٔ موقت عملاً کار نمی‌کند.**
   - هر ذخیرهٔ نوشته (حتی بازبینی‌ها) نسخهٔ پایگاه دانش را بالا می‌برد و کل حافظهٔ موقت باطل می‌شود.
   - `post_modified` جزو هش محتواست، پس هر ذخیره یک خلاصه‌سازی پولی تازه هم دارد (`ContentIndexer.php:436`، `ContentHooks.php:40-41`).
10. **متوسط، ارجاع به انسان از گفت‌وگو جداست.**
    - شناسه‌ای که ویجت می‌فرستد نادیده گرفته می‌شود.
    - متن گفت‌وگو از مرورگر می‌آید، پس می‌تواند پیامی به نام «دستیار» داشته باشد که هوش مصنوعی هرگز نگفته.
11. **کم تا متوسط، عملیاتی.**
    - چندسایته (multisite) پشتیبانی نمی‌شود. (۲۰۲۶-۱۰-۲۸: رفع شد، کار سریع ۱۰.)
    - در هر پرسش `SHOW TABLES` و سه بار `SHOW COLUMNS` اجرا می‌شود.
    - مسیر `/kb/list` بردار JSON را به مرورگر می‌فرستد.
    - سه مسیر پاسخ جدا و ناهمسان وجود دارد: `/kb/chat`، `/public/chat` و `/widget/ask`.

**چیزهایی که درست است:**
- همهٔ SQL با `prepare` است.
- `post_id` کلید یکتاست.
- اگر محتوا تغییر نکرده باشد، دوباره نمایه نمی‌شود.
- جدول ارجاع ستون و نمایهٔ درست دارد.
- ثبت گفت‌وگو اختیاری و پیش‌فرض خاموش است.
- پاکسازی هنگام حذف افزونه در تک‌سایت کامل است.

## ۴. طرح پیشنهادی

**جدول‌ها.** ساختار کامل ستون‌ها و نمایه‌ها در پیوست آمده است.

| جدول | کار |
|---|---|
| `kb_docs` | یک ردیف برای هر نوشته، با نسخه و وضعیت |
| `kb_chunks` | تکه‌های ۸۰۰ تا ۱۲۰۰ نویسه‌ای، بر اساس عنوان‌ها و پاراگراف‌ها |
| `kb_terms` | نمایهٔ کلیدواژهٔ خود افزونه، با یکسان‌سازی حروف فارسی و عربی و ارقام؛ به FULLTEXT در MySQL وابسته نیست |
| `kb_vectors` | بردار به‌صورت BLOB فشرده، اختیاری؛ صفحه‌به‌صفحه خوانده می‌شود تا حافظه محدود بماند |
| `conversations` و `messages` | یک ردیف برای هر پیام، با نتیجه، مدل، توکن و زمان |
| `message_sources` | منابعی که هر پاسخ واقعاً استفاده کرد، با امتیاز |
| `feedback` | بازخورد هر پاسخ |
| `questions` | جایگزین «پرسش‌های بی‌پاسخ» و «حافظهٔ موقت» با یک یکسان‌سازی واحد |
| `saved_answers` | پاسخ‌های ذخیره‌شده، وصل به نوشتهٔ مرتبط |
| `handoffs` | جدول فعلی، به‌اضافهٔ پیوند به گفت‌وگو و منبع متن (سرور یا مرورگر) |

**بازیابی.** نتیجهٔ جست‌وجوی کلیدواژه و جست‌وجوی برداری با هم ترکیب می‌شوند (رتبه‌بندی ترکیبی RRF) و ۶ تا ۸ تکهٔ بهتر انتخاب می‌شوند. اگر امتیاز زیر آستانه باشد، اصلاً به مدل درخواست نمی‌رود و پاسخ «پیدا نشد» است. شش نوبت آخر گفت‌وگو هم فرستاده می‌شود.

**پاسخ تایپ‌دار نسخهٔ ۲.** کلیدهای قدیمی یک نسخه حفظ می‌شوند.

```json
{"ok":true,"schema":2,"conversation_id":"…",
 "message":{"id":18342,"outcome":"answered","origin":"model","language":"fa",
  "answer":{"format":"markdown","text":"…"},
  "citations":[{"n":1,"post_id":42,"chunk_id":311,"title":"…","url":"…","score":0.81}],
  "confidence":"high","clarify":null,"follow_ups":["…"],
  "handoff":{"offer":false,"reason":null}}}
```

- `outcome` یکی از این‌هاست: answered، clarify، not_found، refused.
- `origin` یکی از این‌هاست: model، saved_answer، cache.
- خطا با `ok:false` و کد واقعی HTTP برمی‌گردد.
- `confidence` را سرور از امتیاز بازیابی و ارجاع‌ها حساب می‌کند، نه مدل.
- متن پیشنهاد ارجاع به انسان را خود ویجت به زبان بازدیدکننده می‌نویسد.

**حریم خصوصی.**
- مهلت پیش‌فرض نگه‌داری: گفت‌وگو ۳۰ روز، ارجاع ۱۸۰ روز، متن پرسش ۹۰ روز.
- پاک‌سازی روزانه با WP-Cron.
- حذف ایمیل و تلفن از پیام‌های ذخیره‌شده.
- ثبت ابزار خروجی و پاک‌سازی حریم خصوصی وردپرس.
- چندسایته.

**مهاجرت بدون از دست رفتن داده.**
1. جدول‌های تازه با dbDelta ساخته می‌شوند.
2. یک مهاجرت پس‌زمینه با WP-Cron، هر بار ۲۰۰ ردیف، داده را منتقل می‌کند و اگر قطع شد از همان‌جا ادامه می‌دهد.
3. بعد خواندن از جدول‌های تازه شروع می‌شود.
4. جدول‌ها و گزینه‌های قدیمی یک نسخه برای بازگشت می‌مانند.
5. تکه‌بندی دوباره پولی است، پس در پس‌زمینه و زیر سقف مصرف انجام می‌شود.

## ۵. کارهای سریع (بدون تغییر ساختار پایگاه داده)

**وضعیت (۲۰۲۶-۱۰-۲۸):**

| # | کار | وضعیت |
|---|---|---|
| ۱ | نوشتهٔ رمزدار نمایه نشود | انجام شد (`KnowledgeExtractor::is_indexable()`، و دوباره پیش از نقل) |
| ۲ | تاریخچهٔ گفت‌وگو؛ کش و پاسخ ذخیره‌شده فقط برای پرسش اول | انجام شد (`ConversationHistory`) |
| ۳ | خطا و «پیکربندی نشده» با `ok:false` و کد واقعی HTTP | انجام شد: 503 `assistant_unavailable` یا `assistant_not_configured` در ویجت و `/public/chat`، 503 یا 400 در کنسول مدیر؛ هیچ‌کدام ذخیره نمی‌شوند. جملهٔ پیشنهاد انتقال هنوز در متن «پیدا نشد» می‌آید، چون همان چیزی است که بازدیدکننده می‌بیند. |
| ۴ | `/kb/list` بی‌بردار؛ حذف `SHOW COLUMNS` در هر درخواست | انجام شد (ستون‌های صریح؛ option `aisaautopilot_kb_schema`) |
| ۵ | خواندن صفحه‌به‌صفحهٔ بردارها، بی‌سقف ۲٬۰۰۰، آستانه و امتیاز واقعی | انجام شد (۲۰۰تایی؛ آستانهٔ 0.3 در `KnowledgeSearch`؛ `relevance` از امتیاز پاره) |
| ۶ | نسخهٔ دانش فقط با تغییر واقعی؛ بی‌`post_modified` | انجام شد |
| ۷ | منبع ساختگی | حذف شد (به‌جای برچسب) |
| ۸ | یک یکسان‌سازی پرسش برای همه‌جا | انجام شد (`QuestionKey::of()` برای پاسخ ذخیره‌شده، کش و پرسش‌های بی‌پاسخ؛ همان قاعدهٔ پاسخ‌های ذخیره‌شده تا هیچ پاسخ موجودی جور نشدن نگیرد) |
| ۹ | پاک‌سازی دوره‌ای، ابزار حریم خصوصی، شناسهٔ گفت‌وگو در sessionStorage، پیوند انتقال به گفت‌وگو | بیشترش انجام شد: exporter و eraser وردپرس و متن پیشنهادی سیاست حریم خصوصی (`PrivacyTools`)؛ شناسه در `sessionStorage`؛ انتقال با ثبت روشن متن گفت‌وگوی ذخیره‌شده را به کار می‌برد. **مهلت نگهداری و پاک‌سازی خودکار منتظر تصمیم مالک است** (پیشنهاد این سند: گفت‌وگو ۳۰ روز، انتقال ۱۸۰ روز، متن پرسش ۹۰ روز). |
| ۱۰ | چندسایته | انجام شد: فعال‌سازی شبکه همهٔ سایت‌ها را آماده می‌کند؛ سایت تازه و سایت‌های شبکهٔ بزرگ در نخستین درخواست خود آماده می‌شوند (`Activator::maybe_setup_site()`)؛ سایت حذف‌شده جدول‌های افزونه را با خود می‌برد (`wpmu_drop_tables`)؛ حذف افزونه همهٔ سایت‌ها و جدول‌های جامانده از سایت‌های حذف‌شده را پاک می‌کند. |


1. نوشته‌های رمزدار نمایه نشوند و موارد فعلی پاک شوند.
2. ویجت شش نوبت آخر را بفرستد. حافظهٔ موقت و پاسخ ذخیره‌شده فقط برای اولین پرسش گفت‌وگو استفاده شوند.
3. خطا و حالت «پیکربندی نشده» با `ok:false` و کد واقعی HTTP برگردند. متن ارجاع به پاسخ چسبانده نشود.
4. `/kb/list` بردار را نفرستد. شمارش بردارها در حافظه بماند. `SHOW COLUMNS` در هر درخواست حذف شود.
5. بردارها صفحه‌به‌صفحه خوانده شوند. سقف بی‌صدای ۲٬۰۰۰ حذف شود. آستانهٔ امتیاز اضافه شود و امتیاز واقعی به‌جای ۱٫۰ نشان داده شود.
6. نسخهٔ پایگاه دانش فقط با تغییر واقعی بالا برود. بازبینی‌ها نادیده گرفته شوند. `post_modified` از هش حذف شود.
7. منبع ساختگی برچسب «ارجاع داده نشد» بگیرد.
8. یک روش یکسان‌سازی پرسش برای همه‌جا.
9. پاک‌سازی دوره‌ای و ابزار حریم خصوصی. شناسهٔ گفت‌وگو در sessionStorage بماند. ارجاع به گفت‌وگو وصل شود.
10. فعال‌سازی و حذف افزونه در چندسایته همهٔ سایت‌ها را بپوشانند.

**کارهای بزرگ‌تر:**
- پایگاه دانش تکه‌ای با بازیابی ترکیبی؛ بیشترین اثر روی کیفیت پاسخ.
- جدول‌های پیام، منبع و بازخورد.
- جدول‌های پرسش و پاسخ ذخیره‌شده.
- پاسخ تایپ‌دار نسخهٔ ۲ و یکی کردن سه مسیر پاسخ.
- اجراکنندهٔ مهاجرت.

## پیوست: ستون‌های جدول‌های پیشنهادی

```sql
CREATE TABLE {p}aisa_kb_docs (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, post_id BIGINT UNSIGNED NOT NULL,
  post_type VARCHAR(50) NOT NULL, lang VARCHAR(12) NOT NULL DEFAULT '', title VARCHAR(255) NOT NULL DEFAULT '',
  summary TEXT NULL, content_hash CHAR(64) NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
  status VARCHAR(16) NOT NULL DEFAULT 'pending', error_code VARCHAR(40) NOT NULL DEFAULT '',
  indexed_at DATETIME NULL, updated_at DATETIME NOT NULL,
  PRIMARY KEY  (id), UNIQUE KEY post_id (post_id), KEY status_updated (status,updated_at));
CREATE TABLE {p}aisa_kb_chunks (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, doc_id BIGINT UNSIGNED NOT NULL,
  post_id BIGINT UNSIGNED NOT NULL, doc_version INT UNSIGNED NOT NULL, ordinal SMALLINT UNSIGNED NOT NULL,
  kind VARCHAR(12) NOT NULL DEFAULT 'body', heading VARCHAR(255) NOT NULL DEFAULT '', text TEXT NOT NULL,
  token_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY  (id), KEY doc (doc_id,ordinal), KEY post_id (post_id));
CREATE TABLE {p}aisa_kb_vectors (chunk_id BIGINT UNSIGNED NOT NULL, model VARCHAR(64) NOT NULL,
  dim SMALLINT UNSIGNED NOT NULL, vec BLOB NOT NULL, PRIMARY KEY  (chunk_id), KEY model (model,chunk_id));
CREATE TABLE {p}aisa_kb_terms (term BIGINT UNSIGNED NOT NULL, chunk_id BIGINT UNSIGNED NOT NULL,
  tf SMALLINT UNSIGNED NOT NULL, PRIMARY KEY  (term,chunk_id), KEY chunk_id (chunk_id));
CREATE TABLE {p}aisa_conversations (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id CHAR(36) NOT NULL,
  channel VARCHAR(10) NOT NULL, user_id BIGINT UNSIGNED NOT NULL DEFAULT 0, locale VARCHAR(12) NOT NULL DEFAULT '',
  status VARCHAR(12) NOT NULL DEFAULT 'open', message_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL, last_message_at DATETIME NOT NULL, expires_at DATETIME NULL,
  PRIMARY KEY  (id), UNIQUE KEY public_id (public_id), KEY channel_last (channel,last_message_at), KEY expires_at (expires_at));
CREATE TABLE {p}aisa_messages (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, conversation_id BIGINT UNSIGNED NOT NULL,
  role VARCHAR(10) NOT NULL, outcome VARCHAR(12) NULL, origin VARCHAR(12) NULL, content MEDIUMTEXT NOT NULL,
  lang VARCHAR(12) NOT NULL DEFAULT '', question_id BIGINT UNSIGNED NULL, kb_version INT UNSIGNED NULL,
  model VARCHAR(100) NOT NULL DEFAULT '', top_score DECIMAL(5,4) NULL, prompt_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  completion_tokens INT UNSIGNED NOT NULL DEFAULT 0, latency_ms INT UNSIGNED NULL, created_at DATETIME NOT NULL,
  PRIMARY KEY  (id), KEY conv (conversation_id,id), KEY outcome_created (outcome,created_at), KEY question_id (question_id));
CREATE TABLE {p}aisa_message_sources (message_id BIGINT UNSIGNED NOT NULL, chunk_id BIGINT UNSIGNED NOT NULL,
  post_id BIGINT UNSIGNED NOT NULL, doc_version INT UNSIGNED NOT NULL, pos TINYINT UNSIGNED NOT NULL,
  score DECIMAL(5,4) NOT NULL, cited TINYINT(1) NOT NULL, PRIMARY KEY  (message_id,chunk_id), KEY post_id (post_id));
CREATE TABLE {p}aisa_feedback (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, message_id BIGINT UNSIGNED NOT NULL,
  rating TINYINT NOT NULL, reason VARCHAR(32) NOT NULL DEFAULT '', created_at DATETIME NOT NULL,
  PRIMARY KEY  (id), UNIQUE KEY message_id (message_id));
CREATE TABLE {p}aisa_questions (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, qhash CHAR(32) NOT NULL,
  text VARCHAR(500) NOT NULL, lang VARCHAR(12) NOT NULL DEFAULT '', ask_count INT UNSIGNED NOT NULL DEFAULT 0,
  miss_count INT UNSIGNED NOT NULL DEFAULT 0, last_outcome VARCHAR(12) NOT NULL DEFAULT '',
  status VARCHAR(12) NOT NULL DEFAULT 'open', saved_answer_id BIGINT UNSIGNED NULL, draft_post_id BIGINT UNSIGNED NULL,
  cache_message_id BIGINT UNSIGNED NULL, cache_kb_version INT UNSIGNED NULL, legacy_hash CHAR(32) NULL,
  first_seen DATETIME NOT NULL, last_seen DATETIME NOT NULL,
  PRIMARY KEY  (id), UNIQUE KEY qhash_lang (qhash,lang), KEY status_miss (status,miss_count), KEY last_seen (last_seen));
CREATE TABLE {p}aisa_saved_answers (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, qhash CHAR(32) NOT NULL,
  question VARCHAR(300) NOT NULL, answer TEXT NOT NULL, related_post_id BIGINT UNSIGNED NULL, review_after DATE NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0, last_used_at DATETIME NULL, created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY  (id), UNIQUE KEY qhash (qhash));
-- handoffs: + conversation_id BIGINT UNSIGNED NULL (KEY), transcript_source VARCHAR(8) 'server'|'client'
```
