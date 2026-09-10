<?php
/**
 * Batch editor admin screen: pick a taxonomy (and parent), then edit chosen
 * fields inline in a table or walk through terms one at a time. All saving
 * happens over REST from src/batch-editor.js.
 *
 * @package Ace_Taxonomy_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Ace_Taxonomy_Tools_Batch_Editor {

    const PAGE = 'ace-taxonomy-tools-batch';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'menu' ], 20 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'ace_taxonomy_tools_settings_sidebar', [ $this, 'sidebar_link' ] );
    }

    public function menu(): void {
        add_submenu_page(
            Ace_Taxonomy_Tools_Admin::PAGE,
            __( 'Batch editor', 'ace-taxonomy-tools' ),
            __( 'Batch editor', 'ace-taxonomy-tools' ),
            'manage_categories',
            self::PAGE,
            [ $this, 'render' ],
            0
        );
    }

    public function sidebar_link(): void {
        printf(
            '<a href="%s" class="nav-tab"><span class="dashicons dashicons-editor-table" aria-hidden="true"></span>%s</a>',
            esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ),
            esc_html__( 'Batch editor', 'ace-taxonomy-tools' )
        );
    }

    public function enqueue( string $hook ): void {
        if ( false === strpos( $hook, self::PAGE ) ) {
            return;
        }
        wp_enqueue_style( 'ace-taxonomy-tools-admin', ACE_TAXONOMY_TOOLS_URL . 'assets/css/admin.css', [], ACE_TAXONOMY_TOOLS_VERSION );

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

    public function render(): void {
        if ( ! current_user_can( 'manage_categories' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'ace-taxonomy-tools' ) );
        }
        ?>
        <div class="wrap ace-redis-settings ace-tax-batch">
            <h1><?php esc_html_e( 'Batch term editor', 'ace-taxonomy-tools' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Pick a taxonomy, choose the fields you want to edit, then change values inline. Each row saves on its own; unchanged values are skipped.', 'ace-taxonomy-tools' ); ?></p>
            <div id="ace-tax-batch-app" class="ace-tax-batch-app">
                <noscript><?php esc_html_e( 'The batch editor needs JavaScript.', 'ace-taxonomy-tools' ); ?></noscript>
            </div>
        </div>
        <?php
    }
}
