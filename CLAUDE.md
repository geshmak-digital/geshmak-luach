# CLAUDE.md — Geshmak! Plugin: Luach

> This file is read automatically by Claude Code at the start of every session in this repository.

---

## This Plugin

- **Display name:** Geshmak! - Luach
- **Slug:** `geshmak-luach`
- **Main file:** `geshmak-luach.php`
- **GitHub repo (PUBLIC):** https://github.com/geshmak-digital/geshmak-luach/
- **Purpose:** Exposes the full Hebcal API suite (Jewish calendar, candle lighting, parsha, zmanim, Hebrew dates, holidays, leyning, yahrzeit) via shortcodes, Elementor V3 dynamic tags, and Elementor V4 atomic widgets.

---

## Plugin-specific rules

- **Public repo → tokenless updates.** The Plugin Update Checker must NOT call `setAuthentication()`. Never commit a GitHub PAT to this repo.
- **One data spine.** Every surface (shortcode, dynamic tag, atomic widget) calls `Geshmak_Luach_Hebcal_Service` in `10-core/`. No surface ever calls Hebcal directly.
- **Cache aggressively, fail soft.** Persistent object cache when available, transients otherwise. On API failure serve the last cached value (stale-on-error); never blank a surface.
- **Transliteration.** Hebcal returns Sephardi romanisation; `geshmak-luach-transliteration.php` remaps to the configured scheme (Modern Ashkenaz default). Always keep the original Hebrew, RTL-wrapped.
- **Hebcal CC BY 4.0 attribution** must remain in output and `readme.txt`.

---

## Geshmak! conventions (summary)

- `<?php /** בס״ד` header + `if ( ! defined( 'ABSPATH' ) ) { exit; }` on every PHP file.
- All code prefixed `geshmak_luach_`; constants `GESHMAK_LUACH_*`; text domain `geshmak-luach`.
- Tabs. Sanitise input, escape output (`esc_html`/`esc_attr`/`esc_url`/`wp_kses_post`), nonces on admin forms.
- Autoload folders load in natural sort order: `10-core` → `20-features` → `30-integrations` → `40-boot` → `99-dev`. Add modules to the right folder; never touch the main file.
- Version is read from the plugin header via `get_file_data()` — bump in one place, then sync `readme.txt` `Stable tag` + changelog.

---

## Stack

WordPress 6.0+, PHP 7.4+, WPMU DEV hosting, Elementor Pro, Elementor Atomic (V4). The atomic widget base class only exists when the `e_atomic_elements` experiment is active — always double-guard atomic code.
