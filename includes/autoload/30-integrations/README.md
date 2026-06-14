# includes/autoload/30-integrations/

Third-party plugin integrations (e.g. ACF, Elementor, WooCommerce, Cloudflare Turnstile).

## Conventions

- **Always check** that the required third-party class, function, or constant exists before registering hooks. This prevents fatal errors when the integration plugin is not active:

```php
if ( ! class_exists( 'ACF' ) ) {
    return;
}
// safe to register ACF-dependent hooks here
```

- Name files descriptively: `your-plugin-slug-third-party-name.php`
- Keep integration-specific CSS/JS in an `assets/` subfolder alongside the PHP file that enqueues it.
- Each file must begin with:

```php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```
