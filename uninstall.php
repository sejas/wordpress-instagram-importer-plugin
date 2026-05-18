<?php
/**
 * Uninstall handler for Instagram Importer.
 *
 * @package Instagram_Importer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// No persistent options or custom tables are created by this plugin.
// Imported posts, comments and media are intentionally left in place.
