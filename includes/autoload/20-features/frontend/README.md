# includes/autoload/20-features/frontend/

Front-end / public-facing features that run on the site visible to visitors.

Examples of what belongs here:

- Custom output hooks (`the_content`, `wp_footer`, etc.)
- Front-end asset enqueuing (`wp_enqueue_scripts`)
- Custom query modifications for public pages
- Open Graph / SEO output

## Conventions

- Avoid running admin logic here. Use `is_admin()` guards or admin-specific hooks if you need to share a file with admin code.
- Keep front-end CSS/JS in an `assets/` subfolder alongside the PHP file that enqueues it.
- Each file must begin with:

```php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```
