<?php

namespace KirbyStaticBuilder;

use A;
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
use Url;

/**
 * Static HTML builder class for Kirby CMS
 * Exports page content as HTML files, and copies assets.
 */
class Builder
{
    // Used for building relative URLs
    const URL_PREFIX = '__STATICBUILDER_URL_PREFIX__';

    // Kirby instance
    protected $kirby;
    // Language codes
    protected $langs = [];
    // Project root
    protected $root;
    // Full path to output directory
    protected $dest;

    // Config (there is a 'staticbuilder.[key]' for each one)
    protected $outputdir;
    protected $baseurl;
    protected $assets;
    protected $extension;
    protected $filter;
    protected $uglyurls;
    protected $withfiles;

    // Callable for PHP Errors
    public $shutdown;
    // Storing results
    public $lastpage;
    public $summary = [];

    /**
     * Builder constructor.
     * Resolve config and stuff.
     * @throws Exception
     */
    public function __construct()
    {
        // Signal to templates & controllers that we're running a static build
        define('STATIC_BUILD', true);

        // Kirby instance with some hacks
        $kirby = $this->kirbyInstance();

        // Project root
        $this->root = $this->normalizeSlashes($kirby->roots()->index);

        // Multilingual
        if ($kirby->site()->multilang()) {
            foreach ($kirby->site()->languages() as $language) {
                $this->langs[] = $language->code();
            }
        }
        else $this->langs[] = null;

        // Ouptut directory
        $this->outputdir = rtrim(Plugin::getOption('staticbuilder.outputdir', 'static'), '/');
        $this->dest = $this->validDest($kirby->roots(), $this->outputdir);

        // URL root
        $this->baseurl = Plugin::getOption('staticbuilder.baseurl', false);

        // Normalize assets config
        $assets = Plugin::getOption('staticbuilder.assets', ['assets', 'content', 'thumbs']);
        $this->assets = [];
        foreach ($assets as $key=>$dest) {
            if (!is_string($dest)) continue;
            $this->assets[ is_string($key) ? $key : $dest ] = $dest;
        }

        // Filter for pages to build or ignore
        if (is_callable($filter = Plugin::getOption('staticbuilder.filter'))) {
            $this->filter = $filter;
        }

        // File name or extension for output pages
        if ($ext = Plugin::getOption('staticbuilder.extension', '/index.html')) {
            $ext = trim(str_replace('\\', '/', $ext));
            if (in_array(substr($ext, 0, 1), ['/', '.'])) {
                $this->extension = $ext;
            } else {
                $this->extension = '.' . $ext;
            }
        }

        // Output ugly URLs (e.g. '/my/page/index.html')?
        $this->uglyurls = Plugin::getOption('staticbuilder.uglyurls', false);

        // Copy page files to a folder named after the page URL?
        $withfiles = Plugin::getOption('staticbuilder.withfiles', false);
        if (is_bool($withfiles) || is_callable($withfiles)) {
            $this->withfiles = $withfiles;
        }

        // Save Kirby instance
        $this->kirby = $kirby;
    }

