# Ace Taxonomy Tools changelog

Plain-English record of what changed in each release. Dates are when the version was pushed.

## 0.3.0 - 10 September 2026
- Batch editor inputs now follow the field: colour picker, media picker, select, date, URL, number, checkbox. Column headers sort the page.
- Enter saves a row and moves to the same field on the next row (Shift+Enter goes up).
- Bulk apply's value box follows the chosen field's type.
- Retired term archives are purged from Ace Redis Cache through its new purge-by-URL action.

## 0.2.1 - 10 September 2026
- A settings save now takes effect immediately in the same request.

## 0.2.0 - 10 September 2026
- Everything now lives under Settings → Taxonomy Tools; the top-level menu is gone.
- Batch editor: the parent filter is a dropdown of parents, and every term list screen has a "Batch edit these terms" button.
- Guide panel on every tab, WordPress help tabs and a full Guide tab.

## 0.1.0 - 10 September 2026
- First cut: batch term editor (inline table, walk-through, bulk apply), retired terms, WP-CLI commands.
