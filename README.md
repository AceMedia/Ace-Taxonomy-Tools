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

See [CHANGELOG.md](CHANGELOG.md) for a plain-English record of every release.
