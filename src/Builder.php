<?php

namespace KirbyStaticBuilder;

use C;
use Exception;
use F;
use Folder;
use Page;
use Pages;
use Response;
use Site;
use Str;
use Tpl;

/**
 * Static HTML builder class for Kirby CMS
 * Exports page content as HTML files, and copies assets.
 */
class Builder
{
    // Used for building relative URLs
    const URLPREFIX = 'STATICBUILDER_URL_PREFIX';

    // Kirby instance
    protected $kirby;

    // Project root
    protected $root;

    // Language codes
    protected $langs = [];

    // Config (there is a 'plugin.staticbuilder.[key]' for each one)
    protected $outputdir  = 'static';
    protected $baseurl    = '/';
    protected $assets     = ['assets', 'content', 'thumbs'];
    protected $filter     = null;
    protected $filename   = '.html';
    protected $uglyurls   = false;
    protected $pagefiles  = false;
    protected $catcherror = true;

    // Callable for PHP Errors
    public $shutdown;
    public $lastpage;

    // Storing results
    public $summary = [];

    /**
     * Builder constructor.
     * Resolve config and stuff.
     * @throws Exception
     */
    public function __construct()
    {
        // Kirby instance with some hacks
        $this->kirby = $this->kirbyInstance();

        // Project root
        $this->root = $this->normalizeSlashes($this->kirby->roots()->index);

        // Multilingual
        if ($this->kirby->site()->multilang()) {
            foreach ($this->kirby->site()->languages() as $language) {
                $this->langs[] = $language->code();
            }
        }
        else $this->langs[] = null;

        // Ouptut directory
        $dir = C::get('plugin.staticbuilder.outputdir', $this->outputdir);
        $dir = $this->isAbsolutePath($dir) ? $dir : $this->root . '/' . $dir;
        $folder = new Folder($this->normalizePath($dir));

        if ($folder->name() !== 'static') {
            throw new Exception('StaticBuilder: outputdir MUST be "static" or end with "/static"');
        }
        if ($folder->exists() === false) {
            $folder->create();
        }
        if ($folder->isWritable() === false) {
            throw new Exception('StaticBuilder: outputdir is not writeable.');
        }
        $this->outputdir = $folder->root();

        // URL root
        $this->baseurl = C::get('plugin.staticbuilder.baseurl', $this->baseurl);

        // Normalize assets config
        $assets = C::get('plugin.staticbuilder.assets', $this->assets);
        $this->assets = [];
        foreach ($assets as $a) {
            if (is_string($a)) $this->assets[$a] = $a;
            elseif (is_array($a) && count($a) > 1)
                $this->assets[array_shift($a)] = array_shift($a);
        }

        // Filter for pages to build or ignore
        if (is_callable($filter = C::get('plugin.staticbuilder.filter'))) {
            $this->filter = $filter;
        }

        // File name or extension for output pages
        if ($fn = C::get('plugin.staticbuilder.filename')) {
            $fn = str_replace(['/', '\\'], '', $fn);
            $this->filename = Str::startsWith($fn,'.') ? $fn : '/' . $fn;
        }

        // Output ugly URLs (e.g. '/my/page/index.html')?
        $this->uglyurls = C::get('plugin.staticbuilder.uglyurls', $this->uglyurls);

        // Copy page files to a folder named after the page URL?
        $this->pagefiles = C::get('plugin.staticbuilder.pagefiles', $this->pagefiles);

        // Catch PHP errors while generating pages?
        $this->catcherror = C::get('plugin.staticbuilder.catcherror', $this->catcherror);
    }

    /**
     * Change some of Kirby’s settings to help us building HTML that
     * is a tiny bit different from the live pages.
     * @return \Kirby
     */
    protected function kirbyInstance()
    {
        // This will retrieve the existing instance with stale settings
        $kirby = kirby();
        // We need to call configure again with the new url prefix
        C::set('url', static::URLPREFIX);
        $kirby->configure();
        // But this one stays cached anyway, so we have to update it manually
        $kirby->site->url = static::URLPREFIX;
        return $kirby;
    }

    /**
     * Figure out if a filesystem path is absolute or if we should treat
     * it as relative (to the project folder or output folder).
     * @param string $path
     * @return boolean
     */
    protected function isAbsolutePath($path)
    {
        $pattern = '/^([\/\\\]|[a-z]:)/i';
        return preg_match($pattern, $path) == 1;
    }

