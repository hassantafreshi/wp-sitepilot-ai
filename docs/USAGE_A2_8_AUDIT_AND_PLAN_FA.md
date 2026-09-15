# ممیزی و برنامهٔ اجرای A2.8 — Usage & Cost

تاریخ: 2026-09-05  
مبنای بصری: `mockups/design_handoff_admin_redesign/AutoPilot Admin.dc.html`، بخش Usage در خطوط 1275 تا 1329 و stateهای آن در 2536 تا 2569.

## حکم فنی

موکاپ از «هزینهٔ دلاری»، سقف دلاری، چهار feature و free tier مشخص حرف می‌زند، اما افزونه فقط tokenهای پاسخ provider را محلی ثبت می‌کند. نه price map نسخه‌دار دارد، نه billing API، نه quota قابل‌اعتماد provider و نه attribution تاریخی به Answer Desk/Store/Content Studio. بنابراین UI جدید باید همان ساختار دوکارت، meter، ویرایش cap، نمودار ۷روزه و breakdown موکاپ را نگه دارد، اما **token-first** باشد و قیمت، quota یا ایمیل ساختگی نمایش ندهد.

| بخش موکاپ | قرارداد قابل‌اعتماد افزونه |
|---|---|
| `$6.40 spent` | `12,480 tokens used`؛ مبلغ provider محلی قابل‌محاسبه نیست |
| `$25 monthly cap` | `20,000-token monthly cap`؛ صفر یعنی unlimited |
| «At cap every AI feature pauses and emails» | generation متنی و embedding که از `ProviderManager::make()` می‌گذرند، پیش از transport متوقف می‌شوند؛ ایمیل و voice خارج از این cap هستند |
| `Answer Desk / Store / Content Studio / Site Knowledge` dollar bars | operationهای ثبت‌شدهٔ واقعی (`text_generation`، `structured_output`، `embedding`)؛ attribution featureهای قدیمی جعل نمی‌شود |
| Gemini free-tier count | نمایش داده نمی‌شود؛ provider quota محلی و قابل‌اعتماد نیست |
| هزینهٔ provider | footnote صادقانه: billing و قیمت provider جداگانه است و در افزونه موجود نیست |

## A2.8.0 — قرارداد داده و اجرای واقعی cap (تکمیل)

### A2.8.0a — دورهٔ ماهانه و گزارش واقعی

- `UsageTracker` اکنون پیش از هر read/write، دورهٔ `YYYY-MM` را با timezone سایت بررسی می‌کند؛ rollover تنبل است و به WP-Cron وابسته نیست.
- totals دورهٔ جاری هستند، نه cumulative. دادهٔ legacy بدون period به `historical` منتقل می‌شود و هرگز به ماه جاری نسبت داده نمی‌شود.
- rollover یک snapshot حداکثر ۱۲تایی از totals بسته‌شده نگه می‌دارد. ring buffer حداکثر ۵۰۰ رخداد، برای نمودار حقیقی هفت‌روزه در مرز ماه نگه داشته می‌شود.
- هر رخداد جدید period دارد. API قدیمی `by_context` و `by_model` را حفظ می‌کند؛ کلید additive `period_by_context` و هفت bucket مرتب `last_7_days` برای UI جدید اضافه شده‌اند.
- reset فقط دورهٔ جاری را پاک می‌کند، cap را نگه می‌دارد و رخدادهای ماه قبل را برای نمودار حذف نمی‌کند.

### A2.8.0b — گیت مرکزی transport

- `UsageCappedProvider` تمام providerهای عملیاتی حاصل از `ProviderManager::make()` را wrap می‌کند.
- `generate_structured_output`، `generate_text` و `generate_embedding` در cap، `ProviderException('usage_cap_reached')` می‌دهند و هیچ `wp_remote_*` اجرا نمی‌شود.
- `ProviderManager::build()` عمداً raw باقی مانده تا test connection و model discovery در Settings قابل‌تعمیر باشند.
- این گیت به‌صورت مرکزی Content Studio، Form Composer، EFB، Chat، KB، Vector Search، Store و skillهای متکی به `make()` را می‌پوشاند. Voice STT/TTS transport جدا و usage ثانیه‌ای جدا دارد؛ به token cap نسبت داده نشده است.

### A2.8.0c — پاسخ قابل‌استفاده و صادقانه

- `usage_cap_reached` اکنون status HTTP `429` و پیام actionable دارد.
- Form Composer، KB chat، Admin Chat، Widget و Public Chat این وضعیت را بدون ساختن پاسخ AI یا تماس خارجی برمی‌گردانند.
- fallback JSON→plain text در ChatEngine برای خطاهای غیر-cap حفظ شده است؛ فقط cap از fallback دوم عبور نمی‌کند.
- copy Overview/connection chip از ادعای «همهٔ featureها» به generation متنی/embedding اصلاح شد.

