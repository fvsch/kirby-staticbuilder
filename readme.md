StaticBuilder Plugin for Kirby CMS
==================================

A plugin for converting your Kirby CMS site to static files (HTML, assets, etc.).


## Installation

*   Requires Kirby 2.3
*   Download this repository and put its content in `site/plugins/staticbuilder`
*   In your config (preferably the config for your local instance, not the main or production config!), enable the plugin: `c::set('plugin.staticbuilder.enabled', true);`


## Usage

Navigate to:

-   `/staticbuilder`, or
-   `/staticbuilder/[page-uri]`

To see information about the site or a specific page, and generate the static files for this site or page.

See the [configuration documentation](doc/config.md).


## Known issues

-   When building all pages, [each controller is only executed once](https://github.com/fvsch/kirby-staticbuilder/issues/9). This is a bug in Kirby core which should be fixed soon-ish.


## Roadmap

See:

-   https://github.com/fvsch/kirby-staticbuilder/labels/roadmap
-   https://github.com/fvsch/kirby-staticbuilder/labels/maybe
