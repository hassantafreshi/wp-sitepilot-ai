# ممیزی و پلن اجرایی Content Studio — A2.7

تاریخ: ۲۰۲۶-۰۹-۰۳

## نتیجهٔ ممیزی

موکاپ یک surface واحد با دقیقاً سه tab دارد: `Write`، `Templates` و `History`. قابلیت Form Composer واقعیِ افزونه باید حفظ شود، اما tab چهارم Content Studio نیست؛ به‌صورت deep link تخصصی در دسترس می‌ماند. جریان فعلیِ امن `plan → review → تأیید صریح → WordPress draft` وجود دارد و نباید به انتشار خودکار یا کنترل نمایشی تبدیل شود.

برآورد اجرای UI جدید همراه با تست‌های هر گام: **۸ تا ۱۲ ساعت کاری**. این برآورد شامل provider خارجی واقعی نیست؛ QA proposal در حالت بدون provider فقط state صادقانه را بررسی می‌کند.

## ایرادهای اولویت صفر که پیش از UI رفع شدند

1. endpoint یک surface می‌توانست proposal متعلق به connector دیگری را پیش از تشخیص اشتباه claim و حذف کند. اکنون `ActionStore::claim()` connector مورد انتظار را **پیش از** ساخت replay-lock یا حذف transient می‌سنجد؛ مسیرهای Content Studio، Form Composer، Easy Form Builder و Store Copilot connector خود را صریح می‌فرستند.
2. Content Studio پیش از فراخوانی provider سقف مصرف را بررسی نمی‌کرد. اکنون `UsageTracker::is_exhausted()` بر اساس token خام (نه درصد گرد‌شدهٔ نمایشی) قرارداد مشترک cap است؛ `POST /content/plan` در حالت cap پرشده `429 / usage_cap_reached` می‌دهد و UI نیز CTA را صادقانه غیرفعال می‌کند.

این دو اصلاح با تست unit، integration واقعی WordPress و فیلتر `pre_http_request` پوشش دارند؛ فیلتر provider را configured نشان می‌دهد اما اثبات می‌کند هنگام cap، تعداد کوشش HTTP دقیقاً صفر است.

## نقشهٔ موکاپ به قرارداد واقعی

| tab موکاپ | عناصر لازم | وضعیت فعلی | تصمیم پیاده‌سازی |
|---|---|---|---|
| `Write` | empty state، brief، نشان Voice، CTA نوشتن، recent drafts | A2.7.2 تکمیل شده است؛ draft-only REST و read model markerدار واقعی دارد | renderer canonical با brief واقعی، preview/approval ایمن و فهرست draftهای markerدار |
| `Templates` | چهار کارت، شمار استفاده، preview مونو و `Edit template` | چهار کارت canonical با brief دقیق موکاپ و hand-off واقعی به Write پیاده شده‌اند؛ persistence/edit template ندارد | کارت‌ها با `brief` امن و editable به Write می‌روند؛ شمار استفاده و editor جعلی ساخته نمی‌شود |
| `History` | تاریخ، what، template/cost، destination/state و Export CSV | A2.7.4 تکمیل شده است؛ کارت canonical فقط post/pageهای markerدار را با زمان ایجاد و مقصد WordPress می‌خواند | ۵۰ row نمایشی و CSV حداکثر ۱۰۰ row با nonce/capability؛ template/usage ثبت‌نشده صریح است و log عمومی وارد نمی‌شود |

## قراردادهای موجود که حفظ می‌شوند

- `POST /content/plan` فقط با `manage_options`، Content Studio فعال، سقف مصرفِ باز و provider پیکربندی‌شده proposal می‌سازد.
- `POST /content/execute` فقط action یک‌بارمصرفِ کاربر همان proposal با connector `content_draft` را claim می‌کند و فقط `post` یا `page` با status `draft` می‌سازد.
- `ContentPlanValidator` عنوان، slug، body امن و meta description را دوباره در سرور بررسی می‌کند.
- `ContentDraftExecutor` روی draft مالکیتی `_aisaautopilot_generated=1` می‌نویسد و رویداد `content.draft_created` را log می‌کند.
- Form Composer و Native Forms حذف نمی‌شوند؛ فقط از تب‌های visible Studio بیرون می‌روند و از deep link قابل‌دسترسی می‌مانند.

## قابلیت‌های افزونه که موکاپ برایشان UI ندارد

