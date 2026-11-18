# پوشش ویژگی‌های Easy Form Builder در AI Site Assistant AutoPilot

آخرین بازبینی: ۲۰۲۶-۱۱-۱۸، شاخهٔ `dev4` این افزونه، در برابر Easy Form Builder شاخهٔ `dev4` (کامیت `e890a6d`) و افزونه‌های `vendores-efb` شاخهٔ `dev4` (کامیت `5c4051e`).

## English summary

Source of truth: EFB's own registry (`get_efbFunction()->capabilities_efb()` / `efb_var.capabilities`), read only by
`includes/Efb/EfbCapabilities.php`. No plan level, add-on code or gateway list is kept by hand for decisions (two
name maps remain only to *name* an add-on while EFB does not list it: `EfbPayments::PAY00`, `EfbAddons::OFFERABLE`).

Legend — AI: **C** create in a new form · **E** edit an open form · **X** explain (help answer with this site's live
state) · **H** hand off (to EFB's editor, EFB's Add-ons page, or the Data Tools plugin). Tests: `U:` PHPUnit
(`tests/*Test.php`), `R:` real WordPress REST flow (`tests/efb_e2e/rest_flow.php <scenario>`, driven by
`tests/efb_e2e/run_rest.sh`), `B:` real browser (`tests/efb_e2e/ui.cjs`).

