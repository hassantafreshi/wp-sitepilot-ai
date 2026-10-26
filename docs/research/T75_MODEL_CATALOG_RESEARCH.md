# AI provider and model catalogue: verified state as of 2026-10-26

Target file: `includes/Core/Settings.php`, `presets()` (lines 52-261). The repository was not modified.

## How this was verified (read first)

- **WebFetch did not work in this environment.** Every URL failed DNS resolution (`getaddrinfo ENOTFOUND`, even for example.com). Direct `curl` to provider sites was refused by the egress proxy (HTTP 403).
- **Source 1: WebSearch with `allowed_domains` set to each provider's own domains.** Results are search-engine extracts of official pages. Each one is cited with the official URL the extract came from.
- **Source 2: official SDK source code from npm and PyPI.** Several vendors keep a typed list of model IDs and default base URLs in their SDKs:
  - `openai@7.43.0` (2026-10-22)
  - `@anthropic-ai/sdk@1.3.0` (2026-10-23)
  - `xai-sdk 1.21.0` (2026-10-23)
  - `perplexityai 0.46.1` (2026-10-23)
  - `mistralai 3.5.0` (2026-10-25)
  - `together 3.0.0`, `fireworks-ai 1.2.30`, `cerebras-cloud-sdk 1.91.0`, `zai-sdk 0.2.3`, `cohere 7.3.0`, `sambanova 1.13.0`, `groq 1.7.0`, `llama-api-client 0.6.0`
- **Source 3: official docs and model repositories on GitHub (raw.githubusercontent.com).**
  - `cohere-ai/cohere-developer-experience` (the source of docs.cohere.com)
  - `huggingface/hub-docs` (Inference Providers docs)
  - `MoonshotAI/Kimi-K3`, `zai-org/GLM-5` and `MiniMax-AI/MiniMax-M3` READMEs
- **The WebSearch budget (200 per turn, shared) ran out** partway through. Providers researched after that point have only SDK or GitHub evidence. Their sections say so, and the unconfirmed items are listed under "Unverified / uncertain".
- **Labels used below.** "Verified" means an ID or URL was seen in one of the sources above. Nothing was guessed. Anything inferred is labelled as an inference.

---

## 1. OpenAI (direct)

| Field | Value |
|---|---|
| Base URL | `https://api.openai.com/v1` (unchanged) |
| Auth | `Authorization: Bearer` |
| API-key page | `https://platform.openai.com/api-keys` (unchanged; not re-checked because the site could not be fetched) |
| Recommended default | **`gpt-6-luna`**: $0.10 in / $0.50 out per 1M tokens, structured outputs supported. Alternative: `gpt-5.6-luna` ($0.20 / $1.20 after the 2026-07-30 price cut) |
| GET /models | Yes |
| Embeddings | `text-embedding-3-small`, `text-embedding-3-large` (`text-embedding-ada-002` still exists in the SDK type but is legacy) |

**Chat models worth offering (newest first). All are in the SDK's `ChatModel` union, so they work with Chat Completions:**

| ID | Notes |
|---|---|
| `gpt-6-astra` | Flagship. $10 / $50. 1.05M context. `reasoning_effort: none` is rejected (HTTP 400). Tools are not supported in Chat Completions; they need the Responses API. |
| `gpt-6.1-sol` | Released late Sep 2026. $2 / $10. Same restrictions as Astra: no `none` effort, no tools in Chat Completions. |
| `gpt-6-luna` | Cheapest. $0.10 / $0.50. Supports `none`. Function calling in Chat Completions works only with `reasoning_effort: "none"`. |
| `gpt-6-sol` | Superseded by 6.1 Sol. No current API pricing row was found, so treat it as uncertain. Optional. |
| `gpt-5.6-sol` | $4 / $20 (promotional price through at least Nov 21, 2026). |
| `gpt-5.6-terra` | $2 / $12. |
| `gpt-5.6-luna` | $0.20 / $1.20. |
| `gpt-5.5` | Retired from ChatGPT and Codex sign-in on 2026-10-14, but still available in the API. |
| `gpt-5.4` / `gpt-5.4-mini` | Not marked deprecated on the "All models" page. `gpt-5.4-mini` retired from Codex on 2026-08-31 (replacement `gpt-6-luna`). |

**Deprecated or shut down (do not offer):**

| ID | Status |
|---|---|
| `gpt-5.3-chat-latest` | Shut down 2026-08-10. Replacement: `gpt-5.6-sol`. |
| `gpt-5.2-chat-latest` | Shut down 2026-08-10. |
| `gpt-4.1-nano` | Shut down 2026-10-23. Replacement: `gpt-5.6-luna`. |
| `gpt-4.1`, `gpt-4.1-mini` | Deprecated 2026-10-14. Shutdown 2027-01-14. Replacement for mini: `gpt-6-luna`. |
| `gpt-5.1`, `gpt-5.4-nano`, `gpt-5.3-codex` | Deprecated. Shutdown 2027-04-01. Replacement for nano: `gpt-6-luna`. |
| `gpt-5`, `gpt-5-mini`, `gpt-5-nano`, `gpt-5-pro` | Shutdown 2026-12-11. |
| `chatgpt-4o-latest` | Shut down 2026-02-17. |
| `gpt-4o-2024-05-13` | Shut down 2026-10-23. |
| `gpt-5.2` (base) | No deprecation entry found, but it is superseded. Optional to keep. |

**`gpt-5.5-pro` is not a Chat Completions model.** It appears only in the SDK's `ResponsesModel` type, not in `ChatModel`. Remove it from this Chat Completions preset.

**Notes**
- `GPT-5.6 Sol/Terra/Luna` are real model IDs. The current file's OpenAI IDs are not invented. They are only outdated.
- Speech models are covered in section "Speech models".

**Sources**
- SDK: `openai@7.43.0`, `resources/shared.d.ts` (`ChatModel`, `ResponsesModel`), `resources/embeddings.d.ts`, `resources/audio/*.d.ts`.
- https://developers.openai.com/api/docs/models and https://developers.openai.com/api/docs/models/all (deprecated flags)
- https://developers.openai.com/api/docs/deprecations (all shutdown dates above)
- https://openai.com/index/introducing-gpt-6-sol-and-luna/, https://openai.com/index/gpt-6-astra/, https://openai.com/index/introducing-gpt-6-1-sol/
- https://developers.openai.com/api/docs/models/gpt-6-luna (pricing, structured outputs)
- https://developers.openai.com/api/docs/guides/latest-model ("Using GPT-6": `none` effort and tools-in-Chat-Completions rules)
- https://community.openai.com/t/announcing-a-major-price-drop-for-5-6-terra-and-luna-and-fast-mode-for-5-6-sol/1388484 (5.6 price cut)
- https://developers.openai.com/codex/models (GPT-5.5 and 5.4-mini status)

## 2. Anthropic (native Messages API)

| Field | Value |
|---|---|
| Base URL | `https://api.anthropic.com/v1`, native Messages API (keep `api => anthropic`) |
| Auth | `x-api-key` (plus the `anthropic-version` header) |
| API-key page | `https://console.anthropic.com/settings/keys` is in the file. The docs have moved to platform.claude.com, so the key page may now be `https://platform.claude.com/settings/keys`. This was **not verified**. |
| Recommended default | **`claude-haiku-5-5`** for cost: $0.10 / $0.50 per 1M tokens up to 100K-token prompts, 1M context. **`claude-sonnet-5-5`** for quality: $2 / $10. The docs' "start here" recommendation is `claude-opus-5-5` ($4 / $20). Structured JSON output is supported (`output_config.format`, shown in the SDK `messages.parse` docs). |
| GET /models | Yes (`/v1/models`) |
| Embeddings | None |

**Chat models (newest first):**

| ID | Notes |
|---|---|
| `claude-opus-5-5` | Current Opus. |
| `claude-sonnet-5-5` | |
| `claude-haiku-5-5` | Adaptive thinking only. |
| `claude-fable-5-1` | GA on all platforms. $10 / $50. |
| `claude-opus-5` | |
| `claude-sonnet-5` | |
| `claude-fable-5` | |
| `claude-opus-4-8` | |
| `claude-opus-4-7` | |
| `claude-sonnet-4-6` | Legacy. Active, retirement not before 2027-02-17. |
| `claude-haiku-4-5` | Active. |
| `claude-opus-4-6` | Legacy. Retirement not before 2027-02-05. Optional. |

