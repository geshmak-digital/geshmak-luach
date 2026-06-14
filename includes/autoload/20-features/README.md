# includes/autoload/20-features/

Feature modules for this plugin, grouped into subfolders by context.

## Subfolders

| Subfolder | Contents |
|---|---|
| `admin/` | WordPress admin-panel features |
| `frontend/` | Front-end / public-facing features |
| `shortcodes/` | WordPress shortcode definitions |

Add a new subfolder whenever a new feature context is needed. Subfolders load in natural alphabetical order, and files within each subfolder also load in natural alphabetical order.

## Conventions

- One feature per file (or one closely related group of features).
- Name files descriptively: `your-plugin-slug-feature-name.php`
- Each file must begin with the security guard:

```php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```
