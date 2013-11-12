<?php
/**
 * @author Levi Lansing
 * Created 11/10/13
 */
trait PageController {
    protected $pageTitle;

    /**
     * @return array('js'=>,'css'=>)
     */
    public function getScripts() {
        return [
            'js'=>[],
            'css'=>[]
        ];
    }

    /**
     * render the requested view
     */
    abstract public function render();

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
}
?>