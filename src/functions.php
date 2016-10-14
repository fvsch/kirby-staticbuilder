<?php

namespace Kirby\Plugin\StaticBuilder;

/**
 * Standard filter used to exclude empty "page" directories
 * @param Page $page
 * @return bool
 */
function defaultFilter($page)
{
    // Exclude folders containing Kirby Modules
    // https://github.com/getkirby-plugins/modules-plugin
    if (strpos($page->intendedTemplate(), 'module.') === 0 ) {
        return false;
    }
    // Only include pages which have an existing text file
    // (We check that it exists because Kirby sets the text file
    // name to the folder name when it can't find one.)
    return file_exists($page->textfile());
}