    /**
     * Normalize a file path string to remove ".." etc.
     * @param string $path
     * @param string $sep Path separator to use in output
     * @return string
     */
    protected function normalizePath($path, $sep='/')
    {
        $path = $this->normalizeSlashes($path, $sep);
        $out = [];
        foreach (explode($sep, $path) as $i => $fold) {
            if ($fold == '..' && $i > 0 && end($out) != '..') array_pop($out);
            $fold = preg_replace('/\.{2,}/', '.', $fold);
            if ($fold == '' || $fold == '.') continue;
            else $out[] = $fold;
        }
        return ($path[0] == $sep ? $sep : '') . join($sep, $out);
    }

    /**
     * Normalize slashes in a string to use forward slashes only
     * @param string $str
     * @param string $sep
     * @return string
     */
    function normalizeSlashes($str, $sep='/')
    {
        $result = preg_replace('/[\\/\\\]+/', $sep, $str);
        return $result === null ? '' : $result;
    }

    /**
     * Should we include this page and its files in the static build?
     * @param Page $page
     * @return bool
     * @throws Exception
     */
    protected function filterPage(Page $page)
    {
        if ($this->filter != null) {
            $val = call_user_func($this->filter, $page);
            if (!is_bool($val)) throw new Exception(
                "StaticBuilder page filter must return a boolean value");
            return $val;
        } else {
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
    }

    /**
     * Check that the destination path is somewhere we can write to
     * @param string $absolutePath
     * @return boolean
     */
    protected function filterPath($absolutePath)
    {
        // Unresolved paths with '..' are invalid
        if (Str::contains($absolutePath, '..')) return false;
        return Str::startsWith($absolutePath, $this->outputdir . '/');
    }

    /**
     * Build a relative URL from one absolute path to another,
     * going back as many times as needed. Paths should be absolute
     * or are considered to be starting from the same root.
     * @param string $from
     * @param string $to
     * @return string
     */
    protected function relativeUrl($from='', $to='')
    {
        $from = explode('/', ltrim($from, '/'));
        $to   = explode('/', ltrim($to, '/'));
        $last = false;
        while (count($from) && count($to) && $from[0] === $to[0]) {
            $last = array_shift($from);
            array_shift($to);
        }
        if (count($from) == 0) {
            if ($last) array_unshift($to, $last);
            return './' . implode('/', $to);
        }
        else {
            return './' . str_repeat('../', count($from)-1) . implode('/', $to);
        }
    }

    /**
     * Rewrites URLs in the response body of a page
     * @param string $text Response text
     * @param string $pageUrl URL for the page
     * @return string
     */
    protected function rewriteUrls($text, $pageUrl)
    {
        $relative = $this->baseurl === './';
        if ($relative || $this->uglyurls) {
            // Match restrictively urls starting with prefix, and which are
            // correctly escaped (no whitespace or quotes).
            $find = preg_quote(static::URLPREFIX) . '(\/?[^\?\&<>{}"\'\s]*)';
            $text = preg_replace_callback(
                "!$find!",
                function($found) use ($pageUrl, $relative) {
                    $url = $found[0];
                    if ($this->uglyurls) {
                        $path = $found[1];
                        if (!$path || $path === '/') {
                            $url = rtrim($url, '/') . '/index.html';
                        }
                        elseif (!Str::endsWith($url, '/') && !pathinfo($url, PATHINFO_EXTENSION)) {
                            $url .= $this->filename;
                        }
                    }
                    if ($relative) {
                        $pageUrl .= $this->filename;
                        $pageUrl = str_replace(static::URLPREFIX, '', $pageUrl);
                        $url = str_replace(static::URLPREFIX, '', $url);
                        $url = $this->relativeUrl($pageUrl, $url);
                    }
                    return $url;
                },
                $text
            );
        }
        // Except if we have converted to relative URLs, we still have
        // the placeholder prefix in the text. Swap in the base URL.
        $pattern = '!' . preg_quote(static::URLPREFIX) . '\/?!';
        return preg_replace($pattern, $this->baseurl, $text);
    }

    /**
     * Generate the file path that a page should be written to
     * @param Page $page
     * @param string|null $lang
     * @return string
     * @throws Exception
     */
    protected function pageFilename(Page $page, $lang=null)
    {
        $url  = ltrim(str_replace(static::URLPREFIX, '', $page->url($lang)));
        $base = $this->outputdir . '/' . $url;
        $file = $base . $this->filename;
        // Special case: home page
        if ($base == '' || $base == '/') {
            $file = $base . 'index.html';
        }
        // Don’t add any extension if we already have one (using a short
        // whitelist for possible use cases).
        elseif (preg_match('/\.(js|json|css|txt|svg|xml|atom|rss)$/i', $base)) {
            $file = $base;
        }
        $validPath = $this->normalizePath($file);
        if ($this->filterPath($validPath) == false) {
            throw new Exception('Output path for page goes outside of static directory: ' . $file);
        }
        return $validPath;
    }

    /**
     * Write the HTML for a page and copy its files
     * @param Page $page
     * @param bool $write Should we write files or just report info (dry-run).
     */
    protected function buildPage(Page $page, $write=false)
    {
        // Check if we will build this page and report why not
        if (!$this->filterPage($page)) {
            $log = [
                'type'   => 'page',
                'source' => 'content/'.$page->diruri(),
                'status' => 'ignore',
                'reason' => $this->filter == null ? 'Page has no text file' : 'Excluded by custom filter',
                'dest'   => null,
                'size'   => null
            ];
            $this->summary[] = $log;
            return;
        }

        // Build the HTML for each language version of the page
        foreach ($this->langs as $lang) {
            $this->buildPageVersion(clone $page, $lang, $write);
        }
    }

    /**
     * Write the HTML for a page’s language version
     * @param Page $page
     * @param string $lang Page language code
     * @param bool $write Should we write files or just report info (dry-run).
     * @return array
     */
    protected function buildPageVersion(Page $page, $lang=null, $write=false)
    {
        // Clear the cached data (especially the $page->content object)
        // or we will end up with the first language's content for all pages
        $page->reset();

        // Update the current language and active page
        if ($lang) $page->site->language = $page->site->language($lang);
        $page->site()->visit($page->uri(), $lang);

        // Let's get some metadata
        $source = $this->normalizePath($page->textfile(null, $lang));
        $source = ltrim(str_replace($this->root, '', $source), '/');
        $file   = $this->pageFilename($page, $lang);
        // Store reference to this page in case there's a fatal error
        $this->lastpage = $source;

        $log = [
            'type'   => 'page',
            'status' => '',
            'source' => $source,
            'dest'   => str_replace($this->outputdir, 'static', $file),
            'size'   => null,
            'title'  => $page->title()->value,
            'uri'    => $page->uri(),
            'files'  => []
        ];

        // If not writing, let's report on the existing target page
        if ($write == false) {
            if (is_file($file)) {
                $outdated = filemtime($file) < $page->modified();
                $log['status'] = $outdated ? 'outdated' : 'uptodate';
                $log['size'] = filesize($file);
            }
            else {
                $log['status'] = 'missing';
            }
            if ($this->pagefiles) {
                $log['files'] = $page->files()->count();
            }
            return $this->summary[] = $log;
        }

        // Render page
        $text = $this->kirby->render($page, [], false);
        $text = $this->rewriteUrls($text, $page->url($lang));
        F::write($file, $text);
        $log['size'] = strlen($text);
        $log['status'] = 'generated';
        header_remove();

        // Option: Copy page files in a folder
        if ($this->pagefiles) {
            $dir = str_replace(static::URLPREFIX, '', $page->url($lang));
            $dir = $this->normalizeSlashes($this->outputdir . '/' . $dir);
            foreach ($page->files() as $f) {
                $dest = $dir . '/' . $f->filename();
                if ($f->copy($dest)) {
                    $log['files'][] = str_replace($this->outputdir, 'static', $dest);
                }
            }
        }

        return $this->summary[] = $log;
    }

    /**
     * Copy a file or folder to the static directory
     * This function is responsible for normalizing paths and making sure
     * we don't write files outside of the static directory.
     *
     * @param string $from Source file or folder
     * @param string $to Destination path
     * @param bool $write Should we write files or just report info (dry-run).
     * @return array|boolean
     * @throws Exception
     */
    protected function copyAsset($from=null, $to=null, $write=false)
    {
        if (!is_string($from) or !is_string($to)) {
            return false;
        }
        $log = [
            'type'   => 'asset',
            'status' => '',
            'reason' => '',
            // Use unnormalized, relative paths in log, because they
            // might help understand why a file was ignored
            'source' => $from,
            'dest'   => 'static/',
            'size'   => null
        ];

        // Source can be absolute
        if ($this->isAbsolutePath($from)) {
            $source = $from;
        } else {
            $source = $this->normalizePath($this->root . '/' . $from);
        }

        // But target is always relative to static dir
        $target = $this->normalizePath($this->outputdir . '/' . $to);
        if ($this->filterPath($target) == false) {
            $log['status'] = 'ignore';
            $log['reason'] = 'Cannot copy asset outside of the static folder';
            return $this->summary[] = $log;
        }
        $log['dest'] .= str_replace($this->outputdir . '/', '', $target);

        // Get type of asset
        if (is_dir($source)) {
            $log['type'] = 'dir';
        }
        elseif (is_file($source)) {
            $log['type'] = 'file';
        }
        else {
            $log['status'] = 'ignore';
            $log['reason'] = 'Source file or folder not found';
        }

        // Copy a folder
        if ($write && $log['type'] == 'dir') {
            $source = new Folder($source);
            $existing = new Folder($target);
            if ($existing->exists()) $existing->remove();
            $log['status'] = $source->copy($target) ? 'done' : 'failed';
        }

        // Copy a file
        if ($write && $log['type'] == 'file') {
            $log['status'] = copy($source, $target) ? 'done' : 'failed';
        }

        return $this->summary[] = $log;
    }

    /**
     * Get a collection of pages to work with (collection may be empty)
     * @param Page|Pages|Site $content Content to write to the static folder
     * @return Pages
     */
    protected function getPages($content)
    {
        if ($content instanceof Pages) {
            return $content;
        }
        elseif ($content instanceof Site) {
            return $content->index();
        }
        else {
            $pages = new Pages([]);
            if ($content instanceof Page) $pages->add($content);
            return $pages;
        }
    }

    /**
     * Try to render any PHP Fatal Error in our own template
     * @return bool
     */
    protected function showFatalError()
    {
        $error = error_get_last();
        switch ($error['type']) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
            case E_RECOVERABLE_ERROR:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_PARSE:
                ob_clean();
                echo $this->htmlReport([
                    'error' => 'Error while building pages',
                    'summary' => $this->summary,
                    'lastPage' => $this->lastpage,
                    'errorDetails' => $error['message'] . "<br>\n"
                        . 'In ' . $error['file'] . ', line ' . $error['line']
                ]);
        }
    }

