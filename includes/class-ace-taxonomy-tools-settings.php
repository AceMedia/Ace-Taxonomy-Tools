<?php
/**
 * Settings store for Ace Taxonomy Tools.
 *
 * One option (ace_taxonomy_tools_options), one schema, one sanitiser. The settings page
 * renders from the same schema: tabs → sections (fieldsets) → fields.
 *
 * @package Ace_Taxonomy_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Taxonomy_Tools_Settings {

    /** @var array|null Per-request cache of the merged options; cleared on update(). */
    private static $cache = null;

    const OPTION = 'ace_taxonomy_tools_options';

    /**
     * Field schema keyed by option key.
     * type: checkbox | text | textarea | number | post_types | taxonomies | select
     * tab / section place the field on the settings page.
     */
    public static function fields(): array {
        $fields = [
            'batch_taxonomies' => [
                'tab' => "batch-settings",
                'section' => "batch-scope",
                'type' => "taxonomies",
                'label' => "Taxonomies in the batch editor",
                'help' => "Leave all unticked to allow every taxonomy that has a UI.",
            ],
            'batch_page_size' => [
                'tab' => "batch-settings",
                'section' => "batch-paging",
                'type' => "number",
                'label' => "Terms per page",
                'default' => 50,
                'min' => 10,
                'max' => 500,
            ],
            'retired_taxonomies' => [
                'tab' => "retired",
                'section' => "retired-scope",
                'type' => "taxonomies",
                'label' => "Taxonomies that can be retired",
                'help' => "Adds a Retired flag to the term edit screen, the term list and the batch editor.",
            ],
            'retired_label' => [
                'tab' => "retired",
                'section' => "retired-behaviour",
                'type' => "text",
                'label' => "Historical label",
                'help' => "Appended in brackets to the archive title and SEO title of a retired term. Leave empty for no label.",
                'default' => "Historical",
            ],
            'hide_from_pickers' => [
                'tab' => "retired",
                'section' => "retired-behaviour",
                'type' => "checkbox",
                'label' => "Hide retired terms from editor term pickers for non-admins",
                'help' => "Users with manage_categories still see them.",
                'default' => 1,
            ],
            'static_archives' => [
                'tab' => "retired",
                'section' => "retired-behaviour",
                'type' => "checkbox",
                'label' => "Serve retired term archives as long-lived static HTML",
                'help' => "30-day cache TTL via Ace Redis Cache, purged when the flag toggles or a post in the term is saved.",
                'default' => 1,
            ],
        ];

        /**
         * Lets a site (via a must-use plugin) add or adjust settings fields.
         *
         * @param array $fields Schema keyed by option key.
         */
        return apply_filters( 'ace_taxonomy_tools_settings_fields', $fields );
    }

    /**
     * Tabs: id, label, dashicon, help (guide panel text), sections; custom => true
     * renders through the ace_taxonomy_tools_settings_tab_content action instead of fields.
     */
    public static function tabs(): array {
        $tabs = [[
            'id' => "batch",
            'label' => "Batch editor",
            'icon' => "editor-table",
            'custom' => true,
            'help' => "Pick a taxonomy, tick the fields you want to change, then edit in the table. Each row saves on its own the moment you leave a field; unchanged values are skipped. Tick rows and use the bulk bar to set one field on all of them, or switch on walk-through to go one term at a time.",
            'sections' => [],
        ], [
            'id' => "batch-settings",
            'label' => "Editor settings",
            'icon' => "admin-generic",
            'help' => "Limit the batch editor to the taxonomies people should be bulk-editing. Fields come from register_term_meta() for the taxonomy plus name, slug, description and parent. Keys a plugin writes without registering need the ace_taxonomy_tools_fields filter in a site mu-plugin.",
            'sections' => [[
                'id' => "batch-scope",
                'title' => "Scope",
                'icon' => "category",
                'description' => "",
            ], [
                'id' => "batch-paging",
                'title' => "Paging",
                'icon' => "list-view",
                'description' => "",
            ]],
        ], [
            'id' => "retired",
            'label' => "Retired terms",
            'icon' => "archive",
            'help' => "A retired term is finished with but must not disappear: last season, an old league, a discontinued range. Its archive keeps resolving (no 404, no redirect) and is served as long-lived static HTML through the cache layer, regenerated only when the flag toggles or a post in it is saved. Retired terms are hidden from editor term pickers for non-admins; admins can still assign them.",
            'sections' => [[
                'id' => "retired-scope",
                'title' => "Which taxonomies",
                'icon' => "category",
                'description' => "",
            ], [
                'id' => "retired-behaviour",
                'title' => "Behaviour",
                'icon' => "visibility",
                'description' => "",
            ]],
        ]];
        return apply_filters( 'ace_taxonomy_tools_settings_tabs', $tabs );
    }

    public static function defaults(): array {
        $defaults = [];
        foreach ( self::fields() as $key => $field ) {
            $defaults[ $key ] = $field['default'] ?? ( in_array( $field['type'], [ 'post_types', 'taxonomies' ], true ) ? [] : '' );
        }
        return $defaults;
    }

    public static function all(): array {
        if ( null === self::$cache ) {
            $stored      = get_option( self::OPTION, [] );
            self::$cache = wp_parse_args( is_array( $stored ) ? $stored : [], self::defaults() );
        }
        return self::$cache;
    }

    public static function get( string $key, $fallback = null ) {
        $all   = self::all();
        $value = array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
        return apply_filters( 'ace_taxonomy_tools_setting', $value, $key );
    }

    /**
     * Newline-separated textarea setting as a clean list.
     */
    public static function get_list( string $key ): array {
        $raw   = (string) self::get( $key, '' );
        $lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw ) ) );
        return array_values( array_unique( $lines ) );
    }

    /**
     * Change some settings from code without touching the rest (update() treats a
     * missing checkbox as unticked, which is right for the form but not for scripts).
     */
    public static function patch( array $changes ): array {
        return self::update( array_merge( self::all(), $changes ) );
    }

    public static function update( array $raw ): array {
        $clean = self::sanitise( $raw );
        update_option( self::OPTION, $clean, false );
        self::$cache = null;
        update_option( 'ace_taxonomy_tools_version', ACE_TAXONOMY_TOOLS_VERSION, false );
        do_action( 'ace_taxonomy_tools_settings_saved', $clean );
        return $clean;
    }

    public static function sanitise( array $raw ): array {
        $clean = [];
        foreach ( self::fields() as $key => $field ) {
            $value = $raw[ $key ] ?? null;
            switch ( $field['type'] ) {
                case 'checkbox':
                    $clean[ $key ] = empty( $value ) ? 0 : 1;
                    break;
                case 'number':
                    $number = is_numeric( $value ) ? (int) $value : (int) ( $field['default'] ?? 0 );
                    if ( isset( $field['min'] ) ) {
                        $number = max( (int) $field['min'], $number );
                    }
                    if ( isset( $field['max'] ) ) {
                        $number = min( (int) $field['max'], $number );
                    }
                    $clean[ $key ] = $number;
                    break;
                case 'textarea':
                    $clean[ $key ] = sanitize_textarea_field( wp_unslash( (string) $value ) );
                    break;
                case 'post_types':
                case 'taxonomies':
                    $value         = is_array( $value ) ? $value : [];
                    $clean[ $key ] = array_values( array_filter( array_map( 'sanitize_key', $value ) ) );
                    break;
                case 'select':
                    $options       = $field['options'] ?? [];
                    $value         = sanitize_key( (string) $value );
                    $clean[ $key ] = isset( $options[ $value ] ) ? $value : ( $field['default'] ?? '' );
                    break;
                default:
                    $clean[ $key ] = sanitize_text_field( wp_unslash( (string) $value ) );
            }
        }
        return apply_filters( 'ace_taxonomy_tools_sanitise_settings', $clean, $raw );
    }
}
