=== Own Your Memories ===
Contributors: sejas, mrfoxtalbot
Tags: importer, migration, posts, media, memories
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import posts and media from an Instagram "Download Your Information" ZIP export into your WordPress site — own your memories.

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

The first image in each post is set as the featured image. The post description is added as the excerpt. All extracted media is stored in your site's media library.

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

The importer raises PHP's time and memory limits while running, but very large exports (thousands of posts and gigabytes of media) may still hit server limits. Try splitting the ZIP by requesting multiple output files, each for a different time period, or run on a host that allows long-running scripts.

= Do I need to keep the ZIP after importing? =

No. The plugin deletes the uploaded ZIP from your media library after the import completes.

= Why are some posts missing? =

Stories and reels are intentionally skipped. If feed posts are missing, ensure you exported in **JSON** (not HTML) and selected the **Posts** category.

== Changelog ==

= 0.2.0 =
* Rebranded to Own Your Memories.
* Import comments with timestamps and author profile links.
* Use the caption as the post title and excerpt.

= 0.1.0 =
* Initial release.
