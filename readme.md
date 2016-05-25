StaticBuilder Plugin for Kirby CMS
==================================

A plugin for converting your Kirby CMS site to static files (HTML, assets, etc.).


Installation
------------

*   Requires Kirby 2.3
*   Download this repository and put its content in `site/plugins/staticbuilder`


Usage
-----

1.  Enable the plugin: see the [config documentation](doc/config.md).
    (It’s simple really, but we don’t want to enable it for a live website!)

2.  Load `http://[local-domain]/staticbuilder` in a web browser.
    You should see a list of pages. Check that these are indeed pages you want to export as HTML, and tweak the [options](doc/options.md) if needed.

3.  Hit the “Build” button. Hopefully things will work alright.


Documentation
-------------

-   [StaticBuilder Options](doc/options.md)
-   [Best practices for static sites](doc/static.md)


Running into bugs?
------------------

See the [list of issues](https://github.com/fvsch/kirby-staticbuilder/issues), and if nothing matches please create a new one (you will need a GitHub account).


Known issues
------------

-   When building all pages, [each controller is only executed once](https://github.com/fvsch/kirby-staticbuilder/issues/9). This is a bug in Kirby core which should be fixed soon-ish.


Roadmap
-------

-   [Planned features or fixes](https://github.com/fvsch/kirby-staticbuilder/labels/roadmap)
-   [Under consideration](https://github.com/fvsch/kirby-staticbuilder/labels/maybe)
