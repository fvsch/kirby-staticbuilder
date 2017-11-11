<?php

c::set('debug', true);
c::set('languages', [
    [
        'code'    => 'en',
        'name'    => 'English',
        'default' => true,
        'locale'  => 'en_US',
        'url'     => '/',
    ], [
        'code'    => 'de',
        'name'    => 'Deutsch',
        'locale'  => 'de_DE',
        'url'     => '/de',
    ],
]);

c::set('staticbuilder', true);
c::set('staticbuilder.filter', function($page) {
    if ($page->intendedTemplate() == 'team') {
        return [false, 'No standalone pages for team members'];
    }
    return KirbyStaticBuilder\Plugin::defaultFilter($page);
});
