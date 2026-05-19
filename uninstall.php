<?php
/**
 * Uninstall handler for Own Your Memories.
 *
 * @package Own_Your_Memories
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// No persistent options or custom tables are created by this plugin.
// Imported posts, comments and media are intentionally left in place.
