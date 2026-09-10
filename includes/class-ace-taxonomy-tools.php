<?php
/**
 * Ace Taxonomy Tools core: loads the batch editor and retired-terms features.
 *
 * @package Ace_Taxonomy_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Taxonomy_Tools {

    const RETIRED_META = '_ace_retired';

    private static $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        require_once ACE_TAXONOMY_TOOLS_PATH . 'includes/class-ace-taxonomy-tools-retired.php';
        require_once ACE_TAXONOMY_TOOLS_PATH . 'includes/class-ace-taxonomy-tools-rest.php';

        new Ace_Taxonomy_Tools_Retired();
        new Ace_Taxonomy_Tools_REST();

        if ( is_admin() ) {
            require_once ACE_TAXONOMY_TOOLS_PATH . 'includes/admin/class-ace-taxonomy-tools-batch-editor.php';
            new Ace_Taxonomy_Tools_Batch_Editor();
        }

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            require_once ACE_TAXONOMY_TOOLS_PATH . 'includes/class-ace-taxonomy-tools-cli.php';
            WP_CLI::add_command( 'ace-tax', 'Ace_Taxonomy_Tools_CLI' );
        }
    }

    public static function activate(): void {
        update_option( 'ace_taxonomy_tools_version', ACE_TAXONOMY_TOOLS_VERSION, false );
    }

    /**
     * Taxonomies the batch editor may touch. Empty setting = every taxonomy with a UI.
     */
    public static function batch_taxonomies(): array {
        $chosen = (array) Ace_Taxonomy_Tools_Settings::get( 'batch_taxonomies', [] );
        if ( empty( $chosen ) ) {
            $chosen = array_values( get_taxonomies( [ 'show_ui' => true ] ) );
        }
        return apply_filters( 'ace_taxonomy_tools_batch_taxonomies', $chosen );
    }

    public static function retired_taxonomies(): array {
        return apply_filters( 'ace_taxonomy_tools_retired_taxonomies', (array) Ace_Taxonomy_Tools_Settings::get( 'retired_taxonomies', [] ) );
    }

    /**
     * Editable fields for a taxonomy: core fields plus every registered term meta key.
     * Plugins that register meta with `show_in_rest` get a proper type; others fall back to text.
     *
     * @return array<string, array{label:string,type:string,source:string,options?:array}>
     */
    public static function fields_for( string $taxonomy ): array {
        $fields = [
            'name'        => [ 'label' => __( 'Name', 'ace-taxonomy-tools' ), 'type' => 'string', 'source' => 'core' ],
            'slug'        => [ 'label' => __( 'Slug', 'ace-taxonomy-tools' ), 'type' => 'string', 'source' => 'core' ],
            'description' => [ 'label' => __( 'Description', 'ace-taxonomy-tools' ), 'type' => 'text', 'source' => 'core' ],
        ];
        if ( is_taxonomy_hierarchical( $taxonomy ) ) {
            $fields['parent'] = [ 'label' => __( 'Parent', 'ace-taxonomy-tools' ), 'type' => 'integer', 'source' => 'core' ];
        }

        $registered = get_registered_meta_keys( 'term', $taxonomy ) + get_registered_meta_keys( 'term', '' );
        foreach ( $registered as $key => $args ) {
            $fields[ $key ] = [
                'label'  => ( $args['label'] ?? '' ) ?: ( ( $args['description'] ?? '' ) ?: $key ),
                'type'   => $args['type'] ?? 'string',
                'source' => 'meta',
            ];
        }

        if ( in_array( $taxonomy, self::retired_taxonomies(), true ) ) {
            $fields[ self::RETIRED_META ] = [ 'label' => __( 'Retired', 'ace-taxonomy-tools' ), 'type' => 'boolean', 'source' => 'meta' ];
        }

        /**
         * Add fields the batch editor cannot discover (meta without register_term_meta,
         * or select options for a key). Shape: key => [label, type, source, options, input].
         * input: text | textarea | number | checkbox | select | color | media | url | date.
         */
        $fields = apply_filters( 'ace_taxonomy_tools_fields', $fields, $taxonomy );
        foreach ( $fields as $key => &$field ) {
            if ( ! empty( $field['input'] ) ) {
                continue;
            }
            if ( ! empty( $field['options'] ) ) {
                $field['input'] = 'select';
            } elseif ( 'boolean' === $field['type'] ) {
                $field['input'] = 'checkbox';
            } elseif ( in_array( $field['type'], [ 'integer', 'number' ], true ) ) {
                $field['input'] = preg_match( '/(image|logo|icon|thumbnail|attachment|media)(_id)?$/i', $key ) ? 'media' : 'number';
            } elseif ( 'text' === $field['type'] || 'description' === $key ) {
                $field['input'] = 'textarea';
            } elseif ( preg_match( '/colou?r/i', $key ) ) {
                $field['input'] = 'color';
            } elseif ( preg_match( '/(_url|_src|_link)$/i', $key ) ) {
                $field['input'] = 'url';
            } elseif ( preg_match( '/(_date|_at)$/i', $key ) ) {
                $field['input'] = 'date';
            } else {
                $field['input'] = 'text';
            }
        }
        unset( $field );
        return $fields;
    }
}
