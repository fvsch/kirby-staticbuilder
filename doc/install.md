Installing and activating StaticBuilder
=======================================


Requirements
------------

StaticBuilder requires Kirby 2.3.1 or later.

**You should not use or enable StaticBuilder on a production server.** This could have many security implications. At the very least, if site visitors can go to `http://yourwebsite/staticbuilder/` and see the StaticBuilder page, they can trigger static builds and strain your web server.


Installation
------------

There are 3 different ways to install this plugin: the classic way (downloading a zip), with Kirby CLI, or with Composer. If you’re not sure what method to use, pick the classic install.

### Classic install

1. [Download a ZIP of the latest release](https://github.com/fvsch/kirby-staticbuilder/releases/latest).
2. Unzip it, and rename the folder to `staticbuilder`.
3. Put that folder in your project’s `site/plugins` folder.

Now skip to the “Activation” section.

### Kirby CLI install

If you have the [Kirby CLI](https://github.com/getkirby/cli) tool installed, in a terminal or console at the root of your Kirby project, run:

```sh
kirby plugin:install fvsch/kirby-staticbuilder
```

### Composer install

In a terminal or console at the root of your Kirby project, run:

```
composer require fvsch/kirby-staticbuilder
```

Make sure you’re requering the `vendor/autoload.php` file. For instance, you could have a `site.php` file at the projet root, with this code:

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');
$kirby = kirby();
```


Activation
----------

Enabling this plugin takes two lines of PHP, but we’re going to take a detour first because you probably *don’t want to enable this plugin on a live website*.

So let’s say you only want to enable Kirby StaticBuilder on your own computer. What is the URL you use to access your site locally? Typically it would look like one of those:

- `http://localhost/`
- `http://localhost:8080/`
- `http://test.mywebsite.dev/`

If you don’t have one yet, you should create a config file just for this domain in your `site/config` directory, for instance:

- `site/config/config.localhost.php` (works for the first 2 examples)
- `site/config/config.test.mywebsite.dev.php` (works for the last one)
- `site/config/config.127.0.0.1.php` (should work for all 3 examples)

Then in this domain-specific config file, add:

```php
<?php
// Enable Kirby StaticBuilder locally
c::set('staticbuilder', true);

// StaticBuilder requires Kirby’s cache to be disabled
c::set('cache', false);
```

If you installed the plugin with Composer, add this line:

```php
// Enable routes for the StaticBuilder plugin
Kirby\StaticBuilder\Controller::register();
```


Usage
-----

1. Go to `http://localhost/staticbuilder/`, where `localhost` is the domain where you can see your local Kirby site. (It might be different, depending on the test server you use or how you configured it.)
2. You should see a list of pages. Check that these are indeed pages you want to export as HTML, and [tweak the options](options.md) if needed.
3. Hit the “Build” button. Hopefully things will work alright. If you have many pages (e.g. a few hundred), it might take a few seconds.

Note: every time you do a full build, the content of the `static` folder will be deleted. Don’t make changes there, or you will lose this work!
