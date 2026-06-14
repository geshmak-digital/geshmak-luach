# includes/autoload/40-boot/

Final bootstrap and initialisation hooks.

This folder loads **after** `10-core/`, `20-features/`, and `30-integrations/`, so code here can safely reference anything defined in those folders.

Use this folder for:

- Hooks that need the entire plugin to be loaded before they fire
- Initialising objects that depend on multiple modules being available
- Registering final priority hooks (e.g. `init` at a late priority)

## Conventions

- Keep files small and focused on wiring things together, not on feature logic (that belongs in `20-features/`).
- Each file must begin with:

```php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```