    /**
     * Build or rebuild static content
     * @param Page|Pages|Site $content Content to write to the static folder
     * @param boolean $write Should we actually write files
     * @throws Exception
     */
    public function buildStatic($content, $write=false)
    {
        $this->summary = [];
        $this->kirby->cache()->flush();
        $level = 1;

        if ($write) {
            // Kill PHP Error reporting when building pages, to "catch" PHP errors
            // from the pages or their controllers (and plugins etc.). We're going
            // to try to hande it ourselves
            $level = error_reporting();
            $this->shutdown = function () {
                $this->showFatalError();
            };
            register_shutdown_function($this->shutdown);
            error_reporting(0);
        }

        // Empty folder on full site build
        if ($write && $content instanceof Site) {
            $folder = new Folder($this->dest);
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
        if ($write) {
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
        $data['styles'] = file_get_contents($root . '/assets/report.css');
        $data['script'] = file_get_contents($root . '/assets/report.js');
        $body = Tpl::load(__DIR__ . '/report.php', $data);
        return new Response($body, 'html', $data['error'] ? 500 : 200);
    }

    /**
     * Change some of Kirby’s settings to help us building HTML that
     * is a bit different from the live pages.
     * Side effects: changes Kirby Toolkit and Kirby instance URL config.
     * @return \Kirby
     */
    protected function kirbyInstance()
    {
        // This will retrieve the existing instance with stale settings
        $kirby = kirby();
        // Set toolkit config to static URL prefix
        // This helps making the js(), css() and url() helper functions work
        C::set('url', static::URL_PREFIX);
        Url::$home = static::URL_PREFIX;
        // Same for Kirby options
        $kirby->set('option', 'url', static::URL_PREFIX);
        // Fix base URL for $kirby->urls->* API; not sure if it impacts something else
        $kirby->urls->index = static::URL_PREFIX;
        // Finally, setting the base URL of the "site" page fixes site and page URLS
        $kirby->site->url = static::URL_PREFIX;
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
    protected function normalizeSlashes($str, $sep='/')
    {
        $result = preg_replace('/[\\/\\\]+/', $sep, $str);
        return $result === null ? '' : $result;
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
        return Str::startsWith($absolutePath, $this->dest . '/');
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
     * Check that the outputdir option won't nuke the current Kirby installation,
     * and return the full output directory path
     * @param \Kirby\Roots $roots
     * @param string $name
     * @return string
     * @throws Exception
     */
    protected function validDest($roots, $name)
    {
        $folder = new Folder($this->normalizePath($roots->index . '/' . $name));
        $dirPath = $this->normalizeSlashes($folder->root());
        if ($this->isAbsolutePath($name)) {
            throw new Exception("StaticBuilder - outputdir cannot be an absolute path: '$name'");
        }
        foreach(['index', 'kirby', 'site', 'content', 'assets', 'thumbs'] as $key) {
            $standardPath = $this->normalizeSlashes(call_user_func([$roots, $key]));
            if ($dirPath === $standardPath || Str::startsWith($standardPath, $dirPath . '/')) {
                throw new Exception("StaticBuilder - outputdir '$name' would overwrite a Kirby directory: '$dirPath'");
            }
        }
        if ($folder->exists() === false) {
            $folder->create();
        }
        if ($folder->isWritable() === false) {
            throw new Exception("StaticBuilder - outputdir is not writeable: '$dirPath'");
        }
        return $dirPath;
    }

    /**
     * Rewrites URLs in the response body of a page
     * @param string $text Response text
     * @param string $pageUrl URL for the page
     * @return string
     */
    protected function rewriteUrls($text, $pageUrl)
    {
        $baseurl = $this->baseurl ? $this->baseurl : './';
        $relative = $baseurl === './';

        if ($relative || $this->uglyurls) {
            // Match restrictively urls starting with prefix, and which are
            // correctly escaped (no whitespace or quotes).
            $find = preg_quote(static::URL_PREFIX) . '(\/?[^\?\&<>{}"\'\s]*)';
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
                            $url .= $this->extension;
                        }
                    }
                    if ($relative) {
                        $pageUrl .= $this->extension;
                        $pageUrl = str_replace(static::URL_PREFIX, '', $pageUrl);
                        $url = str_replace(static::URL_PREFIX, '', $url);
                        $url = $this->relativeUrl($pageUrl, $url);
                    }
                    return $url;
                },
                $text
            );
        }
        // Except if we have converted to relative URLs, we still have
        // the placeholder prefix in the text. Swap in the base URL.
        $pattern = '!' . preg_quote(static::URL_PREFIX) . '\/?!';
        return preg_replace($pattern, $baseurl, $text);
    }

