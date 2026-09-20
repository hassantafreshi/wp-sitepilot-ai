# مستندات فنی AI Site Assistant AutoPilot

این پوشه مرجع فنی افزونه است. مخاطبش توسعه‌دهنده‌ها، بازبین‌ها و agentهای همین پروژه‌اند و محتوایش ساختار کد، سطح‌های مدیریت، API، دادهٔ ذخیره‌شده، امنیت، تست و انتشار است. همهٔ ادعاها از کد نسخهٔ 1.0.4 خوانده شده‌اند. اگر کد و این سند اختلاف داشتند، کد درست است و سند باید در همان تغییر به‌روز شود.

## مشخصات کلیدی

| مورد | مقدار |
|---|---|
| نام و فایل اصلی | AI Site Assistant AutoPilot، [`ai-site-assistant-autopilot.php`](../../ai-site-assistant-autopilot.php) |
| نسخه | 1.0.4 (`AISAAUTOPILOT_VERSION`) |
| حداقل محیط | WordPress 6.2، PHP 8.1 (بررسی در `Activator`) |
| namespace | `AISAAutoPilot\` با autoloader داخل فایل اصلی (بدون Composer در زمان اجرا) |
| text domain | `ai-site-assistant-autopilot`، MO فارسی داخل `languages/` |
| REST | فضای `aisaautopilot/v1`، 60 جفت مسیر و متد، 7 مسیر عمومی |
| منوی مدیریت | 10 آیتم قابل‌مشاهده و 8 alias پنهان |
| جدول‌ها | `aisaautopilot_kb`، `aisaautopilot_chats`، `aisaautopilot_widget_chats`، `aisaautopilot_handoffs` |
| ابزار توسعه | PHPUnit، PHP_CodeSniffer با WPCS، PHPStan (سطح پیکربندی در `phpstan.neon`)، Playwright Core برای مرورگر |

## فهرست اسناد

| سند | موضوع |
|---|---|
| [ARCHITECTURE_FA.md](ARCHITECTURE_FA.md) | ساختار پوشه‌ها، راه‌اندازی و hookها، container، سیستم UI مدیریت و الگوهای مشترک رابط |
| [ADMIN_SURFACES_FA.md](ADMIN_SURFACES_FA.md) | ده صفحهٔ منو، aliasها، tabها، اسکریپت هر صفحه و آزمون‌هایی که قراردادش را می‌سنجند |
| [REST_API_FA.md](REST_API_FA.md) | همهٔ مسیرها با متد، مجوز و کاربرد؛ قالب پاسخ، کدهای خطا و محدودیت نرخ |
| [DATA_MODEL_FA.md](DATA_MODEL_FA.md) | optionها، جدول‌ها، post typeها، transientها، cron و رفتار حذف افزونه |
| [AI_AND_KNOWLEDGE_FA.md](AI_AND_KNOWLEDGE_FA.md) | providerها و HTTP امن، سقف مصرف، ایندکس دانش سایت، جست‌وجوی معنایی، موتور پاسخ و تبدیل گفتار |
| [ANSWER_DESK_FA.md](ANSWER_DESK_FA.md) | ویجت بازدیدکننده، ثبت گفت‌وگو، انتقال به انسان، صندوق آن و کنسول پرسش مدیر |
| [FORMS_CONTENT_STORE_FA.md](FORMS_CONTENT_STORE_FA.md) | فرم بومی، Form Composer، Easy Form Builder، Content Studio، Store Copilot، Health و Activity Log |
| [SECURITY_FA.md](SECURITY_FA.md) | مجوزها، nonce، KeyVault، Redactor، SafeHttp، rate limit و زنجیرهٔ «پیشنهاد، تأیید، اجرا» |
| [QUALITY_AND_RELEASE_FA.md](QUALITY_AND_RELEASE_FA.md) | PHPUnit، integration، browser matrix و fixture، گیت‌ها، ترجمه و ساخت نسخهٔ انتشار |
| [EXTENDING_FA.md](EXTENDING_FA.md) | filterها، shortcodeها، connector و provider، و قواعد افزودن قابلیت تازه |

## قواعدی که هر تغییر باید حفظ کند

- هوش مصنوعی پیشنهاد می‌دهد، انسان تأیید می‌کند و backend اجرا می‌کند. هیچ مسیری انتشار یا ارسال خودکار ندارد.
- رابط ساختگی ممنوع است. کنترلی که backend واقعی ندارد قفل یا صادقانه برچسب‌گذاری می‌شود.
- WooCommerce فقط پیش‌نویس می‌سازد.
- کلیدها و اسرار هرگز به DOM، پاسخ REST، لاگ یا خروجی آزمون نمی‌رسند.
- شناسه‌های منجمد: namespace، پیشوند `aisaautopilot_*`، slugهای مدیریت، فضای REST، text domain و `KeyVault::KEY_DERIVATION_SALT` که `'sitepilot-ai|'` می‌ماند.
- منوی مدیریت دقیقاً ده آیتم دارد و صفحه‌های تازه به‌صورت alias یا tab اضافه می‌شوند.

## اسناد مرتبط

- وضعیت جاری و قدم بعدی: [`handoff.md`](../../handoff.md)
- صف کار و تصمیم‌های مالک: [`docs/PRODUCT_COMPLETION_ROADMAP_FA.md`](../PRODUCT_COMPLETION_ROADMAP_FA.md)
- شکاف موکاپ و کد در دو جهت: [`docs/MOCKUP_FEATURE_GAP_REPORT_FA.md`](../MOCKUP_FEATURE_GAP_REPORT_FA.md)
- برنامهٔ S5: [`docs/MOCKUP_GAP_S5_AUDIT_AND_PLAN_FA.md`](../MOCKUP_GAP_S5_AUDIT_AND_PLAN_FA.md)
- راهنمای کاربر و برنامهٔ مقاله‌ها: [`docs/user-guide/`](../user-guide/)
