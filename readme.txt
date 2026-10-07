=== Ace Taxonomy Tools ===
Contributors: shanerounce
Tags: taxonomy, terms, bulk edit, archive, categories
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Batch term editor and retired/archived terms for any taxonomy.

== Description ==

Batch term editor and retired/archived terms for any taxonomy.

Part of the Ace plugin family (Ace Crawl Enhancer, Ace Redis Cache, Ace Community Events). Generic by design: anything site-specific stays behind settings or hooks so the same plugin can be dropped into any site.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install the zip from Plugins > Add New.
2. Activate it through the Plugins screen.
3. Configure it from the plugin's settings screen where one exists.

== Frequently Asked Questions ==

= Where do I report a bug or ask for a feature? =

Open an issue on the plugin's GitHub repository.

== Changelog ==

= 0.3.1 =
* Batch editor keyboard model: a legend above the table and a live "where am I" line (term, field, row, page). Enter saves and moves down, Shift+Enter up, Ctrl+Enter saves in place, arrows move between rows in the same field, Ctrl+arrows jump to the first or last row, Alt+arrows change page, Esc puts a cell back.
= 0.3.0 =
* Batch editor inputs now follow the field: colour picker, media picker, select, date, URL, number, checkbox. Column headers sort the page.
* Enter saves a row and moves to the same field on the next row (Shift+Enter goes up).
* Bulk apply's value box follows the chosen field's type.
* Retired term archives are purged from Ace Redis Cache through its new purge-by-URL action.
= 0.2.1 =
* A settings save now takes effect immediately in the same request.
= 0.2.0 =
* Everything now lives under Settings → Taxonomy Tools; the top-level menu is gone.
* Batch editor: the parent filter is a dropdown of parents, and every term list screen has a "Batch edit these terms" button.
* Guide panel on every tab, WordPress help tabs and a full Guide tab.
= 0.1.0 =
* First cut: batch term editor (inline table, walk-through, bulk apply), retired terms, WP-CLI commands.
