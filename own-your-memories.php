<?php
/**
 * Plugin Name: Own Your Memories
 * Plugin URI: https://github.com/sejas/own-your-memories
 * Description: Import posts, media, and comments from an Instagram "Download Your Information" ZIP export into your WordPress site. Carousels become galleries, hashtags become tags, @mentions become links to the source profile, and comments are imported with dates preserved and author names linked to their source profiles. Post descriptions are imported as the title and the excerpt. The post content only includes images, galleries or videos.
 * Version: 0.2.0
 * Author: Antonio Sejas & Alvaro Gómez
 * Author URI: https://sejas.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: own-your-memories
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package Own_Your_Memories
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OWN_YOUR_MEMORIES_VERSION', '0.2.0' );
define( 'OWN_YOUR_MEMORIES_PLUGIN_FILE', __FILE__ );
define( 'OWN_YOUR_MEMORIES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Boot the importer once WordPress is ready and the importer API is loaded.
 */
function own_your_memories_register(): void {
	if ( ! defined( 'WP_LOAD_IMPORTERS' ) ) {
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/import.php';

	if ( ! class_exists( 'WP_Importer' ) ) {
		$own_your_memories_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
		if ( file_exists( $own_your_memories_wp_importer ) ) {
			require_once $own_your_memories_wp_importer;
		}
	}

	if ( ! class_exists( 'WP_Importer' ) ) {
		return;
	}

	require_once OWN_YOUR_MEMORIES_PLUGIN_DIR . 'includes/class-own-your-memories-importer.php';

	$importer = new Own_Your_Memories_Importer();

	register_importer(
		'own-your-memories',
		__( 'Own Your Memories', 'own-your-memories' ),
		__( 'Import posts and media from an Instagram "Download Your Information" ZIP export. Carousels become galleries, hashtags become tags, and @mentions are linked to the source profile.', 'own-your-memories' ),
		array( $importer, 'dispatch' )
	);
}
add_action( 'admin_init', 'own_your_memories_register' );

/**
 * Bump PHP limits while running the importer. Large exports with many media
 * files take time to process; a short timeout will abort mid-flight.
 */
function own_your_memories_raise_limits(): void {
	if ( ! is_admin() ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing check, no state mutation.
	if ( ! isset( $_GET['import'] ) || 'own-your-memories' !== $_GET['import'] ) {
		return;
	}
	// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Large exports need unbounded execution time.
	@set_time_limit( 0 );
	// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Large exports need extra memory.
	@ini_set( 'memory_limit', '512M' );
}
add_action( 'admin_init', 'own_your_memories_raise_limits', 1 );

/**
 * Register the WP-CLI command. Loaded only when WP-CLI is active so that
 * regular front-end / admin requests don't pay the cost.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once OWN_YOUR_MEMORIES_PLUGIN_DIR . 'includes/class-own-your-memories-importer.php';
	require_once OWN_YOUR_MEMORIES_PLUGIN_DIR . 'includes/class-own-your-memories-importer-cli.php';
}
