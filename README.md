# Instagram Importer

A WordPress plugin that imports posts and media from an Instagram "Download Your Information" ZIP archive into your WordPress site.

## What it does

- Creates one WordPress post per Instagram post.
- Carousels become **core/gallery** blocks (or sequential image/video blocks for mixed media).
- Hashtags from captions are mapped to WordPress **tags**.
- `@mentions` become anchor tags pointing at `https://www.instagram.com/<handle>/`.
- Original creation timestamps are preserved (adjusted for your site's timezone).
- The first image in each post becomes the featured image.

## What it does NOT do

- Does not import Stories, Reels (non-feed), profile photos, comments, or saved items.
- Does not work with the **HTML** export format — only the **JSON** format.

## Install

1. Copy this directory into `wp-content/plugins/instagram-importer` on your site.
2. Activate **Instagram Importer** in **Plugins**.
3. Go to **Tools → Import → Instagram**.
4. Upload your `instagram-<username>-<date>-<hash>.zip`.

## How to get your Instagram export

1. Instagram app → Settings → Accounts Center → Your information and permissions → **Download your information**.
2. Choose **JSON** format.
3. Select **Posts**.
4. Wait for the email, download the ZIP, upload it to WordPress.

## Architecture

```
instagram-importer/
├── instagram-importer.php          # Plugin header + register_importer() hook
├── includes/
│   └── class-instagram-importer.php # WP_Importer subclass: greet → upload → import
└── readme.txt                      # WordPress plugin readme
```

The importer extends WordPress's built-in `WP_Importer` class and registers itself with `register_importer()` so it appears at **Tools → Import** alongside the WordPress, Blogger, Tumblr, etc. importers.

### Pipeline

1. User uploads the ZIP via the standard WordPress importer UI (`wp_import_handle_upload()`).
2. Plugin opens the ZIP with `ZipArchive`, locates every `your_instagram_activity/media/posts_*.json`.
3. For each post entry:
   - Extracts referenced media bytes from the ZIP.
   - Sideloads each media file into the local site's media library via `media_handle_sideload()`.
   - Builds Gutenberg block markup (gallery / image / video).
   - Inserts a published post via `wp_insert_post()`.
   - Applies tags via `wp_set_post_tags()`.
   - Sets the first image as the featured image.
   - Reparents the attachments to the new post.
4. Deletes the uploaded ZIP attachment when finished.

No external services are contacted — everything runs locally on your WordPress install.

## Caveats

- Large exports may hit PHP `max_execution_time` or `memory_limit` even though the plugin raises both. Run on a host that permits long-running admin scripts, or split the ZIP.
- Instagram's JSON export sometimes stores captions as UTF-8 bytes re-encoded as Latin-1 ("mojibake", e.g. `Ã±` instead of `ñ`). The importer detects and repairs this.
- Featured image selection picks the first image (carousel order). If the order matters, re-sort the gallery in the editor after import.

## License

GPL-2.0-or-later. See `LICENSE`.
