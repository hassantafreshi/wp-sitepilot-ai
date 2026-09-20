---
name: clean-install-acceptance-traps
description: Traps when running the plugin's acceptance suite against a WordPress other than the dev install — BOM, dirname(__DIR__), and the core-owned REST namespace index.
metadata:
  type: feedback
---

Running `tests/local_admin_*_smoke.php` against a second WordPress needs scratchpad copies, because they hard-code `C:/xampp/htdocs/wp/wp-load.php`. Three things bite.

**Why:** learned in T24 (2026-09-21), the clean-install acceptance on `C:\xampp\htdocs\wp-clean`. Each trap first looked like a product defect and was not.

**How to apply:**

- **Never write a PHP copy with `Set-Content -Encoding utf8`.** PowerShell 5.1 writes a BOM, so `declare(strict_types=1)` stops being the first statement and PHP dies with a fatal error. Use `[IO.File]::WriteAllText($path, $text, (New-Object System.Text.UTF8Encoding($false)))`. Check with `[IO.File]::ReadAllBytes($p)[0..2]` — it must be `60,63,112` (`<?p`).
- **`local_admin_assets_smoke.php` resolves asset files with `dirname( __DIR__ ) . '/assets/admin/'`**, which is relative to the *script's* location. A scratchpad copy therefore reports `files=no` for every screen — an artifact, not a missing asset. Change it to `AISAAUTOPILOT_DIR . 'assets/admin/'` in the copy: that is also stricter, because it verifies the files inside the installed ZIP rather than the source tree.
- **The bare `/aisaautopilot/v1` route is not the plugin's.** WordPress core auto-creates a discovery index for every namespace, with callback `WP_REST_Server::get_namespace_index` and no `permission_callback`. Asserting "every route in the namespace has a permission_callback" therefore fails for a core-owned entry. Assert on routes under `/aisaautopilot/v1/` (65 routes, 132 handlers) and check the index's identity separately.
- **A `debug.log` entry is not automatically yours.** `wp-includes/default-filters.php` hooks the deprecated `the_block_template_skip_link` onto `wp_footer` for backwards compatibility, so any CLI probe that fires `do_action( 'wp_footer' )` logs a deprecation that has nothing to do with the plugin. Grep the plugin before attributing a log line to it.

For the acceptance itself: snapshot the footprint before install, after activation and after uninstall, and compare six counters (options, tables, transients, cron, legacy options, post meta). Delete via `deactivate_plugins()` then `delete_plugins()` — only that path actually runs `uninstall.php`.

Related: [[wp-test-ai-is-a-harness]].
