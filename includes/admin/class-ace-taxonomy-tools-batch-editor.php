<?php
/**
 * Batch editor: a tab on Settings → Taxonomy Tools. Pick a taxonomy (and
 * parent), edit chosen fields inline in a table or walk through terms one at
 * a time. All saving happens over REST from src/batch-editor.js.
 *
 * @package Ace_Taxonomy_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Taxonomy_Tools_Batch_Editor {

    public function __construct() {
        add_action( 'ace_taxonomy_tools_settings_enqueue', [ $this, 'enqueue' ] );
        add_action( 'ace_taxonomy_tools_settings_tab_content', [ $this, 'render' ] );
        add_action( 'admin_menu', [ $this, 'term_screen_links' ], 99 );
    }

    /**
     * URL of the batch editor tab.
     */
    public static function url(): string {
        return Ace_Taxonomy_Tools_Admin::url( 'batch' );
    }

    /**
     * A "Batch edit" link at the top of each editable taxonomy's term list screen.
     */
    public function term_screen_links(): void {
        foreach ( Ace_Taxonomy_Tools::batch_taxonomies() as $taxonomy ) {
            add_action( "after-{$taxonomy}-table", [ $this, 'term_list_link' ] );
        }
    }

    public function term_list_link( string $taxonomy ): void {
        if ( ! current_user_can( 'manage_categories' ) ) {
            return;
        }
        printf(
            '<p class="ace-tax-batch-link"><a class="button" href="%s"><span class="dashicons dashicons-editor-table" aria-hidden="true" style="vertical-align:text-bottom"></span> %s</a></p>',
            esc_url( self::url() ),
            esc_html__( 'Batch edit these terms', 'ace-taxonomy-tools' )
        );
    }

    public function enqueue(): void {
        $asset_file = ACE_TAXONOMY_TOOLS_PATH . 'build/batch-editor.asset.php';
        $asset      = file_exists( $asset_file ) ? include $asset_file : [ 'dependencies' => [], 'version' => ACE_TAXONOMY_TOOLS_VERSION ];
        wp_enqueue_script( 'ace-taxonomy-tools-batch', ACE_TAXONOMY_TOOLS_URL . 'build/batch-editor.js', $asset['dependencies'] ?? [], $asset['version'] ?? ACE_TAXONOMY_TOOLS_VERSION, true );

        $taxonomies = [];
        foreach ( Ace_Taxonomy_Tools::batch_taxonomies() as $name ) {
            $object = get_taxonomy( $name );
            if ( $object && current_user_can( $object->cap->edit_terms ) ) {
                $taxonomies[] = [ 'name' => $name, 'label' => $object->labels->name, 'hierarchical' => $object->hierarchical ];
            }
        }
        wp_localize_script( 'ace-taxonomy-tools-batch', 'ace_taxonomy_tools_batch', [
            'rest_url'   => esc_url_raw( rest_url( Ace_Taxonomy_Tools_REST::NS ) ),
            'nonce'      => wp_create_nonce( 'wp_rest' ),
            'taxonomies' => $taxonomies,
            'per_page'   => (int) Ace_Taxonomy_Tools_Settings::get( 'batch_page_size', 50 ),
            'i18n'       => [
                'saved'   => __( 'Saved', 'ace-taxonomy-tools' ),
                'skipped' => __( 'No change', 'ace-taxonomy-tools' ),
                'error'   => __( 'Error', 'ace-taxonomy-tools' ),
                'apply'   => __( 'Apply to %d terms', 'ace-taxonomy-tools' ),
            ],
        ] );
    }

    public function render( string $tab_id ): void {
        if ( 'batch' !== $tab_id || ! current_user_can( 'manage_categories' ) ) {
            return;
        }
        ?>
        <div class="ace-tax-batch">
            <div id="ace-tax-batch-app" class="ace-tax-batch-app">
                <noscript><?php esc_html_e( 'The batch editor needs JavaScript.', 'ace-taxonomy-tools' ); ?></noscript>
            </div>
        </div>
        <?php
    }
}
