<?php

namespace KirbyStaticBuilder;

use C;
use Exception;
use Page;
use R;
use Response;
use Str;

class Plugin
{
    /** @var bool */
    private static $registered = false;

    /**
     * Register StaticBuilder's routes
     * Returns a string with the current status
     * @return string
     */
    static function register()
    {
        if (static::$registered) {
            return 'StaticBuilder is already enabled';
        }
        if (!C::get('staticbuilder', false)) {
            return 'StaticBuilder does not seem to be enabled in your config. See https://github.com/fvsch/kirby-staticbuilder/blob/master/doc/install.md for instructions.';
        }
        $kirby = kirby();
        $kirby->set('route', [
            'pattern' => 'staticbuilder',
            'action' => 'KirbyStaticBuilder\\Plugin::siteAction',
            'method' => 'GET|POST'
        ]);
        $kirby->set('route', [
            'pattern' => 'staticbuilder/(:all)',
            'action' => 'KirbyStaticBuilder\\Plugin::pageAction',
            'method' => 'GET|POST'
        ]);
        static::$registered = true;
        return 'Enabled StaticBuilder routes.';
    }

    /**
     * Kirby router action that lists all pages to build and files to copy,
     * and performs the actual build on user confirmation.
     * @return Response
     */
    static function siteAction()
    {
        $write = R::is('POST') and R::get('confirm');
        $site = site();
        $kirby = kirby();
        $builder = new Builder();
        if ($kirby->get('option', 'cache')) {
            return $builder->htmlReport([
                'mode' => 'fatal',
                'error' => 'Configuration error',
                'errorTitle' => 'Disable Kirby’s cache',
                'errorDetails' => '<p>StaticBuilder requires Kirby’s cache option to be disabled.</p>' .
                    '<pre>&lt;?php // site/config/config.localhost.php' . "\n" .
                    'c::set(\'cache\', false);</pre>',

                'confim' => false,
                'summary' => []
            ]);
        }
        $data = [
            'mode' => 'site',
            'error'  => false,
            'confirm' => $write,
            'summary' => []
        ];
        try {
            $builder->buildStatic($site, $write);
            $data['summary'] = $builder->summary;
        }
        catch (Exception $e) {
            $data['mode'] = 'fatal';
            $data['error'] = 'Build error';
            $data['errorTitle'] = 'Error while building static site';
            $data['errorDetails'] = $e->getMessage();
            $data['summary'] = $builder->summary;
        }
        return $builder->htmlReport($data);
    }

    /**
     * Similar to siteAction but for a single page.
     * @param $uri
     * @return Response
     * @throws Exception
     */
    static function pageAction($uri)
    {
        $write = R::is('POST') and R::get('confirm');
        $page = page($uri);
        $builder = new Builder();
        $data = [
            'mode' => 'page',
            'error' => false,
            'confirm' => $write,
            'summary' => []
        ];
        if (!$page) {
            $data['error'] = "Error: Cannot find page for \"$uri\"";
            return $builder->htmlReport($data);
        }
        try {
            $builder->buildStatic($page, $write);
            $data['summary'] = $builder->summary;
        }
        catch (Exception $e) {

        }
        return $builder->htmlReport($data);
    }

    /**
     * Standard filter used to exclude empty "page" directories
     * @param Page $page
     * @return bool|array
     */
    static function defaultFilter($page)
    {
        // Exclude folders containing Kirby Modules
        // https://github.com/getkirby-plugins/modules-plugin
        $mod = C::get('modules.template.prefix', 'module.');
        if (Str::startsWith($page->intendedTemplate(), $mod)) {
            return [false, "Ignoring module pages (template prefix: \"$mod\")"];
        }
        // Exclude pages missing a content file
        // Note: $page->content()->exists() returns the wrong information,
        // so we use the inventory instead. For an empty directory, it can
        // be [] (single-language site) or ['code' => null] (multilang).
        if (array_shift($page->inventory()['content']) === null) {
            return [false, 'Page has no content file.'];
        }
        return true;
    }

    /**
     * Retrieve a config option from both C::$data and Kirby::$options,
     * with the first one taking precedence
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    static function getOption($name, $default=null)
    {
        $c = C::get($name, null);
        $k = kirby()->get('option', $name, null);
        if ($c === null && $k === null) {
            return $default;
        }
        else if ($c === null & $k !== null) {
            return $k;
        }
        else if ($c !== null & $k === null) {
            return $c;
        }
        // If both options exist and are not null, keep the one from C::$data
        return $c;
    }
}