### شواهد A2.8.0

| گیت | نتیجه |
|---|---|
| `wordpress_usage_tracker_report_integration.php` | 6/0: legacy، rollover، archive، ۷ روز، reset و دادهٔ خراب |
| `wordpress_usage_cap_provider_integration.php` | 3/0: structured/text/embedding با HTTP صفر؛ unlimited و Settings raw |
| `wordpress_usage_cap_response_integration.php` | 6/0: Form، KB، Admin Chat، Widget، Public Chat و HTTP صفر |
| regression Content Studio | 3/0 |
| regression Overview | 4/0 |
| PHPUnit | 133 tests / 439 assertions |
| PHPStan | 0 error |
| PHPCS | 127/127 بدون error/warning (پیش از فایل test response؛ اجرای کامل نهایی A2.8 دوباره تکرار می‌شود) |

## برنامهٔ ریز و وضعیت نهایی

| ID | کار کوچک | گیت پذیرش |
|---|---|---|
| A2.8.1 | ساخت read model صفحه: stateهای `empty`، `unlimited`، `normal`، `near` و `exhausted`؛ labelهای operation حقیقی، ۷ day buckets و copy cost صادقانه | test WordPress برای totals/state/markup، PHPStan و PHPCS |
| A2.8.2 | بازسازی `UsagePage` به دو card دقیق موکاپ: token meter/chart و `Where it went`؛ حذف tile/By Model/Budget card قدیمی از surface | render contract و screenshot desktop |
| A2.8.3 | CSS scoped: grid حداقل 320px، meter، bar، chart، logical RTL و mobile | render smoke، RTL و 390px overflow check |
| A2.8.4 | پنل inline `Set a token cap` با range قابل‌دسترس، Save/Cancel، REST nonce و reset با copy دقیق | mouse/keyboard، persistence، no-provider-request |
| A2.8.5 | browser fixture و visual QA همهٔ stateهای واقعی | desktop، RTL، 390px، console/pageerror، screenshot review |

همهٔ ردیف‌های A2.8.1 تا A2.8.5 تکمیل و با تست محلی وردپرس، تست مرورگر و بازبینی screenshot بسته شده‌اند. بازبینی نهایی A2.8.6 نیز این موارد را بست: اعتبارسنجی strict سقف REST، نرمال‌سازی storage خراب، حفاظت period آینده، قرارداد 429 در KB/Store/EFB، cursor session-only برای KB و قفل واقعی UI Usage هنگام درخواست Save.

شواهد نهایی: PHPCS کامل 127/127 بدون error/warning، PHPStan صفر error، PHPUnit 133 test / 439 assertion، تست‌های WordPress Usage/KB/Store/EFB همگی سبز، و browser QA واقعی `fa_IR` برای Usage و Knowledge Base در desktop، RTL بومی و 390px بدون console/page error سبز است. جزئیات قابل‌بازتولید در `USAGE_A2_8_EXECUTION_REPORT_FA.md` ثبت شده‌اند.

## قابلیت‌های افزونه که موکاپ UI مستقلی برایشان ندارد

| قابلیت حفظ‌شده | تصمیم UI/UX |
|---|---|
| log مدل (`by_model`) و ۲۰ رخداد اخیر API | compatibility/API باقی می‌ماند؛ موکاپ سطحی برای جدول مدل یا log ندارد |
| archive حداکثر ۱۲ دوره و legacy historical totals | برای سلامت migration نگه داشته می‌شود؛ موکاپ history ماهانه‌ای طراحی نکرده است |
| reset دستی usage | در پنل cap با تأیید واضح می‌ماند؛ موکاپ فقط Save/Cancel cap نشان می‌دهد |
| VoiceUsageTracker (seconds) | حفظ می‌شود ولی به token یا قیمت تبدیل نمی‌شود؛ UI موکاپ مدل هزینهٔ صوتی واقعی ندارد |
| diagnostics و model discovery | خارج از cap generation باقی می‌ماند تا provider قابل‌تعمیر باشد |

## محدودیت باقی‌ماندهٔ cap

گیت قبل از هر درخواست عمل می‌کند، اما token نهایی فقط بعد از پاسخ provider معلوم می‌شود. بنابراین یک درخواست بزرگ که پیش از رسیدن به cap شروع شده می‌تواند cap را رد کند؛ درخواست بعدی متوقف می‌شود. reservation/estimate اتمیک و قیمت واقعی provider در این افزونه وجود ندارد و نباید در UI به‌صورت سقف هزینهٔ قطعی معرفی شود.
