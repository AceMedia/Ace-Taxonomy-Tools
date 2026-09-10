<?php
/**
 * REST API for the batch editor.
 *
 *   GET  /ace-taxonomy-tools/v1/fields?taxonomy=
 *   GET  /ace-taxonomy-tools/v1/terms?taxonomy=&parent=&search=&page=&per_page=
 *   POST /ace-taxonomy-tools/v1/terms/<id>          { taxonomy, fields: { key: value } }
 *   POST /ace-taxonomy-tools/v1/bulk                { taxonomy, ids: [], field, value }
 *
 * Every write goes through wp_update_term / update_term_meta, so Ace Revisions
 * sees them like any other edit. Unchanged values are skipped.
 *
 * @package Ace_Taxonomy_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Taxonomy_Tools_REST {

    const NS = 'ace-taxonomy-tools/v1';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'routes' ] );
    }

    public function routes(): void {
        $taxonomy_arg = [
            'required'          => true,
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_key',
            'validate_callback' => [ $this, 'validate_taxonomy' ],
        ];

        register_rest_route( self::NS, '/fields', [
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => [ $this, 'can_edit' ],
            'callback'            => [ $this, 'fields' ],
            'args'                => [ 'taxonomy' => $taxonomy_arg ],
        ] );

        register_rest_route( self::NS, '/terms', [
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => [ $this, 'can_edit' ],
            'callback'            => [ $this, 'terms' ],
            'args'                => [
                'taxonomy' => $taxonomy_arg,
                'parent'   => [ 'type' => 'integer', 'default' => -1 ],
                'search'   => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
                'page'     => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
                'per_page' => [ 'type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 500 ],
            ],
        ] );

        register_rest_route( self::NS, '/terms/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => [ $this, 'can_edit_term' ],
            'callback'            => [ $this, 'update' ],
            'args'                => [
                'taxonomy' => $taxonomy_arg,
                'fields'   => [ 'required' => true, 'type' => 'object' ],
            ],
        ] );

        register_rest_route( self::NS, '/bulk', [
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => [ $this, 'can_edit' ],
            'callback'            => [ $this, 'bulk' ],
            'args'                => [
                'taxonomy' => $taxonomy_arg,
                'ids'      => [ 'required' => true, 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                'field'    => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                'value'    => [ 'required' => true ],
            ],
        ] );
    }

    public function validate_taxonomy( $value ): bool {
        return taxonomy_exists( $value ) && in_array( $value, Ace_Taxonomy_Tools::batch_taxonomies(), true );
    }

    public function can_edit( WP_REST_Request $request ): bool {
        $taxonomy = get_taxonomy( (string) $request->get_param( 'taxonomy' ) );
        return $taxonomy && current_user_can( $taxonomy->cap->edit_terms );
    }

    public function can_edit_term( WP_REST_Request $request ): bool {
        return $this->can_edit( $request ) && current_user_can( 'edit_term', (int) $request['id'] );
    }

    public function fields( WP_REST_Request $request ): WP_REST_Response {
        $taxonomy = (string) $request->get_param( 'taxonomy' );
        return rest_ensure_response( [
            'taxonomy'     => $taxonomy,
            'hierarchical' => is_taxonomy_hierarchical( $taxonomy ),
            'fields'       => Ace_Taxonomy_Tools::fields_for( $taxonomy ),
        ] );
    }

    public function terms( WP_REST_Request $request ): WP_REST_Response {
        $taxonomy = (string) $request->get_param( 'taxonomy' );
        $per_page = (int) $request->get_param( 'per_page' );
        $page     = (int) $request->get_param( 'page' );
        $args     = [
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'number'     => $per_page,
            'offset'     => ( $page - 1 ) * $per_page,
            'orderby'    => 'name',
            'search'     => (string) $request->get_param( 'search' ),
        ];
        $parent = (int) $request->get_param( 'parent' );
        if ( $parent >= 0 && is_taxonomy_hierarchical( $taxonomy ) ) {
            $args['parent'] = $parent;
        }
        $args = apply_filters( 'ace_taxonomy_tools_terms_query', $args, $request );

        $query = new WP_Term_Query( $args );
        $total = (int) wp_count_terms( array_diff_key( $args, [ 'number' => 1, 'offset' => 1, 'orderby' => 1 ] ) );
        $fields = Ace_Taxonomy_Tools::fields_for( $taxonomy );
        $items = [];
        foreach ( (array) $query->get_terms() as $term ) {
            $items[] = $this->term_row( $term, $fields );
        }
        $response = rest_ensure_response( $items );
        $response->header( 'X-WP-Total', (string) $total );
        $response->header( 'X-WP-TotalPages', (string) max( 1, (int) ceil( $total / $per_page ) ) );
        return $response;
    }

    private function term_row( WP_Term $term, array $fields ): array {
        $row = [
            'id'       => $term->term_id,
            'count'    => $term->count,
            'children' => is_taxonomy_hierarchical( $term->taxonomy ) ? count( get_term_children( $term->term_id, $term->taxonomy ) ) : 0,
            'link'     => get_edit_term_link( $term ),
            'values'   => [],
        ];
        foreach ( $fields as $key => $field ) {
            $row['values'][ $key ] = 'core' === $field['source'] ? $term->$key : get_term_meta( $term->term_id, $key, true );
        }
        return $row;
    }

    public function update( WP_REST_Request $request ) {
        $taxonomy = (string) $request->get_param( 'taxonomy' );
        $term_id  = (int) $request['id'];
        $result   = self::apply( $term_id, $taxonomy, (array) $request->get_param( 'fields' ) );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        $term = get_term( $term_id, $taxonomy );
        return rest_ensure_response( [
            'changed' => $result,
            'term'    => $this->term_row( $term, Ace_Taxonomy_Tools::fields_for( $taxonomy ) ),
        ] );
    }

    public function bulk( WP_REST_Request $request ) {
        $taxonomy = (string) $request->get_param( 'taxonomy' );
        $field    = (string) $request->get_param( 'field' );
        $value    = $request->get_param( 'value' );
        $ids      = array_map( 'intval', (array) $request->get_param( 'ids' ) );

        if ( class_exists( 'Ace_Revisions' ) ) {
            Ace_Revisions::set_batch( 'bulk_' . gmdate( 'Ymd_His' ) . '_' . get_current_user_id() );
        }
        $changed = [];
        $errors  = [];
        foreach ( $ids as $id ) {
            if ( ! current_user_can( 'edit_term', $id ) ) {
                $errors[ $id ] = 'forbidden';
                continue;
            }
            $result = self::apply( $id, $taxonomy, [ $field => $value ] );
            if ( is_wp_error( $result ) ) {
                $errors[ $id ] = $result->get_error_message();
            } elseif ( $result ) {
                $changed[] = $id;
            }
        }
        return rest_ensure_response( [ 'changed' => $changed, 'skipped' => count( $ids ) - count( $changed ) - count( $errors ), 'errors' => $errors ] );
    }

    /**
     * Apply field values to a term, skipping unchanged ones. Returns the keys changed.
     *
     * @return string[]|WP_Error
     */
    public static function apply( int $term_id, string $taxonomy, array $values ) {
        $fields  = Ace_Taxonomy_Tools::fields_for( $taxonomy );
        $term    = get_term( $term_id, $taxonomy );
        if ( ! $term instanceof WP_Term ) {
            return new WP_Error( 'ace_tax_not_found', __( 'Term not found.', 'ace-taxonomy-tools' ), [ 'status' => 404 ] );
        }
        $core    = [];
        $changed = [];

        foreach ( $values as $key => $value ) {
            if ( ! isset( $fields[ $key ] ) ) {
                continue;
            }
            $value = self::sanitise_value( $value, $fields[ $key ] );
            $value = apply_filters( 'ace_taxonomy_tools_sanitise_value', $value, $key, $taxonomy, $fields[ $key ] );

            if ( 'core' === $fields[ $key ]['source'] ) {
                if ( (string) $term->$key !== (string) $value ) {
                    $core[ $key ] = $value;
                    $changed[]   = $key;
                }
                continue;
            }

            if ( Ace_Taxonomy_Tools::RETIRED_META === $key && ! current_user_can( 'manage_categories' ) ) {
                continue;
            }
            $current = get_term_meta( $term_id, $key, true );
            if ( maybe_serialize( $current ) === maybe_serialize( $value ) ) {
                continue;
            }
            if ( '' === $value || null === $value || false === $value ) {
                delete_term_meta( $term_id, $key );
            } else {
                update_term_meta( $term_id, $key, $value );
            }
            $changed[] = $key;
        }

        if ( $core ) {
            $result = wp_update_term( $term_id, $taxonomy, $core );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        }
        if ( $changed ) {
            do_action( 'ace_taxonomy_tools_term_updated', $term_id, $taxonomy, $changed );
        }
        return $changed;
    }

    private static function sanitise_value( $value, array $field ) {
        switch ( $field['type'] ) {
            case 'boolean':
                return rest_sanitize_boolean( $value ) ? 1 : '';
            case 'integer':
                return (int) $value;
            case 'number':
                return (float) $value;
            case 'text':
                return sanitize_textarea_field( (string) $value );
            case 'array':
            case 'object':
                return is_array( $value ) ? map_deep( $value, 'sanitize_text_field' ) : [];
            default:
                return sanitize_text_field( (string) $value );
        }
    }
}
