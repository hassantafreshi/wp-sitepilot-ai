# گزارش دسترس‌پذیری، RTL و responsive — T17 (Q4.3)

تاریخ: ۲۰۲۶-۰۹-۱۹  
شواهد: `C:\files\projects\SitePilot AI\test-artifacts\admin-a11y\20260919-t17\`، `…\front-chat-widget\20260919-t17\`، `…\efb-copilot\20260919-t17\`

## نتیجه

**گیت T17 سبز است: نقص باز صفر.** هر نقصی که آزمون‌ها یا بازبینی تصویری پیدا کرد در همین task رفع شد و یک سنجهٔ دائمی گرفت؛ هیچ نقصی به task تازه واگذار نشد.

| سطح | آزمون | نتیجهٔ نهایی |
|---|---|---|
| همهٔ ۲۶ route مدیریتی | `tests/admin_a11y_browser_matrix.cjs` (تازه) در `en_US` و `fa_IR` × 1440، 1024 و 390 | **1522 PASS / 0 FAIL** (هر locale 761)؛ اجرای نخست 25 FAIL در هر locale |
| ویجت بازدیدکننده | `tests/front_chat_widget_browser_matrix.cjs` (+۴ سنجه در هر locale) | **437 PASS / 0 FAIL** (en_US 219، fa_IR 218؛ پیش از T17: 429) |
| EFB Copilot | `tests/efb_copilot_browser_matrix.cjs` (+۲ سنجهٔ جهت متن در هر locale) | **156 PASS / 0 FAIL** (پیش از T17: 152) |

## ۱. آنچه سنجیده شد

`tests/admin_a11y_browser_check.cjs` برای هر route و هر عرض این‌ها را می‌سنجد و هر شکست را با جزئیات چاپ می‌کند:

| معیار | روش | مرجع |
|---|---|---|
| reflow و overflow | `scrollWidth` سند در 1440، 1024 و 390؛ هیچ کنترل یا متن مرئی بیرون از viewport مگر درون یک scroller | WCAG 1.4.10 |
| متن بریده | عنصری که `overflow` را پنهان می‌کند و متنش جا نمی‌شود: بی‌ellipsis ممنوع، با ellipsis فقط اگر `title` کل مقدار را داشته باشد | WCAG 1.4.10، یافتهٔ F15-3 |
| تراز RTL سربرگ | متن عنوان صفحه روی لبهٔ آغاز ردیف سربرگ (در RTL لبهٔ راست) | یافتهٔ F15-1 |
| نام دسترس‌پذیر | هر `button`، `a[href]`، `role=button/tab/switch`، `summary` مرئی: `aria-labelledby`، `aria-label`، label، متن، `alt` یا `title` | WCAG 4.1.2 |
| برچسب فیلد | هر `input`، `select` و `textarea` مرئی | WCAG 1.3.1، 3.3.2 |
| کنتراست | هر گرهٔ متنی مرئی `.sp-app`: رنگ متن با opacity اجدادش روی پس‌زمینهٔ ترکیب‌شدهٔ لایه‌ها؛ 4.5:1 و برای متن بزرگ (24px یا 18.66px bold) 3:1؛ کنترل `:disabled` معاف؛ متن روی gradient یا تصویر شمرده و گزارش می‌شود | WCAG 1.4.3 |
| keyboard و focus | از نخستین کنترل صفحه تا ۷۰ توقف Tab: هیچ توقفی روی عنصر نامرئی نیست و هر توقف نشانگر دارد (outline، box-shadow، border، پس‌زمینه یا رنگ نسبت به حالت بی‌focus، پس از پایان transition؛ برای کنترل پنهان مثل radio روی label یا wrapper) | WCAG 2.1.1، 2.4.7 |
| اندازهٔ هدف | در 390: WCAG 2.2 AA 2.5.8 کامل، یعنی 24×24 یا استثنای فاصله (دایرهٔ 24px دور هدف کوچک با هیچ هدف دیگری تلاقی نکند)؛ پیوند درون جمله معاف | WCAG 2.5.8 |
| حرکت کمتر | context با `reducedMotion: 'reduce'`: هیچ انیمیشن یا transition بلندتر از 1ms در `.sp-app` | WCAG 2.3.3 |
| dialogها | Integrations (native `<dialog>`)، confirm مشترک `AISAAutoPilotShell.confirm` (reset مصرف) و کشوی صندوق انتقال: باز شدن با Enter، رفتن focus به درون، بسته شدن با Escape، بازگشت focus به آغازگر؛ confirm ردشده هیچ درخواستی نمی‌فرستد | WCAG 2.1.2، 2.4.3 |
| RTL | `direction` محاسبه‌شده (نه صفت `dir`) برابر زبان مدیر | — |

کشوی صندوق انتقال روی سایت محلی خالی است و این check آن را با گفت‌وگوی واقعی نمی‌سنجد؛ همان رفتار با fixture در `tests/handoff_inbox_browser_matrix.cjs` (بخش Handoff inbox در `browser_release_check.cjs`) سنجیده می‌شود. dialogهای دیگر (ویرایشگر سقف مصرف، picker دانش سایت، حذف گفت‌وگوی کنسول) هم در `browser_release_check.cjs` Escape و بازگشت focus دارند.

## ۲. نقص‌های پیدا و رفع‌شده

| ID | نقص | اثر | رفع | سنجهٔ دائمی |
|---|---|---|---|---|
| A17-1 | **هیچ فیلد متنی مدیریت نشانگر focus نداشت.** `.sp-app input[type="text"].sp-input` با specificity (0,3,1) border و `box-shadow: none` را بر `.sp-app .sp-input:focus` (0,3,0) تحمیل می‌کرد، و `outline: none` همان قاعده outline عمومی `:focus-visible` را هم برمی‌داشت | کاربر keyboard نمی‌دید کدام فیلد focus دارد؛ روی همهٔ صفحه‌ها، از Setup تا سفارشی‌سازی ویجت | قاعدهٔ focus با کلاس تکراری (`.sp-app .sp-input.sp-input:focus`، همچنین select و textarea) | focus audit در هر route |
| A17-2 | **کنتراست متن کم‌رنگ.** token `--sp-faint` برابر `#9ca3af` (2.54:1) و رنگ‌های سخت‌کد `#9a9dbb`، `#9a9db8`، `#a9abc4` و `#7e81a3` (2.26 تا 3.78:1) در Overview، Advanced settings، Human handoff، سفارشی‌سازی ویجت، Content Studio › Write و Form Studio؛ و `opacity: .55` روی فیلدهای جست‌وجوی معنایی خاموش ولی قابل ویرایش (2.39 تا 2.62:1) | متن کمکی، برچسب قفل، «Drop a PNG»، راهنمای composer و یادداشت ایمنی Form Studio برای کم‌بینا ناخوانا بود | `--sp-faint: #686b8a` (4.95:1، برابر token ویجت در T13)، همهٔ رنگ‌های سخت‌کد به token، و `is-muted` به‌جای opacity پس‌زمینهٔ فیلد را رنگ می‌کند | contrast audit در هر route |
| A17-3 | **switchهای Store Copilot › Capabilities نام نداشتند** (`role="switch"` بدون label) | صفحه‌خوان فقط «switch، روشن» می‌گفت | `aria-labelledby` به عنوان و `aria-describedby` به یادداشت کنار آن؛ رشتهٔ تازه‌ای لازم نشد | name audit |
| A17-4 = F15-1 | **عنوان صفحه در RTL از لبهٔ راست جدا بود** روی Store Copilot، Answer Desk، Content Studio، Health و Usage & Cost: `html[dir="rtl"] … .sp-pagebar__titles { text-align: end }`، که در RTL یعنی چپ | عنوان و زیرعنوان وسط‌چپ سربرگ می‌نشستند؛ در Health و Usage به‌خاطر عنوان کوتاه کمتر دیده می‌شد | پنج قاعده حذف شدند؛ `start` پیش‌فرض | titleGap در هر route و هر عرض |
| A17-5 = F15-3 | **مقدارهای «اتصال» در Advanced settings با ellipsis بریده می‌شدند**؛ در RTL ابتدای مقدار لاتین حذف می‌شد («…ordPress Connectors») و مدل یا endpoint بلند در هر زبانی ناقص بود. همین برای نام رنگ «Messages background» در سفارشی‌سازی ویجت | اطلاعات پنهان، بدون راه خواندن کامل | `overflow-wrap: anywhere` به‌جای `nowrap` + ellipsis | truncation audit |
| A17-6 = F15-2 | **پیش‌نمایش Answer Desk متن مدیر را با جهت صفحه رندر می‌کرد**: متن انگلیسی ذخیره‌شده در RTL «?» را به ابتدای جمله می‌برد | پیش‌نمایش با ویجت واقعی (که `dir="auto"` دارد) فرق داشت | `dir="auto"` روی عنوان، زیرعنوان، خوش‌آمد و پرسش پیشنهادی؛ تراز از جهت ریشهٔ پیش‌نمایش، مثل ویجت | بازبینی تصویری |
| A17-7 | **متن پاسخ و پیام کاربر در EFB Copilot `dir` نداشت** (یافتهٔ باز از T04): پاسخ انگلیسی در ویرایشگر فارسی با جهت RTL رندر می‌شد | نشانه‌گذاری و ترتیب کلمات متن لاتین جابه‌جا می‌شد | `dir="auto"` فقط روی گره‌های متن آزاد (`addUser`، `addAi` رشته‌ای، `renderMessage`، `renderPreview`)، نه روی ظرف‌های دکمه و کارت | ۲ سنجه در `efb_copilot_browser_check.cjs` |
| A17-8 | **ویجت: دکمهٔ «آخرین پیام» روی فیلد focusدار کارت انتقال می‌افتاد** (از T13) | فیلد focusدار زیر یک دکمهٔ شناور می‌ماند | `scroll-padding-block-end: 62px` روی thread، پس scroll ناشی از focus فیلد را بالای دکمه نگه می‌دارد | سنجهٔ رفتاری با ردیف‌های پرکننده در هر اجرای main |
| A17-9 | **ویجت: هدف انگشت در sheet موبایل.** chip حدود 32px، دکمه‌های کارت انتقال حدود 30px و پیوند انتقال پانویس 15px | روی گوشی سخت به‌دست می‌آمدند | در ≤640px: `min-block-size: 44px` برای chip و دو دکمهٔ کارت؛ پیوند پانویس `inline-block` با padding رو به پایین (نه رو به composer). دسکتاپ اندازهٔ موکاپ را نگه می‌دارد | ۲ سنجهٔ hit target در 390 |
| A17-10 | متن «Off» ردیف‌های «آنچه روشن است» در Overview رنگ inline `#9a9dbb` داشت (2.65:1) | همان A17-2 | `#686b8a` | contrast audit |

