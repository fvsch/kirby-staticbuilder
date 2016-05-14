<?php

namespace Kirby\Plugin\StaticBuilder;

use C;
use Exception;
use F;
use Folder;
use Page;
use Pages;
use Response;
use Silo;
use Site;
use Tpl;


/**
 * Static HTML builder class for Kirby CMS
 * Exports page content as HTML files, and copies assets.
 *
 * @package Kirby\Plugin\StaticBuilder
 */
class Builder {

	protected $kirby;

	// Config
	protected $index;
	protected $folder;
	protected $suffix;
	protected $assets;
	protected $filter;

	// Storing results
	protected $summary;
	protected $skipped;
	protected $lastpage;

	// Callable for PHP Errors
	public $shutdown;

	public function __construct() {
		$kirby  = kirby();

		// Get config
		$static = $kirby->roots()->index . DS . 'static';
		$folder = c::get('plugin.staticbuilder.folder', $static);
		$suffix = c::get('plugin.staticbuilder.suffix', DS . 'index.html');
		$filter = c::get('plugin.staticbuilder.filter');
		$assets = c::get('plugin.staticbuilder.assets', ['assets', 'content', 'thumbs']);

		// Validate folder path and suffix
		$folder = $this->normalizePath($folder);
		$suffix = $this->normalizePath($suffix);

		// Validate folder
		$folder = new Folder($folder);
		if ($folder->name() !== 'static') {
			throw new Exception('StaticBuilder: destination folder can have any path but the folder name MUST be "static". Configured name was: "' . $folder->name() . '".');
		}
		if ($folder->exists() === false) $folder->create();
		if ($folder->isWritable() === false) {
			throw new Exception('StaticBuilder: destination folder is not writeable.');
		}

		$this->kirby   = $kirby;
		$this->index   = $kirby->roots()->index;
		$this->folder  = $folder->root();
		$this->suffix  = $suffix;
		$this->filter  = is_callable($filter) ? $filter : null;
		$this->assets  = $assets;
		$this->skipped = [];
		$this->summary = new Silo();
	}

	/**
	 * Try to render any PHP Fatal Error in our own template
	 * @return bool
	 */
	protected function showFatalError() {
		$error =  error_get_last();
		// Check if last error is of type FATAL
		if (isset($error['type']) && $error['type'] == E_ERROR) {
			echo $this->htmlReport([
				'error' => 'Error while building pages',
				'summary' => $this->info('summary'),
				'lastPage' => $this->lastpage,
				'errorDetails' => $error['message'] . "<br>\n"
 					. 'In ' . $error['file'] . ', line ' . $error['line']
			]);
		}
	}

	/**
	 * Normalize a file path string to remove ".." etc.
	 * @param string $path
	 * @return string
	 */
	protected function normalizePath($path) {
		$path = preg_replace('/[\\/\\\]+/', DS, $path);
		$out = [];
		foreach (explode(DS, $path) as $i => $fold) {
			if ($fold == '..' && $i > 0 && end($out) != '..') array_pop($out);
			$fold = preg_replace('/\.{2,}/', '.', $fold);
			if ($fold == '' || $fold == '.') continue;
			else $out[] = $fold;
		}
		return ($path[0] == DS ? DS : '') . join(DS, $out);
	}

	/**
	 * Should we include this page and its files in the static build?
	 * @param Page $page
	 * @return bool
	 * @throws Exception
	 */
	protected function filterPage(Page $page) {
		if ($this->filter != null) {
			$val = call_user_func($this->filter, $page);
			if (!is_bool($val)) throw new Exception(
				"StaticBuilder page filter must return a boolean value");
			return $val;
		} else {
			// Only include pages which have an existing text file
			// (We check that it exists because Kirby sets the text file
			// name to the folder name when it can't find one.)
			return file_exists($page->textfile());
		}
	}

