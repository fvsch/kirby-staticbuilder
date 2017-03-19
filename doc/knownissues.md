Known issues
============


### Empty translations when rendering pages

Cause: Translation strings are not loaded. This is a known bug in StaticBuilder.
See [this issue page](https://github.com/fvsch/kirby-staticbuilder/issues/27) for a workaround.

### Server shows a folder listing instead of a parent page

Cause: the default StaticBuilder config outputs pages like this:

```
static/parent.html
static/parent/child.html
```

Loading `/parent` in a web server such as Apache will generally show the `static/parent` *folder*, which is not what we want. A simple fix is to change the StaticBuilder config to:

```php
c::set('staticbuilder.extension', '/index.html');
```

which results in this output:

```
static/parent/index.html
static/parent/child/index.html
```

Related issue: https://github.com/fvsch/kirby-staticbuilder/issues/29

### Script timeout

Building a lot of pages, or sometimes just a few pages, can be intensive. In particular, if you’re making a lot of *thumbs* (resizing images in PHP), the script can time out.
 
Workaround for thumbs: try visiting those pages first to build the thumbs, and start the static build after that.

### Script interrupted by a redirect

If you use Kirby’s `go()` function, or `Redirect::to()` or `Header::redirect()` from the Kirby Toolkit, the script will be interrupted by an `exit;` statement and you will get redirected to a different page.

For a workaround, see [our recommandation for HTTP redirections in Kirby](static.md#http-redirections).

### Buggy page blocks the whole build

If your templates or plugins have uncaught PHP Exceptions or PHP errors, generating the HTML pages will be stopped.

For instance, if you’re building 10 pages and the third one has an error, only the first 2 will be built and written to the `static` folder.) To prevent that:

1.  Fix errors. :)
2.  Or exclude the buggy page(s) from the static build, using the `filter` option.