**نقص بیرون‌آمده از خود اصلاح، و رفع آن:** padding پیوند پانویس نخست با margin منفی رو به پایین لغو می‌شد؛ محتوای بیرون‌زده از sheet اجازه می‌داد scroll ناشی از focus کل sheet را چند پیکسل بالا ببرد و دکمه‌های سربرگ در 390 از 44 به 41 می‌رسیدند. matrix ویجت آن را گرفت؛ padding اکنون درون layout است و sheet حدود 26px در پایین خود فضا می‌گیرد.

## ۳. اختلاف‌های آگاهانه و مرزها

- **هدف 44px ویجت فقط در ≤640px** برای متن‌ها؛ دکمه‌های آیکونی در هر عرض 44px‌اند. در دسکتاپ chipها 32px موکاپ را نگه می‌دارند، چون اشاره‌گر دقیق است و WCAG 2.5.8 (24px) برقرار است.
- **مدیریت با WCAG 2.2 AA 2.5.8 سنجیده می‌شود** (24px یا فاصله)، نه 44px، چون سطح مدیریت ابزار دسکتاپ است؛ همهٔ routeها در 390 سبزند.
- **متن روی gradient** (آیکون سرتیتر دستیار) شمرده و گزارش می‌شود و محاسبه نمی‌شود؛ در این اجرا صفر مورد بود.
- **ترجمه:** «Embeddings» که T56 برای T17 گذاشته بود در T18 به «بردارهای معنایی (Embedding)» رسید و در Health راست‌چین است.

