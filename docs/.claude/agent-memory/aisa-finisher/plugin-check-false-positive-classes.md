---
name: plugin-check-false-positive-classes
description: The recurring Plugin Check false-positive patterns in this plugin, and the proof that Plugin Check deliberately permits fonts.googleapis.com.
metadata:
  type: project
---

T23 (2026-09-20) ran Plugin Check 2.0.0 against the `0.0.1` ZIP: 11 errors, 13 warnings, of which **23 of 24 are false positives**. The same patterns will reappear on every future run, so do not re-derive them.

**Why:** the plugin's own PHPCS gate is 0/0, but Plugin Check uses a stricter ruleset. Knowing which findings are structural false positives prevents pointless code churn on a frozen release tree.

**How to apply:**

- `EscapeOutput.OutputNotEscaped` fires wherever a template echoes a **method return value** — the sniff cannot trace returns. In this plugin that is `AnswerDeskHandoffPage::card_class()`/`state_class()`, which return one of four literal CSS class strings.
- `EscapeOutput.ExceptionNotEscaped` fires on `throw new ProviderException( $code, __( ... ) )`. That constructor message only reaches logs; user-facing copy comes from `ProviderException::user_message()`. Escaping it would be semantically wrong for a string that can reach JSON.
- `AlternativeFunctions.file_system_operations_fclose` fires on CSV exports. The sniff exempts `fopen()` on `php://` streams but cannot follow the resource variable to `fclose()`. `WP_Filesystem` cannot stream to `php://output` at all.
- `NonceVerification.*` fires inside pure reader helpers (`IntegrationsPage::post_value()`, `SettingsPage::is_advanced_route()`) whose callers verify the nonce and capability before any write.
- `MissingUnslash`/`InputNotSanitized` fires when unslashing happens on a later line than the superglobal access.
- **`WooCommerceSettings.php:18` is the one to leave alone**: it deliberately stores the raw `$_GET['tab']` and restores it after an aliased render. Sanitizing it changes behaviour.

**Google Fonts is NOT flagged as an external resource.** `includes/Admin/AdminMenu.php:223` loads two webfonts from `fonts.googleapis.com`, and `offloading_files`, `enqueued_resources` and `external_admin_menu_links` all return zero. The blocklist in `vendor/plugin-check/phpcs-sniffs/PluginCheck/Helpers/OffloadingServicesTrait.php` contains only `ajax\.googleapis\.com` and explicitly exempts font hosts with `(?<!fonts\.)gstatic\.com`. The only finding on that line is `EnqueuedResourceParameters.MissingVersion`, because the sniff counts an explicit `null` version as "not set" (`EnqueuedResourceParametersSniff.php:145`). Do not propose bundling or removing the font on Plugin Check's account.

Related: [[plugin-check-runner-contract]].
