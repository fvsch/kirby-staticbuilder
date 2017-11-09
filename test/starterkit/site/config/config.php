<?php

c::set('debug', true);
c::set('staticbuilder', true);
c::set('staticbuilder.baseurl', './');
c::set('staticbuilder.assets', [
    'assets', 'content', 'thumbs',
    '../static_htaccess' => '.htaccess'
]);
c::set('staticbuilder.filter', function($page) {
    if ($page->intendedTemplate() == 'team') {
        return [false, 'No standalone pages for team members'];
    }
    return Kirby\StaticBuilder\Builder::defaultFilter($page);
});
