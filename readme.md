StaticBuilder Plugin for Kirby CMS
==================================

**Work in progress!** Plugin that tries to convert your Kirby CMS site to static files (HTML, assets, etc.).


## Installation

*   Requires Kirby 2.3
*   Download this repository and put its content in `site/plugins/staticbuilder`
*   In your config (preferably the config for your local instance, not the main or production config!), enable the plugin: `c::set('plugin.staticbuilder.enabled', true);`


## Usage

Navigate to:

-   `/staticbuilder`
-   `/staticbuilder/[page-uri]`

To see information about the site or a specific page, and generate the static files for this site or page.


## Current status

-   Pages are generated
-   Assets are copied
-   This plugin will write files to `[yourproject]/static` and should not EVER write files anywhere else.
-   There might be bugs, [look at the issues](https://github.com/fvsch/kirby-staticbuilder/issues)


## Roadmap

See:

-   https://github.com/fvsch/kirby-staticbuilder/labels/roadmap
-   https://github.com/fvsch/kirby-staticbuilder/labels/maybe
