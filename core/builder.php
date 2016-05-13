<?php

namespace Kirby\Plugin\StaticBuilder;

use C;
use Exception;
use F;
use Folder;
use Page;
use Pages;
use Silo;
use Site;


/**
 * Static HTML builder class for Kirby CMS
 * Exports page content as HTML files, and copies assets.
 *
 * @package Kirby\Plugin\StaticBuilder
 */
class Builder {

	protected $kirby;
	protected $index;
	protected $folder;
	protected $suffix;
	protected $filter;
	protected $assets;
	protected $summary;

	public function __construct() {
		$kirby  = kirby();

		// Get config
		$static = $kirby->roots()->index . DS . 'static';
		$folder = c::get('plugin.staticbuilder.folder', $static);
		$suffix = c::get('plugin.staticbuilder.suffix', DS . 'index.html');
		$filter = c::get('plugin.staticbuilder.filter');
		$assets = c::get('plugin.staticbuilder.assets', ['assets', 'thumbs']);

		// Validate folder path and suffix
		$folder = $this->normalizePath($folder);
		$suffix = $this->normalizePath($suffix);

		// Validate folder name
		$folder = new Folder($folder);
		if ($folder->name() !== 'static') {
			throw new Exception('StaticBuilder: destination folder can have any path but the folder name MUST be "static". Configured name was: "' . $folder->name() . '".');
		}

		$this->kirby   = $kirby;
		$this->index   = $kirby->roots()->index;
		$this->folder  = $folder;
		$this->suffix  = $suffix;
		$this->filter  = is_callable($filter) ? $filter : null;
		$this->assets  = $assets;
		$this->summary = new Silo();
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
	 * @return array
	 */
	protected function buildPage(Page $page, $write=false, $flush=false) {
		$uri = $page->uri();
		$log = [
			'type'  => 'page',
			'name'  => $page->title()->value,
			'dest'  => $uri . $this->suffix,
			'files' => [],
			'done'  => false,
			'size'  => null
		];
		if ($write and $this->filterPage($page)) {
			// Copy page files in a folder (flush it before too)
			if ($page->hasFiles()) {
				$target = new Folder($this->folder->root() . DS . $uri);
				if ($flush) $target->flush();
				foreach ($page->files() as $file) {
					$filename = $file->filename();
					$log['files'][] = $uri . DS . $filename;
					$file->copy($target->root() . DS . $filename);
				}
			}
			// Render and write page (after the files, because of the folder flushing
			$text = $this->kirby->render($page, [], false);
			$log['size'] = strlen($text);
			f::write($this->folder->root() . DS . $log['dest'], $text);
			$log['done'] = true;
		}
		$this->summary->set($uri, $log);
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
		$log = [
			'type' => 'file',
			'name' => basename($from),
			'dest' => $to,
			'done' => false,
			'size' => null
		];
		$source = $this->index . DS . $from;
		$target = $this->folder->root() . DS . $to;

		if (is_dir($source)) {
			$log['type'] = 'folder';
			// TODO: copy folder, perhaps flushing or removing an existing copy
		}
		elseif (is_file($source)) {
			// TODO: copy file, perhaps removing an existing copy beforehand
		}
		$this->summary->set($from, $log);
		return $log['done'];
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

		if (!$this->folder->exists()) {
			$this->folder->create();
		}
		if ($content instanceof Site) {
			$this->copyAssets();
		}
		$pages = $this->getPages($content);
		// Empty the page's target folder when rebuilding just one page
		$flush = $pages->count() == 1;
		foreach($pages as $page) {
			$this->buildPage($page, true, $flush);
		}
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
		elseif ($type == 'folder') return $this->folder->root();
		return null;
	}

}
