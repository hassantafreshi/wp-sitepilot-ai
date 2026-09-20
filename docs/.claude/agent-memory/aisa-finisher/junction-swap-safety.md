---
name: junction-swap-safety
description: The assertion fingerprint that makes swapping the XAMPP plugin junction safe, plus the PowerShell 5.1 encoding trap that breaks scratchpad scripts.
metadata:
  type: feedback
---

When a task needs the real ZIP installed where the junction normally points, rename the junction and assert your way in and out. Never delete recursively through a junction.

**Why:** `C:\xampp\htdocs\wp\wp-content\plugins\ai-site-assistant-autopilot` is a junction to the plugin source, which holds hundreds of uncommitted changes. One `Remove-Item -Recurse` against the wrong path destroys the project. Renaming a link never touches its target, so rename is the only safe swap.

**How to apply:**

- Always write the swap as one script with `try/finally` and a `-DryRun` switch, and run the dry run first.
- Delete the extracted folder only when **all** of these hold, re-checked inside `finally`: it is not a reparse point (`Attributes` has no `ReparsePoint`), its `LinkType` is empty, it contains **none** of the source-only markers `handoff.md, docs, tests, scripts, vendor, node_modules, .git, test-artifacts, composer.json`, and its recursive file count equals the ZIP's file-entry count. The marker list is the strongest signal: not one of them ships in the ZIP.
- After restoring, assert `LinkType=Junction` **and** `Target` equals the source path, then re-hash a few source files and compare the root entry listing before/after. Finish with `preflight.ps1` and `run-gates.ps1 -Only render-smoke`.
- `git status` counts before and after are a free proof that no product file was touched.

**PowerShell 5.1 encoding trap:** scratchpad `.ps1` files written as UTF-8 without a BOM are read as ANSI, so any non-ASCII character (an em dash inside a double-quoted string is enough) corrupts quote parsing and produces a cascade of bogus "missing terminator" parse errors. Keep scratchpad PowerShell scripts pure ASCII.
