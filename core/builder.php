<?php

namespace Kirby\Plugin\StaticBuilder;

use C;
use Exception;
use F;
use Page;


/**
 * Static HTML builder class for Kirby CMS
 * Exports page content as HTML files, and copies assets.
 *
 * @package Kirby\Plugin\StaticBuilder
 */
class Builder {

	public $kirby;
	public $folder;
	public $suffix;
	protected $customFilter;

	public function __construct() {
		$this->kirby = kirby();
		$folder = $this->kirby->roots()->index . DS . 'static';
		$this->folder = c::get('plugin.staticbuilder.folder', $folder);
		$this->suffix = c::get('plugin.staticbuilder.suffix', DS . 'index.html');
		$this->customFilter = c::get('plugin.staticbuilder.filter');
	}

	/**
	 * Should we include this page and its files in the static build?
	 * @param Page $page
	 * @return bool
	 * @throws Exception
	 */
	protected function filter(Page $page) {
		if (is_callable($this->customFilter)) {
			$val = $this->customFilter($page);
			if (!is_bool($val)) throw new Exception(
				"Custom StaticBuilder filter must return a boolean value");
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
	 * @return array
	 */
	public function page(Page $page, $write=true) {
		$info = [
			'uri' => $page->uri(),
			'title' => $page->title()->value,
			'ignored' => true,
			'dest' => '',
			'bytes' => null,
			'files' => []
		];
		if (!$this->filter($page)) return $info;

		// Render page and optionally write it
		$info['ignored'] = false;
		$info['dest'] = $page->uri() . $this->suffix;
		if ($write) {
			$text = $this->kirby->render($page, [], false);
			$info['bytes'] = strlen($text);
			f::write($this->folder . DS . $info['dest'], $text);
		}

		// TODO: copy page files

		return $info;
	}

	/**
	 * Rebuild static content
	 * @param bool $write Should we write files or just report info (dry-run).
	 * @return array
	 */
	public function rebuild($write=true) {
		$info = [];

		// TODO: empty static directory

		// Write all pages and copy their files
		$pages = $this->kirby->site()->index();
		foreach($pages as $page) {
			$info[] = $this->page($page, $write);
		}

		// TODO: copy predefined folders or files

		return $info;
	}

}
