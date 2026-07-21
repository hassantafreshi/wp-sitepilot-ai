# SitePilot AI × Easy Form Builder — Integration Roadmap (self-notes)

Reverse-engineered from the live EFB 4.1.2 install (140 real forms in `wp_emsfb_form`).

## Ground truth (verified from DB + `includes/admin/class-Emsfb-create.php`)

- **Table:** `{prefix}emsfb_form` — columns: `form_id, form_name, form_structer(mediumtext),
  form_email, form_type(default 'form'), form_created_by, form_access_by, form_create_date, status(default 1)`.
- **Persistence format (critical):** EFB stores `form_structer` as
  `str_replace('"', '\\"', json_encode($array, JSON_UNESCAPED_UNICODE))` — i.e. valid JSON with
  every `"` escaped to `\"`. Insert via `$wpdb->insert`. `form_type` = `'form'`, `form_created_by`
  = current user id.
- **Shortcode:** `[EMS_Form_Builder id=N]` (also `ems_form_builder`).
- **Admin pages:** list = `admin.php?page=Emsfb`, builder/create = `admin.php?page=Emsfb_create`.
- **Capability:** `Emsfb` (admins have it). Detect EFB via `defined('EMSFB_PLUGIN_VERSION')`.

## `form_structer` shape (flat array)

`[ formSettings, step, field, option, option, field, ... ]`

1. **formSettings** `type:"form"` — steps, formName, EfbVersion:"2", button_* , thank_you:"msg",
   thank_you_message{thankYou,done,trackingCode,pleaseFillInRequiredFields,icon}, styling tokens,
   sendEmail, trackingCode, captcha, private, dShowBg, btns_align, email/email_to, email_temp.
2. **step** `type:"step"` — id_, dataId, id, name, icon, step:"1", amount, styling, visible:"1".
3. **field** — id_ (slug, also logic field_id), dataId=id_+"-id", type, placeholder, value,
   size:"100", message, id:"", classes:"", name(=label), required:"1"/"0", amount, step:"1",
   label_position:"up", label/el/message styling tokens, el_height:"h-d-efb", *_align, pro:"",
   icon_input:"". Extras: file→{file,value=category}; multiselect→{maxSelect,minSelect};
   heading→minimal(value=text, el_text_size:"display-4").
4. **option** (for select/radio/checkbox/multiselect) `type:"option"` — id_, dataId, parent(field
   id_), value(text), id_op(=id_), step, amount.
5. `amount` = global running counter starting at step=1, ++ per subsequent item (incl. options).

## Safe field types for MVP (all FREE in EFB, pro:"")

text, email, tel, number, textarea, url, date, select, radio, checkbox, multiselect, file, heading.
**Excluded** (safety / Pro / add-on): password, payment, stripe, paypal, persiaPay, login, register,
esign, mobile, yesNo, switch, rating, html, maps, matrix, recorders.

## Design decisions

- **Compact model file** `includes/Efb/efb-form-model.json`: `ai_capabilities` (small list sent to
  the AI) + `templates` (full EFB default props applied locally by the PHP converter). This keeps
  the AI payload tiny — the heavy EFB scaffolding never goes to the model.
- **AI outputs a clean intermediate model** `{title, fields:[{type,label,required,placeholder,options}]}`,
  validated against the EFB safe type set, then the **converter** expands it to real `form_structer`.
- **Connector persists directly** to `wp_emsfb_form` replicating EFB's exact escaping (no dependency
  on EFB's private JS). A `sitepilot_ai_efb_create_form` filter still allows overriding.
- **Floating button** injected only on `page=Emsfb` / `page=Emsfb_create` → opens a modal Copilot →
  prompt → preview → approve → creates a real EFB form → shows shortcode + link to the EFB list.

## Build checklist

- [x] Reverse-engineer structure, types, persistence.
- [ ] `efb-form-model.json` (capabilities + templates).
- [ ] `EfbFormModel` (loader) + `EfbFormConverter` (clean model → form_structer).
- [ ] Generalize `FormPlanValidator` to accept EFB type set (+ option-required types).
- [ ] `EfbFormSkill` (prompt from compact model) + `EfbCopilotController` (plan/create REST).
- [ ] `EasyFormBuilderConnector` real `execute_action` (converter + `$wpdb` insert + shortcode/urls).
- [ ] Floating button + modal assets on EFB pages.
- [ ] Deploy to XAMPP; manual test.

## Follow-ups (later)

- In-builder injection (edit current builder state) instead of creating a new form — needs
  `forms-efb.js` internals.
- Multi-step forms, conditional logic generation (EFB `logic_rules` schema is known — see form 117).
- Field assistant (improve labels/options) and Pro field types behind capability checks.