**Do not offer:**

| ID | Reason |
|---|---|
| `claude-mythos-5-1`, `claude-mythos-5` | Restricted to verified organisations. |
| `claude-mythos-preview` | Deprecated. End of life 2026-06-30. |
| `claude-sonnet-4-5` | Deprecated 2026-09-30. Retires 2026-11-30. |
| `claude-opus-4-1` | Retired 2026-08-05. |
| `claude-sonnet-4`, `claude-opus-4` | Retired 2026-06-15. |

**Notes**
- Since the 4.6 generation, IDs carry no date. Each ID is a fixed snapshot.

**Sources**
- SDK: `@anthropic-ai/sdk@1.3.0`, `resources/messages/messages.d.ts` (`Model` union) and `src/internal/data/deprecated-models.ts`.
- https://platform.claude.com/docs/en/claude_api_primer (lists the 5.5 IDs)
- https://platform.claude.com/docs/en/about-claude/model-deprecations
- https://platform.claude.com/docs/en/models/haiku-5-5/overview, https://platform.claude.com/docs/en/models/sonnet-5-5/overview, https://platform.claude.com/docs/en/models/opus-5-5/overview
- https://www.anthropic.com/claude-fable-and-mythos-5-1
- https://platform.claude.com/docs/en/about-claude/models/model-ids-and-versions

## 3. Google Gemini (OpenAI-compatible endpoint)

| Field | Value |
|---|---|
| Base URL | `https://generativelanguage.googleapis.com/v1beta/openai` (unchanged; still documented) |
| Auth | `Authorization: Bearer <Gemini API key>` |
| API-key page | `https://aistudio.google.com/apikey` (unchanged; not re-checked) |
| Recommended default | **`gemini-3.8-flash`**: "New Stable", the newest Flash. The OpenAI-compatibility docs use it in their code samples. |
| GET /models | Exists. A forum report says it returned 401 through the compatibility layer in the past. Keep the refresh, but expect it to fail sometimes. |
| Embeddings (same endpoint) | `gemini-embedding-001` (text), `gemini-embedding-2-preview` (multimodal, preview) |

**Chat models (newest first):**

| ID | Notes |
|---|---|
| `gemini-4-argon` | GA 2026-10-13. Flagship reasoning model. $2 / $10 introductory price until 2026-11-30, then $4 / $20. Set `max_tokens` of at least 32,768, because thinking tokens count against it. It was **not verified** that this model is served through the OpenAI-compatible layer. |
| `gemini-3.8-flash` | Stable. |
| `gemini-3.6-flash` | Stable. Still available for production. |
| `gemini-3.5-flash-lite` | $0.30 / $2.50. No shutdown date announced. |
| `gemini-3.1-flash-lite` | GA 2026-05-07. Shutdown 2027-05-07. Replacement: `gemini-3.5-flash-lite`. |
| `gemini-3.1-pro-preview` | Preview. No shutdown date. The only Gemini 3 Pro text model. |

**Remove:**

| ID | Reason |
|---|---|
| `gemini-3.7-flash` | Deprecated in the Gemini API. Requests are auto-routed to `gemini-3.8-flash`. Vertex/Agent Platform still lists it as GA with retirement on 2027-01-28. |
| `gemini-3.5-flash` | Deprecated. Requests are auto-routed to `gemini-3.6-flash`. |
| `gemini-2.5-pro`, `gemini-2.5-flash`, `gemini-2.5-flash-lite` | Not deprecated on paper, but "limited to users who have actively used them in the past". New projects get "no longer available to new users". Do not offer them to new installs. |

**Notes**
- Google now calls the Interactions API the "primary interface", and generateContent is labelled "Legacy". The OpenAI-compatibility page is still live.

**Sources**
- https://ai.google.dev/gemini-api/docs/models
- https://ai.google.dev/gemini-api/docs/models/gemini-3.7-flash (deprecation notice)
- https://ai.google.dev/gemini-api/docs/models/gemini-3.5-flash, https://ai.google.dev/gemini-api/docs/models/gemini-3.5-flash-lite, https://ai.google.dev/gemini-api/docs/models/gemini-3.6-flash
- https://ai.google.dev/gemini-api/docs/deprecations
- https://ai.google.dev/gemini-api/docs/models/gemini-4-argon, https://ai.google.dev/gemini-api/docs/latest-model, https://ai.google.dev/gemini-api/docs/changelog
- https://ai.google.dev/gemini-api/docs/openai (base URL, embedding models)
- https://ai.google.dev/gemini-api/docs/models/gemini-2.5-flash (access limitation)
- https://docs.cloud.google.com/gemini-enterprise-agent-platform/models/gemini/3-7-flash

## 4. DeepSeek

| Field | Value |
|---|---|
| Base URL | `https://api.deepseek.com` (the documented OpenAI-format base URL). The file's `https://api.deepseek.com/v1` was historically accepted as an alias, but this was **not re-verified**. The Anthropic-format base is `https://api.deepseek.com/anthropic`. |
| Auth | Bearer |
| API-key page | `https://platform.deepseek.com/api_keys` (unchanged; not re-checked) |
| Recommended default | **`deepseek-flash`** (currently DeepSeek-V4.1-Flash-1017) |
| GET /models | Yes ("Lists Models" API page) |
| Embeddings | None |

**Chat models:**

| ID | Notes |
|---|---|
| `deepseek-flash` | Current. |
| `deepseek-v4-pro` | Still on the pricing page. A Sept news post said V4-Pro would be routed to V4.1-Flash from 2026-09-14. The newer changelog says service continues. Offer it with a caveat. |

DeepSeek offers only these two IDs, so the 5-12 range does not apply.

**Remove:**

| ID | Reason |
|---|---|
| `deepseek-v4-flash` | Legacy name. The model is retired, and the name temporarily routes to V4.1-Flash at the Flash price. |
| `deepseek-chat`, `deepseek-reasoner` | Fully retired and inaccessible after 2026-07-24 15:59 UTC. |

**Notes**
- JSON Output (`response_format: json_object`) has been documented for the chat endpoint since 2024. Not re-checked for `deepseek-flash`.
- Search/Fetch tool billing starts 2026-11-01.

**Sources**
- https://api-docs.deepseek.com/quick_start/pricing/
- https://api-docs.deepseek.com/updates/
- https://api-docs.deepseek.com/news/news260424/
- https://api-docs.deepseek.com/news/news260910/
- https://api-docs.deepseek.com/ (base URLs)
- https://api-docs.deepseek.com/api/list-models/

## 5. Qwen / Alibaba Cloud Model Studio

| Field | Value |
|---|---|
| Base URL (shared, international) | `https://dashscope-intl.aliyuncs.com/compatible-mode/v1` (Singapore). Still valid. |
| Other regions | US (Virginia): `https://dashscope-us.aliyuncs.com/compatible-mode/v1`. Mainland China (Beijing): `https://dashscope.aliyuncs.com/compatible-mode/v1`. |
| Workspace endpoint | Recommended for production: `https://{WorkspaceId}.{region}.maas.aliyuncs.com/compatible-mode/v1` (region `ap-southeast-1` or `us-east-1`). Needs a key from that workspace. |
| Auth | Bearer |
| API-key page | File has `https://bailian.console.alibabacloud.com/?tab=model#/api-key`. The docs now point to `https://modelstudio.console.alibabacloud.com/...`. Exact key-page path **not verified**. |
| Recommended default | **`qwen3.8-flash`** ($0.15 / $0.47, Singapore). Quality pick: `qwen3.7-plus`. |
| GET /models | The file says yes. Not separately verified for compatible-mode. |
| Embeddings (`/embeddings` on the same compatible-mode base) | `text-embedding-v4` (64-2048 dimensions), `text-embedding-v3`, `qwen3.7-text-embedding` (new) |

**Chat models (newest first):**

