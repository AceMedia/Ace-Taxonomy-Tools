# Ace Taxonomy Tools

Batch term editor and retired/archived terms for any taxonomy.

Part of the Ace plugin family by Shane Rounce / AceMedia. Anything site-specific stays behind settings or hooks, so the same plugin works on any site.

## Features

- Batch term editor: an inline table, a walk-through mode and bulk apply, with inputs that follow the field type (colour picker, media picker, select, date, URL, number, checkbox).
- Sortable column headers, a parent filter dropdown, and a "Batch edit these terms" button on every term list screen.
- Keyboard model: Enter saves and moves down, Shift+Enter moves up, Ctrl+Enter saves in place, arrows move between rows, Alt+arrows change page and Esc puts a cell back.
- Retired terms: flag terms as retired on the taxonomies you choose, so they drop out of normal term queries.
- Retired term archives can be purged from Ace Redis Cache when that plugin is active.
- REST endpoints (namespace `ace-taxonomy-tools/v1`: `/terms`, `/terms/<id>`, `/bulk`, `/fields`) and WP-CLI commands.
- Built-in Guide tab and WordPress help tabs.

## Requirements

- WordPress 6.4 or later
- PHP 8.1 or later

## Installation

### From a release (recommended)

1. Download the zip from the [latest GitHub Release](https://github.com/AceMedia/Ace-Taxonomy-Tools/releases/latest).
2. In WordPress go to **Plugins > Add New > Upload Plugin**, choose the zip and click **Install Now**.
3. Click **Activate**.

### From source

```bash
git clone https://github.com/AceMedia/Ace-Taxonomy-Tools.git ace-taxonomy-tools
cd ace-taxonomy-tools
npm install
npm run build
npm run plugin-zip
```

Upload the generated zip, or copy the folder into `wp-content/plugins/` and activate it. The compiled assets are committed, so the build is only needed if you change the source.

## Usage

Everything lives under **Settings > Taxonomy Tools**:

- **Batch editor** (first tab): choose a taxonomy, filter, then edit terms inline, step through them, or apply one value to many terms.
- **Editor settings**: which taxonomies and fields the editor offers.
- **Retired terms**: choose the taxonomies that support retiring terms.
- **Guide**: the full manual.

On any term list screen, use the "Batch edit these terms" button to jump straight in.

## Configuration

Settings are on the tabs above. Developers can use filters such as `ace_taxonomy_tools_batch_taxonomies`, `ace_taxonomy_tools_fields`, `ace_taxonomy_tools_retired_taxonomies`, `ace_taxonomy_tools_is_retired`, `ace_taxonomy_tools_terms_query`, `ace_taxonomy_tools_setting`, `ace_taxonomy_tools_settings_fields` and `ace_taxonomy_tools_settings_tabs`, and actions such as `ace_taxonomy_tools_term_updated`, `ace_taxonomy_tools_retired_toggled` and `ace_taxonomy_tools_settings_saved`. The source documents each hook next to its call.

## Development

```bash
npm install
npm start          # watch JavaScript
npm run build      # build JavaScript and compile the Sass
npm run watch:css  # watch Sass
npm run lint:js
npm run lint:php
npm run plugin-zip
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Licence

GPLv2 or later. See [LICENSE](LICENSE) and <https://www.gnu.org/licenses/gpl-2.0.html>.

Author: [Shane Rounce / AceMedia](https://acemedia.ninja). Repository: <https://github.com/AceMedia/Ace-Taxonomy-Tools>
