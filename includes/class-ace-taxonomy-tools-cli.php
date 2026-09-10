<?php
/**
 * WP-CLI for bulk term work.
 *
 *   wp ace-tax retire <taxonomy> <term-id|slug>... [--unretire]
 *   wp ace-tax set <taxonomy> --field=<key> --value=<value> [--ids=<csv>] [--parent=<id>] [--all] [--dry-run]
 *   wp ace-tax list <taxonomy> [--parent=<id>] [--fields=<csv>] [--format=<format>]
 *
 * Every run shares one Ace Revisions batch id when that plugin is active.
 *
 * @package Ace_Taxonomy_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Taxonomy_Tools_CLI {

    private function batch( string $label ): void {
        if ( class_exists( 'Ace_Revisions' ) ) {
            Ace_Revisions::set_batch( 'cli_' . $label . '_' . gmdate( 'Ymd_His' ) );
        }
    }

    private function resolve_ids( string $taxonomy, array $refs ): array {
        $ids = [];
        foreach ( $refs as $ref ) {
            $term = is_numeric( $ref ) ? get_term( (int) $ref, $taxonomy ) : get_term_by( 'slug', $ref, $taxonomy );
            if ( $term instanceof WP_Term ) {
                $ids[] = $term->term_id;
            } else {
                WP_CLI::warning( "Term not found: {$ref}" );
            }
        }
        return $ids;
    }

    private function select_ids( string $taxonomy, array $assoc ): array {
        if ( ! empty( $assoc['ids'] ) ) {
            return $this->resolve_ids( $taxonomy, array_map( 'trim', explode( ',', $assoc['ids'] ) ) );
        }
        $args = [ 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ];
        if ( isset( $assoc['parent'] ) ) {
            $args['parent'] = (int) $assoc['parent'];
        } elseif ( empty( $assoc['all'] ) ) {
            WP_CLI::error( 'Pass --ids=<csv>, --parent=<id> or --all.' );
        }
        return array_map( 'intval', (array) get_terms( $args ) );
    }

    /**
     * Retire (or unretire) terms.
     *
     * ## OPTIONS
     *
     * <taxonomy>
     * : Taxonomy name.
     *
     * <term>...
     * : Term ids or slugs.
     *
     * [--unretire]
     * : Clear the flag instead of setting it.
     */
    public function retire( array $args, array $assoc ): void {
        $taxonomy = array_shift( $args );
        if ( ! in_array( $taxonomy, Ace_Taxonomy_Tools::retired_taxonomies(), true ) ) {
            WP_CLI::error( "Taxonomy {$taxonomy} is not enabled for retiring (see settings)." );
        }
        $this->batch( 'retire' );
        $retired = empty( $assoc['unretire'] );
        foreach ( $this->resolve_ids( $taxonomy, $args ) as $id ) {
            Ace_Taxonomy_Tools_Retired::set_retired( $id, $retired );
            WP_CLI::log( ( $retired ? 'Retired ' : 'Unretired ' ) . $id );
        }
        WP_CLI::success( 'Done.' );
    }

    /**
     * Set one field on many terms.
     *
     * ## OPTIONS
     *
     * <taxonomy>
     * : Taxonomy name.
     *
     * --field=<key>
     * : Core field (name, slug, description, parent) or term meta key.
     *
     * --value=<value>
     * : Value to set. Empty deletes meta.
     *
     * [--ids=<csv>]
     * : Term ids or slugs.
     *
     * [--parent=<id>]
     * : Every direct child of this term.
     *
     * [--all]
     * : Every term in the taxonomy.
     *
     * [--dry-run]
     * : Report what would change without saving.
     */
    public function set( array $args, array $assoc ): void {
        $taxonomy = $args[0];
        $field    = (string) $assoc['field'];
        $value    = (string) $assoc['value'];
        $ids      = $this->select_ids( $taxonomy, $assoc );
        $dry      = ! empty( $assoc['dry-run'] );
        $this->batch( 'set_' . sanitize_key( $field ) );

        $changed = 0;
        foreach ( $ids as $id ) {
            if ( $dry ) {
                $fields  = Ace_Taxonomy_Tools::fields_for( $taxonomy );
                $current = isset( $fields[ $field ] ) && 'core' === $fields[ $field ]['source'] ? get_term( $id, $taxonomy )->$field : get_term_meta( $id, $field, true );
                if ( (string) $current !== $value ) {
                    WP_CLI::log( "{$id}: {$field} '{$current}' -> '{$value}'" );
                    $changed++;
                }
                continue;
            }
            $result = Ace_Taxonomy_Tools_REST::apply( $id, $taxonomy, [ $field => $value ] );
            if ( is_wp_error( $result ) ) {
                WP_CLI::warning( "{$id}: " . $result->get_error_message() );
            } elseif ( $result ) {
                $changed++;
            }
        }
        WP_CLI::success( sprintf( '%s %d of %d terms.', $dry ? 'Would change' : 'Changed', $changed, count( $ids ) ) );
    }

    /**
     * List terms with chosen fields.
     *
     * ## OPTIONS
     *
     * <taxonomy>
     * : Taxonomy name.
     *
     * [--parent=<id>]
     * : Only direct children of this term.
     *
     * [--fields=<csv>]
     * : Fields to show. Default: id,name,slug.
     *
     * [--format=<format>]
     * : table, json, csv, yaml.
     */
    public function list( array $args, array $assoc ): void {
        $taxonomy = $args[0];
        $ids      = $this->select_ids( $taxonomy, isset( $assoc['parent'] ) ? $assoc : [ 'all' => true ] );
        $fields   = array_map( 'trim', explode( ',', $assoc['fields'] ?? 'id,name,slug' ) );
        $defs     = Ace_Taxonomy_Tools::fields_for( $taxonomy );
        $rows     = [];
        foreach ( $ids as $id ) {
            $term = get_term( $id, $taxonomy );
            $row  = [ 'id' => $id ];
            foreach ( $fields as $field ) {
                if ( 'id' === $field ) {
                    continue;
                }
                $row[ $field ] = isset( $defs[ $field ] ) && 'core' === $defs[ $field ]['source'] ? $term->$field : get_term_meta( $id, $field, true );
            }
            $rows[] = $row;
        }
        WP_CLI\Utils\format_items( $assoc['format'] ?? 'table', $rows, $fields );
    }
}