| ID | Notes |
|---|---|
| `qwen3.8-max` | $2 / $6. Strongest reasoning. Official examples use it. |
| `qwen3.8-flash` | |
| `qwen3.7-plus` | $0.40 / $1.60 up to 256K tokens. Recommended for balanced cost and performance. |
| `qwen3.7-flash` | |
| `qwen3-max` | Still listed, e.g. for the Responses API. |
| `qwen-plus`, `qwen-max`, `qwen-flash`, `qwen-turbo` | Now under "Legacy models". Still callable in Singapore (batch docs). Keep at most one or two. |

**Notes**
- Qwen3.6 and Qwen3.5 plus/flash variants are "legacy".
- A key works only on endpoints of its own billing plan. A mismatch returns 401.
- The Coding Plan uses separate hosts (`coding-intl.dashscope.aliyuncs.com/v1`).

**Sources**
- https://www.alibabacloud.com/help/en/model-studio/base-url
- https://www.alibabacloud.com/help/en/model-studio/compatibility-of-openai-with-dashscope
- https://www.alibabacloud.com/help/en/model-studio/text-generation-model/
- https://www.alibabacloud.com/help/en/model-studio/model-pricing
- https://docs.modelstudio.console.alibabacloud.com/en/model-studio/qwen3-8-flash
- https://www.alibabacloud.com/help/en/model-studio/qwen3-7-plus
- https://www.alibabacloud.com/help/en/model-studio/embedding
- https://www.alibabacloud.com/help/en/model-studio/batch-interfaces-compatible-with-openai
- https://docs.modelstudio.console.alibabacloud.com/en/model-studio/qwen-api-via-openai-chat-completions

## 6. Mistral AI

| Field | Value |
|---|---|
| Base URL | `https://api.mistral.ai/v1`. US server: `https://api.us.mistral.ai` (listed as server `us` in the `mistralai` 3.5.0 SDK). |
| Auth | Bearer |
| API-key page | `https://console.mistral.ai/api-keys` (unchanged; not re-checked) |
| Recommended default | **`mistral-small-latest`** (Small 4: $0.15 / $0.60, hybrid instruct/reasoning) for cost. **`mistral-medium-latest`** (currently Medium 3.5, $1.5 / $7.5) for quality. |
| GET /models | Yes ("List Available Models" API) |
| Embeddings | `mistral-embed`, `codestral-embed` |

**Chat models (verified API names):**

| ID | Notes |
|---|---|
| `mistral-large-4` / `mistral-large-4-0` | Public Preview, released 2026-10-06. Docs disagree on whether `mistral-large-latest` already points to it or still to Large 3. |
| `mistral-large-latest` | |
| `mistral-medium-3-5` | Pinned. |
| `mistral-medium-latest` | Currently Medium 3.5. |
| `mistral-small-2603` | Small 4, pinned. Listed in the Model Selection Guide. |
| `mistral-small-latest` | Vibe docs say Small 4. An older page says Small 3.2. |
| `ministral-14b-latest`, `ministral-8b-latest`, `ministral-3b-latest` | |
| `magistral-small-latest` | Seen in the SDK. |

**Notes**
- Mistral's lifecycle policy: `*-latest` aliases can move across generations, with silent behaviour or price changes. Pin when that matters.
- Medium 3.1 (`mistral-medium-2508`) retired on 2026-08-31.

**Sources**
- https://docs.mistral.ai/models
- https://docs.mistral.ai/models/mistral-large-4-0
- https://docs.mistral.ai/models/model-selection-guide
- https://docs.mistral.ai/resources/changelogs
- https://docs.mistral.ai/vibe/code/cli/configuration
- https://docs.mistral.ai/studio/conversations/function-calling
- https://docs.mistral.ai/inference/model-lifecycle
- https://docs.mistral.ai/api/endpoint/embeddings
- https://docs.mistral.ai/studio/knowledge-rag/embeddings/code_embeddings
- https://docs.mistral.ai/inference/pricing
- SDK: `mistralai 3.5.0` (`client/sdkconfiguration.py`, tests use `mistral-medium-3-5`)

## 7. Groq

| Field | Value |
|---|---|
| Base URL | `https://api.groq.com/openai/v1` (unchanged; the SDK uses `/openai/v1/...` paths) |
| Auth | Bearer |
| API-key page | `https://console.groq.com/keys` (unchanged; not re-checked) |
| Recommended default | **`openai/gpt-oss-120b`**. Use `openai/gpt-oss-20b` for lowest latency and cost. |
| GET /models | Yes |
| Embeddings | No embedding models offered. The SDK has an `/openai/v1/embeddings` route, but the models page lists none. Keep the "no embeddings" note. |

**Chat models available to free and developer tiers:**

| ID | Notes |
|---|---|
| `openai/gpt-oss-120b` | Production. |
| `openai/gpt-oss-20b` | Production. |
| `qwen/qwen3.8-27b` | Preview. Successor to `qwen/qwen3.6-27b`. Supports `reasoning_effort` including `none`. |
| `groq/compound`, `groq/compound-mini` | Agentic systems with built-in web search and code execution. Current status not confirmed. Keep only if the live list still returns them. |

**Enterprise or limited access (do not offer to everyone):**
- `llama-3.1-8b-instant` and `llama-3.3-70b-versatile`. These are now Enterprise-only.
- `minimaxai/minimax-m2.5`, `qwen/qwen3-vl-32b-instruct` and MiniMax M2.7. These are Enterprise.
- Meta "Muse Spark Open". Limited preview; the ID was not captured.

**Remove:**

