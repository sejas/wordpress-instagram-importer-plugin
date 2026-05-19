=== Instagram Importer ===
Contributors: mrfoxtalbot, antoniosejas
Tags: importer, instagram, migration, posts, media
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import Instagram posts from a ZIP. Carousels become galleries, hashtags become tags, and media is imported to your site's media library.

== Description ==

Instagram Importer turns an Instagram data export (JSON format) into native WordPress posts. One WordPress post is created per Instagram post; carousels become Gutenberg gallery blocks; hashtags become tags; @mentions become links to the corresponding Instagram profile.

What gets imported:

* Photo posts (single and carousel)
* Video posts (single and mixed carousel)
* Captions (with hashtag and @mention parsing)
* Original creation date (adjusted to your site's timezone)

What is **not** imported:

* Stories
* Reels (only feed videos)
* Profile photos
* Comments and saved items

The first image in each post is set as the featured image. For video-only posts, a featured image is automatically extracted from the first frame (requires FFmpeg or PHP Imagick on the server). Videos are also uploaded to VideoPress when Jetpack or the standalone VideoPress plugin is active. All extracted media is stored in your site's media library.

== Recommended companion plugin ==

Once your content is imported, check out [Featured Images, Galleries & Videos](https://wordpress.org/plugins/featured-images-gallery-video/). It provides a block that works just like Instagram's own post display: it automatically shows images, galleries, or videos based on each post's content — no manual configuration needed per post.

== How to get your Instagram export ==

1. Open Instagram → Settings → Accounts Center → Your information and permissions → Download your information.
2. Choose **JSON** format (this importer does not read HTML exports).
3. Select **Posts** (other surfaces are ignored by the importer).
4. Wait for the email from Instagram, download the ZIP, and upload it via Tools → Import → Instagram in your WordPress admin.

== Installation ==

1. Upload the `instagram-importer` folder to `/wp-content/plugins/`.
2. Activate the plugin via the **Plugins** menu in WordPress.
3. Visit **Tools → Import → Instagram** and upload your ZIP.

== Frequently Asked Questions ==

= My ZIP is huge — will this work? =

The importer raises PHP's memory limit while running, but very large exports (thousands of posts and gigabytes of media) may still hit server limits. Try splitting the ZIP, or run on a host that allows long-running admin scripts.

= Do I need to keep the ZIP after importing? =

No. The plugin deletes the uploaded ZIP from your media library after the import completes.

= Will my new Instagram posts be imported automatically to my WordPress site? =

No. Instagram Importer is intended as a one-off migration tool to help you move your existing Instagram content into WordPress. Ongoing or automatic syncing of new Instagram posts is out of the scope of this plugin.

= Why are some posts missing? =

Stories and reels are intentionally skipped. If feed posts are missing, ensure you exported in **JSON** (not HTML) and selected the **Posts** category.

== Changelog ==

= 0.3.0 =
* Video posts are now uploaded to VideoPress when Jetpack or the standalone VideoPress plugin is active.
* Featured images are now automatically extracted from the first frame of video-only posts (requires FFmpeg or PHP Imagick).

= 0.2.0 =
* Added WP-CLI support (`wp instagram-importer import <zip>`).
* Added comment import with author names linked to Instagram profiles.

= 0.1.0 =
* Initial release.
