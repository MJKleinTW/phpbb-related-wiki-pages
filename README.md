# Related Wiki Pages for phpBB

Version: 0.4.0-alpha

This phpBB extension shows a **Related Wiki Pages** box on phpBB topic pages.

It searches a configured MediaWiki site using the current topic title and optional first-post text, then displays matching wiki pages inside the phpBB topic view.

This was originally developed for Behringer World to connect forum discussions with related MediaWiki documentation.

## Project status

This is an early proof-of-concept extension.

It is working in limited testing, but it is not yet a polished public phpBB extension. It needs review, cleanup, ACP configuration options, language file support, ranking improvements, and general hardening by someone with more phpBB extension development experience.

Developers are welcome to fork this project, improve it, or turn it into a more complete and generally usable phpBB extension.

## Current features

* Displays related MediaWiki pages inside phpBB topic pages
* Uses the topic title as the primary search signal
* Can also use first-post text as an additional search signal
* Uses MediaWiki's API for searching
* Removes duplicate wiki results
* Includes simple keyword cleanup
* Includes optional alias searches for site-specific terminology
* Opens wiki links in a new tab/window
* Caches results for 24 hours
* Does not modify the phpBB database

## Safety default

Admin-only test mode is enabled by default.

In:

```text
ext/mjklein/relatedwiki/event/listener.php
```

the setting is:

```php
protected $admin_only_test_mode = true;
```

That means only board admins trigger the wiki API lookup and only board admins see the Related Wiki Pages box.

Normal users will not see anything until this is changed to:

```php
protected $admin_only_test_mode = false;
```

## Install

Upload the extension to:

```text
/ext/mjklein/relatedwiki/
```

Then enable it in phpBB:

```text
ACP → Customise → Manage extensions → Related Wiki Pages → Enable
```

Then purge the phpBB cache.

## Main configuration

Configuration is currently hard-coded in:

```text
ext/mjklein/relatedwiki/event/listener.php
```

Main values:

```php
protected $enabled = true;
protected $admin_only_test_mode = true;
protected $allowed_forum_ids = array();
protected $blocked_forum_ids = array();

protected $wiki_api = 'https://behringer.world/mediawiki/api.php';
protected $wiki_page_base = 'https://behringer.world/mediawiki/index.php/';

protected $max_results = 5;
protected $cache_seconds = 86400;
protected $request_timeout = 2;
```

First-post search:

```php
protected $use_first_post_text = true;
protected $first_post_excerpt_chars = 500;
```

## Optional forum allowlist

To show related wiki pages only in selected forums, set forum IDs like this:

```php
protected $allowed_forum_ids = array(2, 7, 14);
```

Leave it empty to allow all forums.

## Aliases

Aliases are configured in:

```php
protected $alias_searches = array(...);
```

These are additional search hints. They do not replace normal MediaWiki search.

## Notes

This is still an MVP / alpha release.

It does not include an ACP settings page yet.

It does not include language files yet.

It does not modify the database.

It reads the first post from phpBB's normal topic view data when available; it does not query the phpBB database separately.