| قابلیت | وضعیت واقعی | تصمیم UI/UX |
|---|---|---|
| Form Composer و فهرست Native Forms | route و جریان مستقل واقعی دارند | deep link ثانویه در Write؛ tab چهارم Studio نمی‌شود |
| ویرایش/ذخیرهٔ template و شمارش استفاده | backend persistence ندارد | CTA به Write با prefill می‌رود؛ هیچ editor یا شمار ساختگی نمایش داده نمی‌شود |
| هزینهٔ دلاری یا usage هر draft | price map و attribution per-draft وجود ندارد | History صریحاً `Usage not recorded` نشان می‌دهد؛ USD، token یا cost ساختگی ممنوع است |
| انتشار خودکار | executor فقط draft ایجاد می‌کند | state auto-publish موکاپ قفل/رد می‌شود و approval-first صریح می‌ماند |
| Export CSV | handler مخصوص Studio اکنون وجود دارد | `admin-post` با nonce و `manage_options` فقط schema محدودِ ۱۰۰ row markerدار را صادر می‌کند؛ body/prompt/log/secret صادر نمی‌شود |
| tone «Learned/Formal/Plain» | مدل tone قابل‌تنظیم در backend وجود ندارد | وضعیت learned فقط توضیحی و locked/صادقانه است؛ انتخاب tone به provider ارسال نمی‌شود |

## تقسیم تسک‌های کوچک و گیت پذیرش

| ID | کار کوچک | خروجی | گیت قبل از ادامه |
|---|---|---|---|
| A2.7.0 | قرارداد ایمنی proposal و usage cap | connector-bound claim، `429 usage_cap_reached` و state UI هم‌سو | PHP syntax، PHPUnit **133/439**، integration WordPress **3/0**، PHPStan **0** و PHPCS **124/124** |
| A2.7.1 | canonical shell و IA | سه tab visible، aliasهای `mode=content`/`mode=templates` و History حفظ، Forms deep link | PHP lint، PHPCS، render/assets smoke و URL/alias integration |
| A2.7.2 | Write | empty/blocked/ready، proposal preview و سه recent draft واقعی | read-model/renderer integration، REST mock flow، RTL/390px و visual review تکمیل |
| A2.7.3 | Templates | چهار کارت موکاپ با preview و prefill واقعی Write | تکمیل: integration **5/0**، keyboard، RTL/390px، browser **exit 0** و visual review |
| A2.7.4 | History | rows واقعی، destination/state، مقدار usage صادقانه و export contract | تکمیل: read-model **3/0**، export **3/0**، renderer **5/0**، nonce/capability، fixture cleanup و بدون هزینهٔ جعلی |
| A2.7.5 | QA نهایی | desktop، 390px، RTL واقعی `fa_IR`، visual screenshot | تکمیل: browser **exit 0**، download با keyboard، console/pageerror صفر و بازبینی مستقیم screenshot |

## اختلاف‌های عمدی با موکاپ

- CTA موکاپ برای `Edit template` editor مشخصی ندارد؛ این نسخه به Write با prefill می‌رود.
- موکاپ نمونه‌های هزینهٔ دلاری و متن «Everything written, what it cost…» دارد؛ افزونه attribution هزینه ندارد و برای صداقت، کارت نهایی `Recent saved output, usage when recorded, and its WordPress destination` را نمایش می‌دهد. هر row حداکثر با `Template not recorded` و `Usage not recorded` برچسب می‌خورد.
- موکاپ برای History سقف داده را بیان نمی‌کند؛ UI فقط ۵۰ خروجی اخیر و CSV حداکثر ۱۰۰ خروجی markerدار را می‌خواند تا query/export bounded بماند. این محدودیت در UI با واژهٔ `Recent` و در CSV schema مستند است.
- موکاپ state auto-publish را نمایش می‌دهد؛ افزونه draft-only است، بنابراین آن state قابلیت عملیاتی نخواهد داشت.
- RTL با CSS logical و locale واقعی WordPress (`fa_IR`) سنجیده می‌شود، نه با copy‌کردن `margin-left` موکاپ.

## نتیجهٔ A2.7.0 — قرارداد ایمنی

- تست واحد `ActionStoreTest` اکنون ثابت می‌کند connector اشتباه نه proposal را پاک می‌کند و نه replay lock می‌سازد؛ اجرای `ContentDraftExecutor` با proposal `native_form` نیز `invalid_action` برمی‌گرداند و proposal برای surface اصلی باقی می‌ماند.
- تست محلی `wordpress_content_studio_contract_integration.php` با **۳/۰** نتیجهٔ زیر را روی WordPress واقعی ثبت کرد: proposal اشتباه حفظ می‌شود؛ با provider ظاهراً configured و cap پرشده، پاسخ `429 / usage_cap_reached` پیش از هر `pre_http_request` برمی‌گردد؛ و UI دکمهٔ نوشتن را disabled کرده و به Usage لینک می‌دهد.
- regression پس از اصلاح: PHP syntax سبز، PHPUnit **133 tests / 439 assertions**، `phase1_smoke.php` **8/0**، PHPStan **0 error** و PHPCS **124/124** بدون error/warning.

