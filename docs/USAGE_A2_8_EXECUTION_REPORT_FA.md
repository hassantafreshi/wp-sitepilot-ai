# گزارش اجرای A2.8 — Usage & Cost

تاریخ: 2026-09-05  
مرجع بصری: `mockups/design_handoff_admin_redesign/AutoPilot Admin.dc.html`، بخش Usage در خطوط 1275 تا 1329.

## نتیجه

صفحهٔ Usage & Cost اکنون به ساختار دقیق دوکارتِ موکاپ بازطراحی شده است، اما تمام داده‌ها token-first و قابل‌اثبات باقی مانده‌اند. مبلغ دلاری، free tier، provider quota، attribution جعلیِ محصول و email ساختگی نمایش داده نمی‌شود.

## پیاده‌سازی

- کارت اول: مقدار مصرف واقعی، meter سقف توکن، panel درون‌خطی `Set a token cap`، نمودار هفت‌روز اخیر و Reset فقط برای دورهٔ جاری.
- کارت دوم: `Where it went` با چهار ردیف ثابت و صادقانه: `Text generation`، `Structured output`، `Embeddings` و `Other or unclassified records`.
- صفر در budget به‌معنای unlimited است. ورودی منفی REST نیز اکنون به صفر normalise می‌شود، نه cap مثبت.
- حالت‌های `empty`، `unlimited`، `normal`، `near` و `exhausted` از دادهٔ واقعی tracker رندر می‌شوند.
- در cap فقط گفته می‌شود درخواست‌های تحت همین token cap تا دورهٔ بعد متوقف‌اند؛ ادعای نادرستِ توقف همهٔ قابلیت‌ها یا ارسال email وجود ندارد.
- CTA هدر، Change cap، Save، Cancel، Escape، بازگشت focus و nonce واقعی REST همگی متصل و آزموده شده‌اند.
- endpoint سقف فقط عدد صحیحِ محدوده‌دار را می‌پذیرد؛ ورودی ناقص، اعشاری، علمی، آرایه و خارج از محدوده با خطای `400 invalid_budget` رد می‌شود و مقدار ذخیره‌شده را تغییر نمی‌دهد.

## اصلاحات داده‌ای پیش از UI

- `UsageTracker` اکنون `aisaautopilot_usage_period_contexts` را مستقل از ring buffer نگه می‌دارد؛ breakdown ماهانه پس از عبور از 500 رخداد کم‌شماری نمی‌کند.
- `aisaautopilot_usage_daily` افزوده شد تا نمودار هفت‌روزه نیز پس از trim شدن event log کامل بماند. rollover ماه را حفظ می‌کند و Reset فقط روزهای دورهٔ جاری را پاک می‌کند.
- migration قدیمی فقط از رخدادهای retained قابل استخراج است و برای رخدادهای حذف‌شده دادهٔ ساختگی تولید نمی‌کند.
- مقدار cap خرابِ منفی در storage به unlimited نرمال می‌شود؛ period معتبر اما آینده نیز به‌صورت تاریخی حفظ و دورهٔ واقعی جاری از نو آغاز می‌شود.
- مدل قدیمی `by_model`، `by_context` و recent log برای compatibility API حفظ شده‌اند، ولی UI جدید از `period_by_context` و daily aggregates استفاده می‌کند.

## hardening مسیرهای متکی به cap

- Site Knowledge هنگام توقف cap، `429 usage_cap_reached` با cursor همان batch برمی‌گرداند؛ مورد ناموفق skip نمی‌شود و reindex کامل نیز تا برداشتن cap، دادهٔ قبلی را پاک نمی‌کند.
- دکمهٔ `Resume indexing` فقط پس از توقف ظاهر می‌شود و cursor را صرفاً در همان tab مرورگر نگه می‌دارد؛ queue یا retry پایدار و پنهانی ایجاد نشده است.
- Store Copilot و EFB نیز کد `usage_cap_reached` را بدون تبدیل به خطای عمومی به HTTP 429 منتقل می‌کنند. تست‌های transport تأیید می‌کنند که در cap هیچ درخواست provider ساخته نمی‌شود.

## تفاوت‌های عمدی با موکاپ

