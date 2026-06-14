# includes/autoload/20-features/shortcodes/

WordPress shortcode definitions.

Each file should register one shortcode, or a small group of closely related shortcodes.

## Conventions

- Register shortcodes with `add_shortcode()`.
- Wrap the callback in `function_exists()` to avoid fatal errors if the file is somehow loaded twice:

```php
if ( ! function_exists( 'your_plugin_my_shortcode' ) ) {
    function your_plugin_my_shortcode( $atts ) {
        // ...
    }
    add_shortcode( 'your-shortcode-tag', 'your_plugin_my_shortcode' );
}
```

- Always return output as a string (never `echo` directly from a shortcode callback).
- Each file must begin with:

```php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```
