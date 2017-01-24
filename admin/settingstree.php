<?php
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/**
 * All DokuWiki plugins to extend the admin function
 * need to inherit from this class
 */
class admin_plugin_dw2pdf_settingstree extends DokuWiki_Admin_Plugin {
	private $helper = null;
	private $enabled = false;
	function get_helper(){
		if (!$this->helper){
			$this->helper = plugin_load('helper','dw2pdf');
		}
		return $this->helper;
	}
	function __construct(){
		$this->enabled = $this->getConf('enable_settingstree');
    }
	function handle() {
		// changes are handled with ajax, so nothing to do here.
    }
	function html(){
		echo '<h1>'.$this->getLang('admin_settingstree').($this->enabled? "" : $this->getLang('admin_is_disabled_title')).'</h1>'.NL;
		if ($this->enabled){
			if (!($this->get_helper()->checkSettingstree()) ){
				echo "<div class='error'>".$this->getLang('cant_load_settingstree')."</div>";
			}else{
				echo $this->get_helper()->getSettingstreeAdminHtml();
			}
		}else{
			echo '<div class="info">'.$this->getLang('admin_is_disabled').'</div>'.NL;
		}
	}
    function forAdminOnly() {	// for only superusers (true) or also for managers (false)?
        return $this->getConf('admin_only_settingstree');
    }
	function getMenuText($language) {
        return $this->getLang('admin_settingstree') . ($this->enabled ? "" : $this->getLang('admin_is_disabled_title'));
    }
    function getMenuSort() {
        return 100;
    }
}