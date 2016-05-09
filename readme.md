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
    (it )


## Roadmap

Things I’d like to include:

- [ ] Copy page files
- [ ] Make it a tiny bit reliable when building many pages. Maybe split the workload in some way?
- [ ] CLI usage (e.g. `php site/plugins/staticbuilder/cli.php [parameters]`)
- [ ] Authentication, maybe? (Right now there is none and it’s not intended to be enabled on a live site! That would be a massive denial-of-service risk!)