| # | Feature | EFB key / contract | AI | Plan / add-on (from EFB) | When unavailable | Plugin code | Test |
|---|---|---|---|---|---|---|---|
| 1 | F01 masks and validators | `setting:mask_postal`, masks catalog | X, H | core; postal presets `AdnADR` (every plan) | live state "needs the Address & Postal add-on" | `efb-knowledge.json#input_masks`, `EfbKnowledge::live_state` | U: EfbKnowledgeRegistryTest (F01) |
| 2 | F02 OTP | `setting:verify_email_retype` / `_email_otp` / `_sms_otp` | X | retype Free, e-mail code Free Plus, SMS Pro + `AdnSS` + WP SMS | "SMS code: needs Pro"; save note `verify_unavailable` | `#verification_otp`, `EfbSaveNotes` | U: EfbKnowledgeRegistryTest (F02, lacks) |
| 3 | F03 text validation | `setting:field_text_limits`, `_unique`, `_pattern`, `_email_domains` | C/E (min/max length), X | Free / Free Plus | plan named | catalog `minimum`/`maximum`, `#text_validation` | U: EfbKnowledgeRegistryTest (F03) |
| 4 | F04 numbers and calc | `field:calc`, `setting:number_format`, `setting:calc_price`, `pay_calc_unavailable` | X, H (never builds calc) | `AdnCLC` Free Plus; amount Pro | Number field instead + install offer; with the add-on "add it in the EFB editor" | `EfbFeatureGate`, `EfbSaveNotes` | U: EfbSplitRegistryTest (calc ×2, save answers); R: split, editor; B: ui.cjs |
| 5 | F05 choices | `setting:choice_*`, `field:ranking` | C/E (options), X | Free; ranking Free Plus; stock / dynamic Pro | ranking → Checkboxes | `EfbFeatureGate`, `#choices_advanced` | U: EfbSplitRegistryTest (builder-only), EfbKnowledgeRegistryTest (F05) |
| 6 | F06 multi-calendar dates | `field:advdate`, `setting:advdate_*`, `field:pdate`/`ardate` | C (plain date), X, H | advdate Free; ranges Pro; availability `AdnBEF` Pro | Date field instead; plan named | `EfbFeatureGate` | U: EfbSplitRegistryTest (plan gate, builder-only) |
| 7 | F07 uploads | `setting:upload_*`, `field:dadfile` | C (file, type, size), X | multi Free Plus; attach Pro | dadfile → File on Free | `EfbFeatureGate`, `#file_uploads_advanced` | U: EfbKnowledgeRegistryTest (F07) |
| 8 | F08 hidden fields and smart tags | `setting:hidden_field`, `setting:smart_geo` | X, H | Free; `{geo:*}` `AdnGEO` Free Plus/Pro | hidden field left out with a note | `EfbFeatureGate`, `#hidden_smart_tags` | U: EfbSplitRegistryTest (builder-only, moved) |
| 9 | F09 repeater | `field:repeater`, `setting:repeater_rows` | X, H | Free Plus; rows Pro | left out with "add it in the EFB editor" | `EfbFeatureGate` | U: EfbSplitRegistryTest (builder-only) |
| 10 | F10 layout and special fields | `setting:review_before_submit`, `field:section`, `setting:esign_mode` | C (drawn signature), X | typed signature `AdnTSG` Free Plus | drawn signature kept, add-on named | `EfbFeatureGate::feature_keys` | U: EfbSplitRegistryTest (typed signature); R: tsg |
| 11 | F11 limits, schedule, save and resume | `setting:form_*` | E (private form), X | Free / Free Plus / Pro | plan named | catalog `require_login`, `#limits_schedule` | U: EfbKnowledgeRegistryTest (F11) |
| 12 | F12 core spam | `capabilities.spam` | X (review advice) | Free | — | `EfbFormReview::spam_advice` | U: EfbKnowledgeRegistryTest (spam advice) |
| 13 | F12b CAPTCHA | `spam.providers`: `recaptcha_v2`, `_v2i`, `_v3`; `turnstile` `AdnCTS`, `hcaptcha` `AdnHCP`, `altcha` `AdnALT`, `friendly` `AdnFRC`, `question` `AdnQCP` | X (never sets keys or the service) | Google: every plan; others Free Plus/Pro add-ons | "Other services come as add-ons"; save notes `captcha_addon_missing` / `captcha_not_ready` | `EfbFormReview`, `EfbSaveNotes`, `#captcha` | U: EfbKnowledgeRegistryTest (spam, F12) |
| 14 | F13 privacy and retention | `setting:privacy_*` | X | Free | — | `#privacy_retention` | U: EfbKnowledgeRegistryTest (F13) |
| 15 | F14 entries | `capabilities.entries` | X (never reads entries) | Free / Free Plus | — | `#entries_management` | U: EfbKnowledgeRegistryTest (F14) |
| 16 | F15 form package 1.1, revisions | `efb_form_package_api()`, `Form_Package::validate/import/delete`, `setting:revisions` | C (every new form), Undo | Free | "Update Easy Form Builder to 4.3.2 or newer" | `EasyFormBuilderConnector`, `EfbCopilotController::undo` | U: EfbFormPackageConnectorTest; R: write; B: ui.cjs (Undo) |
| 17 | F16 inline validation, `autocomplete` | `efb_form_defaults_efb()`, `setting:val_timing`, `setting:autocomplete` | C (defaults), X | Free | — | `EfbFormConverter::convert` | R: address |
| 18 | F17 name and address, `needs_states` | `field_templates_efb('address', {scheme})`, `setting:addr_scheme`, `setting:addr_lookup` | C (address schemes), X | schemes Free Plus; search `AdnADR` | plain address block; preview note for `needs_states` | `EfbFormConverter::efb_address_rows` | R: address |
| 19 | F18 upgrade prompt | `efb_upgrade_prompt_efb`, `?efb_upgrade=<feature>&efb_origin=sitepilot` | H | — | EFB's own dialog | `EfbCapabilities::upgrade_dialog_url`, `efb-copilot.js upgradeActions` | R: free; U: EfbSplitRegistryTest (offer URL) |
| 20 | F19 step backgrounds | `setting:step_bg_color` / `_gradient` | X | Free / Free Plus | plan named | `#step_backgrounds` | U: EfbKnowledgeRegistryTest (F19) |
| 21 | F20-core portal | `capabilities.portal_core`, contract `portal` | X; connector slot `portal.link` | `AdnPRT` Pro | "the customer portal is not ready" | `EfbCapabilities::portal*`, `ConnectorRegistry` | U: EfbKnowledgeRegistryTest (portal), ConnectorRegistryPortalTest; R: portal |
| 22 | F21 renewal reminder | `capabilities.plan.renewal` | X | Pro subscription; nothing on fa_IR | "one-time purchase, no renewal" | `#pro_renewal` | U: EfbKnowledgeRegistryTest (plans) |
| 23 | SPLIT: Numbers & Calculations | `AdnCLC` | see 4 | Free Plus (amount Pro) | see 4 | `EfbFeatureGate` | see 4 |
| 24 | SPLIT: Typed Signature | `AdnTSG` | see 10 | Free Plus | see 10 | | see 10 |
| 25 | SPLIT: Address & Postal | `AdnADR` | map → Text | every plan | "installs on every plan" | `EfbFeatureGate` | U: EfbSplitRegistryTest (map); R: split |
| 26 | SPLIT: Visitor Country | `AdnGEO` | X | Free Plus / Pro | live state | `#hidden_smart_tags` | U: EfbSplitRegistryTest (moved) |
| 27 | SPLIT: Booking | `AdnBEF` | X | Pro | "needs a higher plan" | `#addons_split` | U: EfbKnowledgeRegistryTest (lacks) |
| 28 | SPLIT: `addon_required`, `addon_keys_kept`, `addon_inactive`, `capabilities.split` | split block, save / import codes | C/E (gate), X | — | sentence per code | `EfbFeatureGate`, `EfbSaveNotes` | U: EfbSplitRegistryTest (save answers), EfbFormPackageConnectorTest (7) |
| 29 | Confirmation e-mail default (4.3.2) | `capabilities.email_confirmation` | C (on), E (`visitor_confirmation`) | Free | — | `EfbFormConverter::apply_notifications` / `confirmation_choice`, `efb-copilot.js` | U: EfbFormPackageConnectorTest (4–6); R: split; B: ui.cjs |
| 30 | Conditional logic forward jump | `jump_to_step` | C/E (rule + note), X | `AdnSMF` Free Plus (3 rules) / Pro | — | `EfbLogicRules::jump_note` | U: EfbLogicRulesTest (jump); R: logic |
| 31–33 | Gateways (legacy bridge): stripe, paypal, persiapay | `field:stripe` `AdnSPF`, `field:paypal` `AdnPAP`, `field:persiaPay` `AdnPPF` | C/E | Free Plus, Free Plus, Pro | install offer / plan / update | `EfbPayments` | U: EfbPaymentsTest; R: payments (Stripe off) |
| 34–40 | Gateways (PAY00): mollie, payplug, tappay, paytabs, paymob, omise, xendit | `payment_gateways.<slug>`, `field:<slug>`, `AdnMOL` … `AdnXND` | C/E (field from EFB's template) | Free Plus | install offer; readiness notes | `EfbPayments::gateway_rows` | U: EfbPaymentsTest (PAY00); R: payments (Mollie) |
| 41 | Offline (`AdnOF`) | province / city data add-on | — | — | not a gateway; never offered for payment | — | — |
| 42 | PAY-OUT (legacy code leaving core) | `legacy_gateway` contract, add-on `state` | — | — | "needs a newer version of its add-on"; no registry → "update Easy Form Builder" | `EfbPayments::validate_payment` | U: EfbPaymentsTest (unverified, update) |
| 43 | Data Tools (export / import / CF7) | `split.data_tools` | H | free plugin | live state "not active on this site" | `#data_tools` | U: EfbKnowledgeRegistryTest (data tools) |
| 44 | Plans (Free, Free Plus, Pro; fa_IR one-time) | `capabilities.plan`, `limit:steps`, `limit:logic` | X | — | plan named; Free keeps two pages | `EfbLicense`, `EfbCapabilities::plan_note` | U: EfbSplitRegistryTest (plan), EfbLogicRulesTest (limits); R: free |
| 45 | Native forms (Form Studio) and the hand-off | native contract | C (native), H (to EFB) | — | `needs_efb` card | `FormPlanValidator`, `form-builder.js` | U: FormPlanValidatorTest, FormStudioLayoutTest |

**Counts:** 45 rows (rows 31–40 are one row per gateway). **Covered** — the copilot builds or edits it, or gates it
correctly against EFB's registry, with tests: 27 (3, 5, 7, 10, 11, 16–18, 23–25, 28–40, 42, 44, 45). **Explained and
handed off** — a grounded answer with this site's live state, and the building is left to EFB's editor, EFB's Add-ons
page or the Data Tools plugin: 17 (1, 2, 4, 6, 8, 9, 12–15, 19–22, 26, 27, 43; row 4 is also gated). **Blocked**: 1 —
row 41 (Offline is a data add-on, not a gateway: out of scope by design). Partly blocked: `addon_keys_kept` after the
owner's own Save in EFB's editor reaches the copilot only once EFB passes the save warnings on (core change 1 below).

## روش کار (فارسی)

- **منبع حقیقت:** رجیستری خود EFB. کلاس `EfbCapabilities` تنها جایی است که آن را می‌خواند؛ هیچ سطح پلن یا کد افزونه‌ای برای تصمیم‌گیری دستی نگه داشته نمی‌شود. دو نقشهٔ کوچک (`EfbPayments::PAY00`، `EfbAddons::OFFERABLE`) فقط برای *نام بردن* افزونه‌ای است که EFB هنوز آن را فهرست نکرده (درگاه PAY00 خاموش) یا برای EFB قدیمی بدون رجیستری.
- **هرگز نوشتن مستقیم:** فرم تازه فقط با `Emsfb\Form_Package::import()` ساخته می‌شود؛ پیش از تأیید `validate()` نشان می‌دهد EFB چه چیزی را کنار می‌گذارد. endpoint قدیمی `/efb/save` که جدول `emsfb_form` را مستقیم می‌نوشت اکنون پاسخ 410 می‌دهد. EFB بدون این API پیام «به‌روزرسانی به 4.3.2» می‌گیرد.
- **وعدهٔ بی‌پشتوانه نه:** `EfbFeatureGate` هر فیلد را پیش از رسیدن به فرم با رجیستری می‌سنجد؛ اگر افزونه یا پلن نباشد، نزدیک‌ترین فیلدی که سایت دارد جای آن می‌نشیند (یا کنار گذاشته می‌شود) و پیش‌نمایش نام افزونه، پلن و فیلد جایگزین را می‌گوید و پیشنهاد نصب می‌دهد.

## ردیف‌ها به فارسی

| # | ویژگی | AI چه می‌کند | پلن / افزونه | پیام هنگام نبودن | مسیر کد | تست |
|---|---|---|---|---|---|---|
| ۱ | F01 ماسک‌ها و اعتبارسنج‌ها | توضیح؛ تنظیم ماسک در ویرایشگر EFB | هسته؛ پیش‌تنظیم‌های کد پستی با `AdnADR` (همهٔ پلن‌ها) | «Postal-code masks: needs the Address & Postal add-on» | `efb-knowledge.json#input_masks` | EfbKnowledgeRegistryTest |
| ۲ | F02 کد یک‌بارمصرف | توضیح | ایمیل در هسته (Free Plus)؛ پیامک فقط با افزونهٔ SMS و افزونهٔ WP SMS (Pro) | «SMS code: needs Pro» | `#verification_otp`، `EfbSaveNotes` | EfbKnowledgeRegistryTest |
| ۳ | F03 اعتبارسنجی متن | ساخت/ویرایش حداقل و حداکثر طول؛ بقیه توضیح | Free / Free Plus | نام پلن | کاتالوگ، `#text_validation` | EfbKnowledgeRegistryTest |
| ۴ | F04 عدد و محاسبه | هرگز فیلد محاسبه نمی‌سازد: بی‌افزونه → فیلد عدد + پیشنهاد نصب؛ با افزونه → «در ویرایشگر EFB اضافه کنید» | `AdnCLC` Free Plus؛ مبلغ محاسبه‌ای Pro | جملهٔ دقیق با نام افزونه و پلن؛ `pay_calc_unavailable` | `EfbFeatureGate`، `EfbSaveNotes` | EfbSplitRegistryTest، R: split/editor، B: ui.cjs |
| ۵ | F05 گزینه‌ها | ساخت گزینه‌ها؛ رتبه‌بندی → چک‌باکس | رتبه‌بندی Free Plus؛ موجودی و گزینهٔ پویا Pro | جایگزین نام برده می‌شود | `EfbFeatureGate` | EfbSplitRegistryTest |
| ۶ | F06 تقویم چندگانه | تاریخ ساده؛ advdate/شمسی/هجری → تاریخ + «در ویرایشگر EFB» | advdate Free؛ بازه Pro؛ دسترس‌پذیری `AdnBEF` Pro | نام پلن | `EfbFeatureGate` | EfbSplitRegistryTest |
| ۷ | F07 بارگذاری فایل | ساخت فایل با نوع و حجم؛ بقیه توضیح | چندفایلی Free Plus؛ پیوست ایمیل Pro | dadfile → file در Free | `EfbFeatureGate` | EfbKnowledgeRegistryTest |
| ۸ | F08 فیلد مخفی و برچسب هوشمند | توضیح؛ فیلد مخفی کنار گذاشته و ارجاع به ویرایشگر | Free؛ `{geo:*}` با `AdnGEO` | یادداشت | `EfbFeatureGate` | EfbSplitRegistryTest |
| ۹ | F09 تکرارشونده | توضیح؛ کنار گذاشته با ارجاع به ویرایشگر | Free Plus؛ چند ردیف Pro | یادداشت | `EfbFeatureGate` | EfbSplitRegistryTest |
| ۱۰ | F10 چیدمان و امضای تایپی | امضای کشیدنی ساخته می‌شود؛ تایپی بی‌افزونه حذف | `AdnTSG` Free Plus | نام افزونه | `EfbFeatureGate` | EfbSplitRegistryTest، R: tsg |
| ۱۱ | F11 محدودیت، زمان‌بندی، ادامه بعداً | فرم خصوصی؛ بقیه توضیح | Free/Free Plus/Pro | نام پلن | کاتالوگ | EfbKnowledgeRegistryTest |
| ۱۲ | F12 ضداسپم هسته | توصیهٔ بازبینی | Free | — | `EfbFormReview::spam_advice` | EfbKnowledgeRegistryTest |
| ۱۳ | F12b کپچا | توضیح وضعیت هر سرویس؛ هرگز کلید یا سرویس را تنظیم نمی‌کند | گوگل v2، v2 نامرئی، v3 در همهٔ پلن‌ها؛ Turnstile، hCaptcha، ALTCHA، Friendly، پرسشی: افزونه در Free Plus و Pro | «Other services come as add-ons» | `EfbFormReview`، `EfbSaveNotes` | EfbKnowledgeRegistryTest |
| ۱۴ | F13 حریم خصوصی | توضیح | Free | — | `#privacy_retention` | EfbKnowledgeRegistryTest |
| ۱۵ | F14 پاسخ‌ها | توضیح؛ هرگز پاسخ نمی‌خواند | — | — | `#entries_management` | EfbKnowledgeRegistryTest |
| ۱۶ | F15 بستهٔ فرم 1.1 و نسخه‌ها | هر فرم تازه با import؛ validate پیش از تأیید؛ Undo با delete و import_token | Free | «به 4.3.2 به‌روزرسانی کنید» | `EasyFormBuilderConnector` | EfbFormPackageConnectorTest، R: write، B: ui.cjs |
| ۱۷ | F16 اعتبارسنجی درجا و autocomplete | پیش‌فرض‌های `efb_form_defaults_efb()` روی فرم تازه | Free | — | `EfbFormConverter` | R: address |
| ۱۸ | F17 نام و نشانی، `needs_states` | نشانی ملی (ir، sa، …) از قالب سرور EFB؛ فهرست استان را صفحهٔ EFB پر می‌کند | طرح‌ها Free Plus؛ جست‌وجو `AdnADR` | بلوک نشانی ساده؛ یادداشت `needs_states` | `efb_address_rows` | R: address |
| ۱۹ | F18 دیالوگ ارتقا | پیوند به دیالوگ خود EFB با `efb_origin=sitepilot` | — | دیالوگ EFB | `upgrade_dialog_url` | R: free |
| ۲۰ | F19 پس‌زمینهٔ مرحله | توضیح | Free / Free Plus | نام پلن | `#step_backgrounds` | EfbKnowledgeRegistryTest |
| ۲۱ | F20-core پورتال | توضیح؛ جای اتصال‌دهندهٔ `portal.link` آماده | `AdnPRT` Pro | «پورتال آماده نیست» | `EfbCapabilities::portal*`، `ConnectorRegistry` | EfbKnowledgeRegistryTest، ConnectorRegistryPortalTest، R: portal |
| ۲۲ | F21 یادآور تمدید | توضیح؛ روی fa_IR بی‌اثر (خرید یک‌باره) | Pro اشتراکی | «خرید یک‌باره، بدون تمدید» | `#pro_renewal` | EfbKnowledgeRegistryTest |
| ۲۳–۲۸ | SPLIT | دروازهٔ ویژگی، پیام هر کد (`addon_required`، `addon_keys_kept`، `addon_inactive`) | از `capabilities.split` | جمله با نام افزونه و پلن | `EfbFeatureGate`، `EfbSaveNotes` | EfbSplitRegistryTest، EfbFormPackageConnectorTest |
| ۲۹ | پیش‌فرض ایمیل تأیید | نخستین فیلد ایمیل گیرنده؛ بعدی‌ها خاموش؛ خاموش با درخواست؛ `visitor_confirmation` در ویرایشگر | Free | — | `apply_notifications`، `confirmation_choice` | EfbFormPackageConnectorTest، R: split، B: ui.cjs |
| ۳۰ | پرش رو به جلو | قاعده + جملهٔ «این مرحله‌ها و فیلدهایشان اختیاری‌اند، سنجیده و شارژ نمی‌شوند» | `AdnSMF` | — | `EfbLogicRules::jump_note` | EfbLogicRulesTest، R: logic |
| ۳۱–۴۰ | درگاه‌ها | Stripe، PayPal، PersiaPay (پل قدیمی) و Mollie، Payplug، Tap، PayTabs، Paymob، Omise، Xendit (PAY00) | Free Plus (PersiaPay: Pro) | پیشنهاد نصب، پلن یا به‌روزرسانی | `EfbPayments` | EfbPaymentsTest، R: payments |
| ۴۱ | Offline | افزونهٔ دادهٔ استان/شهر، درگاه نیست | — | — | — | — |
| ۴۲ | انتقال کد درگاه قدیمی (PAY-OUT) | فقط قرارداد افزونه و پرچم رجیستری؛ بی‌رجیستری پرداخت افزوده نمی‌شود | — | «به‌روزرسانی افزونه / EFB» | `validate_payment` | EfbPaymentsTest |
| ۴۳ | Data Tools | ارجاع (برون‌بری، درون‌ریزی، CF7) | افزونهٔ رایگان جدا | «فعال نیست» | `#data_tools` | EfbKnowledgeRegistryTest |
| ۴۴ | پلن‌ها | Free، Free Plus (فعال‌سازی با کلیک انسان)، Pro؛ fa_IR یک‌باره | — | نام پلن | `EfbLicense` | EfbSplitRegistryTest، R: free |
| ۴۵ | فرم‌های بومی | قرارداد انتشار دست‌نخورده؛ کارت «این بخش به EFB نیاز دارد» | — | — | `FormPlanValidator` | FormPlanValidatorTest، FormStudioLayoutTest |

## آنچه به تغییر هستهٔ EFB نیاز دارد

1. رویداد `efb:form-saved` در سازنده `warnings` پاسخ ذخیره را ندارد (`addon_keys_kept`، `pay_*`، `verify_*`)، پس کوپایلوت پس از کلیک Save مالک نمی‌تواند آن‌ها را توضیح دهد. پیشنهاد: `detail.warnings`.
2. API به‌روزرسانی فرم ذخیره‌شده در سمت سرور وجود ندارد (`form.update` با patch و `base_hash`). افزونه تغییر فرم باز را فقط به Save خود EFB می‌سپارد و endpoint نوشتن مستقیمش را بست.
3. `Form_Package::import()` نام تکراری را «… (imported)» می‌کند؛ برای فرمی که AI می‌سازد گمراه‌کننده است. پیشنهاد: آرگومان منبع (`source => 'sitepilot'`) یا پسوند دیگر.
4. حالت پیش‌نویس (`status`) در import و رندر نیست (G7 قرارداد)، پس «ساخت به‌صورت پیش‌نویس» ممکن نیست.
5. `field_templates_efb('stripe')` در PHP بدون افزونه هم ردیف برمی‌گرداند، حال آنکه `newItems()` خطای `addon_required` می‌دهد؛ بهتر است PHP هم همان کد را برگرداند.
6. رجیستری درگاه PAY00 خاموش را در `payment_gateways` نمی‌آورد (فقط `addon:AdnMOL`)؛ افزونه برای نام بردن نقشهٔ کوچک slug→کد نگه می‌دارد. پیشنهاد: `gateway` در مدخل `addon:<code>`.
7. `efb_version` در رجیستری هنوز 4.3.1 است در حالی که ویژگی‌های 4.3.2 (پیش‌فرض ایمیل) هست؛ افزونه با تشخیص ویژگی کار می‌کند، اما پیام «به 4.3.2 به‌روزرسانی کنید» با شمارهٔ نسخه هم‌خوان نیست.

## ماندهٔ کار برای مالک

- ترجمهٔ رشته‌های تازه (فهرست عبارت‌ها در `languages/ai-site-assistant-autopilot.pot` تازه شد؛ طبق دستور ترجمه نشد).
- اجرای تست‌های XAMPP: `wordpress_efb_payment_integration.php`، `wordpress_efb_logic_integration.php` و `wordpress_efb_settings_integration.php` قرارداد قدیمی (افزونه‌های گزارش‌شده از مرورگر، گزینهٔ `emsfb_settings`، «Stripe = Pro») را می‌سنجند و باید با قرارداد رجیستری به‌روز شوند؛ روی سایت لینوکسی این کار پیش و پس از این تغییرات به یک اندازه شکست می‌خورند.
- ساخت ماسک‌ها، OTP، قواعد F03، تکرارشونده و فیلد محاسبه از خود افزونه (اکنون با `efbBuilderApi` و کلیدهای بستهٔ فرم ممکن است؛ این نوبت فقط توضیح و ارجاع است).
