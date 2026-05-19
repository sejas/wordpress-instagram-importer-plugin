<?php
/**
 * Main importer class. Mirrors core WP_Importer subclasses (e.g. WordPress
 * Importer) by dispatching through three steps: greet → upload → import.
 *
 * The actual import work lives in run_import() and is reusable from
 * the WP-CLI command (see class-own-your-memories-importer-cli.php).
 *
 * @package Own_Your_Memories
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Importer' ) ) {
	$own_your_memories_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $own_your_memories_wp_importer ) ) {
		require_once $own_your_memories_wp_importer;
	}
}

if ( ! class_exists( 'WP_Importer' ) ) {
	return;
}

class Own_Your_Memories_Importer extends WP_Importer {

	const POSTS_JSON_PATH_PATTERN = '#your_instagram_activity/media/posts_\d+\.json$#';
	const SOURCE_PROFILE_BASE_URL = 'https://www.instagram.com/';

	/** @var int Uploaded ZIP attachment ID (admin UI only). */
	private $id = 0;

	/** @var string Top-level prefix inside the ZIP (varies per export). */
	private $zip_prefix = '';

	/** @var array<string, int> URI → attachment ID, dedupe identical media references. */
	private $media_cache = array();

	/** @var int */
	private $imported_posts = 0;
	/** @var int */
	private $imported_media = 0;
	/** @var int */
	private $failed_media = 0;
	/** @var int */
	private $imported_comments = 0;

	/** @var int */
	private $author_id = 0;

	/** @var bool */
	private $dry_run = false;

	/** @var callable|null Logger receives ( string $message, string $level ) where level is info|success|warn|error. */
	private $logger = null;

	public function set_logger( ?callable $logger ): void {
		$this->logger = $logger;
	}

	public function set_author_id( int $author_id ): void {
		$this->author_id = $author_id;
	}

	public function set_dry_run( bool $dry_run ): void {
		$this->dry_run = $dry_run;
	}

	/**
	 * Public entry point used by both the admin UI and the WP-CLI command.
	 * Reads the ZIP, processes every post entry, and returns the counters.
	 *
	 * @return array{posts:int,media:int,failed:int}|\WP_Error
	 */
	public function run_import( string $file_path ) {
		$this->imported_posts    = 0;
		$this->imported_media    = 0;
		$this->failed_media      = 0;
		$this->imported_comments = 0;
		$this->media_cache       = array();
		$this->zip_prefix        = '';

		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'oym_no_ziparchive', __( 'PHP\'s ZipArchive extension is required to import the archive.', 'own-your-memories' ) );
		}

		$zip    = new ZipArchive();
		$opened = $zip->open( $file_path );
		if ( true !== $opened || 0 === $zip->numFiles ) {
			return new WP_Error( 'oym_zip_open_failed', __( 'Could not open the ZIP archive.', 'own-your-memories' ) );
		}

		$json_entries = $this->locate_posts_json_entries( $zip );
		if ( empty( $json_entries ) ) {
			$zip->close();
			return new WP_Error( 'oym_no_posts_json', __( 'No posts_*.json file was found in the archive. This does not look like a supported export.', 'own-your-memories' ) );
		}

		if ( ! $this->author_id ) {
			$this->author_id = get_current_user_id();
		}
		if ( ! $this->author_id ) {
			$this->author_id = (int) get_option( 'admin_user_id', 1 );
		}

		foreach ( $json_entries as $entry_name ) {
			$raw = $zip->getFromName( $entry_name );
			if ( false === $raw ) {
				$this->log( sprintf( 'Could not read %s from archive.', $entry_name ), 'warn' );
				continue;
			}
			$decoded = json_decode( $raw, true );
			if ( ! is_array( $decoded ) ) {
				$this->log( sprintf( 'Invalid JSON in %s.', $entry_name ), 'warn' );
				continue;
			}

			foreach ( $decoded as $post_entry ) {
				$this->import_post_entry( $zip, $post_entry );
			}
		}

		$zip->close();

		return array(
			'posts'    => $this->imported_posts,
			'media'    => $this->imported_media,
			'failed'   => $this->failed_media,
			'comments' => $this->imported_comments,
		);
	}

	/**
	 * Entry point routed by register_importer().
	 */
	public function dispatch(): void {
		$step = isset( $_GET['step'] ) ? (int) $_GET['step'] : 0;

		$this->set_logger( array( $this, 'html_log' ) );
		$this->header();

		switch ( $step ) {
			case 0:
				$this->greet();
				break;
			case 1:
				check_admin_referer( 'import-upload' );
				$file = wp_import_handle_upload();
				if ( isset( $file['error'] ) ) {
					echo '<p><strong>' . esc_html__( 'Sorry, there has been an error.', 'own-your-memories' ) . '</strong><br />';
					echo esc_html( $file['error'] ) . '</p>';
					break;
				}
				if ( ! isset( $file['file'], $file['id'] ) ) {
					echo '<p><strong>' . esc_html__( 'Sorry, there has been an error.', 'own-your-memories' ) . '</strong></p>';
					break;
				}
				$this->id = (int) $file['id'];

				echo '<p>' . esc_html__( 'Importing your posts. This can take a while…', 'own-your-memories' ) . '</p>';
				$this->flush_output();

				$result = $this->run_import( (string) $file['file'] );
				$this->cleanup_uploaded_file();

				if ( is_wp_error( $result ) ) {
					echo '<p><strong>' . esc_html( $result->get_error_message() ) . '</strong></p>';
					break;
				}

				echo '<h2>' . esc_html__( 'All done.', 'own-your-memories' ) . '</h2>';
				echo '<p>';
				printf(
					/* translators: 1: post count, 2: media count, 3: failed media count, 4: comment count. */
					esc_html__( 'Imported %1$d post(s), %2$d media file(s), and %4$d comment(s). %3$d media file(s) failed to import.', 'own-your-memories' ),
					(int) $result['posts'],
					(int) $result['media'],
					(int) $result['failed'],
					(int) $result['comments']
				);
				echo '</p>';
				echo '<p><a href="' . esc_url( admin_url( 'edit.php' ) ) . '">' . esc_html__( 'View your imported posts', 'own-your-memories' ) . '</a></p>';
				break;
		}

		$this->footer();
	}

	private function header(): void {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Own Your Memories', 'own-your-memories' ) . '</h1>';
	}

	private function footer(): void {
		echo '</div>';
	}

	private function greet(): void {
		echo '<div class="narrow">';
		echo '<p>' . esc_html__( 'Howdy! Upload your Instagram "Download Your Information" ZIP archive and we\'ll create one WordPress post per source post. Carousels become gallery blocks, hashtags become tags, @mentions are linked to the source profile, and comments are imported as WordPress comments with author names linked to their source profiles.', 'own-your-memories' ) . '</p>';
		echo '<p>' . esc_html__( 'Stories, reels, profile photos, and saved items are not imported.', 'own-your-memories' ) . '</p>';
		wp_import_upload_form( add_query_arg( 'step', 1 ) );
		echo '</div>';
	}

	/**
	 * Default logger for the admin UI: emits paragraphs and flushes.
	 */
	public function html_log( string $message, string $level = 'info' ): void {
		if ( 'info' === $level ) {
			echo '<p>' . esc_html( $message ) . '</p>';
		} else {
			$class = 'notice notice-' . ( 'warn' === $level ? 'warning' : $level );
			echo '<p class="' . esc_attr( $class ) . '">' . esc_html( $message ) . '</p>';
		}
		$this->flush_output();
	}

	private function log( string $message, string $level = 'info' ): void {
		if ( is_callable( $this->logger ) ) {
			call_user_func( $this->logger, $message, $level );
		}
	}

	/**
	 * @param mixed $post_entry
	 */
	private function import_post_entry( ZipArchive $zip, $post_entry ): void {
		if ( ! is_array( $post_entry ) ) {
			return;
		}

		$media_list = array();
		if ( isset( $post_entry['media'] ) && is_array( $post_entry['media'] ) ) {
			$media_list = $post_entry['media'];
		} elseif ( isset( $post_entry['uri'] ) ) {
			$media_list = array( $post_entry );
		}
		if ( empty( $media_list ) ) {
			return;
		}

		$caption   = isset( $post_entry['title'] ) && is_string( $post_entry['title'] )
			? $this->fix_mojibake( $post_entry['title'] )
			: '';
		$timestamp = isset( $post_entry['creation_timestamp'] ) ? (int) $post_entry['creation_timestamp'] : 0;

		$uploaded = array();
		foreach ( $media_list as $media ) {
			if ( empty( $media['uri'] ) ) {
				continue;
			}
			if ( 0 === $timestamp && isset( $media['creation_timestamp'] ) ) {
				$timestamp = (int) $media['creation_timestamp'];
			}
			if ( '' === $caption && isset( $media['title'] ) && is_string( $media['title'] ) ) {
				$caption = $this->fix_mojibake( $media['title'] );
			}

			if ( $this->dry_run ) {
				++$this->imported_media;
				$uploaded[] = array(
					'id'   => 0,
					'url'  => 'about:blank',
					'mime' => $this->guess_mime_from_uri( (string) $media['uri'] ),
				);
				continue;
			}

			$attachment_id = $this->sideload_media_from_zip( $zip, (string) $media['uri'] );
			if ( ! $attachment_id ) {
				++$this->failed_media;
				$this->log( sprintf( 'Media upload failed: %s', $media['uri'] ), 'warn' );
				continue;
			}
			++$this->imported_media;

			$url = wp_get_attachment_url( $attachment_id );
			if ( ! $url ) {
				continue;
			}
			$uploaded[] = array(
				'id'   => $attachment_id,
				'url'  => $url,
				'mime' => (string) get_post_mime_type( $attachment_id ),
			);
		}

		if ( empty( $uploaded ) ) {
			return;
		}

		list( $caption_block, $hashtags ) = $this->parse_caption( $caption );
		$media_block                      = $this->build_media_block( $uploaded );

		$content = $media_block;
		$excerpt = $this->build_excerpt( $caption );
		$title   = $this->build_post_title( $caption, $timestamp );
		$date    = $this->format_post_date( $timestamp );

		if ( $this->dry_run ) {
			++$this->imported_posts;
			$dry_comments = count( $this->extract_comments_from_entry( $post_entry ) );
			$this->log( sprintf( '[dry-run] Would import "%s" with %d media item(s) and %d comment(s).', $title, count( $uploaded ), $dry_comments ), 'info' );
			return;
		}

		$post_id = wp_insert_post(
			array(
				'post_title'    => $title,
				'post_content'  => $content,
				'post_excerpt'  => $excerpt,
				'post_status'   => 'publish',
				'post_type'     => 'post',
				'post_author'   => $this->author_id,
				'post_date'     => $date,
				'post_date_gmt' => get_gmt_from_date( $date ),
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			$this->log( 'Failed to insert post: ' . ( is_wp_error( $post_id ) ? $post_id->get_error_message() : 'unknown error' ), 'error' );
			return;
		}

		if ( ! empty( $hashtags ) ) {
			wp_set_post_tags( (int) $post_id, $hashtags, false );
		}

		// Make the first media item the featured image when it's an image.
		foreach ( $uploaded as $m ) {
			if ( 0 === strpos( $m['mime'], 'image/' ) ) {
				set_post_thumbnail( (int) $post_id, (int) $m['id'] );
				break;
			}
		}

		// Reparent attachments to the new post so cleanup tools track them.
		foreach ( $uploaded as $m ) {
			wp_update_post(
				array(
					'ID'          => (int) $m['id'],
					'post_parent' => (int) $post_id,
				)
			);
		}

		$comments = $this->extract_comments_from_entry( $post_entry );
		if ( ! empty( $comments ) ) {
			$this->import_post_comments( (int) $post_id, $comments, $timestamp );
		}

		++$this->imported_posts;
		$this->log( sprintf( 'Imported "%s" (%d comment(s))', $title, count( $comments ) ), 'success' );
	}

	/**
	 * Extract a media file from the ZIP, write to a temp file, and sideload
	 * it into the local site's media library. Returns attachment ID or 0.
	 */
	private function sideload_media_from_zip( ZipArchive $zip, string $uri ): int {
		if ( isset( $this->media_cache[ $uri ] ) ) {
			return $this->media_cache[ $uri ];
		}

		$candidates = array();
		if ( '' !== $this->zip_prefix ) {
			$candidates[] = $this->zip_prefix . $uri;
		}
		$candidates[] = $uri;

		$bytes = false;
		foreach ( $candidates as $candidate ) {
			$bytes = $zip->getFromName( $candidate );
			if ( false !== $bytes ) {
				break;
			}
		}

		if ( false === $bytes ) {
			return 0;
		}

		$filename = sanitize_file_name( basename( $uri ) );
		$tmp      = wp_tempnam( $filename );
		if ( ! $tmp ) {
			return 0;
		}

		$written = @file_put_contents( $tmp, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === $written ) {
			wp_delete_file( $tmp );
			return 0;
		}

		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp,
		);

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_handle_sideload( $file_array, 0 );
		if ( file_exists( $tmp ) ) {
			wp_delete_file( $tmp );
		}
		if ( is_wp_error( $attachment_id ) ) {
			return 0;
		}

		$this->media_cache[ $uri ] = (int) $attachment_id;
		return (int) $attachment_id;
	}

	private function guess_mime_from_uri( string $uri ): string {
		$ext = strtolower( pathinfo( $uri, PATHINFO_EXTENSION ) );
		switch ( $ext ) {
			case 'jpg':
			case 'jpeg':
				return 'image/jpeg';
			case 'png':
				return 'image/png';
			case 'webp':
				return 'image/webp';
			case 'gif':
				return 'image/gif';
			case 'mp4':
				return 'video/mp4';
			case 'mov':
				return 'video/quicktime';
			default:
				return 'application/octet-stream';
		}
	}

	/**
	 * @return array<int, string>
	 */
	private function locate_posts_json_entries( ZipArchive $zip ): array {
		$entries = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = $zip->getNameIndex( $i );
			if ( ! $name ) {
				continue;
			}
			if ( preg_match( self::POSTS_JSON_PATH_PATTERN, $name ) ) {
				$entries[] = $name;
				if ( '' === $this->zip_prefix ) {
					$this->zip_prefix = (string) preg_replace( self::POSTS_JSON_PATH_PATTERN, '', $name );
				}
			}
		}
		sort( $entries );
		return $entries;
	}

	/**
	 * @param array<int, array{id:int,url:string,mime:string}> $uploaded
	 */
	private function build_media_block( array $uploaded ): string {
		if ( 1 === count( $uploaded ) ) {
			return $this->build_single_media_block( $uploaded[0] );
		}

		$all_images = true;
		foreach ( $uploaded as $m ) {
			if ( 0 !== strpos( $m['mime'], 'image/' ) ) {
				$all_images = false;
				break;
			}
		}

		if ( $all_images ) {
			return $this->build_gallery_block( $uploaded );
		}

		$blocks = array();
		foreach ( $uploaded as $m ) {
			$blocks[] = $this->build_single_media_block( $m );
		}
		return implode( "\n\n", $blocks );
	}

	/**
	 * @param array{id:int,url:string,mime:string} $media
	 */
	private function build_single_media_block( array $media ): string {
		if ( 0 === strpos( $media['mime'], 'video/' ) ) {
			return "<!-- wp:video {\"id\":" . (int) $media['id'] . "} -->\n"
				. '<figure class="wp-block-video"><video controls src="' . esc_url( $media['url'] ) . '"></video></figure>'
				. "\n<!-- /wp:video -->";
		}
		return '<!-- wp:image {"id":' . (int) $media['id'] . ',"sizeSlug":"large","linkDestination":"none"} -->' . "\n"
			. '<figure class="wp-block-image size-large"><img src="' . esc_url( $media['url'] ) . '" alt="" class="wp-image-' . (int) $media['id'] . '"/></figure>'
			. "\n<!-- /wp:image -->";
	}

	/**
	 * @param array<int, array{id:int,url:string,mime:string}> $uploaded
	 */
	private function build_gallery_block( array $uploaded ): string {
		$ids   = array();
		$inner = array();
		foreach ( $uploaded as $m ) {
			$ids[]   = (int) $m['id'];
			$inner[] = '<!-- wp:image {"id":' . (int) $m['id'] . ',"sizeSlug":"large","linkDestination":"none"} -->' . "\n"
				. '<figure class="wp-block-image size-large"><img src="' . esc_url( $m['url'] ) . '" alt="" class="wp-image-' . (int) $m['id'] . '"/></figure>'
				. "\n<!-- /wp:image -->";
		}
		$attrs = wp_json_encode(
			array(
				'ids'    => $ids,
				'linkTo' => 'none',
			)
		);
		return '<!-- wp:gallery ' . $attrs . " -->\n"
			. '<figure class="wp-block-gallery has-nested-images columns-default is-cropped">' . "\n"
			. implode( "\n", $inner ) . "\n"
			. '</figure>' . "\n"
			. '<!-- /wp:gallery -->';
	}

	/**
	 * @return array{0: string, 1: array<int, string>}
	 */
	private function parse_caption( string $caption ): array {
		$caption = trim( $caption );
		if ( '' === $caption ) {
			return array( '', array() );
		}

		$hashtags = array();
		if ( preg_match_all( '/(^|\s)#([\p{L}\p{N}_]+)/u', $caption, $matches ) ) {
			foreach ( $matches[2] as $tag ) {
				if ( '' !== $tag ) {
					$hashtags[] = $tag;
				}
			}
		}
		$hashtags = array_values( array_unique( $hashtags ) );

		$display = (string) preg_replace( '/(^|\s)#[\p{L}\p{N}_]+/u', '$1', $caption );
		$display = trim( (string) preg_replace( '/\s+/u', ' ', $display ) );

		if ( '' === $display ) {
			return array( '', $hashtags );
		}

		$escaped = esc_html( $display );
		$escaped = (string) preg_replace_callback(
			'/(^|\s)@([A-Za-z0-9_.]+)/u',
			function ( array $m ): string {
				$handle = $m[2];
				$href   = self::SOURCE_PROFILE_BASE_URL . rawurlencode( $handle ) . '/';
				return $m[1] . '<a href="' . esc_url( $href ) . '">@' . esc_html( $handle ) . '</a>';
			},
			$escaped
		);

		$paragraph = "<!-- wp:paragraph -->\n<p>" . $escaped . "</p>\n<!-- /wp:paragraph -->";
		return array( $paragraph, $hashtags );
	}

	private function build_excerpt( string $caption ): string {
		$text = trim( $caption );
		if ( '' === $text ) {
			return '';
		}
		$text = (string) preg_replace( '/(^|\s)#[\p{L}\p{N}_]+/u', '$1', $text );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	private function build_post_title( string $caption, int $timestamp ): string {
		$caption = trim( $caption );
		if ( '' !== $caption ) {
			$first = strtok( $caption, "\n" );
			$first = trim( (string) preg_replace( '/#[\p{L}\p{N}_]+/u', '', (string) $first ) );
			if ( '' !== $first ) {
				if ( function_exists( 'mb_strlen' ) && mb_strlen( $first ) > 80 ) {
					$first = mb_substr( $first, 0, 77 ) . '…';
				}
				return $first;
			}
		}
		$date = $timestamp > 0 ? gmdate( 'Y-m-d', $timestamp ) : gmdate( 'Y-m-d' );
		return 'Memory — ' . $date;
	}

	private function format_post_date( int $timestamp ): string {
		if ( $timestamp <= 0 ) {
			return current_time( 'mysql' );
		}
		$offset = (float) get_option( 'gmt_offset', 0 );
		return gmdate( 'Y-m-d H:i:s', $timestamp + (int) ( $offset * HOUR_IN_SECONDS ) );
	}

	/**
	 * Pull comments out of a post entry, handling multiple known export formats.
	 *
	 * @return array<int, array{author:string,text:string,timestamp:int}>
	 */
	private function extract_comments_from_entry( array $post_entry ): array {
		$comments = array();

		// Format A: { "comments": { "comments": [ … ] } }
		if ( isset( $post_entry['comments']['comments'] ) && is_array( $post_entry['comments']['comments'] ) ) {
			$source = $post_entry['comments']['comments'];
		// Format B: { "comments": [ … ] }
		} elseif ( isset( $post_entry['comments'] ) && is_array( $post_entry['comments'] ) ) {
			$source = $post_entry['comments'];
		} else {
			return array();
		}

		foreach ( $source as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$normalized = $this->normalize_comment( $raw );
			if ( null !== $normalized ) {
				$comments[] = $normalized;
			}
		}

		return $comments;
	}

	/**
	 * Normalize a raw comment array from any known export variant.
	 *
	 * @return array{author:string,text:string,timestamp:int}|null
	 */
	private function normalize_comment( array $c ): ?array {
		$author    = '';
		$text      = '';
		$timestamp = 0;

		// Flat fields used by older exports.
		if ( isset( $c['author'] ) && is_string( $c['author'] ) ) {
			$author = $c['author'];
		}
		if ( isset( $c['value'] ) && is_string( $c['value'] ) ) {
			$text = $c['value'];
		} elseif ( isset( $c['text'] ) && is_string( $c['text'] ) ) {
			$text = $c['text'];
		}
		if ( isset( $c['creation_timestamp'] ) ) {
			$timestamp = (int) $c['creation_timestamp'];
		} elseif ( isset( $c['timestamp'] ) ) {
			$timestamp = (int) $c['timestamp'];
		}

		// string_list_data format: author in 'title', text + ts in the data list.
		if ( isset( $c['string_list_data'] ) && is_array( $c['string_list_data'] ) ) {
			if ( '' === $author && isset( $c['title'] ) && is_string( $c['title'] ) ) {
				$author = $c['title'];
			}
			foreach ( $c['string_list_data'] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				if ( '' === $text && isset( $item['value'] ) && is_string( $item['value'] ) ) {
					$text = $item['value'];
				}
				if ( 0 === $timestamp && isset( $item['timestamp'] ) ) {
					$timestamp = (int) $item['timestamp'];
				}
			}
		}

		// string_map_data format used in newer exports.
		if ( isset( $c['string_map_data'] ) && is_array( $c['string_map_data'] ) ) {
			if ( '' === $author && isset( $c['title'] ) && is_string( $c['title'] ) ) {
				$author = $c['title'];
			}
			$map = $c['string_map_data'];
			if ( '' === $text && isset( $map['Comment']['value'] ) && is_string( $map['Comment']['value'] ) ) {
				$text = $map['Comment']['value'];
			}
			if ( 0 === $timestamp && isset( $map['Comment']['timestamp'] ) ) {
				$timestamp = (int) $map['Comment']['timestamp'];
			}
		}

		if ( '' === $author || '' === $text ) {
			return null;
		}

		return array(
			'author'    => $this->fix_mojibake( $author ),
			'text'      => $this->fix_mojibake( $text ),
			'timestamp' => $timestamp,
		);
	}

	/**
	 * Insert each extracted comment as a WordPress comment on $post_id.
	 * The comment author name becomes a link to the commenter's source profile
	 * via comment_author_url, which most themes render as a hyperlink.
	 *
	 * @param array<int, array{author:string,text:string,timestamp:int}> $comments
	 */
	private function import_post_comments( int $post_id, array $comments, int $post_timestamp ): void {
		foreach ( $comments as $comment ) {
			$ts       = $comment['timestamp'] > 0 ? $comment['timestamp'] : $post_timestamp;
			$date     = $this->format_post_date( $ts );
			$date_gmt = $ts > 0 ? gmdate( 'Y-m-d H:i:s', $ts ) : get_gmt_from_date( $date );

			$comment_id = wp_insert_comment(
				array(
					'comment_post_ID'    => $post_id,
					'comment_author'     => $comment['author'],
					'comment_author_url' => self::SOURCE_PROFILE_BASE_URL . rawurlencode( $comment['author'] ) . '/',
					'comment_content'    => $comment['text'],
					'comment_date'       => $date,
					'comment_date_gmt'   => $date_gmt,
					'comment_approved'   => 1,
					'comment_type'       => 'comment',
					'comment_author_IP'  => '',
					'comment_agent'      => 'Own Your Memories',
					'user_id'            => 0,
				)
			);

			if ( $comment_id && ! is_wp_error( $comment_id ) ) {
				++$this->imported_comments;
			}
		}
	}

	private function fix_mojibake( string $string ): string {
		if ( '' === $string ) {
			return $string;
		}
		if ( ! preg_match( '/\xC3[\x80-\xBF]/', $string ) ) {
			return $string;
		}
		if ( ! function_exists( 'mb_convert_encoding' ) ) {
			return $string;
		}
		$fixed = @mb_convert_encoding( $string, 'ISO-8859-1', 'UTF-8' );
		if ( false === $fixed || null === $fixed ) {
			return $string;
		}
		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $fixed, 'UTF-8' ) ) {
			return $string;
		}
		return $fixed;
	}

	private function cleanup_uploaded_file(): void {
		if ( $this->id > 0 ) {
			wp_delete_attachment( $this->id, true );
			$this->id = 0;
		}
	}

	private function flush_output(): void {
		if ( function_exists( 'wp_ob_end_flush_all' ) ) {
			wp_ob_end_flush_all();
		}
		if ( function_exists( 'flush' ) ) {
			@flush();
		}
	}
}