    /**
     * Replace full 'dest' folder path with the short 'outputdir' path
     * @param string $path
     * @return string
     */
    protected function summaryPath($path)
    {
        return str_replace($this->dest . '/', $this->outputdir . '/', $path);
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
        // We basically want the page $page->id(), but localized for multilang
        $url = $page->url($lang);
        // Strip the temporary URL prefix
        $url = trim(str_replace(static::URL_PREFIX, '', $url), '/');
        // Page URL fragment should not contain a protocol or domain name at this point;
        // did we fail to override the base URL with static::URL_PREFIX?
        if (Str::startsWith($url, 'http') || Str::contains($url, '://')) {
            throw new Exception("StaticBuilder - cannot use '$url' as basis for page's file name.");
        }
        // Special case: home page
        if (!$url) {
            $file = $this->dest . '/index.html';
        }
        // Don’t add any extension if we already have one in the URL
        // (using a short whitelist for likely use cases).
        elseif (preg_match('/\.(js|json|css|txt|svg|xml|atom|rss)$/i', $url)) {
            $file = $this->dest . '/' . $url;
        }
        else {
            $file = $this->dest . '/' . $url . $this->extension;
        }
        $validPath = $this->normalizePath($file);
        if ($this->filterPath($validPath) == false) {
            throw new Exception("StaticBuilder - output path for page goes outside of static directory: '$file'");
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
        // Check if we will build this page and report why not.
        // Note: in 2.1 the page filtering API changed, the return value
        // can be a boolean or an array with a boolean + a string.
        if (is_callable($this->filter)) {
            $filterResult = call_user_func($this->filter, $page);
        } else {
            $filterResult = Plugin::defaultFilter($page);
        }
        if (!is_array($filterResult)) {
            $filterResult = [$filterResult];
        }
        if (A::get($filterResult, 0, false) == false) {
            $log = [
                'type'   => 'page',
                'source' => 'content/'.$page->diruri(),
                'status' => 'ignore',
                'reason' => A::get($filterResult, 1, 'Excluded by filter'),
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
        // Store reference to this page in case there's a fatal error
        $this->lastpage = $source;

        $log = [
            'type'   => 'page',
            'status' => '',
            'source' => $source,
            'dest'   => null,
            'size'   => null,
            'title'  => $page->title()->value,
            'uri'    => $page->uri(),
            'files'  => []
        ];

        try {
            $file = $this->pageFilename($page, $lang);
            $log['dest'] = $this->summaryPath($file);
        }
        catch (Exception $e) {
            $log['status'] = 'error';
            $log['dest'] = $e->getMessage();
            return $this->summary[] = $log;
        }

        // Figure out if we have files to copy
        $files = [];
        if ($this->withfiles) {
            $files = $page->files();
            if (is_callable($this->withfiles)) {
                $files = $files->filter($this->withfiles);
            }
        }

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
            $log['files'] = count($files);
            return $this->summary[] = $log;
        }

        // Render page
        try {
            $text = $this->kirby->render($page, [], false);
        }
        catch (Exception $e) {
            $log['status'] = 'error';
            $log['dest'] = $e->getMessage();
            return $this->summary[] = $log;
        }
        $text = $this->rewriteUrls($text, $page->url($lang));
        F::write($file, $text);
        $log['size'] = strlen($text);
        $log['status'] = 'generated';
        header_remove();

        // Option: Copy page files in a folder
        if (count($files) > 0) {
            $dir = str_replace(static::URL_PREFIX, '', $page->url($lang));
            $dir = $this->normalizeSlashes($this->dest . '/' . $dir);
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
            foreach ($files as $f) {
                $dest = $dir . '/' . $f->filename();
                if ($f->copy($dest)) {
                    $log['files'][] = $this->summaryPath($dest);
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
            'dest'   => null,
            'size'   => null
        ];

        // Source can be absolute
        if ($this->isAbsolutePath($from)) {
            $source = $from;
        } else {
            $source = $this->normalizePath($this->root . '/' . $from);
        }

        // But target is always relative to static dir
        $target = $this->normalizePath($this->dest . '/' . $to);
        if ($this->filterPath($target) == false) {
            $log['status'] = 'ignore';
            $log['reason'] = 'Cannot copy asset outside of the static folder';
            return $this->summary[] = $log;
        }
        $log['dest'] = $this->summaryPath($target);

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
                    'mode' => 'fatal',
                    'error' => 'Error while building pages',
                    'summary' => $this->summary,
                    'errorTitle' => 'Failed to build page <code>' . $this->lastpage . '</code>',
                    'errorDetails' => $error['message'] . "<br>\n"
                        . 'In ' . $error['file'] . ', line ' . $error['line']
                ]);
        }
    }
}
