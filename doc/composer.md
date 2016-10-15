Installing StaticBuilder with Composer
======================================

Starting with 2.0, StaticBuilder can be installed with [Composer](https://getcomposer.org/).

This very short guide is based on the assumption that you have Composer installed and you are comfortable using it for installing packages.


## Using StaticBuilder

In a Terminal or console at the root of your Kirby project, run:

```
composer require fvsch/kirby-staticbuilder
```

If you don’t have those already, this will create a `composer.json` file, a `composer.lock` and a `vendor` directory. Note that, if you’re using Git, you should probably add `/vendor` to your `.gitignore`.


## Activating the StaticBuilder plugin

1.  At the root of your project, you should have a `index.php`. Now you should create an empty `site.php` file alongside it, with this code:
    ```php
    <?php
    require_once(__DIR__ . '/vendor/autoload.php');
    $kirby = kirby();
    ```

2.  In your options, and preferably in the options for your *local* site only, activate the plugin:
    ```php
    c::set('staticbuilder', true);
    ```

3.  In your `site/plugins` folder, create a `composer.php` script, with this content:
    ```php
    <?php
    // Enable routes for the StaticBuilder plugin
    Kirby\StaticBuilder\Controller::register();
    ```

Note that you can name this last file any way you want. I’m using `site/plugins/composer.php` because I tend to use this file to register other plugins installed with Composer.
