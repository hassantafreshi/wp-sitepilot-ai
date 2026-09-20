---
name: plugin-check-runner-contract
description: How to drive Plugin Check 2.0.0 from a scratchpad PHP runner, which imports matter, and why a positive control is mandatory before trusting a 0/0 result.
metadata:
  type: project
---

Plugin Check 2.0.0 (installed at `C:\xampp\htdocs\wp\wp-content\plugins\plugin-check`) can be driven headlessly without WP-CLI. Contract confirmed against the installed source in T23 (2026-09-20).

**Why:** the plugin must pass WordPress.org review, and Plugin Check must run against the built ZIP (the dev folder produces ~1400 noise findings). WP-CLI is not installed in this environment, so a scratchpad runner is the only route.

**How to apply:**

- `Checks::run_checks( Check_Context $ctx, array $checks, ?Check_Runner $runner = null )` — the third argument is **optional**, so a runner instance is not needed. It also executes each check's `Preparation`/cleanup for you. Call it once per check to attribute findings to a check slug.
- `Static_Check` must be imported from `WordPress\Plugin_Check\Checker`. The `...\Checker\Checks` namespace silently yields zero checks.
- `Plugin_Context::__construct( $main_file, $slug = '', $mode = 'new' )`. Mode `new` is the strictest (new-plugin submission) and is what a first release should be judged by; anything not `new`/`update` silently falls back to `new`.
- In this version there are **33 registered checks: 27 static, 6 runtime**, and **zero experimental** ones, so `INCLUDE_EXPERIMENTAL` adds nothing. Expect `checks_available == checks_run == 27`.
- `Check_Result::get_errors()/get_warnings()` return a nested `[file][line][column][]` structure — flatten it before reporting.
- **Never trust a 0/0 result without a positive control.** Run the same runner against another real plugin first (`akismet` gives 33 errors / 101 warnings). A runner that resolves zero checks otherwise reports a silent, meaningless pass.

Related: [[plugin-check-false-positive-classes]], [[junction-swap-safety]].