    /**
     * Build or rebuild static content
     * @param Page|Pages|Site $content Content to write to the static folder
     * @param boolean $write Should we actually write files
     * @return array
     */
    public function run($content, $write=false)
    {
        $this->summary = [];
        $this->kirby->cache()->flush();

        if ($write) {
            // Kill PHP Error reporting when building pages, to "catch" PHP errors
            // from the pages or their controllers (and plugins etc.). We're going
            // to try to hande it ourselves
            $level = error_reporting();
            if ($this->catcherror) {
                $this->shutdown = function () {
                    $this->showFatalError();
                };
                register_shutdown_function($this->shutdown);
                error_reporting(0);
            }
        }

        // Empty folder on full site build
        if ($write && $content instanceof Site) {
            $folder = new Folder($this->outputdir);
            $folder->flush();
        }

        // Build each page (possibly several times for multilingual sites)
        foreach($this->getPages($content) as $page) {
            $this->buildPage($page, $write);
        }

        // Copy assets after building pages (so that e.g. thumbs are ready)
        if ($content instanceof Site) {
            foreach ($this->assets as $from=>$to) {
                $this->copyAsset($from, $to, $write);
            }
        }

        // Restore error reporting if building pages worked
        if ($write && $this->catcherror) {
            error_reporting($level);
            $this->shutdown = function () {};
        }
    }

    /**
     * Render the HTML report page
     *
     * @param array $data
     * @return Response
     */
    public function htmlReport($data=[])
    {
        // Forcefully remove headers that might have been set by some
        // templates, controllers or plugins when rendering pages.
        header_remove();
        $root = dirname(__DIR__);
        $css = $root . '/assets/report.css';
        $js  = $root . '/assets/report.js';
        $tpl = $root . '/templates/report.php';
        $data['styles'] = file_get_contents($css);
        $data['script'] = file_get_contents($js);
        $body = Tpl::load($tpl, $data);
        return new Response($body, 'html', $data['error'] ? 500 : 200);
    }
}
