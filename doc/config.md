Kirby StaticBuilder configuration
=================================


## Enable the plugin

```php
c::set('plugin.staticbuilder.enabled', true);
```


## Defaults

```php
c::set([
    'plugin.staticbuilder.enabled',    => false,
    'plugin.staticbuilder.outputdir'   => 'static',
    'plugin.staticbuilder.filename'    => '.html',
    'plugin.staticbuilder.baseurl'     => '/',
    'plugin.staticbuilder.assets'      => ['assets', 'content', 'thumbs'],
    'plugin.staticbuilder.uglyurls'    => false,
    'plugin.staticbuilder.pagefiles'   => false,
    'plugin.staticbuilder.filter'      => null,
    'plugin.staticbuilder.catcherrors' => false
]);
```