	/**
	 * Write the HTML for a page and copy its files
	 * @param Page $page
	 * @param bool $write Should we write files or just report info (dry-run).
	 * @param bool $flush Should we empty the page’s target folder?
	 */
	protected function buildPage(Page $page, $write=false, $flush=false) {

		if ($this->filterPage($page) == false) {
			$this->skipped[] = $page->uri();
			return;
		}
		$root   = $this->folder;
		$folder = $this->normalizePath( $root . DS . $page->uri() );
		$file   = $this->normalizePath( $root . DS . $page->uri() . DS . $this->suffix );
		$files  = [];
		$size   = null;
		$status = '';

		// Never ever write outside of the static folder!
		// TODO: implement check

		// Is content file newer than existing file?
		if ($write == false) {
			if (!file_exists($file)) {
				$status = 'missing';
			}
			elseif (filemtime($file) < $page->modified()) {
				$status = 'outdated';
			}
			else {
				$status = 'uptodate';
			}
		}
		else {
			// Store reference to this page in case there's a fatal error
			$this->lastpage = $page->uri();
			// Render page
			$text = $this->kirby->render($page, [], false);
			$size = strlen($text);

			// Empty destination of page files
			// (only do it if building 1 page)
			if ($flush) (new Folder($folder))->flush();

			// Write page content
			f::write($file, $text);
			$status = 'generated';

			// Copy page files in a folder
			foreach ($page->files() as $file) {
				$filedest = $folder . DS . $file->filename();
				$file->copy($filedest);
				$log['files'][] = str_replace($root . DS, '', $filedest);
			}
		}

		$this->summary->set( $page->uri(), [
			'type'   => 'page',
			'uri'    => $page->uri(),
			'url'    => $page->url(),
			'status' => $status,
			'name'   => $page->title()->value,
			'dest'   => str_replace($root . DS, '', $file),
			'files'  => $files,
			'size'   => $size,
		]);
	}

	/**
	 * Copy a file or folder to the static directory
	 * @param string $from Source file or folder
	 * @param string $to Destination path
	 * @return bool
	 * @throws Exception
	 */
	protected function copyAsset($from=null, $to=null) {
		if (!is_string($from) or !is_string($to)) return false;
		$from = $this->normalizePath($from);
		$to = $this->normalizePath($to);
		if (strpos($to, '..') !== false) {
			throw new Exception('StaticBuilder: Error in assets definition. The ".." fragment must not appear in destination path "' . $to . '".');
		}
		$info = [
			'type'   => 'file',
			'name'   => basename($from),
			'dest'   => $to,
			'status' => '',
			'size'   => null
		];
		$source = $this->index . DS . $from;
		$target = $this->folder . DS . $to;

		if (is_dir($source)) {
			$info['type'] = 'folder';
			// TODO: copy folder, perhaps flushing or removing an existing copy
		}
		elseif (is_file($source)) {
			// TODO: copy file, perhaps removing an existing copy beforehand
		}
		$this->summary->set($from, $info);
		return true;
	}

	/**
	 * Normalize assets/folder paths and call the copy function
	 */
	protected function copyAssets() {
		foreach ($this->assets as $item) {
			if (is_string($item)) {
				$item = $this->normalizePath($item);
				$this->copyAsset($item, $item);
			}
			elseif (is_array($item) and count($item) == 2) {
				$from = array_shift($item);
				$to = array_shift($item);
				$this->copyAsset($from, $to);
			}
		}
	}

	/**
	 * Get a collection of pages to work with
	 * @param Page|Pages|Site $content Content to write to the static folder
	 * @return Pages
	 */
	protected function getPages($content) {
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
	 * Build or rebuild static content
	 * @param Page|Pages|Site $content Content to write to the static folder
	 * @return array
	 */
	public function write($content) {
		$this->summary->remove();

		if ($content instanceof Site) {
			$this->copyAssets();
		}
		$pages = $this->getPages($content);

		// Kill PHP Error reporting when building pages, to "catch" PHP errors
		// from the pages or their controllers (and plugins etc.). We're going
		// to try to hande it ourselves
		$level = error_reporting();
		$this->shutdown = function(){ $this->showFatalError(); };
		register_shutdown_function($this->shutdown);
		error_reporting(0);

		// Empty the page's target folder when rebuilding just one page
		$flush = $pages->count() == 1;
		foreach($pages as $page) {
			$this->buildPage($page, true, $flush);
		}

		// Restore error reporting if building pages worked
		error_reporting($level);
		$this->shutdown = function(){};
	}

	/**
	 * Build or rebuild static content
	 * @param Page|Pages|Site $content Content to write to the static folder
	 * @return array
	 */
	public function dryrun($content) {
		$this->summary->remove();
		foreach($this->getPages($content) as $page) {
			$this->buildPage($page, false);
		}
	}

	/**
	 * Get build information (selected information to show in templates)
	 * @param string $type
	 * @return array
	 */
	public function info($type=null) {
		if ($type == 'summary') return $this->summary->get();
		elseif ($type == 'skipped') return $this->skipped;
		elseif ($type == 'folder') return $this->folder;
		return null;
	}

	/**
	 * Render the HTML report page
	 *
	 * @param array $data
	 * @return Response
	 */
	public function htmlReport($data) {
		// Forcefully remove headers that might have been set by some
		// templates, controllers or plugins when rendering pages.
		header_remove();
		$body = tpl::load(__DIR__ . DS . '..' . DS . 'templates' . DS . 'html.php', $data);
		return new Response($body, 'html', $data['error'] ? 500 : 200);
	}

}
