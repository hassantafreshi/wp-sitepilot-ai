# گزارش اجرای S3.1 — Form Composer

تاریخ پایان: ۲۰۲۶-۰۹-۱۰  
مرجع تصویری: `mockups/design_handoff_admin_redesign/Form Composer.dc.html`

## نتیجه

Form Composer روی همان route واقعی افزونه و با یک پوستهٔ تخصصی واحد بازطراحی شد. header، canvas و پنل Form Assistant در هر سه وضعیت compose، review و result ثابت می‌مانند. تعداد صفحه‌های اصلی افزونه تغییر نکرده و route فهرست فرم‌های native همچنان یک ابزار پنهان سازگاری است.

## تغییرات فنی و UI

- compose شامل مقصد ایمیل واقعیِ تنظیم‌شده توسط مدیر، empty state، سه مثال prefill-only، متن، voice و دکمهٔ ارسال صریح است.
- مثال‌ها هیچ provider request خودکاری نمی‌فرستند و New form ورودی را پاک کرده و focus را بازمی‌گرداند.
- review در همان canvas/assistant موکاپ، preview همهٔ فیلدها، Structure، Desktop/Mobile، وضعیت نهایی، ریسک، مقصد اعلان، شناسهٔ کوتاه و expiry را نشان می‌دهد.
- اجرا فقط پس از approval انجام می‌شود. مسیر publish یک تأیید دوم دارد و دکمهٔ آن تا acknowledgement غیرفعال است.
- وضعیت draft/publish هنگام ساخت proposal در `ActionStore` ثابت می‌شود؛ تغییر Settings پس از review نتیجهٔ تأییدشده را عوض نمی‌کند و context خراب fail-closed به draft می‌رود.
- result کارت سبز موکاپ، shortcode واقعی، Copy، Open draft/form، Build another و لینک EFB را فقط در صورت availability واقعی نشان می‌دهد.
- متن draft از نظر فنی اصلاح شد: shortcode فرم پیش‌نویس برای بازدیدکننده نمایش داده نمی‌شود و UI مدیر را به انتشار فرم پیش از استفاده هدایت می‌کند.
- تخمین ثابت و غیرواقعی `~1,200 tokens`، breadcrumb/rail قدیمی و لینک‌های خارج از surface موکاپ حذف شدند.
- رشته‌های جدید فارسی افزوده و فایل MO با ۱۱۹۴ entry دوباره کامپایل شد.

## اختلاف‌های آگاهانه با موکاپ

- انتخاب مستقیم title/field/button در chat و revision مکالمه‌ای پیاده نشده، چون backend patch/refinement وجود ندارد.
- تبدیل خودکار فرم native به EFB ادعا نشده؛ فقط handoff واقعی به صفحهٔ EFB وجود دارد.
- scenario switch و typing animation ابزار prototype هستند و وارد محصول نشده‌اند.
- review/approval/expiry/risk و Structure/Mobile preview افزوده‌های واقعی افزونه‌اند که موکاپ برایشان state کامل ندارد؛ به‌خاطر قرارداد امنیتی و بازبینی قبل از write حفظ شدند.
- recipient فقط از Settings می‌آید و AI نمی‌تواند آن را تغییر دهد.

فهرست کامل قابلیت‌های موکاپِ فاقد backend و قابلیت‌های افزونهٔ فاقد UI موکاپ در `FORM_COMPOSER_S3_1_AUDIT_AND_PLAN_FA.md` باقی مانده است.

## شواهد تست

| گیت | نتیجه |
|---|---|
| PHP syntax و JavaScript syntax | پاس |
| PHPCS کامل | ۱۳۰/۱۳۰، بدون error یا warning |
| PHPStan | بدون error |
| PHPUnit | ۱۳۳ test، ۴۳۹ assertion |
| renderer contract | ۴/۰ |
| proposal status context | ۳/۰ |
| email-only delivery | PASS؛ بدون ذخیرهٔ دادهٔ بازدیدکننده |
| Phase 1 integration | ۴۵/۰ |
| menu/assets/render smoke | منوی دقیق ۱۰تایی، ۱۸ قرارداد asset و ۱۸ route render پاس |
| browser matrix | `en_US` و `fa_IR`، desktop و 390px، همه exit 0 |
| مرورگر — ایمنی | no-auto-request، no execute before approval، publish acknowledgement و single execute پاس |
| مرورگر — UI | canvas/assistant order در LTR/RTL، stacking موبایل و overflow صفر |
| مرورگر — runtime | console error و pageerror افزونه صفر |
| cleanup | حساب موقت QA حذف شد؛ RESTها mock شدند و form واقعی ساخته نشد |

## خروجی visual QA

تصاویر empty، review و result در دسکتاپ و موبایل برای هر دو locale در `test-artifacts/form-composer/` ثبت و بازبینی شدند. ساختار، hierarchy، فاصله‌ها، رنگ‌ها، RTL واقعی و کارت نتیجه با موکاپ هم‌راستا هستند؛ داده‌های انگلیسی باقی‌مانده در fixture مرورگر محتوای فرم تولیدشده‌اند، نه copy ترجمه‌نشدهٔ UI.
