# SitePilot AI — Manual Testing Checklist

راهنمای تست دستی مرحله‌به‌مرحله. هر گام یک **Action** (کاری که انجام می‌دهید) و یک **Expected**
(نتیجه‌ی مورد انتظار) دارد. کنار هر گام تیک بزنید. اگر گامی شکست خورد، شماره‌اش را یادداشت کنید.

- Plugin path (source): `apps/wordpress-plugin/sitepilot-ai`
- Deployed to: `C:\xampp\htdocs\wp\wp-content\plugins\sitepilot-ai`
- نیازمندی: یک کلید API از یک سرویس سازگار با OpenAI (پیش‌فرض: **DeepSeek** → https://platform.deepseek.com/api_keys). cloud.ai / OpenAI / OpenRouter / Groq هم کار می‌کنند.

> اگر کلید API ندارید، همه‌ی گام‌ها به‌جز «تولید فرم واقعی» را می‌توانید تست کنید (Parts 0,1-partial,5b,6).

---

## Part 0 — Install & activate

- [ ] **0.1** Start XAMPP → Apache + MySQL running. Open your site admin: `http://localhost/wp/wp-admin`.
  **Expected:** WordPress dashboard loads.
- [ ] **0.2** Go to **Plugins → Installed Plugins**. Find **SitePilot AI**.
  **Expected:** It appears in the list (deployed by the sync script).
- [ ] **0.3** Click **Activate**.
  **Expected:** Activates with no error. A **SitePilot AI** menu (superhero icon) appears in the left admin sidebar.
- [ ] **0.4** (PHP < 8.1 only) If activation is blocked, you should see a clear message “requires PHP 8.1 or higher”.
  **Expected:** Graceful message, not a fatal white screen.

---

## Part 1 — Settings & provider connection

- [ ] **1.1** Open **SitePilot AI → Settings**.
  **Expected:** Settings page loads with a blue info banner about standalone mode.
- [ ] **1.2** In **AI Provider**, pick **DeepSeek**.
  **Expected:** Base URL auto-fills to `https://api.deepseek.com/v1`, Model to `deepseek-chat`, and a “Get your DeepSeek API key” link appears under the API Key field.
- [ ] **1.3** Change provider to **Custom (OpenAI-compatible)**.
  **Expected:** Base URL / Model are **not** wiped (your typed values are preserved). Switching back to DeepSeek auto-fills again.
- [ ] **1.4** Paste your API key into **API Key**. Leave “create as **Draft**” selected. Click **Save settings**.
  **Expected:** “Settings saved.” notice. Reload the page — the key field now shows a masked placeholder “•••••••• (a key is saved…)”, i.e. the real key is never shown back.
- [ ] **1.5** Click **Test connection**.
  **Expected:** A spinner, then a green **“Connection successful.”** (If the key is wrong you get a red “rejected your API key”; if base URL is wrong, “could not reach the provider”.)
- [ ] **1.6** Scroll to **Form connectors** table.
  **Expected:** “SitePilot Built-in Forms” = **Available**. “Easy Form Builder” = **Not active** (expected, until an EFB integration is registered).

---

## Part 2 — Generate a form (happy path)

- [ ] **2.1** Open **SitePilot AI → Copilot**.
  **Expected:** Composer card with a textarea and example chips. No warning banner (because a key is configured).
- [ ] **2.2** Click the example chip **“Create a contact form with name, email, phone, and message.”**
  **Expected:** The text drops into the textarea.
- [ ] **2.3** Click **Generate preview**.
  **Expected:** Button shows a spinner + “Thinking… drafting your form.”, then a **Preview** card appears with: title, a pills row (connector name, field count, Risk: …, Draft/Published), and a fields table (Label / Type / Required / Options).
- [ ] **2.4** Check the fields table.
  **Expected:** Rows like Name (text), Email (email), Phone (phone/tel), Message (textarea). Types are from the allowed set only.

---

## Part 3 — Approve → create

- [ ] **3.1** In the Preview card, click **Cancel**.
  **Expected:** Preview hides. Nothing was created.
- [ ] **3.2** Generate the preview again (repeat 2.2–2.3), then click **Approve & create form**.
  **Expected:** Spinner + “Creating the form…”, then a **Result** card: “Form created as a draft”, a shortcode box `[sitepilot_form id="N"]` with a **Copy shortcode** button, and an **Open the form** button.
- [ ] **3.3** Click **Copy shortcode**.
  **Expected:** Button briefly says “Copied!”. (Clipboard now holds the shortcode.)
- [ ] **3.4** Confirm the form exists: click **Open the form** (or check the DB) — it opens the SitePilot Forms edit screen for the new post.
  **Expected:** A `sitepilot_form` post exists with your title, saved as **Draft**.

---

## Part 4 — Show the form on the site & submit it

- [ ] **4.1** Create a page: **Pages → Add New**. Title “Contact test”. In the content, paste the shortcode `[sitepilot_form id="N"]` (use your N). **Publish**.
  **Expected:** Page publishes.
- [ ] **4.2** Click **View page**.
  **Expected:** The form renders with styled fields (name/email/phone/message), required fields marked with a red `*`, and a submit button.
- [ ] **4.3** Submit the form **empty**.
  **Expected:** Browser blocks submission on required fields (HTML5 validation).
- [ ] **4.4** Fill valid values and submit.
  **Expected:** Page reloads and shows the green confirmation message (“Thank you…”).
- [ ] **4.5** In admin, check the submission was stored: the `sitepilot_submission` post type holds a “Submission: …” entry with the values.
  **Expected:** One private submission record with your field values.

---

## Part 5 — Safety tests (important)

- [ ] **5.1 (forbidden fields)** In Copilot, ask: **“Create a payment form with name and credit card number and CVV.”** → Generate preview.
  **Expected:** Either the plan comes back **without** the card/CVV fields (payment fields stripped, risk may be “high”), or you get a validation error refusing forbidden fields. A card/CVV field must **never** appear in the preview.
- [ ] **5.2 (login/password)** Ask: **“Make a user registration form with username and password.”** → Generate.
  **Expected:** Password field is rejected/omitted. No password input in the preview.
- [ ] **5.3 (no key)** In Settings, tick **“Remove the saved key”**, Save. Go to Copilot.
  **Expected:** A yellow banner “No AI provider is connected yet” with a link to Settings; the textarea and Generate button are **disabled**. (Re-add your key afterward to continue.)
- [ ] **5.4 (approval expiry / single-use)** Generate a preview, then wait **>15 minutes** (or open two tabs, approve in one, then click Approve again after it succeeded). Try to approve the stale one.
  **Expected:** “This proposal has expired. Please generate the form again.” — an approved plan cannot be executed twice.
- [ ] **5.5 (gibberish)** Ask something impossible: **“asdkjfh qwqw”** → Generate.
  **Expected:** A friendly error like “Could not generate a valid structured result…”, no crash.

---

## Part 6 — Activity log

- [ ] **6.1** Open **SitePilot AI → Logs**.
  **Expected:** A table of recent events: `plan.created`, `action.executed`, plus any `*.error` rows. Times in UTC, level pills.
- [ ] **6.2** Inspect messages.
  **Expected:** **No API key** or secret appears anywhere in the log (redaction works).
- [ ] **6.3** Click **Clear log** → confirm.
  **Expected:** “Logs cleared.” and the table empties.

---

## Part 7 — Draft vs Published setting

- [ ] **7.1** In Settings, choose **create as Published**, Save. Create a new form via Copilot and approve.
  **Expected:** Result says “Form created” (not “as a draft”); the `sitepilot_form` post status is **Published**. The preview pill should have shown “Will be published”.
- [ ] **7.2** Switch back to **Draft** for safety.

---

## Part 8 — Easy Form Builder fallback (design check)

- [ ] **8.1** With no EFB integration registered, generate + approve a form.
  **Expected:** The connector used (shown in the preview pill and result) is **SitePilot Built-in Forms** — the built-in fallback works even without EFB.
- [ ] **8.2** (Optional, for developers) Register the two filters from `README.md` (`sitepilot_ai_efb_available`, `sitepilot_ai_efb_create_form`) with Easy Form Builder active.
  **Expected:** The preview/result now show **Easy Form Builder** as the connector, and the form is created via EFB.

---

## Part 9 — Uninstall hygiene (optional)

- [ ] **9.1** Deactivate the plugin.
  **Expected:** No error. Your created forms/pages remain.
- [ ] **9.2** Delete the plugin.
  **Expected:** Plugin settings, the encrypted key, and logs are removed; your `sitepilot_form` / `sitepilot_submission` posts are intentionally left in place.

---

## Result summary

| Part | Pass? | Notes |
|---|---|---|
| 0 Install |  |  |
| 1 Settings/connection |  |  |
| 2 Generate |  |  |
| 3 Approve/create |  |  |
| 4 Frontend/submit |  |  |
| 5 Safety |  |  |
| 6 Logs |  |  |
| 7 Draft/publish |  |  |
| 8 EFB fallback |  |  |

اگر جایی خطا دیدید: شماره‌ی گام + پیام خطا (و در صورت امکان یک اسکرین‌شات یا محتوای صفحه‌ی **Logs**) را بفرستید تا سریع رفع کنیم.
