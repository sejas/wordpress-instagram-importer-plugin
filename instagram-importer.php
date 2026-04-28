<?php
/**
 * Plugin Name: Instagram Importer
 * Plugin URI: https://github.com/sejas/instagram-importer
 * Description: Import posts, media, and comments from an Instagram "Download Your Information" ZIP export into your WordPress site. Carousels become galleries, hashtags become tags, @mentions become links to instagram.com, and comments are imported with dates preserved and author names linked to Instagram profiles. Post descriptions are imported as the title and the excerpt. The Post content only includes, images, galleries or videos.
 * Version: 0.2.0
 * Author: Antonio Sejas & Alvaro Gómez
 * Author URI: https://sejas.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: instagram-importer
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package Instagram_Importer
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'INSTAGRAM_IMPORTER_VERSION', '0.2.0' );
define( 'INSTAGRAM_IMPORTER_PLUGIN_FILE', __FILE__ );
define( 'INSTAGRAM_IMPORTER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Boot the importer once WordPress is ready and the importer API is loaded.
 */
function instagram_importer_register(): void {
	if ( ! defined( 'WP_LOAD_IMPORTERS' ) ) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/import.php';

	if ( ! class_exists( 'WP_Importer' ) ) {
		$wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
		if ( file_exists( $wp_importer ) ) {
			require_once $wp_importer;
		}
	}

	if ( ! class_exists( 'WP_Importer' ) ) {
		return;
	}

	require_once INSTAGRAM_IMPORTER_PLUGIN_DIR . 'includes/class-instagram-importer.php';

	$importer = new Instagram_Importer();

	register_importer(
		'instagram',
		__( 'Instagram', 'instagram-importer' ),
		__( 'Import posts and media from an Instagram "Download Your Information" ZIP export. Carousels become galleries, hashtags become tags, and @mentions are linked to instagram.com.', 'instagram-importer' ),
		array( $importer, 'dispatch' )
	);
}
add_action( 'admin_init', 'instagram_importer_register' );

/**
 * Bump PHP limits while running the importer. Large exports with many media
 * files take time to process; a short timeout will abort mid-flight.
 */
function instagram_importer_raise_limits(): void {
	if ( ! is_admin() ) {
		return;
	}
	if ( ! isset( $_GET['import'] ) || 'instagram' !== $_GET['import'] ) {
		return;
	}
	@set_time_limit( 0 );
	@ini_set( 'memory_limit', '512M' );
}
add_action( 'admin_init', 'instagram_importer_raise_limits', 1 );

/**
 * Register the WP-CLI command. Loaded only when WP-CLI is active so that
 * regular front-end / admin requests don't pay the cost.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once INSTAGRAM_IMPORTER_PLUGIN_DIR . 'includes/class-instagram-importer.php';
	require_once INSTAGRAM_IMPORTER_PLUGIN_DIR . 'includes/class-instagram-importer-cli.php';
}
