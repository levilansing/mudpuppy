<?php
defined('MUDPUPPY') or die('Restricted');

trait PageController {

	/**
	 * Renders the page header. The default implementation adds the page title and imports any js and css files
	 * specified by getScripts().
	 */
	public function renderHeader() {
		$title = $this->pageTitle ? $this->pageTitle : Config::$appTitle;
		print " <title>$title</title>\n";
		$scripts = $this->getScripts();
		if (isset($scripts['css'])) {
			foreach ($scripts['js'] as $script) {
				echo "  <script type=\"text/javascript\" src=\"$script\"></script>\n";
			}
		}
		if (isset($scripts['css'])) {
			foreach ($scripts['css'] as $css) {
				echo "  <link rel=\"stylesheet\"  type=\"text/css\" href=\"$css\" />\n";
			}
		}
	}

	/**
	 * Renders the page body.
	 */
	abstract public function render();

	/**
	 * @return array associative array with two keys, 'js' and 'css', each being an array of script paths for use by the
	 * default implementation of renderHeader()
	 */
	protected function getScripts() {
		return [
			'js' => [],
			'css' => []
		];
	}

	/** @var string optional page title override */
	protected $pageTitle;

}

?>