# assets/

Plugin assets for WordPress.org and general use.

This folder is **not** scanned by the PHP autoloader — place non-PHP files here freely.

## Contents

| File | Purpose |
|---|---|
| `screenshot-1.png` (etc.) | Screenshots shown on the WordPress.org plugin page |
| `icon-128x128.png` | Plugin icon (128 × 128 px) |
| `icon-256x256.png` | Plugin icon (256 × 256 px, retina) |
| `banner-772x250.png` | Plugin banner (772 × 250 px) |
| `banner-1544x500.png` | Plugin banner (1544 × 500 px, retina) |

## CSS / JS

If the plugin needs front-end or admin assets (CSS, JS, images), prefer placing them alongside the feature PHP file that enqueues them, inside the relevant `includes/autoload/` subfolder. Reserve this `assets/` folder for WordPress.org presentation assets.