| ID | Reason |
|---|---|
| `llama-3.1-8b-instant` (current default) | Shut down for free and developer tiers on 2026-08-16. Replacement: `openai/gpt-oss-20b`. |
| `llama-3.3-70b-versatile` | Same date. Replacement: `openai/gpt-oss-120b` (Groq's table also names `qwen/qwen3.6-27b`, itself shut down since). |
| `meta-llama/llama-4-scout-17b-16e-instruct` | Shut down 2026-07-17. Replacement: `openai/gpt-oss-120b` or `qwen/qwen3.6-27b`. |
| `meta-llama/llama-4-maverick-17b-128e-instruct` | No deprecation entry found, but it is not in the current production or preview list. Treat it as gone. |
| `qwen/qwen3.6-27b` | Shut down 2026-09-14. Replacement: `qwen/qwen3.8-27b`. |

**Sources**
- https://console.groq.com/docs/models
- https://console.groq.com/docs/deprecations
- https://console.groq.com/docs/changelog
- https://console.groq.com/docs/model/qwen/qwen3.6-27b
- https://console.groq.com/docs/reasoning
- https://console.groq.com/docs/structured-outputs
- SDK: `groq 1.7.0`

## 8. Perplexity (status of the Sonar endpoint after 2026-09-27)

**What actually happened.** The migration guide says "Sonar Chat Completions support ended on September 27, 2026."
- Synchronous and streaming `POST /chat/completions` requests are being reformulated into Agent API requests, rolled out gradually by model.
- Async Sonar requests are no longer supported.
- Calls may still answer today, but that is a transitional shim, not a supported API. The official LangChain integration page warns it "will stop working".

**What replaces it**

| API | Endpoint and format |
|---|---|
| Agent API | `POST https://api.perplexity.ai/v1/agent`. `/v1/responses` remains an alias "for OpenAI compatibility". This is the **Responses** wire format: `input` instead of `messages`, `preset` instead of `model`, answer in `output_text`. It is not Chat Completions. |
| Presets | `fast`, `low`, `medium`, `high`. The `preset` field is now marked deprecated in favour of `effort` in the 2026-10-23 SDK. The Sonar mapping differs between pages: the overview says Sonar → fast, Sonar Pro → fast, Reasoning Pro → low, Deep Research → high. The benchmarks page says Sonar Pro → low and Reasoning Pro → medium. |
| Orchestrator API | For Chat Completions format, the docs point to this. It serves open-weight models hosted by Perplexity (example model `perplexity/kimi-k3`), not Sonar web search. Its base URL is documented inconsistently: `https://api.perplexity.ai/router/v1` (dedicated page; models list at `/router/v1/models` with prices) versus `https://api.perplexity.ai/orchestrator/v1` (quickstart). |
| Embeddings | Perplexity has `/v1/embeddings` with `pplx-embed-v1-0.6b` and `pplx-embed-v1-4b` (from the official SDK). |
| Auth | Bearer. Key page `https://www.perplexity.ai/account/api/keys` (unchanged; not re-checked). |

**Recommendation.**
- Either drop the `perplexity` preset from the Chat Completions group, or mark it "legacy / may stop working".
- If kept, the IDs `sonar` and `sonar-pro` are the only ones still referenced. A developer reports the Agent API model table lists only `perplexity/sonar`.
- Remove `sonar-reasoning-pro` and `sonar-deep-research`. Async/deep-research is not supported on the shim.
- Rewrite the note: "Sonar Chat Completions ended 27 Sep 2026; requests are being transparently rewritten to the Agent API for now."

**Sources**
- https://docs.perplexity.ai/docs/agent-api/migrate-from-sonar/overview
- https://docs.perplexity.ai/docs/agent-api/migrate-from-sonar/how-to
- https://docs.perplexity.ai/docs/agent-api/migrate-from-sonar/benchmarks
- https://docs.perplexity.ai/docs/resources/changelog
- https://docs.perplexity.ai/docs/getting-started/quickstart
- https://docs.perplexity.ai/docs/router/quickstart
- https://community.perplexity.ai/t/sonar-is-moving-to-the-agent-api/5802
- https://community.perplexity.ai/t/keep-support-for-sonar-pro-on-the-agents-api/6172
- SDK: `perplexityai 0.46.1`, `generated/api.py` (paths `/chat/completions`, `/v1/responses`, `/v1/embeddings`, `/v1/models`; embedding IDs)

## 9. Azure OpenAI (Microsoft Foundry)

| Field | Value |
|---|---|
| Base URL | `https://YOUR-RESOURCE.openai.azure.com/openai/v1`. This is the v1 GA API; no `api-version` query parameter is needed. Foundry resources may also expose `https://YOUR-RESOURCE.services.ai.azure.com/openai/v1` (not verified in this pass). |
| Auth | Docs show the key passed as `api_key` to the plain `OpenAI()` client (Bearer). Entra ID tokens also work. The file's `api-key` header style was the classic Azure header. Microsoft's v1 docs reviewed here showed Bearer and did not show a literal `api-key` header. **Recommendation:** keep `api-key`, which Azure has always accepted, or test Bearer. |
| Model | The deployment name. No fixed list (unchanged). |
| GET /models | Yes (Foundry REST "Models"; `api-version` optional, default v1) |

**Sources**
- https://learn.microsoft.com/en-us/azure/foundry/openai/api-version-lifecycle
- https://learn.microsoft.com/en-us/azure/developer/ai/how-to/switching-endpoints
- https://learn.microsoft.com/en-us/rest/api/microsoft-foundry/azureopenai/models

## 10. OpenRouter (gateway)

| Field | Value |
|---|---|
| Base URL | `https://openrouter.ai/api/v1` (unchanged) |
| Auth | Bearer |
| API-key page | `https://openrouter.ai/keys` (unchanged; not re-checked) |
| GET /models | Yes. Returns text-output models by default; add `?output_modalities=embeddings` for embedding models. There is also `GET /api/v1/embeddings/models`. |
| Embeddings | Yes: `POST /api/v1/embeddings`, with an optional `dimensions` parameter. |
| Recommended default | `openai/gpt-6-luna` (seen in OpenRouter rankings) |

**Slugs seen on openrouter.ai (all can go in the starting list):**
- `openai/gpt-6-luna`
- `anthropic/claude-sonnet-5.5` (OpenRouter writes Claude versions with dots)
- `google/gemini-3.8-flash`
- `x-ai/grok-4.7`
- `deepseek/deepseek-v4.1-flash` (dated snapshot `deepseek/deepseek-v4.1-flash-1017`)
- `mistralai/mistral-medium-3-5` (seen in a compare URL)

**Current file slugs to remove (not seen on openrouter.ai):** `anthropic/claude-opus-5`, `anthropic/claude-sonnet-5`, `google/gemini-3.6-flash`, `deepseek/deepseek-v4-flash` (legacy), `mistralai/mistral-large-3`. Use "Refresh from provider" for everything else.

**Sources**
- https://openrouter.ai/rankings
- https://openrouter.ai/anthropic
- https://openrouter.ai/enterprise
- https://openrouter.ai/compare/deepseek/deepseek-v4.1-flash/mistralai/mistral-medium-3-5
- https://openrouter.ai/docs/api/api-reference/embeddings/list-all-embeddings-models
- https://openrouter.ai/docs/api/api-reference/models/list-all-models-and-their-properties
- https://openrouter.ai/docs/api/api-reference/embeddings/create-embeddings

## 11. AI/ML API (gateway)

| Field | Value |
|---|---|
| Base URL | `https://api.aimlapi.com/v1`. Chat at `/v1/chat/completions`. |
| Auth | Bearer |
| Key page | `https://aimlapi.com/app/keys` (verified via the official Kilo Code integration doc) |
| GET /models | Yes, `/v1/models`. **No API key required.** Supports filters, e.g. `type=openai/chat-completions`. |
| Note | The file entry is correct. |

**Sources**
- https://docs.aimlapi.com/api-references/service-endpoints/complete-model-list
- https://docs.aimlapi.com/integrations/kilo-code
- https://docs.aimlapi.com/integrations/aider

## 12. Novita AI (gateway)

| Field | Value |
|---|---|
| Base URL | The docs give the OpenAI-SDK base as `https://api.novita.ai/openai`. The models list is documented as `GET https://api.novita.ai/openai/v1/models`. The file's `https://api.novita.ai/openai/v1` produces `/openai/v1/chat/completions` and `/openai/v1/models`, which matches the documented HTTP paths, so keep it. The old `https://api.novita.ai/v3/openai` is outdated. |
| Auth | Bearer |
| Key page | `https://novita.ai/settings/key-management` (unchanged; not re-checked) |
| Embeddings | Mentioned in Novita blog posts. The exact path is not verified. |

**Sources**
- https://novita.ai/docs/guides/llm-api
- https://docs.novita.ai/api-reference/api-reference-overview

## 13. Eden AI (gateway)

| Field | Value |
|---|---|
| Base URL | `https://api.edenai.run/v3` (OpenAI-compatible `POST /v3/chat/completions`). EU host: `https://api.eu.edenai.run/v3`, which returns only EU-eligible models. |
| Auth | Bearer (Eden AI key) |
| Model IDs | `provider/model`, e.g. `openai/gpt-latest`, `vertex/gemini-flash-latest` (from docs examples) |
| GET /models | Yes, `GET /v3/models`, public, no key needed. Add `?view=models` to group by routable name. There are separate listings for embeddings and audio. |
| Embeddings | Yes (Embeddings doc and "List Embeddings Models"). |
| Key page | `https://app.edenai.run/admin/iam/api-keys` (unchanged; **not verified**) |
| Note | The file entry is correct. Two optional defaults seen in the docs: `openai/gpt-latest` and `vertex/gemini-flash-latest`. |

**Sources**
- https://docs.edenai.co/v3/llms/chat-completions
- https://www.edenai.co/docs/v3/integrations/openai-sdk-python
- https://www.edenai.co/docs/v3/llms/listing-models
- https://www.edenai.co/docs/v3/data-governance/eu-endpoint
- https://www.edenai.co/docs/v3/llms/embeddings

## 14. Clusy.io (does it exist?)

**Verdict:** Clusy.io exists, but no Clusy chat-completions gateway (`https://api.clusy.io/v1`) or `clusy-chat` model could be confirmed.

**What was found:**
- Clusy is an "AI Notebook IDE for Jupyter" at `https://www.clusy.io/`. An agent writes and runs notebook cells on cloud CPUs and GPUs.
- It supports bring-your-own Anthropic or OpenAI keys inside its own product.
- There is a status page (`status.clusy.io`) listing "API" and "AI Agent", and docs at `docs.clusy.io` (an "MCP servers" page).
- A Toolify listing calling it a "unified API gateway to 100+ LLMs" contradicts Clusy's own site and is unreliable.

**What was not found:**
- No public documentation for an OpenAI-compatible endpoint.
- Nothing for the key page `clusy.io/dashboard/api-keys` or the model `clusy-chat`.

**Recommendation:** remove the `clusy` preset unless you have private documentation from Clusy. A follow-up search of docs.clusy.io for an API reference was not possible because the search budget had run out.

**Sources**
- https://www.clusy.io/
- https://www.clusy.io/blog/bring-your-own-key
- https://www.clusy.io/changelog
- https://status.clusy.io/
- https://docs.clusy.io/docs/models/mcp-servers

---

## 15. xAI (Grok): add (highest priority)

| Field | Value |
|---|---|
| Base URL | `https://api.x.ai/v1` (OpenAI-compatible; xAI docs show the OpenAI SDK with this `base_url`) |
| Regional | `https://us.api.x.ai/v1` keeps inference in the US, costs 1.1× and currently serves only `grok-4.7` and `grok-4.6`. The same keys work on both hosts. No EU endpoint is documented. |
| Auth | `Authorization: Bearer` |
| API-key page | xAI Console at `https://console.x.ai` (the SDK says "API keys can be created and managed in our API console … console.x.ai"; exact sub-path not verified) |
| Recommended default | **`grok-4.3`**: $1.25 / $2.50, 1M context, default reasoning effort `low`, supports `none`. xAI names it as the replacement for the retired fast models. Quality pick: `grok-4.7`. |
| GET /models | Yes (`/v1/models`, and `us.api.x.ai/v1/models` for the US list) |
| Embeddings | No public embeddings model found. Treat it as "no embeddings". |

**All current Grok model IDs (chat/text, newest first):**

| ID | Notes |
|---|---|
| `grok-4.7` | Flagship, released September 2026. 500K context. $2 / $6 (prompts over 200K tokens: $4 / $12). Reasoning low/medium/high (default high)/xhigh. Knowledge cut-off May 2026. |
| `grok-4.6` | August 2026. 500K context. $2 / $6. Knowledge cut-off 2026-02-01. |
| `grok-4.5` | 500K context. $2 / $6. Alias `grok-4.5-latest`. |
| `grok-4.3` | 1M context. $1.25 / $2.50. Reasoning none/low (default)/medium/high. Alias `grok-4.3-latest`. |
| `grok-4.20-0309-reasoning` | 1M context. $1.25 / $2.50. Aliases `grok-4.20`, `grok-4.20-0309`, `grok-4.20-reasoning-latest`. |
| `grok-4.20-0309-non-reasoning` | Supports structured outputs and tools. Aliases `grok-4.20-non-reasoning`, `grok-4.20-non-reasoning-latest`. |
| `grok-4.20-multi-agent-0309` | Multi-agent research. `reasoning.effort` selects 4 or 16 agents. Aliases `grok-4.20-multi-agent`, `grok-4.20-multi-agent-latest`. Not suitable for form JSON; optional. |

**Not on the public API:**
- `grok-build-0.1`: Cursor and Grok Build only, although the SDK lists it and xAI's migration table maps `grok-code-fast-1` to it.
- `grok-4.7 Fast`: Cursor and Grok Build only.

**Retired on 2026-05-15 12:00 PT** (requests to retired slugs are billed at `grok-4.3` rates):

| Retired ID | Mapped to |
|---|---|
| `grok-4-1-fast-reasoning` | `grok-4.3` (low effort) |
| `grok-4-1-fast-non-reasoning` | `grok-4.3` (none) |
| `grok-code-fast-1` | `grok-build-0.1` |
| `grok-3` | `grok-4.3` (none) |

"Several other models" were retired at the same time. The SDK still lists `grok-4`, `grok-4-0709`, `grok-4-fast-*`, `grok-3-mini*` and `grok-3-fast*`. Do not offer them; they are presumed retired, but not individually confirmed.

**Other model families (not chat):**

| Family | IDs |
|---|---|
| Image | `grok-imagine-image`, `grok-imagine-image-2.0`. `grok-imagine-image-quality` retires 2026-11-02 and is served by `grok-imagine-image-2.0` at low quality. |
| Video | `grok-imagine-video`, `grok-imagine-video-1.5`, `grok-imagine-video-1.5-lite` |
| Voice (speech-to-speech Voice Agent API, not the OpenAI audio endpoints) | `grok-voice-3.0` (alias `grok-voice-latest`). `grok-voice-2.0` is deprecated. |

**Notes**
- Some xAI pages are now branded "SpaceXSI API". It is the same product.

**Sources**
- https://docs.x.ai/developers/models and https://docs.x.ai/developers/models.md
- https://docs.x.ai/developers/models/grok-4.7, https://docs.x.ai/developers/grok-4-7
- https://docs.x.ai/developers/models/grok-4.5, https://docs.x.ai/developers/models/grok-4.3
- https://docs.x.ai/developers/models/grok-4.20-0309-non-reasoning
- https://docs.x.ai/developers/models/grok-4.20-multi-agent-beta-0309
- https://docs.x.ai/developers/pricing
- https://docs.x.ai/developers/rate-limits
- https://docs.x.ai/developers/regions
- https://docs.x.ai/developers/release-notes
- https://docs.x.ai/developers/migration/may-15-retirement
- https://docs.x.ai/developers/migration/imagine-image-quality-nov-2
- https://x.ai/news/grok-4-6
- SDK: `xai-sdk 1.21.0`, `xai_sdk/types/model.py` and `client.py`

## 16. Together AI: add (partially verified)

| Field | Value |
|---|---|
| Base URL | `https://api.together.ai/v1`. Inference from SDK source: the 2026-10-22 SDK's default host is `https://api.together.ai`, and chat is sent to `https://api-inference.together.ai/v1/chat/completions`. The legacy `https://api.together.xyz/v1` was **not re-verified**. |
| Auth | Bearer (`TOGETHER_API_KEY`) |
| Endpoints in SDK | `/v1/chat/completions`, `/v1/embeddings`, `/v1/audio/transcriptions`, `/v1/audio/speech` |
| Models | Model IDs are Hugging Face-style (e.g. `openai/whisper-large-v3` for STT, from the SDK README). No current chat model list was verified. Leave `models` empty and rely on refresh. |
| Key page | Not verified |

**Sources**
- SDK: `together 3.0.0` (`_client.py`, `resources/chat/completions.py`)
- Together is also listed as a provider in https://huggingface.co/docs/inference-providers (`huggingface/hub-docs` source)

## 17. Fireworks AI: add (partially verified)

| Field | Value |
|---|---|
| Base URL | `https://api.fireworks.ai/inference/v1` (SDK uses `/inference/v1/chat/completions`; an Anthropic-format `/inference/v1/messages` also exists) |
| Auth | Bearer |
| Key page | `https://fireworks.ai/account/api-keys` (from the official SDK) |
| Model IDs | Format `accounts/fireworks/models/<name>`. SDK README examples: `accounts/fireworks/models/kimi-k2-instruct-0905`, `accounts/fireworks/models/qwen3-8b`. These may be dated, so rely on refresh. |
| GET /models | Not verified |

**Sources**
- SDK: `fireworks-ai 1.2.30`

## 18. Cerebras: add (partially verified)

| Field | Value |
|---|---|
| Base URL | `https://api.cerebras.ai/v1` (SDK host `https://api.cerebras.ai`, route `/v1/models`; OpenAI-style client) |
| Auth | Bearer |
| Models | `gpt-oss-120b` (the only model in the SDK README examples) |
| GET /models | Yes |
| Embeddings | No |
| Key page | Not verified |

**Sources**
- SDK: `cerebras-cloud-sdk 1.91.0` (2026-07-16)

## 19. Moonshot AI (Kimi): add (partially verified)

| Field | Value |
|---|---|
| Platform | The Kimi K3 README (current) points to `https://platform.kimi.ai` and says Moonshot provides "an OpenAI/Anthropic-compatible API". The older Kimi-K2 README pointed to `https://platform.moonshot.ai`. |
| Model | `kimi-k3`. Always thinking. `reasoning_effort` low/high/max, default max. Multi-turn must echo `reasoning_content` back. Structured output is supported (per README). |
| Base URL | **Not verified in this pass.** Historically `https://api.moonshot.ai/v1` (global) or `https://api.moonshot.cn/v1` (China). Confirm before adding. |
| Auth | Bearer (OpenAI-compatible) |
| Note | Because `max` thinking is the default, set `reasoning_effort: "low"` for fast form generation. |

**Sources**
- https://raw.githubusercontent.com/MoonshotAI/Kimi-K3/main/README.md ("Deployment" and "Model Usage")
- https://raw.githubusercontent.com/MoonshotAI/Kimi-K2/main/README.md

## 20. Zhipu / Z.ai (GLM): add

| Field | Value |
|---|---|
| Base URL | Global: `https://api.z.ai/api/paas/v4`. Mainland China (Zhipu BigModel): `https://open.bigmodel.cn/api/paas/v4`. |
| Auth | Bearer |
| Models (from the SDK README) | `glm-5.2`, `glm-5.1`, `glm-5v-turbo` (vision) |
| Newer models | The GLM-5 README announces **GLM-5.3** and **GLM-5.3-Flash** on the Z.ai API (docs page `docs.z.ai/guides/llm/glm-5.3`). The likely API IDs are `glm-5.3` and `glm-5.3-flash`, but this is **not verified**. |
| Reasoning | GLM-5.2 accepts `reasoning_effort` of `high` or `max` only. GLM-5.3 accepts low/high/max (default max). |
| Recommended default | `glm-5.2` (verified ID). Switch to `glm-5.3-flash` once confirmed. |
| GET /models | Not verified |
| Key page | Not verified |

**Sources**
- SDK: `zai-sdk 0.2.3` (`zai/_client.py` base URLs; README examples)
- https://raw.githubusercontent.com/zai-org/GLM-5/main/README.md

## 21. MiniMax: add (minimal verification)

| Field | Value |
|---|---|
| Platform | `https://platform.minimax.io/`. Text-generation guide: `https://platform.minimax.io/docs/guides/text-generation`. China: `platform.minimaxi.com`. |
| Models | MiniMax-M3 (newest; `thinking` = enabled/adaptive/disabled), MiniMax-M2.7, MiniMax-M2.5. **Exact API model IDs and the OpenAI-compatible base URL were not verified.** Historically the base URL was `https://api.minimax.io/v1` with IDs like `MiniMax-M2`. |

**Sources**
- https://raw.githubusercontent.com/MiniMax-AI/MiniMax-M3/main/README.md
- https://raw.githubusercontent.com/MiniMax-AI/MiniMax-M2.7/main/README.md

## 22. Cohere (Compatibility API): add (verified from Cohere's docs source)

| Field | Value |
|---|---|
| Base URL | `https://api.cohere.ai/compatibility/v1`. The docs also show `https://api.cohere.com/compatibility/v1` for audio. |
| Auth | Bearer |
| Recommended default | **`command-a-plus-05-2026`** (Command A+: the latest Command, a MoE model with vision, reasoning and translation; 128K context, 64K output). It is used in all compatibility-API examples. |
| Embeddings | `/embeddings` supported: `embed-v5.0-pro`, `embed-v5.0-fast`, `embed-v4.0`. The `dimensions` parameter is **not** supported in compatibility mode. |
| Key page | Not verified |
| GET /models | Not listed as supported in the compatibility API. Cohere's native `/v1/models` exists but was not verified for this base. |

**Chat models:**

| ID | Notes |
|---|---|
| `command-a-plus-05-2026` | |
| `command-a-reasoning-08-2025` | |
| `command-a-vision-07-2025` | |
| `command-a-translate-08-2025` | 8K context. |
| `command-a-03-2025` | |
| `command-r7b-12-2024` | |
| `command-r-plus-08-2024`, `command-r-08-2024` | Still "Live". |
| `command-r`, `command-r-plus`, `command-light`, `*-03/04-2024` | **Deprecated 2025-09-15.** Do not offer. |

**Supported parameters (compatibility API)**
- Supported: `response_format` (`json_object` and JSON schema), `tools`, `reasoning_effort` (**only `none` and `high`**), `stream`, `temperature`, `max_tokens`, `stop`, `seed`, `top_p` and penalties.
- Not supported: `n`, `logit_bias`, `parallel_tool_calls`, `service_tier`, `store`, `metadata`.

**Sources**
- https://raw.githubusercontent.com/cohere-ai/cohere-developer-experience/main/fern/pages/v2/text-generation/compatibility-api.mdx (published at docs.cohere.com, "Using the OpenAI SDK")
- https://raw.githubusercontent.com/cohere-ai/cohere-developer-experience/main/fern/pages/models/models.mdx (docs.cohere.com models page)

## 23. Meta Llama API: add? (could not verify current state)

| Field | Value |
|---|---|
| Native base | `https://api.llama.com/v1` (official `llama-api-client` SDK, last released **2025-12-18**) |
| OpenAI-compatible endpoint | Historically `https://api.llama.com/compat/v1/`. **Not verified.** |
| Current models | **Not verified.** Meta's new "Muse Spark Open" appears on Groq in limited preview. |
| Recommendation | Do not add until the docs at llama.developer.meta.com are checked. The SDK has had no release in more than 10 months. |

**Sources**
- SDK: `llama-api-client 0.6.0`
- https://raw.githubusercontent.com/meta-llama/llama-api-python/main/README.md

## 24. NVIDIA NIM / build.nvidia.com: add (partially verified)

| Field | Value |
|---|---|
| Base URL | `https://integrate.api.nvidia.com/v1` (OpenAI-compatible; default in the NVIDIA LangChain connector `langchain-nvidia-ai-endpoints 1.5.0`) |
| Auth | Bearer (`nvapi-...` key from build.nvidia.com; key page **not verified**) |
| Models | The connector's defaults are `nvidia/nemotron-3-nano-30b-a3b` (chat) and `nvidia/llama-nemotron-embed-1b-v2` (embeddings). The catalogue is large; rely on GET `/v1/models`. |
| Note | The connector is published from the `langchain-ai/langchain-nvidia` repo, not an nvidia.com docs page. Treat the model IDs as medium-confidence. |

## 25. Hugging Face Inference Providers (router): add (verified)

| Field | Value |
|---|---|
| Base URL | `https://router.huggingface.co/v1` |
| Auth | Bearer, using an HF fine-grained token with "Make calls to Inference Providers" permission. Key page: `https://huggingface.co/settings/tokens/new?ownUserPermissions=inference.serverless.write&tokenType=fineGrained` |
| Model IDs | Hub repo IDs, with an optional policy suffix `:fastest` (default), `:cheapest` or `:preferred`. Example in the docs: `openai/gpt-oss-120b`. |
| Recommended default | `openai/gpt-oss-120b` |
| GET /models | Yes, `GET /v1/models`, with per-provider pricing, context length and throughput |
| Embeddings | **No.** "This OpenAI-compatible endpoint is currently available for chat completion tasks only." |
| Providers behind it | Baseten, Cerebras, Cohere, DeepInfra, Featherless, Fireworks, Groq, HF Inference, Novita, Nscale, OVHcloud, Public AI, Scaleway, Together, Z.ai, among others |

**Sources**
- https://raw.githubusercontent.com/huggingface/hub-docs/main/docs/inference-providers/index.md (published at https://huggingface.co/docs/inference-providers)

## 26. SambaNova: add (partially verified)

| Field | Value |
|---|---|
| Base URL | `https://api.sambanova.ai/v1` |
| Auth | Bearer |
| Endpoints in SDK | `/chat/completions`, `/embeddings`, `/audio/transcriptions`, `/audio/translations`, `/models` |
| Models | `gpt-oss-120b` (SDK README examples). STT: `Whisper-Large-v3`. Other current IDs not verified. |
| GET /models | Yes |

**Sources**
- SDK: `sambanova 1.13.0` (2026-09-17)

## 27. Other widely used providers

These appear as Inference Providers in Hugging Face's docs, but their OpenAI-compatible base URLs were not verified in this pass:
- **DeepInfra**: historically `https://api.deepinfra.com/v1/openai`
- **Baseten**, **Nscale**, **Scaleway**, **OVHcloud AI Endpoints**

---

## Speech models (for the voice features)

### Speech-to-text (OpenAI-compatible `/audio/transcriptions`)

| Provider | Model IDs | Status / notes | Source |
|---|---|---|---|
| OpenAI | **`gpt-transcribe`** | New, recommended for files. | SDK `AudioModel`; https://developers.openai.com/api/docs/guides/speech-to-text |
| OpenAI | `gpt-4o-transcribe`, `gpt-4o-mini-transcribe` (snapshot `gpt-4o-mini-transcribe-2025-12-15`), `gpt-4o-transcribe-diarize`, `whisper-1` | **All deprecated, shutdown 2027-02-26**, as is `/v1/audio/translations`. Replacements are `gpt-transcribe`, or `gpt-live-transcribe` for streaming via Realtime. `gpt-4o-mini-transcribe-2025-03-20` shuts down 2027-01-20. | https://developers.openai.com/api/docs/deprecations |
| Groq | `whisper-large-v3-turbo` ($0.04/hour, no translation), `whisper-large-v3` ($0.111/hour, supports translation) | Production | https://console.groq.com/docs/speech-to-text, https://console.groq.com/docs/models |
| Cohere | `cohere-transcribe-03-2026` | `language` is **required**. Only `response_format=json`. Accepts flac/mp3/mpeg/mpga/ogg/wav but **not mp4/m4a/webm** (relevant for browser MediaRecorder webm). | Cohere compatibility-api.mdx (above) |
| Mistral | `voxtral-mini-latest` on `/v1/audio/transcriptions` | Realtime variant `voxtral-mini-transcribe-realtime-3`. OpenAI-format parity not verified. | SDK `mistralai 3.5.0` |
| Together | `openai/whisper-large-v3` on `/v1/audio/transcriptions` | | SDK `together 3.0.0` README |
| SambaNova | `Whisper-Large-v3` on `/audio/transcriptions` | | SDK `sambanova 1.13.0` README |

### Text-to-speech (OpenAI-compatible `/audio/speech`)

| Provider | Model IDs | Status / notes | Source |
|---|---|---|---|
| OpenAI | `tts-1`, `tts-1-hd`, `gpt-4o-mini-tts` (snapshots `-2025-03-20`, `-2025-12-15`) | **All deprecated, shutdown 2027-01-06.** OpenAI's named replacement, `gpt-realtime-2.1-mini`, is **not** supported on `/v1/audio/speech` and needs the Realtime API. No new `/audio/speech` model was found. Voices `marin` and `cedar` are recommended. | https://developers.openai.com/api/docs/guides/text-to-speech, https://developers.openai.com/api/docs/deprecations |
| Groq | `canopylabs/orpheus-v1-english` ($22 per 1M characters, vocal directions), `canopylabs/orpheus-arabic-saudi` ($40 per 1M characters) | `playai-tts` and `playai-tts-arabic` were shut down 2025-12-31. | https://console.groq.com/docs/text-to-speech, https://console.groq.com/docs/text-to-speech/orpheus, https://console.groq.com/docs/deprecations |
| Mistral | `voxtral-mini-tts-3` / `voxtral-tts` on `/v1/audio/speech` | Request schema parity with OpenAI is not verified (the SDK uses `voice_id`). | SDK `mistralai 3.5.0` |
| Together | `/v1/audio/speech` exists | Model IDs not verified. | SDK `together 3.0.0` |
| xAI | `grok-voice-3.0` (alias `grok-voice-latest`) | Speech-to-speech Voice Agent API only. **Not** an `/audio/speech` drop-in. `grok-voice-2.0` is deprecated. | https://docs.x.ai/developers/model-capabilities/audio/speech-to-speech |

**Implication for the plugin.** OpenAI's whole Speech API model line and its older STT models have announced shutdowns: TTS on 2027-01-06, STT on 2027-02-26.
- Default STT to `gpt-transcribe`. Keep Groq Whisper as the fast and cheap alternative.
- For TTS, plan either a Realtime-API path or Groq Orpheus before January 2027.

---

## Changes to the current file

### openai
- **Default:** `gpt-5.6-luna` → **`gpt-6-luna`** (or keep `gpt-5.6-luna`, which is still valid).
- **Add:** `gpt-6-astra`, `gpt-6.1-sol`, `gpt-6-luna`, and optionally `gpt-6-sol`.
- **Remove:**
  - `gpt-5.3-chat-latest`: shut down 2026-08-10.
  - `gpt-4.1-nano`: shut down 2026-10-23.
  - `gpt-5.5-pro`: Responses API only, not Chat Completions.
  - `gpt-4.1` and `gpt-4.1-mini`: deprecated, shutdown 2027-01-14.
  - `gpt-5.1` and `gpt-5.4-nano`: deprecated, shutdown 2027-04-01.
- **Optional to drop:** `gpt-5.2` (superseded).
- **Keep:** `gpt-5.6-sol`, `gpt-5.6-terra`, `gpt-5.6-luna`, `gpt-5.5`, `gpt-5.4`, `gpt-5.4-mini`.
- **Embeddings:** unchanged.
- **Code note:** tools in Chat Completions on `gpt-6-astra` and `gpt-6.1-sol` are unsupported. On `gpt-6-luna` and `gpt-6-sol` they require `reasoning_effort: "none"`.

### anthropic
- **Default:** `claude-opus-5` → **`claude-haiku-5-5`** (cost) or **`claude-sonnet-5-5`** (balance).
- **Add:** `claude-opus-5-5`, `claude-sonnet-5-5`, `claude-haiku-5-5`, `claude-fable-5-1`.
- **Keep:** `claude-opus-5`, `claude-sonnet-5`, `claude-fable-5`, `claude-opus-4-8`, `claude-opus-4-7`, `claude-sonnet-4-6`, `claude-haiku-4-5`. `claude-opus-4-6` is legacy; drop it or keep it last.
- **Do not add:** `claude-mythos-*` (restricted access).
- **Key URL:** check whether `console.anthropic.com/settings/keys` now redirects to platform.claude.com.

### gemini
- **Default:** `gemini-3.6-flash` → **`gemini-3.8-flash`**.
- **Add:** `gemini-3.8-flash`, `gemini-4-argon` (its OpenAI-compatible availability is unverified).
- **Remove:**
  - `gemini-3.7-flash`: deprecated, routes to 3.8.
  - `gemini-3.5-flash`: deprecated, routes to 3.6.
  - `gemini-2.5-pro`, `gemini-2.5-flash`, `gemini-2.5-flash-lite`: unavailable to new users and projects.
- **Keep:** `gemini-3.6-flash`, `gemini-3.5-flash-lite`, `gemini-3.1-flash-lite` (shutdown 2027-05-07), `gemini-3.1-pro-preview`.
- **Embeddings:** add `gemini-embedding-2-preview`.

### deepseek
- **base_url:** `https://api.deepseek.com/v1` → `https://api.deepseek.com` (documented form; `/v1` probably still works).
- **Default:** `deepseek-v4-flash` → **`deepseek-flash`**.
- **Models:** `deepseek-flash`, `deepseek-v4-pro` (with the routing caveat).
- **Remove:** `deepseek-v4-flash` (legacy alias of a retired model), `deepseek-chat` and `deepseek-reasoner` (retired 2026-07-24).

### qwen
- **Default:** `qwen-plus` → **`qwen3.8-flash`**.
- **Models:** `qwen3.8-max`, `qwen3.8-flash`, `qwen3.7-plus`, `qwen3.7-flash`, `qwen3-max`. Optionally keep `qwen-plus` and `qwen-flash` as legacy.
- **Remove:** `qwen-turbo` and `qwen-max` (legacy). Optional.
- **Embeddings:** add `qwen3.7-text-embedding`.
- **Note:** also mention the US endpoint (`dashscope-us.aliyuncs.com`) and the workspace-dedicated `{WorkspaceId}.{region}.maas.aliyuncs.com` endpoints.

### mistral
- **Fix every model ID.** The file uses model-card URL slugs (`mistral-medium-3-5-26-04`, `mistral-large-3-25-12`, `mistral-small-4-0-26-03`, `ministral-3-14b-25-12`, `codestral-25-08`, `mistral-embed-23-12`, `codestral-embed-25-05`). No source showed any of these as API names.
- **Replace with:** `mistral-large-latest`, `mistral-large-4` (preview), `mistral-medium-latest`, `mistral-medium-3-5`, `mistral-small-latest`, `mistral-small-2603`, `ministral-14b-latest`, `ministral-8b-latest`, `ministral-3b-latest`, `magistral-small-latest`.
- **Default:** `mistral-medium-3-5` (verified) or `mistral-small-latest`.
- **Embeddings:** `mistral-embed`, `codestral-embed`.
- **Codestral:** `codestral-latest` is a likely ID but was not verified in this pass; add it only after checking.

### groq
- **Default:** `llama-3.1-8b-instant` (shut down for non-enterprise 2026-08-16) → **`openai/gpt-oss-120b`** (or `openai/gpt-oss-20b`).
- **Models:** `openai/gpt-oss-120b`, `openai/gpt-oss-20b`, `qwen/qwen3.8-27b`. Keep `groq/compound` and `groq/compound-mini` only if live.
- **Remove:** both `meta-llama/llama-4-*` models, `llama-3.3-70b-versatile`, `llama-3.1-8b-instant`.
- **Note:** the Llama models now need an Enterprise contract.

### perplexity
- **Update the note:** the Sonar Chat Completions endpoint officially ended on 2026-09-27. Sync and stream calls are temporarily rewritten to the Agent API, and async calls are unsupported.
- **Recommendation:** remove the preset or label it legacy. If kept: models `sonar` and `sonar-pro` only; default `sonar`.
- **Alternatives:** the Responses-format Agent API (`/v1/agent` or `/v1/responses`), or the Orchestrator Chat Completions endpoint. The Orchestrator's base URL conflicts between pages: `/router/v1` versus `/orchestrator/v1`.
- **Embeddings:** `/v1/embeddings` exists, with `pplx-embed-v1-0.6b` and `pplx-embed-v1-4b`.

### azure
- Base URL is correct (v1, no `api-version`). Consider supporting Bearer as well as `api-key`.

### openrouter
- **Default:** `openai/gpt-5.6-luna` → `openai/gpt-6-luna`.
- **Models:** `openai/gpt-6-luna`, `anthropic/claude-sonnet-5.5`, `google/gemini-3.8-flash`, `x-ai/grok-4.7`, `deepseek/deepseek-v4.1-flash`, `mistralai/mistral-medium-3-5`.
- **Remove unverified slugs:** `anthropic/claude-opus-5`, `anthropic/claude-sonnet-5`, `google/gemini-3.6-flash`, `deepseek/deepseek-v4-flash`, `mistralai/mistral-large-3`.
- **Embeddings:** OpenRouter supports `/embeddings`. Add, for example, `openai/text-embedding-3-small`; this slug was not checked on openrouter.ai, so confirm via `/embeddings/models`.

### aimlapi, novita, edenai
- No change needed.
- Eden AI: optionally add the EU host note (`api.eu.edenai.run/v3`) and default model examples.

### clusy
- **Remove.** No public OpenAI-compatible API or `clusy-chat` model could be found. Clusy.io is a Jupyter notebook IDE.

### Add (new presets)
- **`xai`**
  - Base: `https://api.x.ai/v1`. Bearer. Key page: `https://console.x.ai`. Default: `grok-4.3`.
  - Models: `grok-4.7`, `grok-4.6`, `grok-4.5`, `grok-4.3`, `grok-4.20-0309-reasoning`, `grok-4.20-0309-non-reasoning`.
  - Note: the US regional endpoint and the 2026-05-15 retirements.
- **`cohere`**
  - Base: `https://api.cohere.ai/compatibility/v1`. Bearer. Default: `command-a-plus-05-2026`.
  - Models: as listed in section 22.
  - Embeddings: `embed-v5.0-pro`, `embed-v5.0-fast`, `embed-v4.0`.
- **`huggingface`** (gateway)
  - Base: `https://router.huggingface.co/v1`. Bearer. Default: `openai/gpt-oss-120b`. No embeddings.
- **`zai`**
  - Base: `https://api.z.ai/api/paas/v4` (China: `open.bigmodel.cn`). Default: `glm-5.2`.
  - Models: `glm-5.2`, `glm-5.1`, `glm-5v-turbo`; add `glm-5.3` and `glm-5.3-flash` once confirmed.
- **`cerebras`**
  - Base: `https://api.cerebras.ai/v1`. Default: `gpt-oss-120b`. Refresh for the rest.
- **`sambanova`**
  - Base: `https://api.sambanova.ai/v1`. Default: `gpt-oss-120b`. Has `/embeddings` and `/models`.
- **`fireworks`**
  - Base: `https://api.fireworks.ai/inference/v1`. Key page: `https://fireworks.ai/account/api-keys`. Model IDs: `accounts/fireworks/models/...`. Leave `models` empty and refresh.
- **`together`**
  - Base: `https://api.together.ai/v1` (verify; the legacy host is `api.together.xyz`). Leave `models` empty and refresh. Has `/embeddings` and audio.
- **`nvidia`**
  - Base: `https://integrate.api.nvidia.com/v1`. Leave `models` empty and refresh. Example model: `nvidia/nemotron-3-nano-30b-a3b`.
- **Hold until verified:**
  - **Moonshot/Kimi:** the model `kimi-k3` is verified; the base URL is not.
  - **MiniMax:** neither IDs nor base URL are verified.
  - **Meta Llama API:** current status unknown.

---

## Unverified / uncertain

1. **Tool limitation.** WebFetch was non-functional (DNS failure for all hosts) and direct HTTP to provider sites was blocked. Every "verified" item comes from WebSearch extracts of official pages, from official SDK source, or from official GitHub docs and READMEs. Search extracts can lag or misread pages. Spot-check the most important IDs in a browser.
2. **Search budget exhausted.** These providers have only SDK or GitHub evidence: Together, Fireworks, Cerebras, Moonshot, Z.ai, MiniMax, Meta Llama, NVIDIA, SambaNova and Clusy (docs.clusy.io was not searched).
3. **OpenAI**
   - `gpt-6-sol`: pricing row and lifecycle after the GPT-6.1 Sol launch not found.
   - `gpt-5.2`: lifecycle not found.
   - `gpt-5.4-mini`: API deprecation status unclear.
   - Whether any new `/v1/audio/speech` model exists besides the deprecated ones: none was found.
4. **Anthropic.** Exact key-page URL (console.anthropic.com versus platform.claude.com).
5. **Gemini**
   - Whether `gemini-4-argon` is served through `/v1beta/openai`.
   - Exact price of `gemini-3.8-flash`.
   - Status of `gemini-3.5-flash-lite`: one page says "previous generation", but there is no shutdown date.
6. **DeepSeek**
   - Whether `deepseek-v4-pro` still runs V4-Pro or is routed to V4.1-Flash (sources conflict).
   - Whether the `/v1` base path is still accepted.
7. **Qwen.** Exact API-key page URL on the renamed console (modelstudio.console.alibabacloud.com). Whether GET `/models` works on compatible-mode.
8. **Mistral**
   - Whether `mistral-large-latest` points to Large 3 or Large 4.
   - Exact Ministral 3 pinned IDs (only `-latest` aliases verified).
   - Codestral ID: `codestral-latest` is likely but unverified.
   - Whether the file's dated slugs (e.g. `mistral-medium-3-5-26-04`) are also accepted by the API. No source showed them as API names.
9. **Groq**
   - Whether `groq/compound` and `groq/compound-mini` are still live.
   - Which models support strict `json_schema` (check the structured-outputs page).
   - The Muse Spark Open model ID.
10. **Perplexity**
    - How long the Chat Completions shim will keep working.
    - The correct Orchestrator base path: `/router/v1` versus `/orchestrator/v1`.
    - Which of the two preset mappings is current.
11. **xAI**
    - The exact list of "other models" retired on 2026-05-15.
    - Whether `grok-4.20-multi-agent-*` accepts Chat Completions.
    - Exact key-page sub-path in console.x.ai.
    - Whether xAI offers OpenAI-compatible STT/TTS endpoints.
12. **Azure.** Whether the `api-key` header is still accepted on `/openai/v1`. Microsoft's v1 pages show Bearer; historically both worked.
13. **OpenRouter.** Slugs are taken from rankings, compare and enterprise pages, not individual model pages. The embedding model slugs were not checked.
14. **Novita.** Embeddings path.
15. **Eden AI.** API-key page URL.
16. **Together and Cerebras.** API-key pages; GET `/v1/models` path for Together.
17. **Mistral TTS/STT.** Request and response parity with OpenAI's `/audio/*` formats.
