<?php
/**
 * Video processor: extract a first-frame thumbnail and upload to VideoPress.
 *
 * Called after each video attachment is sideloaded during an Instagram import.
 * The thumbnail is created via FFmpeg (preferred) or PHP Imagick and registered
 * as a standard WordPress attachment so it can be used as a featured image.
 * VideoPress upload is triggered when Jetpack or the standalone VideoPress
 * plugin is active; the call is a no-op otherwise, so the plugin degrades
 * gracefully on sites without VideoPress.
 *
 * @package Instagram_Importer
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Instagram_Video_Processor {

	/**
	 * Extract a first-frame thumbnail and initiate a VideoPress upload.
	 *
	 * @param int $attachment_id  Video attachment ID already in the media library.
	 * @param int $post_id        Parent post ID — the thumbnail is parented here.
	 * @return int Thumbnail attachment ID on success, 0 on failure.
	 */
	public function process( int $attachment_id, int $post_id ): int {
		$thumbnail_id = $this->extract_thumbnail( $attachment_id, $post_id );
		$this->upload_to_videopress( $attachment_id );
		return $thumbnail_id;
	}

	// -------------------------------------------------------------------------
	// Thumbnail extraction
	// -------------------------------------------------------------------------

	private function extract_thumbnail( int $attachment_id, int $post_id ): int {
		$video_path = get_attached_file( $attachment_id );
		if ( ! $video_path || ! file_exists( $video_path ) ) {
			return 0;
		}

		$image_path = $this->extract_frame( $video_path, $attachment_id );
		if ( '' === $image_path ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$file_array = array(
			'name'     => 'video-thumb-' . $attachment_id . '.jpg',
			'tmp_name' => $image_path,
		);

		$thumbnail_id = media_handle_sideload( $file_array, $post_id, '' );

		if ( file_exists( $image_path ) ) {
			wp_delete_file( $image_path );
		}

		if ( is_wp_error( $thumbnail_id ) ) {
			return 0;
		}

		return (int) $thumbnail_id;
	}

	/**
	 * Try FFmpeg first (shell), then PHP Imagick.
	 * Returns the absolute path to a JPEG temp file, or '' on failure.
	 */
	private function extract_frame( string $video_path, int $attachment_id ): string {
		$upload   = wp_upload_dir();
		$tmp_path = trailingslashit( $upload['path'] ) . 'ig-thumb-' . $attachment_id . '.jpg';

		if ( $this->has_ffmpeg() ) {
			return $this->extract_frame_ffmpeg( $video_path, $tmp_path );
		}

		if ( $this->has_imagick() ) {
			return $this->extract_frame_imagick( $video_path, $tmp_path );
		}

		return '';
	}

	private function has_ffmpeg(): bool {
		if ( ! function_exists( 'exec' ) ) {
			return false;
		}
		$out = array();
		exec( 'which ffmpeg 2>/dev/null', $out );
		return ! empty( $out[0] );
	}

	private function has_imagick(): bool {
		return extension_loaded( 'imagick' ) && class_exists( 'Imagick' );
	}

	/**
	 * Extract via FFmpeg. Tries at 1 s first, then frame 0 for short clips.
	 */
	private function extract_frame_ffmpeg( string $video_path, string $output_path ): string {
		foreach ( array( '00:00:01', '00:00:00' ) as $ts ) {
			$cmd = sprintf(
				'ffmpeg -y -i %s -ss %s -vframes 1 %s 2>/dev/null',
				escapeshellarg( $video_path ),
				$ts,
				escapeshellarg( $output_path )
			);
			exec( $cmd ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			if ( file_exists( $output_path ) && filesize( $output_path ) > 0 ) {
				return $output_path;
			}
		}
		return '';
	}

	/**
	 * Extract via Imagick (requires Imagick built with video/delegate support).
	 */
	private function extract_frame_imagick( string $video_path, string $output_path ): string {
		try {
			$im = new Imagick( $video_path . '[0]' );
			$im->setImageFormat( 'jpeg' );
			$im->writeImage( $output_path );
			$im->destroy();
			return ( file_exists( $output_path ) && filesize( $output_path ) > 0 ) ? $output_path : '';
		} catch ( Exception $e ) {
			return '';
		}
	}

	// -------------------------------------------------------------------------
	// VideoPress upload
	// -------------------------------------------------------------------------

	/**
	 * Upload the video to VideoPress when available.
	 *
	 * Supports the standalone VideoPress plugin and the Jetpack VideoPress
	 * module. Falls back to firing the `add_attachment` action so any active
	 * VideoPress handler registered on that hook can pick it up.
	 */
	private function upload_to_videopress( int $attachment_id ): void {
		// Standalone VideoPress plugin (videopress/videopress.php).
		if ( function_exists( 'videopress_upload_attachment' ) ) {
			videopress_upload_attachment( $attachment_id );
			return;
		}

		// Jetpack VideoPress module.
		if ( function_exists( 'videopress_handle_upload' ) ) {
			videopress_handle_upload( $attachment_id );
			return;
		}

		// Last resort: fire the core add_attachment action so any VideoPress handler picks it up.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- intentionally firing core WP hook
		do_action( 'add_attachment', $attachment_id );
	}
}
