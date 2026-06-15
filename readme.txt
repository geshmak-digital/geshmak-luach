=== Geshmak! - Luach ===
Contributors: Tom Kriha Goldstein (tom@krihagoldstein.com.au)
Donate link:
Tags: hebcal, jewish calendar, zmanim, candle lighting, parsha, hebrew date, elementor
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.4
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bring the full Hebcal API suite to WordPress, Elementor and Elementor Atomic — candle lighting, parsha, zmanim, Hebrew dates, holidays, leyning and yahrzeits. https://geshmak.com.au

== Description ==

**Geshmak! - Luach** exposes the complete Hebcal REST API through WordPress in three ways:

* **Shortcodes** — one per Hebcal family, with attributes that override the site defaults per instance.
* **Elementor (V3) dynamic tags** — bind any Heading or Text widget to this week's parsha, candle lighting, today's Hebrew date, the next holiday, and more.
* **Elementor Atomic (V4) widgets** — high-value display components (candle lighting, parsha, Hebrew date, zmanim table, and a "today" panel) built natively on the V4 atomic system.

Every surface calls a single cached Hebcal service, so the API is never hit directly and never hammered across multiple client sites.

**Hebcal coverage**

* Jewish Calendar (`/hebcal`): major/minor/modern holidays, Rosh Chodesh, fasts, special Shabbatos, candle lighting, havdalah, parsha, Omer, molad, and daily learning schedules (Daf Yomi, etc.). Diaspora vs Israel, candle offset and havdalah method all configurable.
* Hebrew Date Converter (`/converter`): Gregorian↔Hebrew both ways, with after-sunset handling.
* Leyning / Torah Reading: full kriyah, triennial, weekday, maftir and haftarah.
* Zmanim (`/zmanim`): the full set of halachic times, with elevation support.
* Yahrzeit / Hebrew Birthday / Anniversary.

**Caching & resilience**

Responses are cached aggressively (persistent object cache when available, transients otherwise). On an API failure the last cached value is served (stale-on-error) so a widget never blanks out. A daily WP-Cron warmer pre-fetches the current and next week for the default location.

**Transliteration**

Hebcal returns Sephardi romanisation. Geshmak! - Luach remaps it to the scheme you choose — Modern Ashkenaz by default ("Shavuos", "Parshas") — while always preserving the original Hebrew, wrapped for RTL.

**Attribution**