| مورد موکاپ | تصمیم نهایی |
|---|---|
| دلار و سقف دلاری | توکن و سقف توکن؛ plugin price map یا billing API ندارد. |
| slider مبلغی 5 تا 200 دلار | input عددی توکن با مقدار واقعی دلخواه؛ 0 یعنی unlimited. |
| چهار feature محصولی | operationهای ثبت‌شدهٔ واقعی؛ feature attribution در backend وجود ندارد. |
| free tier و quota provider | حذف؛ دادهٔ قابل‌اعتماد محلی وجود ندارد. |
| «همهٔ AIها pause و email می‌شود» | copy محدود به درخواست‌های تحت token cap؛ voice و email خارج از این قراردادند. |
| ادامهٔ reindex پس از cap | موکاپ state آن را ندارد؛ یک کنترل session-only افزوده شد تا cursor و دادهٔ قبلی از دست نرود. |

## قابلیت‌های حفظ‌شده اما خارج از surface موکاپ

| قابلیت | وضعیت UI |
|---|---|
| `by_model` و 20 رخداد اخیر | compatibility/API حفظ شده؛ موکاپ برای آن surface ندارد. |
| archive دوره‌های بسته و historical migration | حفظ‌شده برای سلامت داده؛ به‌عنوان مصرف ماه جاری نمایش داده نمی‌شود. |
| VoiceUsageTracker برحسب ثانیه | حفظ‌شده و عمداً به token/price تبدیل نشده است. |
| Reset current period | در panel cap با confirm صریح حفظ شده؛ موکاپ فقط Save/Cancel دارد. |
| Refresh و Last AI request قدیمی | surface موکاپ ندارند؛ refresh مرورگر کافی است و timestamp در UI کوتاه جدید اضافه نشد. |

## شواهد تست

| گیت | نتیجه |
|---|---|
| `wordpress_usage_tracker_report_integration.php` | 11/0؛ legacy، rollover، reset، ring trim، cap خراب، period آینده و migration یک‌باره |
| `wordpress_usage_budget_api_integration.php` | 15/0؛ قرارداد strict، range، ورودی‌های نامعتبر و منفی→unlimited |
| `wordpress_usage_cap_provider_integration.php` | 3/0؛ structured/text/embedding بدون transport در cap |
| `wordpress_usage_cap_response_integration.php` | 6/0؛ پاسخ cap در surfaceهای عمومی/admin |
| `wordpress_usage_cap_kb_index_integration.php` | 3/0؛ 429، cursor جزئی، حفظ داده و resume |
| `wordpress_usage_cap_store_integration.php` | 2/0؛ 429، بدون action و transport |
| `wordpress_usage_cap_efb_integration.php` | 2/0؛ 429 و transport صفر |
| `wordpress_usage_page_integration.php` | 3/0؛ empty/unlimited، near، exhausted و reconciliation |
| `wordpress_knowledge_hub_integration.php` | 8/0؛ control resume، copy session-only و contract صفحه |
| `wordpress_phase1_integration.php` | 45/0؛ رگرسیون صفحات و menu |
| PHP lint / PHPCS / PHPStan | PHPCS کامل 127/127 بدون error/warning؛ PHPStan 0 error |
| PHPUnit | 133 test / 439 assertion |
| `node --check tests/browser_release_check.cjs` | سبز |
| browser QA fixture | Usage در محیط واقعی `fa_IR`: desktop، keyboard/focus، قفل کامل controls/Escape هنگام Save، RTL بومی در shell وردپرس، 390px و console/pageerror سبز؛ Knowledge Base نیز در RTL/390px با resume پنهان پیش‌فرض سبز |
| browser persistence fixture | Save cap و Reset با nonce واقعی، reload و cleanup سبز |

اسکرین‌شات‌های بازبینی‌شده در `test-artifacts/aisaautopilot-usage-{desktop,rtl,mobile}.png` هستند. fixture optionها و حساب administrator موقت پس از هر اجرا حذف و مقادیر قبلی بازگردانده شده‌اند.

## محدودیت باقی‌ماندهٔ معتبر

cap قبل از transport بررسی می‌شود، اما token نهایی فقط پس از پاسخ provider مشخص است. بنابراین یک درخواست بزرگ که پیش از رسیدن به cap شروع شده می‌تواند از سقف عبور کند؛ درخواست بعدی متوقف خواهد شد. reservation اتمی یا قیمت provider در افزونه وجود ندارد و UI آن را وعده نمی‌دهد.
