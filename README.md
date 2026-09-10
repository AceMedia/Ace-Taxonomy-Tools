# Ace Taxonomy Tools

Batch term editor and retired/archived terms for any taxonomy.

Part of the Ace plugin family (Ace Crawl Enhancer, Ace Redis Cache, Ace Community Events). Generic by design:
anything site-specific stays behind settings or hooks so the same plugin can be submoduled into every site.

**Requires:** WordPress 6.4+, PHP 8.1+. **Licence:** GPLv2 or later.

## Install

Add as a submodule in the site's plugins directory and activate:

```bash
git submodule add git@github.com:AceMedia/Ace-Taxonomy-Tools.git assets/plugins/ace-taxonomy-tools
```

Build output is committed, so no build step is needed on deploy.

## Develop

```bash
npm install
npm run build
```

## Hooks

- `ace_taxonomy_tools_settings_fields` - add or adjust settings fields (schema array).
- `ace_taxonomy_tools_settings_tabs` - add or adjust settings tabs.
- `ace_taxonomy_tools_setting` - filter a single resolved setting value.
- `ace_taxonomy_tools_settings_saved` - action after settings are saved.

Plugin-specific hooks are documented in the source next to each `apply_filters` / `do_action`.

## Where things are

- **Settings → Taxonomy Tools**: the Batch editor is the first tab; Editor settings and Retired terms follow; Guide holds the manual. Each term list screen links straight to the batch editor.

## Changelog

### 0.2.0
- Everything now lives under **Settings → Taxonomy Tools** (no top-level menu): Batch editor, Editor settings, Retired terms, Guide.
- Batch editor: parent filter is a dropdown of parents; "Batch edit these terms" link on each term list screen.
- Guide panels on every tab, WordPress help tabs, full Guide tab.

### 0.1.0
- Initial scaffold: settings page, options store, build tooling.

## Features

- **Batch editor** (Taxonomy Tools → Batch editor): pick a taxonomy, filter by parent or search, tick the fields to
  edit, then change values inline. Rows save individually over REST and unchanged values are skipped. Tick rows and
  apply one value to all of them, or switch to walk-through mode to step one term at a time.
- **Retired terms**: a `_ace_retired` flag on the taxonomies chosen in settings. Retired terms are hidden from
  editor term pickers for non-admins, their archives get a long cache TTL (purged when the flag toggles or a post
  in the term is saved) and an optional "Historical" label on the archive and SEO title.

Fields come from `register_term_meta()` for the taxonomy plus core fields; add undiscoverable keys with the
`ace_taxonomy_tools_fields` filter.

## REST

```
GET  /wp-json/ace-taxonomy-tools/v1/fields?taxonomy=category
GET  /wp-json/ace-taxonomy-tools/v1/terms?taxonomy=category&parent=0&search=&page=1&per_page=50
POST /wp-json/ace-taxonomy-tools/v1/terms/<id>   { "taxonomy": "...", "fields": { "name": "..." } }
POST /wp-json/ace-taxonomy-tools/v1/bulk         { "taxonomy": "...", "ids": [..], "field": "...", "value": ... }
```

## WP-CLI

```bash
wp ace-tax retire <taxonomy> <id|slug>... [--unretire]
wp ace-tax set <taxonomy> --field=<key> --value=<value> (--ids=<csv> | --parent=<id> | --all) [--dry-run]
wp ace-tax list <taxonomy> [--parent=<id>] [--fields=id,name,slug,<meta>] [--format=json]
```

## Plugin hooks

- `ace_taxonomy_tools_fields`, `ace_taxonomy_tools_batch_taxonomies`, `ace_taxonomy_tools_retired_taxonomies`.
- `ace_taxonomy_tools_terms_query`, `ace_taxonomy_tools_sanitise_value`, `ace_taxonomy_tools_term_updated`.
- `ace_taxonomy_tools_is_retired`, `ace_taxonomy_tools_retired_label`, `ace_taxonomy_tools_static_ttl`.
- `ace_taxonomy_tools_retired_toggled` (term_id, taxonomy, retired), `ace_taxonomy_tools_purge_url` (url, term).