## نتیجهٔ A2.7.1 — shell و IA canonical

- URLهای canonical Studio اکنون روی یک route واحد هستند: `?page=aisaautopilot-form-builder&tab=write|templates|history`. header دقیقاً سه tab `Write`، `Templates` و `History` را به همان ترتیب موکاپ می‌سازد.
- bookmarkهای قدیمی `mode=content`، `mode=templates` و alias `aisaautopilot-history` همچنان به surface متناظر می‌رسند. `mode=forms` نیز Form Composer را نگه می‌دارد، اما دیگر tab چهارم Studio نیست و در header مستقل خودش render می‌شود.
- assetها بر اساس view واقعی enqueue می‌شوند: Write/Templates فقط `content-studio.js`، History هیچ script مخصوص view نمی‌گیرد (فقط shell مشترک) و Form Composer فقط `form-builder.js`. در نتیجه اسکریپتِ صفحهٔ دیگر به surface اشتباه تزریق نمی‌شود.
- CSS shell برای سه tab در ۳۹۰px grid منطقی و برای RTL با `inline-size`/`text-align:end` محافظت شد.
- شواهد: `wordpress_content_studio_navigation_integration.php` **5/0**، asset smoke **19/0**، render smoke بدون error، browser QA با `fa_IR` واقعی در desktop/RTL/390px و `exit 0`، PHPUnit **133/439**، Phase-1 **45/0**، PHPStan **0** و PHPCS **124/124**.

## نتیجهٔ A2.7.2 — Write داده‌محور و draft-only

- `ContentStudioReadModel` فقط `post` و `page` دارای marker مالکیتی `_aisaautopilot_generated=1` را می‌خواند؛ status، نوع، زمان و مقصد WordPress از دادهٔ واقعی می‌آیند و هیچ draft معمولی یا WooCommerce وارد فهرست نمی‌شود. surface Write دقیقاً سه ردیف اخیر را نشان می‌دهد.
- UI موکاپی Write اکنون به‌ترتیب شامل empty-state dashed با عرض ۵۲۰px، composer با عرض ۷۲۰px و ورودی ۷۸px، سه chip غیرعملیاتی Voice (`Learned from your site`، `Formal` و `Plain`)، CTA `Write a draft` و کارت `Recent drafts` است. دادهٔ نمونه یا state جعلی در فهرست نمایش داده نمی‌شود.
- چرخهٔ واقعی و ایمن `plan → preview → تأیید صریح → WordPress draft` حفظ شد. preview و approval در موکاپ وجود ندارد، اما عمداً به‌عنوان اختلاف ایمنی باقی مانده‌اند؛ هیچ کنترل publish/autopublish اضافه نشده است. پاسخ `usage_cap_reached` اکنون composer را در مرورگر هم قفل می‌کند تا CTA قدیمی دوباره فعال نشود.
- تست‌های محلی: read-model **2/0**، renderer Write **3/0**، قرارداد cap/connector **3/0**، navigation **5/0**، asset smoke **19/0**، render smoke **18 route**، `phase1_smoke.php` **8/0** و `wordpress_phase1_integration.php` **45/0** همگی پاس شدند. PHPStan **0 error**، PHPCS کامل **125/125** و PHPUnit **133 test / 439 assertion** هستند.
- QA مرورگر با کاربر موقت `fa_IR` و `exit 0` انجام شد: درخواست‌های `plan/execute` برای تست mock شدند، پس provider یا draft واقعی مصرف/ایجاد نشد؛ پیش‌نمایش قبل از تأیید row اضافه نکرد، تأیید صریح row و لینک مقصد را افزود، پاسخ cap هر دو کنترل composer را قفل کرد، desktop/RTL/390px overflow نداشت و console/pageerror صفر بود. screenshotهای desktop و ۳۹۰px نیز مستقیماً بازبینی شدند.

## نتیجهٔ A2.7.3 — Templates با hand-off واقعی

