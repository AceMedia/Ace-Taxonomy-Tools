<?php
/**
 * Settings store for Ace Taxonomy Tools.
 *
 * One option (ace_taxonomy_tools_options), one schema, one sanitiser. The admin page
 * renders from the same schema so a new setting is a one-line addition here.
 *
 * @package Ace_Taxonomy_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Taxonomy_Tools_Settings {

    const OPTION = 'ace_taxonomy_tools_options';

    /**
     * Field schema keyed by option key.
     * type: checkbox | text | textarea | number | post_types | taxonomies | select
     */
    public static function fields(): array {
        $fields = [
            'batch_taxonomies' => [
                'tab' => 'batch',
                'type' => 'taxonomies',
                'label' => 'Taxonomies in the batch editor',
                'help' => 'Leave all unticked to allow every public taxonomy.',
            ],
            'batch_page_size' => [
                'tab' => 'batch',
                'type' => 'number',
                'label' => 'Terms per page',
                'default' => 50,
                'min' => 10,
                'max' => 500,
            ],
            'retired_taxonomies' => [
                'tab' => 'retired',
                'type' => 'taxonomies',
                'label' => 'Taxonomies that can be retired',
                'help' => 'Adds a Retired flag to the term edit screen and the batch editor for these taxonomies.',
            ],
            'retired_label' => [
                'tab' => 'retired',
                'type' => 'text',
                'label' => 'Historical label',
                'help' => 'Appended to the archive title and SEO title of a retired term. Leave empty for no label.',
                'default' => 'Historical',
            ],
            'hide_from_pickers' => [
                'tab' => 'retired',
                'type' => 'checkbox',
                'label' => 'Hide retired terms from editor term pickers for non-admins',
                'default' => 1,
            ],
            'static_archives' => [
                'tab' => 'retired',
                'type' => 'checkbox',
                'label' => 'Serve retired term archives as long-lived static HTML through the cache layer',
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

    public static function tabs(): array {
        $tabs = [['batch', 'Batch editor', 'editor-table'], ['retired', 'Retired terms', 'archive']];
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
        static $cache = null;
        if ( null === $cache ) {
            $stored = get_option( self::OPTION, [] );
            $cache  = wp_parse_args( is_array( $stored ) ? $stored : [], self::defaults() );
        }
        return $cache;
    }

    public static function get( string $key, $fallback = null ) {
        $all = self::all();
        $value = array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
        return apply_filters( 'ace_taxonomy_tools_setting', $value, $key );
    }

    /**
     * Newline-separated textarea setting as a clean list.
     */
    public static function get_list( string $key ): array {
        $raw = (string) self::get( $key, '' );
        $lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw ) ) );
        return array_values( array_unique( $lines ) );
    }

    public static function update( array $raw ): array {
        $clean = self::sanitise( $raw );
        update_option( self::OPTION, $clean, false );
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
                    $value = is_array( $value ) ? $value : [];
                    $clean[ $key ] = array_values( array_filter( array_map( 'sanitize_key', $value ) ) );
                    break;
                case 'select':
                    $options = $field['options'] ?? [];
                    $value   = sanitize_key( (string) $value );
                    $clean[ $key ] = isset( $options[ $value ] ) ? $value : ( $field['default'] ?? '' );
                    break;
                default:
                    $clean[ $key ] = sanitize_text_field( wp_unslash( (string) $value ) );
            }
        }
        return apply_filters( 'ace_taxonomy_tools_sanitise_settings', $clean, $raw );
    }
}
