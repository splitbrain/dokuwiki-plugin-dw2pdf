<?php

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');

class helper_plugin_dw2pdf extends DokuWiki_Plugin {

	private $settings_helper = false;	// false: not initialized, null: disabled/not available, object: the helper.
	private $_folder_confs = array();
	private $exportConfig = null;
	
	private function get_settingstree(){
		if ($this->settings_helper === false){
			if (parent::getConf('enable_settingstree') || parent::getConf('enable_export_config_popup')){ 	// the config settings which require settingstree 
				$this->settings_helper = plugin_load('helper','settingstree');
			}else{	// else settingstree is not required.
				$this->settings_helper = null;
				return null;
			}
		}else{
			return $this->settings_helper;
		}
		$confdir = DOKU_INC.'lib/plugins/dw2pdf/conf/';
		// check if the already stored version is the same as the current one we offer, and reregister options if the settingstree version is older.
		if ($this->settings_helper->checkSettingsVersion('dw2pdf',			
			$ver = max(			
				// we're comparing the registered settings versus the configuration files' last change.
				// metadata may have an extra '_ignore_for_settingstree' =>true, in which case it won't be configurable by settingstree
				filemtime($confdir.'metadata.php'),
				filemtime($confdir.'default.php'),
				// extends.php have exactly the same syntax as default.php + metadata.php (in the same file), but these settings are only configurable by settingstree plugin and not by config.
				@filemtime($confdir.'extends.php')
			))){
			$meta = array();
			$conf = array();
			include($confdir.'metadata.php');
			include($confdir.'default.php');
			if (file_exists($confdir.'extends.php')) {include($confdir.'extends.php');}	// this file may not exist
			$this->settings_helper->registerSettings('dw2pdf',$ver,$meta,$conf);	// (re)register settings for this plugin..
		}
		return $this->settings_helper;
	}
	
	
	function __construct(){
		
    }
	/**
	 * Overrides the configuration settings when 'co' (abbr. for config override) is present in the input and it is an actual export
	 */
	function _checkExportOverride(){
		global $INPUT;
		if (is_array($co = $INPUT->arr('co',null)) && strncasecmp($INPUT->str('do',''),'export_pdf',10) === 0){
			$this->_overrideExportConfig($co);
		}
	}
	
    function getMethods() {
		$result = array();
		$result[] = array(
			'name'   => 'getSettingstreeAdminHtml',
			'desc'   => 'Returns html for admin page.',
			'parameters' => array(
			),
			'return' => 'string html or null on error.'
		);
		$result[] = array(
			'name'   => 'checkSettingstree',
			'desc'   => 'Checks if settingstree is available.',
			'parameters' => array(
			),
			'return' => 'boolean availability'
		);
		$result[] = array(
			'name'   => 'getConf',
			'desc'   => 'Returns a configuration value for a key including overrides/extends from settingstree for a given folder',
			'parameters' => array(
				'key'	=> "string the setting's name (e.g. 'pagesize')",
				'notset' => "mixed what to return if the setting is not available",
				'folder' => "string path of to look up (e.g. ':mynamespace:mypage'), if null use current page.",
			),
			'return' => 'mixed value or null on error or if value not defined.',
		);
		$result[] = array(
			'name'   => 'getAllConf',
			'desc'   => 'Returns all configuration value including overrides/extends from settingstree for a given folder.',
			'parameters' => array(
				'folder' => "string path of to look up (e.g. ':mynamespace:mypage'), if null use current page.",
			),
			'return' => "array the config settings key=>value array, e.g. array('pagesize'=>'A4', 'orientation'=>'protrait' ...)",
		);
		$result[] = array(
			'name'   => 'loadExportConfig',
			'desc'   => "Returns an array of config settings that are going to be used. This includes checking $INPUT for overrides and using current page's hierarchical settings if enabled.",
			'return' => "array the config settings key=>value array, e.g. array('pagesize'=>'A4', 'orientation'=>'protrait' ...)",
		);
		$result[] = array(
			'name'   => 'addExportButton',
			'desc'   => "Adds the export by (hierarchical) settings.",
			'return' => "void",
		);
		$result[] = array(
			'name'   => 'replyAjax',
			'desc'   => "Handles calls.",
			'return' => "void (it handled the call so nothing more to do)",
		);
		return $result;
	}
	
	function checkSettingstree(){
		return !!$this->get_settingstree();
	}

