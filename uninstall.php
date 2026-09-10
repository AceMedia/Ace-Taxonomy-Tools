<?php
/**
 * Uninstall Ace Taxonomy Tools: remove options only. Tracked data is left in place
 * on purpose so deleting the plugin never destroys history or content.
 *
 * @package Ace_Taxonomy_Tools
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'ace_taxonomy_tools_options' );
delete_option( 'ace_taxonomy_tools_version' );
