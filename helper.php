<?php

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');

class helper_plugin_dw2pdf extends DokuWiki_Plugin {

	private $settings_helper = false;	// false: not initialized, null: disabled/not available, object: the helper.
	private $_folder_confs = array();
	private $exportConfig = array();
	
	private function get_settingstree(){
		if ($this->settings_helper === false){
			if (!parent::getConf('enable_settingstree')){ return ($this->settings_helper = null);}
			$this->settings_helper = plugin_load('helper','settingstree');
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
		
		return (array_key_exists($key, $this->_folder_confs[$folder]) ? $this->_folder_confs[$folder][$key] : $notset);
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
	
// MOVED HERE FROM action.php
	/**
     * Return settings read from:
     *   1. url parameters
     *   2. plugin config
     *   3. global config
     *
     * @return array
     */
// NOTE: as moved to different plugin, visibility is changed to public.
    public function loadExportConfig() {
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
		// OOPS: did not return the array...
		return $this->exportConfig;
    }
	
	
}