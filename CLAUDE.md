# Ace Taxonomy Tools (`ace_taxonomy_tools`)

Batch term editor and retired/archived terms for any taxonomy. Canonical repo: `git@github.com:AceMedia/Ace-Taxonomy-Tools.git`, checked out at
`/var/www/html/plugins/ace-taxonomy-tools`. For AceMedia-wide conventions (British English, commit rules, deploy
patterns) use the **speedforce** skill; this file only holds plugin-specific facts.

**Canonical plan = the GitHub issues on this repo.** Pick work up from there and keep them current.

## Shared plugin rules

- Shared across sites as a submodule. **Keep it generic**: no domains, site slugs, or another site's CPT/meta
  keys. Site-specific behaviour lives in that site's must-use plugin and reaches this plugin through filters.
- Native WP APIs only, no new runtime dependencies. Capability checks, nonces, sanitise on input, escape late.
  Multisite safe (per-site options, `ace_taxonomy_tools_options`).
- Negligible frontend cost: at most one cached lookup per request; invalidate on save. Cache through the object
  cache / Ace-Redis-Cache with a version stamp option, never bare transients (see speedforce conventions).
- Plays nicely with Ace Crawl Enhancer meta (`_ace_seo_*`) and the Ace Redis Cache drop-ins.
- **Bump `ACE_TAXONOMY_TOOLS_VERSION` and the `Version:` header on every release** so asset URLs bust caches.

## Layout

- `ace-taxonomy-tools.php` - header, constants, loader.
- `includes/class-ace-taxonomy-tools-settings.php` - option schema + sanitiser. Add a setting by adding one entry to `fields()`.
- `includes/class-ace-taxonomy-tools.php` - plugin core (hooks wired in `__construct`).
- `includes/admin/` - settings page under **Settings** (`class-ace-taxonomy-tools-admin.php` + `views/settings.php`): tabs →
  fieldset sections → fields from the settings schema; custom tabs render via `ace_taxonomy_tools_settings_tab_content`;
  `class-ace-taxonomy-tools-guide.php` holds the manual (Guide tab + WP help tabs + per-tab guide panels).
- `src/` - admin JS (wp-scripts). `styles/scss/admin.scss` - compiles to `assets/css/admin.css`.
- `build/` and `assets/css/` are committed: consuming sites do not run a build.

## Build & verify

```bash
npm install
npm run build        # wp-scripts + sass
npm run lint:php     # php8.4 -l over every file
```

Verify on a `.pi` site with `node /var/www/html/pi-verify.cjs https://<site>.pi/wp-admin/...` or in the
WordPress Playground MCP.

## Gotchas

- `deleted_term_meta` passes an **array** of meta ids first; the retired-flag listener is untyped for that reason.
- The batch editor only offers fields it can discover (`get_registered_meta_keys('term', $taxonomy)`). Plugins
  that write term meta without `register_term_meta()` need the `ace_taxonomy_tools_fields` filter in a site
  mu-plugin, otherwise their keys are silently ignored on save.
- Retiring goes through `update_term_meta` / `delete_term_meta`, so Ace Revisions logs it when the taxonomy is tracked.
