# AI form builders: feature inventory and the QA scenarios built from it (2026-11-10)

Sources: two research passes (web search summaries citing official vendor URLs; direct fetches of vendor
sites were refused by this environment's network, so every claim below is as relayed by search results).
Confidence: **H** official docs/help, **M** marketing, changelog mirrors or third parties, **U** no source found.
Products: WPForms (WPF), Fluent Forms (FF), Formidable (FMD), Gravity Forms MCP (GF), Jotform (JF), Typeform
(TF), Tally (TY), Google Forms Gemini (GG), Ninja Forms (NF), Everest Forms (EV), SureForms (SF).

## What the market offers

| Area | Who has it | Conf. | Our status after this round |
|---|---|---|---|
| Form from a prompt | all 11 | H | Form Studio and EFB copilot |
| Prompt in any language | TY, WPF; GG fixed list | H | Both; form text follows the site language |
| Multi-page by AI | WPF, SF, FF claimed; GG no | M | EFB copilot (steps); Form Studio hands off to EFB |
| **Columns / side by side by AI** | nobody documents it (WPF excludes its Layout field) | U | **Both, 12-column widths** |
| Edit an existing form by chat | WPF Smart Edit, JF, TF, TY, NF, EV | H | Both |
| **Click a field, then talk about it** | nobody documents it | U | **Both (Form Studio chip + EFB selection)** |
| Undo of AI edits | WPF revision per edit, TY | H | Form Studio Undo; EFB snapshot undo |
| Conditional logic by AI | WPF, JF, TF, TY, GF MCP; EV/FF M | H | EFB, new and open forms, add-on installed with permission |
| Calculations by AI | WPF (Pro + addon), JF, TY | H | Not yet (EFB Pro has calculations; not driven by AI) |
| Payment fields by AI | U for all | U | **EFB: Stripe, PayPal, ZarinPal with prices from the request** |
| Min/max/regex by AI | U | U | Form Studio number/date min/max; regex not yet |
| Translation by AI | TF, TY, WPF | H | EFB translate; Form Studio relabel by message |
| Conditional notifications | JF, WPF, GF | H | Not driven by AI (EFB logic add-on email rules are out of scope by design) |
| Import from document/URL | JF, GG, TF | H | Not yet |
| Review the form | WPF | H | EFB review |
| Submissions analysis | GF/FMD MCP, TY | H/M | Not in scope |

## Conditional-logic patterns tested (18)

Show if choice = X · hide · require if · AND · OR of several values · number greater/less · empty / not empty ·
contains (checkbox includes) · show a step · jump to a step · chained · not equals · end early · several
rules in one request · Persian requests · logic on a deleted field · circular rules · add-on missing.

## Plan limits (EFB)

Conditional Logic add-on (AdnSMF): Free Plus and Pro. Free Plus: 3 rules per form, 2 conditions per rule
(and 2 conditional email rules). Free: two pages at most. Payment add-ons: Pro.

## Tricky prompts (40) the scenarios draw on

Vague ("make a form"), duplicate fields, "make it shorter", "this" with nothing selected, several edits in
one message, unsupported features in Form Studio, "all optional but email", contradictory widths, "40%
wide", four per row, move to top, upload/signature in Form Studio, ratings 1–10, all countries, age ranges,
postal code patterns, prices missing, toman via Stripe, per-guest pricing, add-on missing, rule about a
missing option, the 4th rule on Free Plus, orphaned rules, circular rules, 120 questions, 50 options,
Persian/Arabic prompts, brand names in translation, injection text, script in placeholders, recipient
changes, other sites' data, undo/redo.

The 200 runnable scenarios are in `tests/live_ai_scenarios.cjs`; results are in the QA report.
