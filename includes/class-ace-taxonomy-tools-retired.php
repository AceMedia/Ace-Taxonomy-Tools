<?php
/**
 * Retired / archived terms.
 *
 * - Term meta flag `_ace_retired` on the taxonomies chosen in settings.
 * - Retired terms hidden from editor term pickers for non-admins (REST + classic).
 * - Archive still resolves; served long-lived through the cache layer and purged
 *   only when the flag toggles or a post in the term is saved.
 * - Optional "Historical" label on the archive title (theme) and SEO title (Ace Crawl Enhancer).
 *
 * @package Ace_Taxonomy_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Taxonomy_Tools_Retired {

    const META = Ace_Taxonomy_Tools::RETIRED_META;

    /** Seconds a retired archive may live in the page cache before a natural refresh. */
    const STATIC_TTL = 30 * DAY_IN_SECONDS;

    public function __construct() {
        add_action( 'init', [ $this, 'register_meta' ], 20 );
        add_action( 'init', [ $this, 'hook_taxonomies' ], 30 );

        add_action( 'updated_term_meta', [ $this, 'on_meta_change' ], 10, 3 );
        add_action( 'added_term_meta', [ $this, 'on_meta_change' ], 10, 3 );
        add_action( 'deleted_term_meta', [ $this, 'on_meta_change' ], 10, 3 );
        add_action( 'save_post', [ $this, 'on_post_save' ], 20, 2 );

        add_filter( 'get_terms_args', [ $this, 'hide_in_classic_pickers' ], 10, 2 );
        add_filter( 'single_term_title', [ $this, 'label_title' ] );
        add_filter( 'get_the_archive_title', [ $this, 'label_archive_title' ] );
        add_filter( 'ace_seo_title', [ $this, 'label_seo_title' ] );
        add_filter( 'ace_redis_cache_ttl', [ $this, 'static_ttl' ] );
        add_filter( 'body_class', [ $this, 'body_class' ] );
    }

    public function register_meta(): void {
        foreach ( Ace_Taxonomy_Tools::retired_taxonomies() as $taxonomy ) {
            register_term_meta( $taxonomy, self::META, [
                'type'              => 'boolean',
                'single'            => true,
                'default'           => false,
                'show_in_rest'      => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'auth_callback'     => static function () {
                    return current_user_can( 'manage_categories' );
                },
                'label'             => __( 'Retired', 'ace-taxonomy-tools' ),
            ] );
        }
    }

    public function hook_taxonomies(): void {
        foreach ( Ace_Taxonomy_Tools::retired_taxonomies() as $taxonomy ) {
            add_action( "{$taxonomy}_edit_form_fields", [ $this, 'edit_field' ], 5, 2 );
            add_action( "edited_{$taxonomy}", [ $this, 'save_field' ] );
            add_filter( "rest_{$taxonomy}_query", [ $this, 'hide_in_rest_pickers' ], 10, 2 );
            add_filter( "manage_edit-{$taxonomy}_columns", [ $this, 'column' ] );
            add_filter( "manage_{$taxonomy}_custom_column", [ $this, 'column_value' ], 10, 3 );
        }
    }

    public static function is_retired( $term ): bool {
        $term = get_term( $term );
        if ( ! $term instanceof WP_Term ) {
            return false;
        }
        return (bool) apply_filters( 'ace_taxonomy_tools_is_retired', (bool) get_term_meta( $term->term_id, self::META, true ), $term );
    }

    public static function set_retired( int $term_id, bool $retired ): void {
        if ( $retired ) {
            update_term_meta( $term_id, self::META, 1 );
        } else {
            delete_term_meta( $term_id, self::META );
        }
    }

    public function edit_field( WP_Term $term, string $taxonomy ): void {
        if ( ! current_user_can( 'manage_categories' ) ) {
            return;
        }
        wp_nonce_field( 'ace_tax_retired_' . $term->term_id, 'ace_tax_retired_nonce' );
        ?>
        <tr class="form-field">
            <th scope="row"><label for="ace-tax-retired"><?php esc_html_e( 'Retired', 'ace-taxonomy-tools' ); ?></label></th>
            <td>
                <label>
                    <input type="checkbox" id="ace-tax-retired" name="<?php echo esc_attr( self::META ); ?>" value="1" <?php checked( self::is_retired( $term ) ); ?>>
                    <?php esc_html_e( 'Hide from editor term pickers for non-admins and serve the archive as long-lived static HTML.', 'ace-taxonomy-tools' ); ?>
                </label>
            </td>
        </tr>
        <?php
    }

    public function save_field( int $term_id ): void {
        if ( ! isset( $_POST['ace_tax_retired_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ace_tax_retired_nonce'] ) ), 'ace_tax_retired_' . $term_id ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_categories' ) ) {
            return;
        }
        self::set_retired( $term_id, ! empty( $_POST[ self::META ] ) );
    }

    public function column( array $columns ): array {
        $columns['ace_retired'] = __( 'Retired', 'ace-taxonomy-tools' );
        return $columns;
    }

    public function column_value( string $content, string $column, int $term_id ): string {
        if ( 'ace_retired' !== $column ) {
            return $content;
        }
        return self::is_retired( $term_id ) ? '<span class="dashicons dashicons-archive" title="' . esc_attr__( 'Retired', 'ace-taxonomy-tools' ) . '"></span>' : '';
    }

    /**
     * Toggling the flag (or any change to it) purges the archive and notifies listeners.
     */
    public function on_meta_change( $meta_ids, int $term_id, string $meta_key ): void {
        if ( self::META !== $meta_key ) {
            return;
        }
        $term = get_term( $term_id );
        if ( ! $term instanceof WP_Term ) {
            return;
        }
        $this->purge_archive( $term );
        do_action( 'ace_taxonomy_tools_retired_toggled', $term_id, $term->taxonomy, self::is_retired( $term ) );
    }

    /**
     * A post in a retired term changed: regenerate that archive only.
     */
    public function on_post_save( int $post_id, WP_Post $post ): void {
        if ( wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
            return;
        }
        foreach ( Ace_Taxonomy_Tools::retired_taxonomies() as $taxonomy ) {
            $terms = get_the_terms( $post_id, $taxonomy );
            if ( ! is_array( $terms ) ) {
                continue;
            }
            foreach ( $terms as $term ) {
                if ( self::is_retired( $term ) ) {
                    $this->purge_archive( $term );
                }
            }
        }
    }

    private function purge_archive( WP_Term $term ): void {
        $url = get_term_link( $term );
        if ( is_wp_error( $url ) ) {
            return;
        }
        /**
         * Purge a single URL from whatever page cache the site runs. Ace Redis Cache
         * (or a site mu-plugin) listens here; nothing is assumed about the cache layer.
         */
        do_action( 'ace_taxonomy_tools_purge_url', $url, $term );
        do_action( 'ace_redis_cache_purge_url', $url );
    }

    /**
     * Editors' term pickers use the REST collection. Exclude retired terms unless the
     * user can manage terms (admins can still assign them) or the request opts in.
     */
    public function hide_in_rest_pickers( array $args, WP_REST_Request $request ): array {
        if ( ! Ace_Taxonomy_Tools_Settings::get( 'hide_from_pickers', 1 ) ) {
            return $args;
        }
        if ( current_user_can( 'manage_categories' ) || $request->get_param( 'ace_include_retired' ) ) {
            return $args;
        }
        if ( ! empty( $args['include'] ) ) {
            return $args; // Resolving already-assigned terms by id must still work.
        }
        $args['meta_query'] = $this->not_retired_meta_query( $args['meta_query'] ?? [] );
        return $args;
    }

    /**
     * Classic metabox / wp_dropdown_categories on admin screens for non-admins.
     */
    public function hide_in_classic_pickers( array $args, array $taxonomies ): array {
        if ( ! is_admin() || ! Ace_Taxonomy_Tools_Settings::get( 'hide_from_pickers', 1 ) || current_user_can( 'manage_categories' ) ) {
            return $args;
        }
        if ( ! array_intersect( $taxonomies, Ace_Taxonomy_Tools::retired_taxonomies() ) || ! empty( $args['include'] ) ) {
            return $args;
        }
        if ( isset( $args['fields'] ) && 'count' === $args['fields'] ) {
            return $args;
        }
        $args['meta_query'] = $this->not_retired_meta_query( $args['meta_query'] ?? [] );
        return $args;
    }

    private function not_retired_meta_query( $existing ): array {
        $clause = [
            'relation' => 'OR',
            [ 'key' => self::META, 'compare' => 'NOT EXISTS' ],
            [ 'key' => self::META, 'value' => '1', 'compare' => '!=' ],
        ];
        $existing = is_array( $existing ) ? $existing : [];
        return empty( $existing ) ? [ $clause ] : [ 'relation' => 'AND', $existing, $clause ];
    }

    private function current_retired_term(): ?WP_Term {
        if ( ! is_tax() && ! is_category() && ! is_tag() ) {
            return null;
        }
        $term = get_queried_object();
        return ( $term instanceof WP_Term && self::is_retired( $term ) ) ? $term : null;
    }

    public function label(): string {
        return (string) apply_filters( 'ace_taxonomy_tools_retired_label', Ace_Taxonomy_Tools_Settings::get( 'retired_label', '' ) );
    }

    public function label_title( string $title ): string {
        $label = $this->label();
        return ( $label && $this->current_retired_term() ) ? $title . ' (' . $label . ')' : $title;
    }

    public function label_archive_title( string $title ): string {
        $label = $this->label();
        return ( $label && $this->current_retired_term() && false === strpos( $title, '(' . $label . ')' ) ) ? $title . ' (' . $label . ')' : $title;
    }

    public function label_seo_title( $title ) {
        $label = $this->label();
        return ( $label && is_string( $title ) && $this->current_retired_term() && false === strpos( $title, $label ) ) ? $title . ' (' . $label . ')' : $title;
    }

    public function static_ttl( $ttl ) {
        if ( Ace_Taxonomy_Tools_Settings::get( 'static_archives', 1 ) && $this->current_retired_term() ) {
            return (int) apply_filters( 'ace_taxonomy_tools_static_ttl', self::STATIC_TTL );
        }
        return $ttl;
    }

    public function body_class( array $classes ): array {
        if ( $this->current_retired_term() ) {
            $classes[] = 'ace-retired-archive';
        }
        return $classes;
    }
}
