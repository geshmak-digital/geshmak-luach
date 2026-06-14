# includes/autoload/10-core/

Core plugin utilities. **Loaded first**, before features and integrations.

Place here anything that other modules depend on:

- Abstract base classes
- Helper / utility functions
- Custom post type and taxonomy registration
- Global configuration values or defaults

## Load order

This folder is prefixed `10-` so it loads before `20-features/`, `30-integrations/`, and `40-boot/`. Files within this folder load in natural alphabetical order.

## Conventions

- Prefix all function and class names with your plugin slug to avoid clashes with other plugins.
- Each file should include the standard security guard at the top:

```php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```
