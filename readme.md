StaticBuilder Plugin for Kirby CMS
==================================

**Work in progress!** Plugin that tries to convert your Kirby CMS site to static files (HTML, assets, etc.).

## Installation

*   Requires Kirby 2.3 (currently in beta)!
*   Download this repository and put its content in `site/plugins/staticbuilder`
*   In your config (preferably the config for your local instance, not the main or production config!), enable the plugin: `c::set('plugin.staticbuilder.enabled', true);`


## Current status

Working:

-   Generate a single page by navigating to
    `/staticbuilder/page/[page-uri]`

Perhaps working, perhaps broken:

-   Generate all pages by navigating to
    `staticbuilder/site`


## Roadmap

See:

-   https://github.com/fvsch/kirby-staticbuilder/labels/roadmap
-   https://github.com/fvsch/kirby-staticbuilder/labels/maybe
