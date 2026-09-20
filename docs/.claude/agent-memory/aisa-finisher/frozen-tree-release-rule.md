---
name: frozen-tree-release-rule
description: The owner's standing reasoning for the 0.0.1 release — code does not change once the T15-T20 gates are green; findings become 0.0.2 debt.
metadata:
  type: project
---

For the `0.0.1` release the owner decided (D-33, 2026-09-20, his own words): *"they stay in 0.0.1 and are removed in 0.0.2 — removing them is a code change and the T15 to T20 gates went green on today's tree."*

**Why:** the T15-T20 gates (browser smoke, visual parity, a11y, i18n, contract audit, security review) were all run against one specific working tree. Any product edit after that means those gates certify a tree that no longer exists, and re-running the whole `-Tier full` suite costs sessions. The verified ZIP artifact (170 entries, 759,388 bytes, SHA-256 `90E5AE16…`) is the release candidate and is not rebuilt.

**How to apply:**

- When a late release task turns up findings that need product edits, default to deferring them to `0.0.2` with a backlog row (D-33 produced B-10), rather than editing and rebuilding.
- A **partial** fix is the worst option: it does not make the gate green, but it does invalidate the independently verified ZIP. Either fix everything or nothing.
- Raise a decision row with three honest options (ship as-is / fix only the true positives / fix everything) and let the owner choose. Versioning policy until `1.0.0`: bump only the rightmost component, so the next version is `0.0.2`.
- This reasoning was applied again in T23 (D-34) for the Plugin Check findings.

Related: [[plugin-check-false-positive-classes]].
