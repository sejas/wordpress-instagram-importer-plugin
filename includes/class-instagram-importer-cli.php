<?php
/**
 * WP-CLI command for Instagram Importer.
 *
 * Usage:
 *   wp instagram-importer import <zip> [--user=<user>] [--dry-run] [--quiet]
 *
 * @package Instagram_Importer
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

if ( ! class_exists( 'Instagram_Importer' ) ) {
	require_once dirname( __FILE__ ) . '/class-instagram-importer.php';
}

/**
 * Imports Instagram "Download Your Information" archives from the command line.
 */
class Instagram_Importer_CLI extends WP_CLI_Command {

	/**
	 * Imports posts and media from an Instagram export ZIP.
	 *
	 * ## OPTIONS
	 *
	 * <zip>
	 * : Path to the Instagram export ZIP archive on disk.
	 *
	 * [--user=<user>]
	 * : ID, login, or email of the user to attribute imported posts to.
	 * Defaults to user ID 1 (the site's first administrator).
	 *
	 * [--dry-run]
	 * : Walk the archive and report what would be imported without
	 * creating any posts or attachments.
	 *
	 * [--quiet]
	 * : Suppress per-post log lines; only the final summary is shown.
	 *
	 * ## EXAMPLES
	 *
	 *   # Import an export, attributing posts to the user with login "antonio".
	 *   wp instagram-importer import ~/Downloads/instagram-export.zip --user=antonio
	 *
	 *   # See what would be imported without making any changes.
	 *   wp instagram-importer import ~/Downloads/instagram-export.zip --dry-run
	 *
	 * @param array<int, string>  $args
	 * @param array<string, string> $assoc_args
	 */
	public function import( array $args, array $assoc_args ): void {
		list( $zip_path ) = $args;

		$zip_path = (string) $zip_path;
		if ( '' === $zip_path ) {
			WP_CLI::error( 'Path to ZIP archive is required.' );
		}

		// Resolve to an absolute path; CLI users typically pass relative paths.
		$resolved = realpath( $zip_path );
		if ( false === $resolved ) {
			WP_CLI::error( "ZIP file not found: {$zip_path}" );
		}
		$zip_path = $resolved;

		if ( ! is_readable( $zip_path ) ) {
			WP_CLI::error( "ZIP file is not readable: {$zip_path}" );
		}

		$dry_run = ! empty( $assoc_args['dry-run'] );
		$quiet   = ! empty( $assoc_args['quiet'] );

		$author_id = $this->resolve_author_id( $assoc_args['user'] ?? null );

		// Raise PHP limits — large exports take time.
		@set_time_limit( 0 );
		@ini_set( 'memory_limit', '1024M' );

		$importer = new Instagram_Importer();
		$importer->set_author_id( $author_id );
		$importer->set_dry_run( $dry_run );
		$importer->set_logger(
			static function ( string $message, string $level = 'info' ) use ( $quiet ): void {
				if ( $quiet && 'success' === $level ) {
					return;
				}
				switch ( $level ) {
					case 'error':
						WP_CLI::warning( $message );
						break;
					case 'warn':
						WP_CLI::warning( $message );
						break;
					case 'success':
						WP_CLI::success( $message );
						break;
					default:
						WP_CLI::log( $message );
				}
			}
		);

		WP_CLI::log(
			sprintf(
				'%sImporting %s as user %d…',
				$dry_run ? '[dry-run] ' : '',
				$zip_path,
				$author_id
			)
		);

		$result = $importer->run_import( $zip_path );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success(
			sprintf(
				'%sImported %d post(s) and %d media file(s). %d media file(s) failed.',
				$dry_run ? '[dry-run] ' : '',
				(int) $result['posts'],
				(int) $result['media'],
				(int) $result['failed']
			)
		);
	}

	/**
	 * Resolve a --user= argument to a numeric user ID. Accepts ID, login, or
	 * email. Falls back to user ID 1 when no value is given.
	 *
	 * @param int|string|null $user
	 */
	private function resolve_author_id( $user ): int {
		if ( null === $user || '' === $user ) {
			return 1;
		}

		if ( is_numeric( $user ) ) {
			$id = (int) $user;
			if ( get_userdata( $id ) ) {
				return $id;
			}
			WP_CLI::error( "User with ID {$id} does not exist." );
		}

		$user_obj = get_user_by( 'login', (string) $user );
		if ( ! $user_obj ) {
			$user_obj = get_user_by( 'email', (string) $user );
		}
		if ( ! $user_obj ) {
			WP_CLI::error( "User '{$user}' does not exist." );
		}
		return (int) $user_obj->ID;
	}
}

WP_CLI::add_command( 'instagram-importer', 'Instagram_Importer_CLI' );
