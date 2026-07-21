# SitePilot AI — WordPress Plugin (standalone MVP)

The local executor of SitePilot AI. This build runs in **temporary standalone mode**: it calls your
AI provider directly (OpenAI-compatible: DeepSeek, OpenAI, OpenRouter, Groq, cloud.ai, or a custom
base URL). No SitePilot Cloud backend is required yet. The cloud path from
[Epic 06](../../docs/tasks/06-wordpress-plugin.md) can be layered on later without changing the UX.

## The safety loop (never bypassed)

```
request → AI plan → preview → your approval → create → activity log
```

Nothing is created on the site until an administrator approves the preview. Payment, password, and
user-registration fields are blocked by the validator.

## Install & try it

1. Copy the `sitepilot-ai/` folder into `wp-content/plugins/` (or zip it and upload via
   **Plugins → Add New → Upload**).
2. Activate **SitePilot AI**.
3. **SitePilot AI → Settings** → pick a provider (default **DeepSeek**), paste your API key,
   **Test connection**, and **Save**. The key is encrypted at rest and never shown again.
4. **SitePilot AI → Copilot** → type e.g. *"Create a contact form with name, email, phone and
   message"* → **Generate preview** → review → **Approve & create form**.
5. Copy the shortcode (`[sitepilot_form id="123"]`) into any page/post to display the form.

## How it is organized

```
includes/
  Core/        bootstrap, container, settings, activation
  Security/    KeyVault (encrypt BYOK key), Redactor (scrub logs)
  Providers/   ProviderInterface, OpenAiCompatibleProvider (direct API), ProviderManager
  Skills/      FormPlanSchema, FormPlanValidator, CreateFormSkill  (AI output is untrusted → validated)
  Connectors/  ConnectorInterface, ConnectorRegistry, NativeFormConnector, EasyFormBuilderConnector
  Execution/   ActionStore (pending action + expiry), ActionExecutor (approve → run → log)
  Logging/     Logger (redacted ring buffer)
  Admin/       AdminMenu, CopilotPage, SettingsPage, LogsPage
  Api/         RestRoutes, CopilotController  (capability + REST nonce protected)
  Frontend/    FormShortcode (render + submit)
assets/        admin UI (css/js) + front-end form css
tests/         PHPUnit unit tests for the pure logic
```

## Easy Form Builder integration

The built-in **SitePilot Forms** connector always works. To route creation through **Easy Form
Builder** instead, register the two filters below from a small integration (its exact write API is
not bundled here on purpose — plug in the verified calls):

```php
add_filter( 'sitepilot_ai_efb_available', '__return_true' );

add_filter( 'sitepilot_ai_efb_create_form', function ( array $default, array $plan, array $ctx ) {
    // Build the form via the real Easy Form Builder API, then return:
    return array(
        'status'    => 'success',
        'connector' => 'Easy Form Builder',
        'form_id'   => $created_id,
        'edit_url'  => get_edit_post_link( $created_id, 'raw' ),
        'shortcode' => '[your_efb_shortcode id="' . $created_id . '"]',
        'is_draft'  => ( 'draft' === ( $ctx['status'] ?? 'draft' ) ),
        'warnings'  => array(),
    );
}, 10, 3 );
```

With both registered (and Easy Form Builder active), the registry prefers it automatically when
"Use Easy Form Builder when available" is on in Settings.

## Running the tests

Unit tests cover the pure logic (validation, encryption, provider parsing, connector checks,
redaction) and run without a WordPress install:

```bash
cd apps/wordpress-plugin/sitepilot-ai
composer install
composer test        # phpunit
composer lint        # phpcs (WordPress standards)
composer analyze     # phpstan
```

> Integration tests that exercise real WordPress (post creation, REST, nonces) belong in a
> `wp-env`/WP test-suite harness and are the next step after this standalone slice.

## Security notes

- Provider key: encrypted with libsodium (AES-256-GCM fallback); decrypted only server-side; never
  echoed to the browser; removable from Settings.
- REST endpoints require `manage_options` + the REST cookie nonce.
- AI output is schema-validated before preview; forbidden field types are rejected.
- Approved actions expire after 15 minutes and are single-use (cannot be replayed).
- Logs are redacted (no keys/tokens).
