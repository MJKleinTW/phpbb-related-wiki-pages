# Related Wiki Pages for phpBB

Version: 0.4.0

This phpBB extension shows a "Related Wiki Pages" box on topic pages.

It searches the Behringer World MediaWiki using the current topic title plus optional first-post text, then displays matching wiki pages.

## New in 0.4

- Uses first-post text as an additional search signal.
- Keeps the topic title as the primary search signal.
- Adds domain-specific BW aliases such as Dante, MIDI, OSC, Hub4, DP48, Ultranet, stagebox, S16, routing, etc.
- Cache key includes topic title, first-post timestamp, and a short first-post text hash.
- If the first post is edited, results refresh automatically after the cache key changes.
- Keeps v0.3 duplicate removal.
- Keeps wiki links opening in a new tab/window.
- Keeps admin-only test mode enabled by default.
- Includes the stronger visible box CSS.

## Safety default

In:

    ext/mjklein/relatedwiki/event/listener.php

the setting is:

    protected $admin_only_test_mode = true;

That means only board admins trigger the wiki API lookup and only board admins see the Related Wiki Pages box.

Normal users will not see anything until you change it to:

    protected $admin_only_test_mode = false;

## Install / update

Upload the folder structure to:

    ext/mjklein/relatedwiki/

Overwrite the existing files.

Then in phpBB ACP:

    General > Purge the cache

If phpBB acts odd after overwriting files, disable and re-enable the extension, then purge cache again.

## Main config

Edit these values in:

    ext/mjklein/relatedwiki/event/listener.php

Main values:

    protected $enabled = true;
    protected $admin_only_test_mode = true;
    protected $allowed_forum_ids = array();
    protected $blocked_forum_ids = array();
    protected $wiki_api = 'https://behringer.world/mediawiki/api.php';
    protected $wiki_page_base = 'https://behringer.world/mediawiki/index.php/';
    protected $max_results = 5;
    protected $cache_seconds = 86400;
    protected $request_timeout = 2;

First-post search:

    protected $use_first_post_text = true;
    protected $first_post_excerpt_chars = 500;

## Optional forum allowlist

To show related wiki pages only in selected forums, set forum IDs like this:

    protected $allowed_forum_ids = array(2, 7, 14);

Leave it empty to allow all forums.

## Aliases

Aliases are configured in:

    protected $alias_searches = array(...);

These are additional search hints. They do not replace normal search.

## Notes

This is still an MVP.

It does not include an ACP settings page yet.

It does not modify the database.

It reads the first post from phpBB's viewtopic data when available; it does not query the phpBB database separately.
