<?php
/**
 * Plugin Name: Ace Taxonomy Tools
 * Plugin URI: https://github.com/AceMedia/Ace-Taxonomy-Tools
 * Description: Batch term editor and retired/archived terms for any taxonomy.
 * Version: 0.2.1
 * Author: AceMedia
 * Author URI: https://acemedia.ninja
 * Text Domain: ace-taxonomy-tools
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Ace_Taxonomy_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Bump on every release: drives asset cache-busting and the options migration check.
define( 'ACE_TAXONOMY_TOOLS_VERSION', '0.2.1' );
define( 'ACE_TAXONOMY_TOOLS_FILE', __FILE__ );
define( 'ACE_TAXONOMY_TOOLS_PATH', plugin_dir_path( __FILE__ ) );
define( 'ACE_TAXONOMY_TOOLS_URL', plugin_dir_url( __FILE__ ) );
define( 'ACE_TAXONOMY_TOOLS_BASENAME', plugin_basename( __FILE__ ) );

require_once ACE_TAXONOMY_TOOLS_PATH . 'includes/class-ace-taxonomy-tools-settings.php';
require_once ACE_TAXONOMY_TOOLS_PATH . 'includes/class-ace-taxonomy-tools.php';

if ( is_admin() ) {
    require_once ACE_TAXONOMY_TOOLS_PATH . 'includes/admin/class-ace-taxonomy-tools-admin.php';
}

register_activation_hook( __FILE__, [ 'Ace_Taxonomy_Tools', 'activate' ] );

add_action( 'plugins_loaded', [ 'Ace_Taxonomy_Tools', 'instance' ] );
