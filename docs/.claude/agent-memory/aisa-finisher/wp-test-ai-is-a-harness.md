---
name: wp-test-ai-is-a-harness
description: C:\xampp\htdocs\wp-test-ai is the EFB language-pack test harness, not a clean WordPress — check before using it for any acceptance run.
metadata:
  type: project
---

`C:\xampp\htdocs\wp-test-ai` (database `wp_test_ai`, `http://localhost/wp-test-ai`) looks like a spare clean install but is a **purpose-built harness for the owner's Easy Form Builder language-pack work**, actively used through 2026-09-20.

**Why:** it was proposed as the D-04 clean-install target for T24. A read-only inspection on 2026-09-21 showed it cannot serve that purpose, and running an acceptance there would produce evidence that proves nothing.

**How to apply — check these before ever using it:**

- `wp-content/mu-plugins/wptai-tools.php` loads on every request. It hooks `pre_wp_mail` and swallows **every** `wp_mail()` (nothing is ever sent, it lands in option `wptai_mail_log`), and logs outbound HTTP into `wptai_http_log`. Any mail or HTTP assertion there is meaningless.
- `wp-config.php` reads `wptai-scenario.json` at the WP root and contains `define( $wptai_name, $wptai_value )` — arbitrary scenario-driven constant injection. `DISALLOW_FILE_MODS` has been switched on this way, which silently blocks plugin-ZIP installation.
- `DISABLE_WP_CRON = true` and `EFB_DEBUG = true`.
- `ru_RU`, `hi_IN` and `nl_NL` language packs are installed and some `debug.log` warnings are in Swedish, so the admin locale is not `en_US`. Menu/label assertions will not behave.
- `debug.log` is already full of `emsfb_language_packs` runs, so "activation produced no PHP warning" is not measurable without marking the log first.

Treat it as another workstream's property: inspect read-only, never reset it, and get an explicit owner decision before installing anything into it.

Related: [[frozen-tree-release-rule]].
