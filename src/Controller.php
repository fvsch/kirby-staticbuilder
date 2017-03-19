<?php

namespace Kirby\StaticBuilder;

use C;
use R;
use Response;

class Controller
{
    /** @var bool */
    private static $registered = false;

    /**
     * Register StaticBuilder's routes
     */
    static function register()
    {
        // The plugin must be enabled in config to be able to run,
        // which allows enabling it only for a local dev environment.
        if (static::$registered === false && C::get('staticbuilder') === true) {
            $kirby = kirby();
            if (!class_exists('Kirby\\Registry')) {
                throw new Exception('Twig plugin requires Kirby 2.3 or higher. Current version: ' . $kirby->version());
            }
            $kirby->set('route', [
                'pattern' => 'staticbuilder',
                'action' => 'Kirby\\StaticBuilder\\Controller::siteAction',
                'method' => 'GET|POST'
            ]);
            $kirby->set('route', [
                'pattern' => 'staticbuilder/(:all)',
                'action' => 'Kirby\\StaticBuilder\\Controller::pageAction',
                'method' => 'GET|POST'
            ]);
            static::$registered = true;
        }
        return static::$registered;
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

        // bail if cache is active
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

        // try a build
        $builder->run($site, $write);

        // this may not run if we've had errors
        return $builder->htmlReport([
            'mode'    => 'site',
            'error'   => false,
            'confirm' => $write,
            'summary' => $builder->summary
        ]);
    }

    /**
     * Similar to siteAction but for a single page.
     * @param $uri
     * @return Response
     */
    static function pageAction($uri)
    {
        $write = R::is('POST') and R::get('confirm');
        $page = page($uri);
        $builder = new Builder();
        $data = [
            'mode'    => 'page',
            'error'   => false,
            'confirm' => $write,
            'summary' => []
        ];
        if (!$page) {
            $data['error'] = "Error: Cannot find page for \"$uri\"";
        }
        else {
            $builder->run($page, $write);
            $data['summary'] = $builder->summary;
        }
        return $builder->htmlReport($data);
    }
}
