<?php

namespace Kirby\StaticBuilder;

use R;
use Response;

class Controller
{
    /**
     * Register StaticBuilder's routes
     */
    static function register()
    {
        $kirby = kirby();

        if (!class_exists('Kirby\\Registry')) {
            throw new Exception('Twig plugin requires Kirby 2.3 or higher. Current version: ' . $kirby->version());
        }

        // The plugin must be enabled in config to be able to run,
        // which allows enabling it only for a local dev environment.
        if (!$kirby->get('option', 'staticbuilder', false)) {
            return;
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
        $builder = new Builder();

        $builder->run($site, $write);

        $data = [
            'mode'    => 'site',
            'error'   => false,
            'confirm' => $write,
            'summary' => $builder->summary
        ];
        return $builder->htmlReport($data);
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
