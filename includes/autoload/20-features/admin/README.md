# includes/autoload/20-features/admin/

WordPress admin-panel features and enhancements.

Examples of what belongs here:

- Custom admin columns or meta boxes
- Admin notices
- Custom settings pages or options screens
- Enhancements to the post/page edit screens
- Login page customisations

## Conventions

- Wrap hooks that should only fire in the admin with `is_admin()` where appropriate, or use admin-specific hooks (`admin_init`, `admin_enqueue_scripts`, etc.) which only fire in the admin context anyway.
- Keep admin-only CSS/JS in an `assets/` subfolder alongside the PHP file that enqueues it.
- Each file must begin with:

```php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```