## ۴. فایل‌های تغییریافته

| فایل | تغییر |
|---|---|
| `assets/admin/aisaautopilot-ui.css` | A17-1، A17-2، A17-4، A17-5، A17-6 (تراز پیش‌نمایش) |
| `includes/Admin/StoreCopilotPage.php` | A17-3 |
| `includes/Admin/HomePage.php` | A17-10 |
| `includes/Admin/AnswerConversationsPage.php` | A17-6 |
| `assets/admin/efb-copilot.js` | A17-7 |
| `assets/frontend/widget.css` | A17-8، A17-9 |
| `tests/admin_a11y_browser_check.cjs`، `tests/admin_a11y_browser_matrix.cjs` | تازه |
| `tests/front_chat_widget_browser_check.cjs` | +۴ سنجه (hit target متن و کارت، فیلد زیر دکمهٔ آخرین پیام) |
| `tests/efb_copilot_browser_check.cjs` | +۲ سنجه (جهت پاسخ و پیام کاربر) |

## ۵. شواهد

| اجرا | نتیجه | فایل |
|---|---|---|
| a11y مدیریت، اجرای ۱ (پیش از رفع) | en_US 25 FAIL، fa_IR 25 FAIL | `admin-a11y/20260919-t17/run1.txt` |
| a11y مدیریت، اجرای ۲ | en_US 1 FAIL (برچسب رنگ بریده)؛ fa_IR همهٔ routeها سبز ولی حلقهٔ reduced-motion با timeout سرور محلی شکست، چون retry نداشت (در check اضافه شد) | `…/run2.txt` |
| a11y مدیریت، اجرای ۳ | **1522 PASS / 0 FAIL**، `MATRIX RESULT PASS` | `…/run3.txt`، تصویرها در `…/run3/<locale>/` |
| ویجت، اجرای ۱ | 6 FAIL در 390: دکمه‌های سربرگ 44×41 و پیوند پانویس 43px (نقص خود اصلاح اول، بالا)، و دکمه‌های کارت 0×0 (نقص check: کارت پیش از سنجش به دید نیامده بود؛ اکنون `scrollIntoView` می‌شود) | `front-chat-widget/20260919-t17/matrix-run1.txt` |
| ویجت، اجرای ۲ | **437 PASS / 0 FAIL**، `MATRIX RESULT PASS`؛ fixture پس از هر سناریو و در `finally` برداشته شد | `…/matrix-run2.txt`، تصویرها در `test-artifacts/front-chat-widget/t17/<locale>/` |
| EFB Copilot | **156 PASS / 0 FAIL**؛ در fa_IR پاسخ انگلیسی `computed: ltr` | `efb-copilot/20260919-t17/matrix.txt` |
| integrationهای مرتبط | ۱۱ suite سبز: widget render 97/0، Store Copilot page 6/0 و settings 7/0، Answer conversations 5/0، Answer widget page 4/0، Advanced settings 11/0، Health 3/0، Usage 3/0، widget customise 12/0، Overview states 4/0، EFB contract 15/0 | `admin-a11y/20260919-t17/related-integrations.txt` |
| smoke | **8/8**: php-lint 234/234، node-check 45/45، PHPCS 134/134 با 0/0، PHPStan 0، PHPUnit 155 / 544، menu 5/0، assets 20/0، render 18/0 | `test-artifacts/gates/20260919-031013-smoke` |
| پاک‌سازی | preflight پس از browser و در پایان: `settings_stored=False`، WARNINGS 0، BLOCKERS 0 | `admin-a11y/20260919-t17/preflight-final.txt` |

**یک اجرای بی‌اعتبار، افشا:** نخستین اجرای تشخیصی a11y پیش از هر سنجه گیر کرد، چون `open()` منتظر پایان همهٔ انیمیشن‌ها بود و انیمیشن‌های بی‌پایان (spinner، نقطهٔ پالس) هرگز تمام نمی‌شوند. همان پردازه با PID متوقف و حساب QA با `local_browser_user.php delete` حذف شد؛ انتظار اکنون فقط انیمیشن‌های متناهی را با سقف ۲ ثانیه می‌پاید. در همان مسیر check دو خطای خودش را هم رفع کرد: سبک focus را پیش از پایان transition می‌خواند، و radio یا file ورودی شفافی را که روی label خوابیده «نامرئی» می‌شمرد.