	function getSettingstreeAdminHtml(){
		if (!$this->get_settingstree()){ return null;}
		return $this->settings_helper->showAdmin('dw2pdf',$this->_getFolder(null));
	}
	private function _getFolder($folder = null){
		if (is_null($folder)){
			global $ID;
			$folder = $ID;
		}
		return ':'.ltrim(strtr($folder,'/',':'),':');
	}

	function getConf($key,$notset = false, $folder = null){
		$folder = $this->_getFolder($folder);
		if (!is_array(@$this->_folder_confs[$folder])){
			$this->getAllConf($folder);
		}
		
		return (array_key_exists($key, (array)$this->_folder_confs[$folder]) ? $this->_folder_confs[$folder][$key] : $notset);
	}
	
	function getAllConf($folder = null){
		$folder = $this->_getFolder($folder);
		if (!is_array(@$this->_folder_confs[$folder])){
			global $conf;
			$cnf = $conf['plugin']['dw2pdf'];
			if ($this->get_settingstree()){
				$cnf = array_merge($cnf,$this->settings_helper->getConf('dw2pdf',$folder));
			}
			if (!is_array($cnf)) {$cnf = array();}	// should never happen if the plugin have configs, and this plugin have configs.
			$this->_folder_confs[$folder] = $cnf;
		}
		return $this->_folder_confs[$folder];

	}
	
	// CODE MOVED HERE FROM action.php
	function addExportButton(&$event){
        if($this->getConf('showexportbutton') && $event->data['view'] == 'main') {
			// insert button at position before last (up to top)
			$event->data['items'] = array_slice($event->data['items'], 0, -1, true) +
				array('export_pdf' => 
						  '<li>'
						  .'<a ' . $this->_exportButtonHtml()	// this is the htmlcode for the opening a tag's href and onclick attributes.
						  . '"  class="action export_pdf" rel="nofollow" title="' . $this->getLang('export_pdf_button') . '">'
						  . '<span>' . $this->getLang('export_pdf_button') . '</span>'
						  . '</a>'
						  . '</li>'
				) +
				array_slice($event->data['items'], -1, 1, true);
        }
    }
	private function _getExportUrl($type = 'export_pdf',$params = array(),$_id = null,$_rev = null){
		global $ID, $REV;
		if ($_id === null && $_rev === null) $_rev = $REV;	// don't set current page's $REV to an overridden $_id;
		if ($_id === null) $_id = $ID;
		$params['do'] = $type;
		if($_rev) {
			$params['rev'] = $_rev;
		}
		return wl($_id)."?".http_build_query($params);
	}
	private function _exportButtonHtml(){
		$href = $this->_getExportUrl(); $onclick="";
		if ($this->getConf('enable_export_config_popup') && $this->get_settingstree()){	// fall back to normal export button if settingstree can't be loaded.
			$opts = array(
				'path' => $this->_getFolder(null),
				'pluginname' => 'dw2pdf',
				'token' => getSecurityToken(),
				'options' => array(
					'title' => $this->getLang('export_config_title'),
				),
				'on_complete' => 'dw2pdf_export',	// this function is implemented in dw2pdf's script.js 
			);
			/* 'settingstree_show_export' is implemented in settingstree's script.js, the plugin may not be installed, so we need to check if it's callable.
			 * It takes care of all the fuss with creating a popup and displaying the settings inside and returns true if the popup can be displayed, false otherwise.
			 * The onclick takes care to fall back to normal export button (as returning true) if:
			 *	 1, there is some kind of script or syntax error
			 *   2, the settingstree plugin is not installed (or the function is not available for some reason)
			 *   3, if the popup can not be displayed for some reason. (e.g. the on_complete callback is invalid)
			 * The json_encode takes care of displaying the array with doublequoted strings, but single quotes still needs to be escaped (e.g. from the title).
			 */
			$onclick = " onclick='var ret= true; try{ ret = (!( (typeof settingstree_show_export === \"function\") && (settingstree_show_export(".addcslashes(json_encode($opts),"'").")) )); } catch(e){ ret = false; } return ret;'";	
		}
		return "href=\"{$href}\"{$onclick}";
	}
	
	
// MOVED HERE FROM action.php
	/**
     * Return settings read from:
     *   1. url parameters
     *   2. plugin config
     *   3. global config
     *
     * @return array
     */
// NOTE: as moved to different class, visibility is changed to public.
    public function loadExportConfig() {
        if ($this->exportConfig === null){
			global $INPUT;
			global $conf;

			// NOTE: getConf for helper is overridden, hence no code change is necessary. use 'parent::getConf' if you need to access the original 'getConf'...
			$this->exportConfig = array();
			// decide on the paper setup from param or config
			$this->exportConfig['pagesize'] = $INPUT->str('pagesize', $this->getConf('pagesize'), true);
			$this->exportConfig['orientation'] = $INPUT->str('orientation', $this->getConf('orientation'), true);

			$doublesided = $INPUT->bool('doublesided', (bool) $this->getConf('doublesided'));
			$this->exportConfig['doublesided'] = $doublesided ? '1' : '0';

			$hasToC = $INPUT->bool('toc', (bool) $this->getConf('toc'));
			$levels = array();
			if($hasToC) {
				$toclevels = $INPUT->str('toclevels', $this->getConf('toclevels'), true);
				list($top_input, $max_input) = explode('-', $toclevels, 2);
				list($top_conf, $max_conf) = explode('-', $this->getConf('toclevels'), 2);
				$bounds_input = array(
					'top' => array(
						(int) $top_input,
						(int) $top_conf
					),
					'max' => array(
						(int) $max_input,
						(int) $max_conf
					)
				);
				$bounds = array(
					'top' => $conf['toptoclevel'],
					'max' => $conf['maxtoclevel']

				);
				foreach($bounds_input as $bound => $values) {
					foreach($values as $value) {
						if($value > 0 && $value <= 5) {
							//stop at valid value and store
							$bounds[$bound] = $value;
							break;
						}
					}
				}

				if($bounds['max'] < $bounds['top']) {
					$bounds['max'] = $bounds['top'];
				}

				for($level = $bounds['top']; $level <= $bounds['max']; $level++) {
					$levels["H$level"] = $level - 1;
				}
			}
			$this->exportConfig['hasToC'] = $hasToC;
			$this->exportConfig['levels'] = $levels;

			$this->exportConfig['maxbookmarks'] = $INPUT->int('maxbookmarks', $this->getConf('maxbookmarks'), true);

			$tplconf = $this->getConf('template');
			$tpl = $INPUT->str('tpl', $tplconf, true);
			if(!is_dir(DOKU_PLUGIN . 'dw2pdf/tpl/' . $tpl)) {
				$tpl = $tplconf;
			}
			if(!$tpl){
				$tpl = 'default';
			}
			$this->exportConfig['template'] = $tpl;

			$this->exportConfig['isDebug'] = $conf['allowdebug'] && $INPUT->has('debughtml');
		}
		// OOPS: did not return the array...
		return $this->exportConfig;
    }
	/** 
	 * Overrides the exportconfig with the given key/values.
	 */
	private function _overrideExportConfig(array $values){
		$this->loadExportConfig();
		foreach ($values as $key=>$value){
			$this->exportConfig[$key] = $value;
		}
	}
	