Calendar, zmanim and leyning data is provided by [Hebcal](https://www.hebcal.com/) and licensed under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/). This plugin is not affiliated with or endorsed by Hebcal.

== Installation ==

1. Upload the `geshmak-luach` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to the **Luach** menu in the admin sidebar and set your default location, Diaspora/Israel, candle offset, havdalah method and transliteration scheme.
4. Drop a shortcode (e.g. `[geshmak_luach_candles]`) into any page, or use the Luach dynamic tags / atomic widgets in Elementor.

See the **Usage** section below for the full list of shortcodes, dynamic tags and atomic widgets, with every option.

== Usage ==

Set your site-wide defaults once under the **Luach** admin menu (location, Diaspora/Israel, candle offset, havdalah method, transliteration, default zmanim, date format, cache lifetime). Then use any of the surfaces below. Anything you pass on an individual shortcode or widget **overrides** the global default just for that instance.

= Quickstart =

* This week's candle lighting:      `[geshmak_luach_candles]`
* This week's parsha:               `[geshmak_luach_parsha]`
* Today's zmanim:                   `[geshmak_luach_zmanim]`
* Today's Hebrew date:              `[geshmak_luach_hebrew_date]`

= Options common to most shortcodes =

* `geonameid` — GeoNames city ID to use instead of the site default (e.g. `geonameid="2158177"` for Melbourne).
* `location` — alias for `geonameid` when given a numeric ID.
* `latitude` / `longitude` / `tzid` — manual coordinates + IANA timezone (e.g. `tzid="Australia/Melbourne"`), used when no GeoNames ID is given.
* `israel` (or `i`) — `1` for the Israel schedule, `0` for Diaspora.
* `translit` — `ashkenaz` (Modern Ashkenaz), `sephardi` (Hebcal default), or `hebrew` (Hebrew only).
* `show_hebrew` — `1`/`0`, show the Hebrew text alongside the transliteration (default `1`).
* `show_credit` — `1`/`0`, show the small Hebcal attribution (default `1`, except date shortcodes).

Date-range options (where supported): `year` (e.g. `2026` or `now`), `month` (`1`–`12`), or an explicit `start` and `end` (`YYYY-MM-DD`).

= Shortcodes =

**1. `[geshmak_luach_candles]` — Candle lighting & havdalah**
Extra options: `b` (candle-lighting minutes before sunset), `havdalah_mode` (`tzeit` or `mins`), `havdalah_mins` (minutes after sunset when `mode=mins`).
Example: `[geshmak_luach_candles geonameid="281184" b="40" havdalah_mode="mins" havdalah_mins="72"]`

**2. `[geshmak_luach_parsha]` — This week's parsha**
Extra options: `leyning` (or `show_leyning`) — `1` to also show the Torah and Haftarah references.
Example: `[geshmak_luach_parsha leyning="1" translit="ashkenaz"]`

**3. `[geshmak_luach_zmanim]` — Zmanim table**
Extra options: `date` (`YYYY-MM-DD`, default today), `start`+`end` for a range, `times` (comma-separated keys, e.g. `times="alotHaShachar,sunrise,sunset"`), `all="1"` to show every available time, `elevation` (metres).
Example: `[geshmak_luach_zmanim date="2026-09-12" times="sunrise,sofZmanShma,chatzot,sunset,tzeit7083deg"]`

**4. `[geshmak_luach_hebrew_date]` — Hebrew date**
Options: `date` (`YYYY-MM-DD`, default today), `after_sunset` (`1` to roll to the next Hebrew day).
Example: `[geshmak_luach_hebrew_date date="2026-09-12" after_sunset="1"]`

**5. `[geshmak_luach_convert]` — Date converter (both directions)**
Options: `direction` (`g2h` Gregorian→Hebrew, or `h2g` Hebrew→Gregorian), plus `date`/`gy`/`gm`/`gd` for Gregorian input, or `hy`/`hm`/`hd` for Hebrew input (`hm` is the month name, e.g. `Kislev`), and `after_sunset`.
Examples:
`[geshmak_luach_convert date="2026-12-15"]`
`[geshmak_luach_convert direction="h2g" hy="5787" hm="Kislev" hd="25"]`

**6. `[geshmak_luach_holidays]` — Holidays**
Family toggles (set any to `1`): `major`, `minor`, `modern`, `roshchodesh`, `fasts`, `special`, `omer`, `molad`, `dafyomi`, `mishnayomi`, `yerushalmi`, `nachyomi`. With none set, the standard holiday set is shown. Also: `upcoming="1"` (only future items) and `limit` (max number of items).
Example: `[geshmak_luach_holidays major="1" upcoming="1" limit="5"]`

**7. `[geshmak_luach_leyning]` — Torah reading detail**
Shows the full kriyah (each aliyah) and Haftarah. Options: `triennial="1"`, `weekday="1"` (Monday/Thursday reading), plus the date-range options.
Example: `[geshmak_luach_leyning start="2026-10-01" end="2026-10-31"]`

**8. `[geshmak_luach_yahrzeit]` — Yahrzeit / Hebrew birthday / anniversary**
Options: `type` (`Yahrzeit`, `Birthday`, or `Anniversary`), `name`, the original Gregorian date as `date` (`YYYY-MM-DD`) or `gy`/`gm`/`gd`, `after_sunset` (`1` if the event was after sunset), and `years` (how many future occurrences to list).
Example: `[geshmak_luach_yahrzeit type="Yahrzeit" name="Moshe ben Yaakov" date="1990-03-15" after_sunset="1" years="5"]`

= Elementor (V3) Dynamic Tags =

In any Heading, Text or Button widget, click the dynamic-tags icon and choose from the **Luach** group. Each tag has an optional GeoNames ID and transliteration override in its settings.

* **Luach: Parsha** — this week's parsha name.
* **Luach: Candle Lighting** — the next candle-lighting time.
* **Luach: Havdalah** — the next havdalah time.
* **Luach: Hebrew Date (today)** — today's Hebrew date.
* **Luach: Next Holiday** — the name of the next upcoming holiday.
* **Luach: Zman (today)** — a single halachic time you pick from a dropdown (sunrise, sunset, etc.).

= Elementor Atomic (V4) Widgets =

Search the V4 widget panel for "Luach" and drag any of these in. Each widget has its own panel options for GeoNames ID override, transliteration, Show Hebrew and Show Hebcal credit.

* **Geshmak Luach Candle Lighting** — candle-lighting / havdalah times.
* **Geshmak Luach Parsha** — this week's parsha (with an optional *Show Torah / Haftarah* toggle).
* **Geshmak Luach Hebrew Date** — today's Hebrew date.
* **Geshmak Luach Zmanim** — a zmanim table (with a *Show all available times* toggle; otherwise shows your configured default set).
* **Geshmak Luach Today Panel** — a compact panel combining today's Hebrew date, the parsha and the next candle lighting.

= Theme template tags (for developers) =

Each Hebcal family is also a PHP function returning a structured array, e.g. `geshmak_luach_get_candle_times()`, `geshmak_luach_get_parsha()`, `geshmak_luach_get_zmanim()`, `geshmak_luach_get_hebrew_date()`, `geshmak_luach_get_holidays()`, `geshmak_luach_get_leyning()`, `geshmak_luach_get_yahrzeit()`, and `geshmak_luach_convert_date()`. They accept the same options as the shortcodes (as an array) and run through the same cache.

== Frequently Asked Questions ==

= Does it work without Elementor? =

Yes. The shortcodes and settings work on any WordPress 6.0+ site. The Elementor dynamic tags load only when Elementor is active; the atomic widgets load only when the Elementor V4 atomic system is active.

= Where does the data come from? =

The [Hebcal](https://www.hebcal.com/) REST API. Responses are cached aggressively so Hebcal is not hit on every page load.

= Can I override the site location for a single shortcode or widget? =

Yes. Every shortcode and Elementor surface accepts per-instance overrides (location / GeoNames ID, Diaspora/Israel, transliteration, date, etc.) that take precedence over the global settings.

== Screenshots ==

1. The Luach settings page.
2. A candle-lighting shortcode rendered on the front end.

== Changelog ==

= 1.0.4 =

* Fix: parsha, candle lighting and leyning surfaces showed the first item of the Gregorian year (e.g. a parsha from January) instead of the upcoming one. They now default to a forward window starting today, so "this week's parsha", "next candle lighting" and "next holiday" are correct. Explicit year/month/start/end attributes still override.
* Cron warmer now primes the exact default surface calls so visitors hit a warm cache.

= 1.0.3 =

* Fix: candle lighting, havdalah and zmanim times displayed about half a day out (evening times shown as morning). Times are now formatted using the UTC offset Hebcal embeds in each value via wp_date(), so they show in the correct local time of the location — regardless of the WordPress site timezone.

= 1.0.2 =

* Fix: the atomic widgets now register and display on Elementor 4.1+. The primitive prop types (String/Boolean) moved to a new namespace (PropTypes\Primitives\) in Elementor 4.1; the widgets now target that location, with a bridge that keeps them working on older 4.0.x atomic builds too. This restores the five Luach atomic widgets that 1.0.1 was safely skipping.

= 1.0.1 =

* Fix: opening the Elementor editor could trigger a critical error on some Elementor versions while the atomic widgets built their panel config. Atomic widgets now smoke-test their editor API and skip gracefully if the installed Elementor atomic version is incompatible, so the editor can never be taken down.
* Atomic widgets no longer ship a base style (all presentation is in the namespaced stylesheet; spacing/colour remain editable via the Style tab).
* Hardened dynamic-tag registration against errors.

= 1.0.0 =

* Initial release.
* Hebcal service layer with aggressive caching, stale-on-error and a daily cron warmer.
* Transliteration remap (Sephardi, Modern Ashkenaz, raw Hebrew) with Hebrew always preserved.
* Settings page (default location, Diaspora/Israel, candle offset, havdalah method, transliteration, zmanim selection, formats, cache TTL, clear-cache).
* Shortcodes: candles, parsha, zmanim, hebrew_date, convert, holidays, leyning, yahrzeit.
* Elementor V3 dynamic tags mirroring the shortcode coverage.
* Elementor V4 atomic widgets: candle lighting, parsha, Hebrew date, zmanim table, and a "today" panel.

== Upgrade Notice ==

Use the latest version to reduce security risks.
