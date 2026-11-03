# گسترش افزونه

## filterها

| filter | ورودی | کاربرد |
|---|---|---|
| `aisaautopilot_provider_presets` | آرایهٔ presetها | افزودن یا تغییر provider در فهرست Setup. هر preset باید `label`، `group`، `api` (`openai` یا `anthropic`)، `baseUrl`، `model`، `models`، `embeddings` و `note` داشته باشد. |
| `aisaautopilot_allow_insecure_provider_url` | `bool $allow`، `string $endpoint` | اجازهٔ HTTP ساده برای provider یا تبدیل گفتار در شبکهٔ خصوصی مورد اعتماد. پیش‌فرض false است. |
| `aisaautopilot_efb_available` | `bool` | بازنویسی نتیجهٔ تشخیص EFB، مثلاً در آزمون |
| `aisaautopilot_client_ip` | `string $ip`، `string $remote` | نشانی بازدیدکننده برای محدودیت نرخ، برای سایتی پشت proxy غیر از Cloudflare (Cloudflare خودکار شناخته می‌شود). فقط نشانی‌ای را برگردانید که خود proxy می‌گذارد، نه سربرگی که کلاینت می‌تواند بنویسد. مقدار نامعتبر نادیده گرفته می‌شود. |
| `aisaautopilot_efb_create_form` | پارامترهای ساخت فرم EFB | مداخله در ساخت فرم EFB |

## shortcodeها

| shortcode | کاربرد |
|---|---|
| `[aisaautopilot_chat]` | قرار دادن ویجت Answer Desk در محتوا، با همان دروازه‌های نمایش |
| `[aisaautopilot_form id="123"]` | نمایش فرم بومی تأییدشده. پاسخ‌ها فقط ایمیل می‌شوند. |

## افزودن connector فرم

1. **قرارداد:** کلاسی بسازید که `Connectors\ConnectorInterface` را اجرا کند: `is_available()`، `get_capabilities()`، `validate_action()`، `execute_action()` و `get_health()`.
2. **ثبت:** در `Plugin::register_services()` به `ConnectorRegistry` اضافه کنید. `resolve()` connector بومی را پیش‌فرض امن نگه می‌دارد.
3. **اجرا:** `execute_action()` فقط plan معتبر را اجرا کند و خروجی مدل را مستقیم نپذیرد.
4. **آزمون:** integration آزمون و ردیف Health اضافه کنید.

## افزودن provider

- **provider سازگار با OpenAI:** یک preset با filter کافی است.
- **API ناسازگار:** کلاسی بسازید که `ProviderInterface` را اجرا کند و برای HTTP از trait `SafeHttp` استفاده کند. در `ProviderManager::build()` آن را انتخاب کنید. خطاها باید `ProviderException` با کد پایدار و بدون متن کلید باشند.

## افزودن سطح مدیریت

1. **جایگاه:** menu تازه نسازید. صفحه را به‌صورت alias در `AdminMenu::register()` و `ALIAS_OWNERS` یا به‌صورت tab در ناوبری موجود اضافه کنید.
2. **ساختار صفحه:**
   - با `Ui::page_header()` شروع کنید؛
   - از tokenهای `aisaautopilot-ui.css` و propertyهای منطقی استفاده کنید؛
   - هر رشته را با text domain قابل ترجمه کنید؛
   - متن JS را با `Ui::json_script()` بدهید؛
   - اسکریپت را در `SCREEN_SCRIPTS` و انتظار آن را در `tests/local_admin_assets_smoke.php` ثبت کنید.
3. **الگوهای ARCHITECTURE:** از الگوهای [ARCHITECTURE_FA.md](ARCHITECTURE_FA.md) استفاده کنید: confirm، overlay زیر `.sp-app`، نوار ذخیره، ذخیرهٔ محدود به فرم و بدون ارسال خودکار.
4. **آزمون:**
   - integration برای قرارداد رندر و ذخیره؛
   - browser check با fixture و matrix در `en_US` و `fa_IR`، در 1440 و 390 پیکسل؛
   - ترجمهٔ فارسی در PO و کامپایل MO با `scripts/compile-mo.php`.

## افزودن مسیر REST

قواعد در [REST_API_FA.md](REST_API_FA.md#افزودن-مسیر-تازه) آمده است.