- دقیقاً چهار starter موکاپی `Product description`، `Blog post outline`، `Meta description` و `Support answer` با متن‌های preset دقیق، preview مونو و grid منطقی حداقل ۳۲۰px رندر می‌شوند. در ۳۹۰px به یک ستون تبدیل می‌شوند و overflow افقی ندارند.
- هر `Use template` یک لینک واقعی و قابل‌استفاده با keyboard است که به URL canonical `tab=write` می‌رود و brief را به textarea قابل‌ویرایش Write منتقل می‌کند. مقدار query پیش از `esc_url()` percent-encode می‌شود؛ بنابراین placeholderهایی مانند `{product}` در WordPress و مرورگر حذف نمی‌شوند. این hand-off هیچ `plan` یا `execute` خودکاری انجام نمی‌دهد.
- اختلاف عمدی و ثبت‌شده با موکاپ: چون افزونه persistence، editor و attribution قابل‌اتکا برای template ندارد، به‌جای شمار ساختگی `Used N times` برچسب صادقانهٔ `Built-in` و به‌جای `Edit template`، CTA عملیاتی `Use template` نمایش داده می‌شود.
- شواهد: `wordpress_content_studio_templates_integration.php` **5/0** (شامل escaped URL، brace placeholder و sanitization/۶۰۰۰ کاراکتر)، `node --check` سبز، browser QA با کاربر موقت `fa_IR` در desktop/RTL/390px با **exit 0**، keyboard hand-off و صفر REST خودکار؛ screenshotهای Templates در desktop و mobile مستقیماً بازبینی شدند. رگرسیون کامل: PHPStan **0 error**، PHPCS **125/125**، PHPUnit **133/439**، navigation **5/0**، read-model **2/0**، Write **3/0**، contract **3/0**، assets **19/0**، render smoke **18 route**، Phase-1 **8/0** و WordPress Phase-1 **45/0**. کاربر QA موقت و fixtureهای تست پاک شدند.

## نتیجهٔ A2.7.4 — History واقعی و CSV محدود/امن

- `HistoryPage` دیگر Activity Log، فرم‌ها یا proposalهای کوتاه‌عمر را مخلوط نمی‌کند. `ContentStudioReadModel::history()` فقط `post/page`های دارای `_aisaautopilot_generated=1` را بر پایهٔ **زمان ایجاد** می‌خواند؛ post معمولی و نوع‌های دیگر وارد نمی‌شوند. draftهای تازه‌ای که `post_date_gmt` صفر دارند نیز با تاریخ محلی WordPress timestamp صحیح می‌گیرند.
- کارت History ساختار موکاپ (header، زمان ۹۲px، what، metadata و state) را دارد، با layout logical و wrap موبایل. عنوان به مقصد WordPress واقعی لینک می‌شود و state/kind از post واقعی می‌آید. template، usage و قیمت حدس زده نمی‌شود.
- `ContentStudioHistoryExport` یک endpoint مستقل `admin-post` با nonce action-specific و `manage_options` است. CSV فقط ستون‌های `created_at,title,destination,state,template,usage,edit_url` و حداکثر ۱۰۰ row markerدار دارد؛ prompt/body، credential و Activity Log عمومی خارج‌اند. cellهای آغازشونده با `= + - @` برای جلوگیری از spreadsheet formula injection خنثی می‌شوند.
- شواهد: read-model **3/0**، export **3/0** (schema، marker-only و formula guard)، renderer **5/0** (empty state)، navigation **5/0**، assets **19/0**، render smoke **18 route** و Phase‑1 **45/0**. fixtureهای WordPress در `finally` و fixture/browser user موقت در `finally` پاک شدند.

## نتیجهٔ A2.7.5 — QA نهایی Content Studio

- browser QA واقعی با locale `fa_IR` و **exit 0** هر سه tab را پوشش داد: Write plan/preview/approve/cap، Templates keyboard prefill بدون REST خودکار، و History marker-only/download CSV با Enter و صفر REST. desktop، RTL واقعی و viewport ‌۳۹۰px هیچ overflow، console error یا pageerror نداشتند.
- screenshotهای Write، Templates و History در desktop/mobile/RTL مستقیماً بازبینی شدند. پس از آخرین تغییر timestamp و copy صادقانهٔ History نیز browser QA و visual review دوباره اجرا شدند.
- رگرسیون نهایی: PHPStan **0 error**، PHPCS **126/126**، PHPUnit **133 tests / 439 assertions**، Node syntax سبز، contract **3/0**، navigation **5/0**، Write **3/0**، Templates **5/0**، History read/export/page **3/0 / 3/0 / 5/0**، assets **19/0**، render smoke **18 route**، `phase1_smoke.php` **8/0** و `wordpress_phase1_integration.php` **45/0**.
