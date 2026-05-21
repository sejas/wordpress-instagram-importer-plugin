=== Own Your Memories ===
Contributors: mrfoxtalbot, antoniosejas
Tags: importer, migration, posts, media, memories
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import posts, media, and comments from an Instagram "Download Your Information" ZIP export into your WordPress site — own your memories.

== Description ==

Own Your Memories turns an Instagram data export (JSON format) into native WordPress posts. One WordPress post is created per source post; carousels become Gutenberg gallery blocks; hashtags become tags; @mentions become links to the corresponding source profile.

What gets imported:

* Photo posts (single and carousel)
* Video posts (single and mixed carousel)
* Captions (with hashtag and @mention parsing)
* Original creation date (adjusted to your site's timezone)
* Comments (with original timestamps and author profile links)

What is **not** imported:

* Stories
* Reels (only feed videos)
* Profile photos
* Saved items

The first image in each post is set as the featured image. All extracted media is stored in your site's media library.

== Recommended companion plugin ==

Once your content is imported, check out [Featured Images, Galleries & Videos](https://wordpress.org/plugins/featured-images-gallery-video/). It provides a block that works just like Instagram's own post display: it automatically shows images, galleries, or videos based on each post's content — no manual configuration needed per post.

== How to get your export ==

1. Open Instagram → Settings → Accounts Center → Your information and permissions → Download your information.
2. Choose **JSON** format (this importer does not read HTML exports).
3. Select **Posts** (other items are ignored by the importer).
4. Select the desired **date range**.
5. Wait for the email, download the ZIP, and upload it via Tools → Import → Own Your Memories in your WordPress admin.

== Installation ==

1. Upload the `own-your-memories` folder to `/wp-content/plugins/`.
2. Activate the plugin via the **Plugins** menu in WordPress.
3. Visit **Tools → Import → Own Your Memories** and upload your ZIP.

== Frequently Asked Questions ==

= My ZIP is huge — will this work? =

The importer raises PHP's memory limit while running, but very large exports (thousands of posts and gigabytes of media) may still hit server limits. Try splitting the ZIP, or run on a host that allows long-running admin scripts.

= Do I need to keep the ZIP after importing? =

No. The plugin deletes the uploaded ZIP from your media library after the import completes.

= Will my new posts be imported automatically to my WordPress site? =

No. Own Your Memories is a one-off migration tool to help you move your existing content into WordPress. Ongoing or automatic syncing of new posts is out of scope for this plugin.

= Why are some posts missing? =

Stories and reels are intentionally skipped. If feed posts are missing, ensure you exported in **JSON** (not HTML) and selected the **Posts** category.

== Changelog ==

= 0.2.0 =
* Rebranded to Own Your Memories.
* Added WP-CLI support (`wp own-your-memories import <zip>`).
* Import comments with timestamps and author profile links.
* Use the caption as the post title and excerpt.

= 0.1.0 =
* Initial release.
