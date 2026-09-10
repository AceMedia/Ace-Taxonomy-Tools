<?php
/**
 * The manual: rendered on the Guide tab and as WordPress help tabs.
 *
 * @package Ace_Taxonomy_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Taxonomy_Tools_Guide {

    /**
     * @return array<string, array{title:string, icon:string, content:string}>
     */
    public static function sections(): array {
        $sections = [
            'start' => [
                'title'   => __( 'Getting started', 'ace-taxonomy-tools' ),
                'icon'    => 'welcome-learn-more',
                'content' => '
<p>Two jobs, one plugin. The <strong>batch editor</strong> lets you change a field on hundreds of terms without opening each one in a new tab. <strong>Retired terms</strong> let you park a finished season, league or range: hidden from editors, archive still live and served static, optional "Historical" label.</p>
<ol>
<li><strong>Batch editor</strong> tab: pick a taxonomy and start editing straight away.</li>
<li><strong>Editor settings</strong>: optionally limit which taxonomies are bulk-editable.</li>
<li><strong>Retired terms</strong>: tick the taxonomies that can be retired, then use the Retired checkbox on any term.</li>
</ol>',
            ],
            'batch' => [
                'title'   => __( 'Batch editor', 'ace-taxonomy-tools' ),
                'icon'    => 'editor-table',
                'content' => '
<p><strong>Choose a taxonomy.</strong> For hierarchical taxonomies you can filter to the children of one parent (enter its id; <code>0</code> means top level, <code>-1</code> means everything). Search narrows by name.</p>
<p><strong>Tick the fields you want to edit.</strong> Core fields (name, slug, description, parent) are always there; the rest come from whatever term meta is registered for that taxonomy, plus the Retired flag where enabled. Untick what you are not touching so the table stays narrow.</p>
<p><strong>Edit inline.</strong> Inputs follow the field: colour picker, media picker, select, date, URL. Press <kbd>Enter</kbd> to save and drop to the same field on the next row (<kbd>Shift+Enter</kbd> goes up); click a column header to sort the page. Each row saves by itself the moment you leave a field. The status column tells you what changed; a row that did not change is skipped, so nothing is rewritten needlessly and Ace Revisions only sees real edits.</p>
<p><strong>Bulk apply.</strong> Tick the rows (or "All" on this page), pick one field, enter a value and press Apply. Booleans take <code>1</code> or empty. The run is grouped under one Ace Revisions batch id.</p>
<p><strong>Walk-through.</strong> Switch it on to see one term at a time with Previous / Next. Useful for the "go through every team and fill in two things" job. Where you got to is remembered in the browser.</p>',
            ],
            'fields' => [
                'title'   => __( 'Where fields come from', 'ace-taxonomy-tools' ),
                'icon'    => 'editor-code',
                'content' => '
<p>The editor discovers fields with <code>get_registered_meta_keys( \'term\', $taxonomy )</code>. A plugin that registers its term meta with <code>register_term_meta()</code> appears automatically with the right type (text, number, boolean).</p>
<p>A plugin that writes term meta without registering it is invisible to the editor and its keys are ignored on save. Expose them from a site mu-plugin:</p>
<pre>add_filter( \'ace_taxonomy_tools_fields\', function ( $fields, $taxonomy ) {
    if ( \'ace_team\' === $taxonomy ) {
        $fields[\'primary_colour\'] = [ \'label\' => \'Primary colour\', \'type\' => \'string\', \'source\' => \'meta\' ];
        $fields[\'hidden\']         = [ \'label\' => \'Hidden\', \'type\' => \'boolean\', \'source\' => \'meta\' ];
    }
    return $fields;
}, 10, 2 );</pre>
<p>Add an <code>options</code> array to a field to get a select, or set <code>input</code> to <code>color</code>, <code>media</code>, <code>textarea</code>, <code>date</code>, <code>url</code> or <code>number</code>. Keys containing "colour" and ids ending in image/logo/icon are detected automatically.</p>',
            ],
            'retired' => [
                'title'   => __( 'Retired terms', 'ace-taxonomy-tools' ),
                'icon'    => 'archive',
                'content' => '
<p>Retiring is a term meta flag (<code>_ace_retired</code>). Tick it on the term edit screen, in the batch editor, or with <code>wp ace-tax retire</code>. What happens:</p>
<ul>
<li><strong>Editors no longer see it</strong> in term pickers (block editor and classic). Anyone with <code>manage_categories</code> still does, and posts already in the term keep it.</li>
<li><strong>The archive keeps working.</strong> No 404, no redirect. It is served with a 30-day cache TTL through Ace Redis Cache and purged only when the flag toggles or a post in the term is saved, so it costs nothing to keep.</li>
<li><strong>Optional label.</strong> The "Historical label" from settings is appended in brackets to the archive title and the SEO title, and the body gets an <code>ace-retired-archive</code> class for the theme.</li>
</ul>
<p>Unretire at any time; the archive is purged and regenerates on the next visit.</p>',
            ],
            'cli' => [
                'title'   => __( 'WP-CLI', 'ace-taxonomy-tools' ),
                'icon'    => 'editor-code',
                'content' => '
<pre>wp ace-tax retire &lt;taxonomy&gt; &lt;id|slug&gt;... [--unretire]
wp ace-tax set &lt;taxonomy&gt; --field=&lt;key&gt; --value=&lt;value&gt; (--ids=&lt;csv&gt; | --parent=&lt;id&gt; | --all) [--dry-run]
wp ace-tax list &lt;taxonomy&gt; [--parent=&lt;id&gt;] [--fields=id,name,slug,&lt;meta&gt;] [--format=json]</pre>
<p><code>set</code> honours the same field rules as the editor and skips unchanged terms. Always try <code>--dry-run</code> first. Runs are grouped under one Ace Revisions batch id.</p>',
            ],
            'hooks' => [
                'title'   => __( 'Hooks for developers', 'ace-taxonomy-tools' ),
                'icon'    => 'admin-plugins',
                'content' => '
<table>
<tr><th><code>ace_taxonomy_tools_fields</code></th><td>Add or adjust editable fields per taxonomy</td></tr>
<tr><th><code>ace_taxonomy_tools_batch_taxonomies</code></th><td>Filter the taxonomies the editor offers</td></tr>
<tr><th><code>ace_taxonomy_tools_terms_query</code></th><td>Filter the term query behind the table</td></tr>
<tr><th><code>ace_taxonomy_tools_sanitise_value</code></th><td>Sanitise a value before it is written</td></tr>
<tr><th><code>ace_taxonomy_tools_term_updated</code></th><td>Action after a term changed (term_id, taxonomy, changed keys)</td></tr>
<tr><th><code>ace_taxonomy_tools_retired_taxonomies</code></th><td>Filter which taxonomies can be retired</td></tr>
<tr><th><code>ace_taxonomy_tools_is_retired</code></th><td>Override the flag for a term</td></tr>
<tr><th><code>ace_taxonomy_tools_retired_toggled</code></th><td>Action when the flag changes (term_id, taxonomy, retired)</td></tr>
<tr><th><code>ace_taxonomy_tools_purge_url</code></th><td>Action asking the cache layer to purge one URL</td></tr>
<tr><th><code>ace_taxonomy_tools_retired_label</code>, <code>ace_taxonomy_tools_static_ttl</code></th><td>Filter the label and TTL</td></tr>
</table>
<p>Example: retire every event whose season ended, from a site mu-plugin on a daily cron, by calling <code>Ace_Taxonomy_Tools_Retired::set_retired( $term_id, true )</code>.</p>',
            ],
        ];
        $sections['changelog'] = [
            'title'   => __( "What's new", 'ace-taxonomy-tools' ),
            'icon'    => 'megaphone',
            'content' => self::changelog_html(),
        ];
        return apply_filters( 'ace_taxonomy_tools_guide_sections', $sections );
    }

    /**
     * CHANGELOG.md as HTML (headings, bullets, paragraphs only).
     */
    public static function changelog_html(): string {
        $file = ACE_TAXONOMY_TOOLS_PATH . 'CHANGELOG.md';
        if ( ! file_exists( $file ) ) {
            return '';
        }
        $html = '';
        $list = false;
        foreach ( file( $file, FILE_IGNORE_NEW_LINES ) as $line ) {
            if ( 0 === strpos( $line, '# ' ) ) {
                continue;
            }
            if ( 0 === strpos( $line, '## ' ) ) {
                $html .= ( $list ? '</ul>' : '' ) . '<h4>' . esc_html( substr( $line, 3 ) ) . '</h4>';
                $list  = false;
            } elseif ( 0 === strpos( $line, '- ' ) ) {
                $html .= ( $list ? '' : '<ul>' ) . '<li>' . esc_html( substr( $line, 2 ) ) . '</li>';
                $list  = true;
            } elseif ( '' !== trim( $line ) ) {
                $html .= ( $list ? '</ul>' : '' ) . '<p>' . esc_html( $line ) . '</p>';
                $list  = false;
            }
        }
        return $html . ( $list ? '</ul>' : '' );
    }

    public static function render(): void {
        echo '<div class="ace-guide">';
        foreach ( self::sections() as $id => $section ) {
            printf( '<section id="guide-%1$s"><h3><span class="dashicons dashicons-%2$s" aria-hidden="true"></span>%3$s</h3>%4$s</section>', esc_attr( $id ), esc_attr( $section['icon'] ), esc_html( $section['title'] ), wp_kses_post( $section['content'] ) );
        }
        echo '</div>';
    }
}