	function replyAjax(){
		global $INPUT; //available since release 2012-10-13 "Adora Belle"
/*		if (!checkSecurityToken()){ //Do we need sectok for this?
			$data = array('error'=>true,'msg'=>'invalid security token!');
		}else{*/
			switch($INPUT->str('operation')){
				case 'export':
					$config = $INPUT->arr('config',null);
					$template = $INPUT->arr('template',array());
					$type = $INPUT->str('type','export_pdf');
					$location = $INPUT->str('location');
					$rev = $INPUT->str('rev');
					global $ID;
					$pID = $ID;	// id is overridden for the time that we grab the configuration.
					$ID = $location;
					if (is_array($config)) $this->_overrideExportConfig($config);
					$ID =  $pID;
					// if ($this->getConf('enable_extended_templates') && $this->isExtendedTemplate($this->getConf('template')))	-> will be added in follow-up commit, left here as reminder for that.
					$data = array(
						'html' => "<iframe src='".$this->_getExportUrl($type,array('co'=>$config),$location,$rev)."' style='width: 100%; height: 100%; border: none'></iframe>",	// simple but effective.
						'location' => $location,
						'url' => $this->_getExportUrl($type,array(),$location,$rev),
						'error' => false,
					);
					break;
				default:
					$data = array('error'=>true,'msg'=>'Unknown operation: '.$INPUT->str('operation'));
					break;
			}
		/*} // Do we need to handle sectoks?
		if (is_array($data)) $data['token'] = getSecurityToken();
		*/
		require_once DOKU_INC . 'inc/JSON.php';
		$json = new JSON();
	 	//set content type
		header('Content-Type: application/json');
		echo $json->encode($data);
	}
	
}