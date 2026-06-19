# v0.4.1-alpha

Public-safe alpha update.

## What changed

* Default configuration no longer points to Behringer World.
* Extension is disabled by default.
* Wiki API URL uses `https://example.com/mediawiki/api.php`.
* Wiki page base URL uses `https://example.com/mediawiki/index.php/`.
* Alias list is empty by default.
* README now clearly states that configuration is required before use.

## Important

Before enabling this extension, edit:

```text
event/listener.php
```

and configure your own MediaWiki API URL and wiki page base URL.

This is still an alpha proof-of-concept and needs review by experienced phpBB extension developers.
